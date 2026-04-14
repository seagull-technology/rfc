<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegistrationCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_can_update_registration_after_needs_completion(): void
    {
        Storage::fake('local');
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $group = \App\Models\Group::query()->where('code', 'organizations')->firstOrFail();

        $user = User::query()->create([
            'name' => 'Review Company',
            'username' => 'company-review',
            'email' => 'review@company.test',
            'phone' => '0793001000',
            'status' => 'needs_completion',
            'registration_type' => 'company',
            'password' => Hash::make('password123'),
        ]);

        $entity = Entity::query()->create([
            'group_id' => $group->getKey(),
            'name_en' => 'Review Company',
            'name_ar' => 'Review Company',
            'registration_no' => 'COMP-200',
            'email' => 'review@company.test',
            'phone' => '0793001000',
            'status' => 'needs_completion',
            'registration_type' => 'company',
            'metadata' => [
                'address' => 'Old address',
                'description' => 'Old description',
                'review' => [
                    'note' => 'Update your registration details.',
                ],
                'registration_document_path' => 'registration-documents/company/old-license.pdf',
                'registration_document_name' => 'old-license.pdf',
                'registration_document_mime' => 'application/pdf',
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

        $page = $this->actingAs($user)->get(route('registration.completion.edit'));

        $page
            ->assertOk()
            ->assertSeeText('Complete Registration')
            ->assertSeeText('Update your registration details.');

        $response = $this->actingAs($user)->post(route('registration.completion.update'), [
            'entity_name' => 'Updated Review Company',
            'registration_number' => 'COMP-201',
            'email' => 'updated@company.test',
            'phone' => '0793001999',
            'address' => 'New address',
            'description' => 'New description',
            'registration_document' => UploadedFile::fake()->create('updated-license.pdf', 120, 'application/pdf'),
        ]);

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Your updated registration details have been submitted for review.');

        $entity->refresh();
        $user->refresh();

        $this->assertSame('Updated Review Company', $entity->name_en);
        $this->assertSame('COMP-201', $entity->registration_no);
        $this->assertSame('updated@company.test', $entity->email);
        $this->assertSame('962793001999', $entity->phone);
        $this->assertSame('pending_review', $entity->status);
        $this->assertSame('New address', data_get($entity->metadata, 'address'));
        $this->assertSame('New description', data_get($entity->metadata, 'description'));
        $this->assertNotNull(data_get($entity->metadata, 'resubmission.submitted_at'));
        Storage::disk('local')->assertExists((string) data_get($entity->metadata, 'registration_document_path'));

        $this->assertSame('Updated Review Company', $user->name);
        $this->assertSame('updated@company.test', $user->email);
        $this->assertSame('962793001999', $user->phone);
        $this->assertSame('pending_review', $user->status);
    }
}
