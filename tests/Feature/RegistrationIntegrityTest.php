<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use App\Support\ProfileChangeRequests;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public static function applicantTypes(): array
    {
        return array_map(static fn (string $type): array => [$type], ['student', 'company', 'ngo', 'school']);
    }

    #[DataProvider('applicantTypes')]
    public function test_national_id_cannot_be_replaced_or_cleared_for_any_applicant_type(string $type): void
    {
        [$admin, $entity, $owner] = $this->createRegistration($type, 'active');

        foreach (['9981051999', null] as $replacement) {
            $this->actingAs($admin)->postJson(route('admin.entities.update', $entity), [
                ...$this->entityPayload($entity),
                'registration_type' => 'staff',
                'national_id' => $replacement,
            ])->assertUnprocessable()->assertJsonValidationErrors('national_id');

            $this->actingAs($admin)->postJson(route('admin.users.update', $owner), [
                'name' => $owner->name,
                'registration_type' => 'staff',
                'username' => $owner->username,
                'email' => $owner->email,
                'national_id' => $replacement,
                'phone' => $owner->phone,
                'status' => $owner->status,
            ])->assertUnprocessable()->assertJsonValidationErrors('national_id');
        }

        $this->assertSame('9981051001', $entity->fresh()->national_id);
        $this->assertSame('9981051001', $owner->fresh()->national_id);
        $this->assertSame($type, $entity->fresh()->registration_type);
        $this->assertSame($type, $owner->fresh()->registration_type);
        $this->assertFalse(ProfileChangeRequests::officialFields($entity)['national_id']['mutable']);
        $this->assertArrayNotHasKey('national_id', ProfileChangeRequests::validationRules($entity));
    }

    public function test_an_empty_company_national_id_cannot_be_filled_with_someone_elses_identity(): void
    {
        [$admin, $entity, $owner] = $this->createRegistration('company', 'active', null);

        $this->actingAs($admin)->postJson(route('admin.entities.update', $entity), [
            ...$this->entityPayload($entity),
            'national_id' => '9981051999',
        ])->assertUnprocessable()->assertJsonValidationErrors('national_id');

        $this->assertNull($entity->fresh()->national_id);

        try {
            $owner->forceFill(['national_id' => '9981051999'])->save();
            $this->fail('A model update must not bypass registration identity protection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('national_id', $exception->errors());
        }

        $this->assertNull($owner->fresh()->national_id);
    }

    public function test_a_student_cannot_gain_an_unverified_registration_number_login_alias(): void
    {
        [$admin, $entity] = $this->createRegistration('student', 'active');

        $this->actingAs($admin)->postJson(route('admin.entities.update', $entity), [
            ...$this->entityPayload($entity),
            'registration_no' => '101021036',
        ])->assertUnprocessable()->assertJsonValidationErrors('registration_no');

        $this->assertNull($entity->fresh()->registration_no);
    }

    public function test_direct_entity_model_updates_cannot_bypass_identity_protection(): void
    {
        [, $entity] = $this->createRegistration('company', 'active');

        foreach (['national_id', 'registration_no'] as $field) {
            $record = $entity->fresh();
            $original = $record->{$field};

            try {
                $record->forceFill([$field => 'REPLACEMENT'])->save();
                $this->fail('A model update must not bypass registration identity protection.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }

            $this->assertSame($original, $entity->fresh()->{$field});
        }
    }

    public function test_admin_edit_does_not_overwrite_a_review_completed_during_validation(): void
    {
        [$admin, $entity, $owner] = $this->createRegistration('ngo', 'pending_review');
        $interleaved = false;

        // Commit the competing review after the edit loaded its snapshot, during validation.
        DB::listen(function (QueryExecuted $query) use ($entity, $owner, &$interleaved): void {
            if ($interleaved || ! str_contains($query->sql, 'count(*)') || ! str_contains($query->sql, 'registration_no')) {
                return;
            }

            $interleaved = true;
            DB::table('entities')->where('id', $entity->getKey())->update([
                'status' => 'active',
                'metadata' => json_encode(['review' => ['decision' => 'approve']]),
            ]);
            DB::table('users')->where('id', $owner->getKey())->update(['status' => 'active']);
        });

        $this->actingAs($admin)->postJson(route('admin.entities.update', $entity), [
            ...$this->entityPayload($entity),
            'name_en' => 'Stale administrator edit',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertTrue($interleaved);
        $this->assertSame('active', $entity->fresh()->status);
        $this->assertSame('approve', data_get($entity->fresh()->metadata, 'review.decision'));
        $this->assertSame('Registered Applicant', $entity->fresh()->name_en);
    }

    public function test_signed_completion_cannot_reopen_a_registration_approved_during_validation(): void
    {
        [, $entity, $owner] = $this->createRegistration('ngo', 'needs_completion');
        $signedUrl = URL::temporarySignedRoute('registration.completion.link.edit', now()->addHour(), ['entity' => $entity]);
        $interleaved = false;

        DB::listen(function (QueryExecuted $query) use ($entity, $owner, &$interleaved): void {
            if ($interleaved || ! str_contains($query->sql, 'count(*)') || ! str_contains($query->sql, 'registration_no')) {
                return;
            }

            $interleaved = true;
            DB::table('entities')->where('id', $entity->getKey())->update(['status' => 'active']);
            DB::table('users')->where('id', $owner->getKey())->update(['status' => 'active']);
        });

        $this->post($signedUrl, [
            'entity_name' => 'Stale completion',
            'registration_number' => $entity->registration_no,
            'email' => $owner->email,
            'phone' => '0793555999',
            'address' => 'Amman',
            'description' => 'Stale submission',
        ])->assertNotFound();

        $this->assertTrue($interleaved);
        $this->assertSame('active', $entity->fresh()->status);
        $this->assertSame('active', $owner->fresh()->status);
        $this->assertSame('Registered Applicant', $entity->fresh()->name_en);
    }

    public function test_only_pending_registrations_accept_review_actions(): void
    {
        [$admin, $entity, $owner] = $this->createRegistration('ngo', 'pending_review');

        foreach (['approve' => 'active', 'reject' => 'rejected', 'needs_completion' => 'needs_completion'] as $first => $status) {
            DB::table('entities')->where('id', $entity->getKey())->update(['status' => 'pending_review', 'metadata' => null]);
            DB::table('users')->where('id', $owner->getKey())->update(['status' => 'pending_review']);

            $this->actingAs($admin)->post(route('admin.entities.review', $entity), ['decision' => $first])
                ->assertRedirect(route('admin.entities.show', $entity));

            foreach (['approve', 'reject', 'needs_completion'] as $second) {
                $this->actingAs($admin)->postJson(route('admin.entities.review', $entity), ['decision' => $second])
                    ->assertUnprocessable()->assertJsonValidationErrors('decision');
            }

            $this->assertSame($status, $entity->fresh()->status);
            $this->assertSame($status, $owner->fresh()->status);
            $this->assertCount(1, data_get($entity->fresh()->metadata, 'review_history'));
        }
    }

    private function createRegistration(string $type, string $status, ?string $nationalId = '9981051001'): array
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Notification::fake();

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $group = Group::query()->where('code', $type === 'student' ? 'individuals' : 'organizations')->firstOrFail();
        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Registered Applicant',
            'name_ar' => 'Registered Applicant',
            'registration_no' => $type === 'student' ? null : 'REGISTERED-1001',
            'national_id' => $nationalId,
            'status' => $status,
            'registration_type' => $type,
        ]);
        $owner = User::factory()->create([
            'username' => 'registered-applicant',
            'status' => $status,
            'national_id' => $nationalId,
            'registration_type' => $type,
        ]);
        $entity->users()->attach($owner, ['is_primary' => true, 'status' => 'active', 'joined_at' => now()]);

        return [$admin, $entity, $owner];
    }

    private function entityPayload(Entity $entity): array
    {
        return [
            'group_id' => $entity->group_id,
            'code' => $entity->code,
            'name_en' => $entity->name_en,
            'name_ar' => $entity->name_ar,
            'registration_no' => $entity->registration_no,
            'national_id' => $entity->national_id,
            'email' => $entity->email,
            'phone' => $entity->phone,
            'status' => $entity->status,
            'address' => '',
            'description' => '',
        ];
    }
}
