<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SignedRegistrationCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ngo_user_can_complete_registration_through_signed_link(): void
    {
        Storage::fake('local');
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $group = \App\Models\Group::query()->where('code', 'organizations')->firstOrFail();

        $user = User::query()->create([
            'name' => 'Pending NGO',
            'username' => 'pending-ngo',
            'email' => 'ngo-review@example.com',
            'phone' => '0793555000',
            'status' => 'needs_completion',
            'registration_type' => 'ngo',
            'password' => Hash::make('password123'),
        ]);

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Pending NGO',
            'name_ar' => 'Pending NGO',
            'registration_no' => 'NGO-777',
            'email' => 'ngo-review@example.com',
            'phone' => '0793555000',
            'status' => 'needs_completion',
            'registration_type' => 'ngo',
            'metadata' => [
                'address' => 'Old NGO address',
                'description' => 'Old NGO description',
                'review' => [
                    'note' => 'Please update your NGO information.',
                ],
            ],
        ]);

        $entity->users()->attach($user->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $user->assignRole('applicant_owner');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $signedUrl = URL::temporarySignedRoute('registration.completion.link.edit', now()->addDays(7), [
            'entity' => $entity->getKey(),
        ]);

        $page = $this->get($signedUrl);

        $page
            ->assertOk()
            ->assertSeeText('Complete Registration')
            ->assertSeeText('Please update your NGO information.');

        $response = $this->post($signedUrl, [
            'entity_name' => 'Updated NGO',
            'registration_number' => 'NGO-778',
            'email' => 'ngo-updated@example.com',
            'phone' => '0793555999',
            'address' => 'Updated NGO address',
            'description' => 'Updated NGO description',
            'registration_document' => UploadedFile::fake()->create('ngo-license.pdf', 120, 'application/pdf'),
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your updated registration details have been submitted for review. You can sign in after your account is approved.');

        $entity->refresh();
        $user->refresh();

        $this->assertSame('Updated NGO', $entity->name_en);
        $this->assertSame('NGO-778', $entity->registration_no);
        $this->assertSame('ngo-updated@example.com', $entity->email);
        $this->assertSame('962793555999', $entity->phone);
        $this->assertSame('pending_review', $entity->status);
        $this->assertTrue((bool) data_get($entity->metadata, 'resubmission.via_signed_link'));
        Storage::disk('local')->assertExists((string) data_get($entity->metadata, 'registration_document_path'));

        $this->assertSame('pending_review', $user->status);
        $this->assertSame('ngo-updated@example.com', $user->email);
    }

    public function test_signed_completion_page_rejects_unsigned_access(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $group = \App\Models\Group::query()->where('code', 'organizations')->firstOrFail();
        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Unsigned NGO',
            'name_ar' => 'Unsigned NGO',
            'registration_no' => 'NGO-990',
            'status' => 'needs_completion',
            'registration_type' => 'ngo',
        ]);

        $this->get(route('registration.completion.link.edit', ['entity' => $entity->getKey()]))
            ->assertForbidden();
    }
}
