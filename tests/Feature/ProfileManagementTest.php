<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_owner_can_update_account_contact_details_and_logo(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Notification::fake();
        Storage::fake('local');

        [$user, $entity] = $this->createCompanyApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('profile.account.update'), [
                'email' => 'updated.owner@example.test',
                'phone' => '0791112233',
                'current_password' => 'password',
                'password' => 'NewPass@123',
                'password_confirmation' => 'NewPass@123',
            ])
            ->assertRedirect(route('profile.show'));

        $user->refresh();
        $this->assertSame('updated.owner@example.test', $user->email);
        $this->assertSame('962791112233', $user->phone);
        $this->assertTrue(Hash::check('NewPass@123', $user->password));

        $logo = UploadedFile::fake()->image('profile-logo.png', 80, 80)->size(128);

        $this
            ->actingAs($user)
            ->post(route('profile.contact.update'), [
                'email' => 'studio-contact@example.test',
                'phone' => '0792223344',
                'address' => 'Jabal Amman',
                'website_url' => 'https://example.test/studio',
                'description' => 'Updated production profile.',
                'logo' => $logo,
            ])
            ->assertRedirect(route('profile.show'));

        $entity->refresh();

        $this->assertSame('studio-contact@example.test', $entity->email);
        $this->assertSame('962792223344', $entity->phone);
        $this->assertSame('Jabal Amman', data_get($entity->metadata, 'address'));
        $this->assertSame('https://example.test/studio', data_get($entity->metadata, 'website_url'));
        $this->assertSame('Updated production profile.', data_get($entity->metadata, 'description'));
        $this->assertSame('profile-logo.png', data_get($entity->metadata, 'logo_name'));
        Storage::disk('local')->assertExists(data_get($entity->metadata, 'logo_path'));

        $this
            ->actingAs($user)
            ->get(route('profile.logo'))
            ->assertOk();
    }

    public function test_official_profile_changes_are_reviewed_before_being_applied(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Notification::fake();

        [$user, $entity] = $this->createCompanyApplicantContext();

        $this
            ->actingAs($user)
            ->post(route('profile.official-change-request.store'), [
                'name_en' => 'Applicant Studio',
                'name_ar' => 'استوديو مقدم الطلب',
                'registration_no' => 'REG-10001',
                'national_id' => 'NAT-10001',
                'company_registration_date' => '2026-01-10',
                'company_capital' => '250000',
                'note' => 'Capital was updated after registration.',
            ])
            ->assertRedirect(route('profile.show'));

        $entity->refresh();
        $changeRequest = collect(data_get($entity->metadata, 'profile_change_requests'))->first();

        $this->assertSame('pending', $changeRequest['status']);
        $this->assertSame('120000', data_get($entity->metadata, 'company_capital'));
        $this->assertSame('250000', data_get($changeRequest, 'fields.company_capital.requested'));

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.entities.profile-change-requests.review', [$entity->getKey(), $changeRequest['id']]), [
                'decision' => 'approve',
                'note' => 'Approved after document check.',
            ])
            ->assertRedirect(route('admin.entities.show', $entity));

        $entity->refresh();
        $reviewedRequest = collect(data_get($entity->metadata, 'profile_change_requests'))->first();

        $this->assertSame('250000', data_get($entity->metadata, 'company_capital'));
        $this->assertSame('approved', $reviewedRequest['status']);
        $this->assertSame('Approved after document check.', $reviewedRequest['review_note']);
    }

    /**
     * @return array{0:User,1:Entity}
     */
    private function createCompanyApplicantContext(): array
    {
        $group = Group::query()->where('code', 'organizations')->firstOrFail();

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'code' => 'test-applicant-studio',
            'name_en' => 'Applicant Studio',
            'name_ar' => 'استوديو مقدم الطلب',
            'registration_no' => 'REG-10001',
            'national_id' => 'NAT-10001',
            'email' => 'studio@example.test',
            'phone' => '0790001122',
            'registration_type' => 'company',
            'status' => 'active',
            'metadata' => [
                'address' => 'Amman',
                'description' => 'Production company.',
                'company_registration_date' => '2026-01-10',
                'company_capital' => '120000',
            ],
        ]);

        $user = User::factory()->create([
            'name' => 'Applicant Owner',
            'email' => 'owner@example.test',
            'phone' => '0790001123',
            'status' => 'active',
            'registration_type' => 'company',
        ]);

        $user->entities()->attach($entity->getKey(), [
            'job_title' => 'Primary owner',
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $entity];
    }
}
