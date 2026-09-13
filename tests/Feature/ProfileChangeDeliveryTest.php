<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use App\Notifications\Channels\SmsNotificationChannel;
use App\Notifications\ProfileChangeInboxNotification;
use App\Support\NotificationRecipients;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class ProfileChangeDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_tls_failure_does_not_skip_reviewers_or_duplicate_the_pending_request(): void
    {
        [$admin, $entity, $owner] = $this->createContext();
        $anotherAdmin = $this->addReviewer($admin);
        $attempts = 0;
        $this->installRetryingTransports($attempts, 3, 2);

        $this->actingAs($owner)->post(route('profile.official-change-request.store'), $this->changePayload($entity))
            ->assertRedirect(route('profile.show'))->assertSessionHasNoErrors();
        $this->assertSame(0, $attempts);
        $this->assertSame('Profile Delivery Company', $entity->fresh()->name_en);
        $this->assertCount(1, $this->requests($entity));
        $this->assertSame('pending', $this->requests($entity)[0]['status']);
        $this->assertSame(['name_en'], array_keys($this->requests($entity)[0]['fields']));
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('jobs', 4);
        $this->assertEqualsCanonicalizing([$admin->id, $anotherAdmin->id], DB::table('notifications')->pluck('notifiable_id')->all());

        // A repeated form is rejected before any inbox or queue insert.
        $this->actingAs($owner)->postJson(route('profile.official-change-request.store'), $this->changePayload($entity))
            ->assertUnprocessable()->assertJsonValidationErrors('profile');
        $this->assertCount(1, $this->requests($entity));
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('jobs', 4);

        foreach (range(1, 4) as $delivery) {
            $this->runNextDelivery();
        }
        $this->assertSame(2, $attempts);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame(2, DB::table('notification_logs')->where('channel', 'sms')->where('status', 'sent')->count());
        $this->assertSame(1, DB::table('notification_logs')->where('channel', 'mail')->where('status', 'failed')->count());
        $this->travel(16)->seconds();
        $this->runNextDelivery();
        $this->assertSame(3, $attempts);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertCount(1, $this->requests($entity));
        $this->assertSame('pending', $this->requests($entity)[0]['status']);
        $this->assertOriginalIdentityAndContacts($entity, $owner);
    }

    public static function decisions(): array
    {
        return [['approve', 'approved', 'Profile Delivery Company SV-REVIEW'], ['reject', 'rejected', 'Profile Delivery Company']];
    }

    #[DataProvider('decisions')]
    public function test_review_tls_failure_retries_after_success_without_repeating_the_decision(string $decision, string $status, string $name): void
    {
        [$admin, $entity, $owner] = $this->createContext();
        $this->createPendingRequest($entity, $owner);
        $attempts = 0;
        $this->installRetryingTransports($attempts, 2, 1);
        $url = route('admin.entities.profile-change-requests.review', [$entity, 'profile-delivery-request']);

        $this->actingAs($admin)->post($url, ['decision' => $decision, 'note' => 'SV-REVIEW'])
            ->assertRedirect(route('admin.entities.show', $entity))->assertSessionHasNoErrors();
        $this->assertSame(0, $attempts);
        $this->assertSame($name, $entity->fresh()->name_en);
        $this->assertSame($status, $this->requests($entity)[0]['status']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id, 'type' => ProfileChangeInboxNotification::class]);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('jobs', 2);

        $this->runNextDelivery();
        $this->assertSame(1, $attempts);
        $this->assertSame($status, $this->requests($entity)[0]['status']);
        $this->runNextDelivery();
        $this->travel(16)->seconds();
        $this->runNextDelivery();
        $this->assertSame(2, $attempts);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $owner->id, 'channel' => 'mail', 'status' => 'failed']);
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $owner->id, 'channel' => 'mail', 'status' => 'sent']);

        $this->actingAs($admin)->postJson($url, ['decision' => $decision === 'approve' ? 'reject' : 'approve'])
            ->assertUnprocessable()->assertJsonValidationErrors('profile_change_request');
        $this->assertSame($name, $entity->fresh()->name_en);
        $this->assertSame($status, $this->requests($entity)[0]['status']);
        $this->assertCount(1, $this->requests($entity));
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertOriginalIdentityAndContacts($entity, $owner);
    }

    public static function writePaths(): array
    {
        return [['request'], ['review']];
    }

    #[DataProvider('writePaths')]
    public function test_second_enqueue_failure_rolls_back_profile_state_inbox_audit_and_first_job(string $path): void
    {
        [$admin, $entity, $owner] = $this->createContext();
        if ($path === 'review') {
            $this->createPendingRequest($entity, $owner);
        }
        $before = $entity->fresh()->getAttributes();
        $inserts = 0;
        DB::connection()->beforeExecuting(function (string $query) use (&$inserts): void {
            if (str_starts_with($query, 'insert into "jobs"') && ++$inserts === 2) {
                throw new RuntimeException('Controlled second delivery enqueue failure.');
            }
        });

        $this->performWrite($path, $admin, $entity, $owner)->assertStatus(500);
        $this->assertSame(2, $inserts);
        $this->assertSame($before, $entity->fresh()->getAttributes());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('notification_logs', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertOriginalIdentityAndContacts($entity, $owner);
    }

    public function test_later_reviewer_enqueue_failure_rolls_back_all_recipient_deliveries_and_the_request(): void
    {
        [$admin, $entity, $owner] = $this->createContext();
        $this->addReviewer($admin);
        $inserts = 0;
        DB::connection()->beforeExecuting(function (string $query) use (&$inserts): void {
            if (str_starts_with($query, 'insert into "jobs"') && ++$inserts === 3) {
                throw new RuntimeException('Controlled later reviewer enqueue failure.');
            }
        });

        $this->performWrite('request', $admin, $entity, $owner)->assertStatus(500);
        $this->assertSame(3, $inserts);
        $this->assertSame([], $this->requests($entity));
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('notification_logs', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    #[DataProvider('writePaths')]
    public function test_separate_queue_database_is_rejected_without_committing_profile_changes(string $path): void
    {
        [$admin, $entity, $owner] = $this->createContext();
        if ($path === 'review') {
            $this->createPendingRequest($entity, $owner);
        }
        $before = $entity->fresh()->getAttributes();
        config([
            'database.connections.profile_delivery_other' => config('database.connections.sqlite'),
            'queue.connections.database.connection' => 'profile_delivery_other',
        ]);

        $this->performWrite($path, $admin, $entity, $owner)->assertStatus(500);
        $this->assertSame($before, $entity->fresh()->getAttributes());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    #[DataProvider('writePaths')]
    public function test_queued_profile_message_keeps_locale_link_and_minimal_primitives_without_private_data(string $path): void
    {
        [$admin, $entity, $owner] = $this->createContext();
        if ($path === 'review') {
            $this->createPendingRequest($entity, $owner);
        }
        $this->performWrite($path, $admin, $entity, $owner)->assertRedirect();
        $this->assertDatabaseCount('jobs', 2);
        $recipient = $path === 'request' ? $admin : $owner;
        foreach (DB::table('jobs')->get() as $job) {
            $serialized = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR)['data']['command'];
            foreach ([$owner->getRawOriginal('password'), $admin->getRawOriginal('password'), 'PRIVATE-PROFILE-TOKEN',
                'PRIVATE-PROFILE-METADATA', 'PRIVATE-PROFILE-NATIONAL-ID', 'PRIVATE-PROFILE-REGISTRATION',
                'PRIVATE-PROFILE-NOTE', 'private-profile-owner@example.invalid', 'private-profile-entity@example.invalid',
                'PRIVATE-PROFILE-ADDRESS'] as $privateValue) {
                $this->assertStringNotContainsString($privateValue, $serialized);
            }
        }
        $notification = unserialize(json_decode(DB::table('jobs')->first()->payload, true, flags: JSON_THROW_ON_ERROR)['data']['command'])->notification;
        $before = $notification->toArray($recipient);
        $mail = $notification->toMail($recipient);
        $sms = $notification->toSms($recipient);
        $this->assertInstanceOf(ProfileChangeInboxNotification::class, $notification);
        $this->assertSame('en', $notification->locale);
        $this->assertSame(3, $notification->tries);
        $this->assertSame(90, $notification->timeout);
        $this->assertSame(15, $notification->backoff);
        $this->assertTrue($notification->retryFailedSmsDelivery);
        $this->assertSame($entity->id, $before['entity_id']);
        $this->assertSame($path === 'request' ? 'profile_change_requested' : 'profile_change_approved', $before['type_key']);
        $this->assertSame($path === 'request' ? ['entity' => $entity->id] : [], $before['route_parameters']);
        $this->assertSame($path === 'request' ? route('admin.entities.show', $entity) : route('profile.show'), $mail->actionUrl);
        $this->assertArrayNotHasKey('fields', $before);
        $this->assertArrayNotHasKey('note', $before);

        $entity->forceFill(['name_en' => 'Later renamed profile'])->save();
        URL::forceRootUrl('https://worker-context.invalid');
        $restored = unserialize(serialize($notification));
        $this->assertSame($before, $restored->toArray($recipient));
        $this->assertSame($mail->actionUrl, $restored->toMail($recipient)->actionUrl);
        $this->assertSame($mail->introLines, $restored->toMail($recipient)->introLines);
        $this->assertSame($sms, $restored->toSms($recipient));
    }

    private function installRetryingTransports(int &$attempts, int $mailCalls, int $smsCalls): void
    {
        $mail = Mockery::mock(MailChannel::class);
        $mail->shouldReceive('send')->times($mailCalls)->andReturnUsing(function () use (&$attempts): void {
            if (++$attempts === 1) {
                throw new TransportException('Unable to connect with STARTTLS: certificate verify failed.');
            }
        });
        $this->app->instance(MailChannel::class, $mail);
        $sms = Mockery::mock(SmsNotificationChannel::class);
        $sms->shouldReceive('send')->times($smsCalls)->andReturn(['ok' => true, 'stage' => 'sent']);
        $this->app->instance(SmsNotificationChannel::class, $sms);
    }

    private function runNextDelivery(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'default', '--once' => true, '--sleep' => 0])
            ->assertExitCode(0);
    }

    private function performWrite(string $path, User $admin, Entity $entity, User $owner): TestResponse
    {
        if ($path === 'request') {
            return $this->actingAs($owner)->post(route('profile.official-change-request.store'), $this->changePayload($entity));
        }

        return $this->actingAs($admin)->post(route('admin.entities.profile-change-requests.review', [$entity, 'profile-delivery-request']), ['decision' => 'approve']);
    }

    private function changePayload(Entity $entity): array
    {
        return [
            'name_en' => 'Profile Delivery Company SV-REVIEW', 'name_ar' => $entity->name_ar,
            'registration_no' => 'IGNORED-REGISTRATION-TAMPER', 'national_id' => 'IGNORED-IDENTITY-TAMPER', 'registration_type' => 'staff',
            'company_registration_date' => '2026-01-10', 'company_capital' => '100', 'note' => 'PRIVATE-PROFILE-NOTE',
        ];
    }

    private function requests(Entity $entity): array
    {
        return data_get($entity->fresh()->metadata, 'profile_change_requests', []);
    }

    private function createPendingRequest(Entity $entity, User $owner): void
    {
        $entity->forceFill(['metadata' => [...$entity->metadata, 'profile_change_requests' => [[
            'id' => 'profile-delivery-request', 'status' => 'pending', 'requested_at' => now()->toDateTimeString(),
            'requested_by_user_id' => $owner->id, 'note' => 'PRIVATE-PROFILE-NOTE',
            'fields' => ['name_en' => ['label' => 'Name', 'current' => $entity->name_en, 'requested' => 'Profile Delivery Company SV-REVIEW']],
        ]]]])->save();
    }

    private function addReviewer(User $admin): User
    {
        $reviewer = User::factory()->create(['status' => 'active', 'phone' => null]);
        $admin->entities()->firstOrFail()->users()->attach($reviewer, ['status' => 'active', 'is_primary' => true]);

        return $reviewer;
    }

    private function assertOriginalIdentityAndContacts(Entity $entity, User $owner): void
    {
        $this->assertSame('PRIVATE-PROFILE-NATIONAL-ID', $entity->fresh()->national_id);
        $this->assertSame('PRIVATE-PROFILE-REGISTRATION', $entity->fresh()->registration_no);
        $this->assertSame('company', $entity->fresh()->registration_type);
        $this->assertSame('private-profile-entity@example.invalid', $entity->fresh()->email);
        $this->assertNull($entity->fresh()->phone);
        $this->assertSame('private-profile-owner@example.invalid', $owner->fresh()->email);
        $this->assertNull($owner->fresh()->phone);
    }

    private function createContext(): array
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        Http::preventStrayRequests();
        config([
            'queue.default' => 'database',
            'queue.connections.database.connection' => config('database.default'),
            'queue.connections.database.after_commit' => true,
        ]);
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $admin->forceFill(['phone' => null])->save();
        NotificationRecipients::adminUsers()->reject(fn (User $recipient) => $recipient->is($admin))
            ->each(fn (User $recipient) => $recipient->forceFill(['status' => 'inactive'])->save());
        $this->assertCount(1, NotificationRecipients::adminUsers());
        $entity = Entity::query()->create([
            'group_id' => Group::query()->where('code', 'organizations')->firstOrFail()->id,
            'name_en' => 'Profile Delivery Company', 'name_ar' => 'Profile Delivery Company',
            'registration_type' => 'company', 'registration_no' => 'PRIVATE-PROFILE-REGISTRATION',
            'national_id' => 'PRIVATE-PROFILE-NATIONAL-ID', 'status' => 'active', 'email' => 'private-profile-entity@example.invalid',
            'metadata' => ['company_registration_date' => '2026-01-10', 'company_capital' => '100',
                'address' => 'PRIVATE-PROFILE-ADDRESS', 'private_note' => 'PRIVATE-PROFILE-METADATA'],
        ]);
        $owner = User::factory()->create([
            'username' => 'profile-delivery-owner', 'email' => 'private-profile-owner@example.invalid',
            'phone' => null, 'registration_type' => 'company', 'status' => 'active', 'remember_token' => 'PRIVATE-PROFILE-TOKEN',
        ]);
        $entity->users()->attach($owner, ['is_primary' => true, 'status' => 'active', 'joined_at' => now()]);

        return [$admin, $entity, $owner];
    }
}
