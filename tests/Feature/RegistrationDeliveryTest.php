<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use App\Notifications\Channels\SmsNotificationChannel;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RegistrationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public static function decisions(): array
    {
        return [['approve', 'active'], ['reject', 'rejected'], ['needs_completion', 'needs_completion']];
    }

    #[DataProvider('decisions')]
    public function test_transport_failure_is_retried_separately_from_saved_review(string $decision, string $status): void
    {
        [$admin, $entity, $owner] = $this->createRegistration();
        $mailAttempts = 0;
        $mail = Mockery::mock(MailChannel::class);
        $mail->shouldReceive('send')->twice()->andReturnUsing(function () use (&$mailAttempts): void {
            if (++$mailAttempts === 1) {
                throw new RuntimeException('Controlled mail transport outage.');
            }
        });
        $this->app->instance(MailChannel::class, $mail);
        $sms = Mockery::mock(SmsNotificationChannel::class);
        $sms->shouldReceive('send')->once()->andReturn(['ok' => true, 'stage' => 'sent']);
        $this->app->instance(SmsNotificationChannel::class, $sms);

        $this->actingAs($admin)->post(route('admin.entities.review', $entity), ['decision' => $decision])
            ->assertRedirect(route('admin.entities.show', $entity))
            ->assertSessionHas('status', __('app.admin.entities.review_saved'));

        $this->assertSame(0, $mailAttempts);
        $this->assertSame($status, $entity->fresh()->status);
        $this->assertSame($status, $owner->fresh()->status);
        $this->assertCount(1, data_get($entity->fresh()->metadata, 'review_history'));
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('jobs', 2);

        $this->runNextDelivery(); // Failed mail is released; the committed review remains successful.
        $this->assertSame(1, $mailAttempts);
        $this->assertDatabaseCount('jobs', 2);
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $owner->id, 'channel' => 'mail', 'status' => 'failed']);
        $this->runNextDelivery(); // SMS is independent of the mail failure.
        $this->assertDatabaseCount('jobs', 1);
        $this->travel(16)->seconds();
        $this->runNextDelivery();

        $this->assertSame(2, $mailAttempts);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $owner->id, 'channel' => 'mail', 'status' => 'sent']);
        $this->assertSame($status, $entity->fresh()->status);
        $this->assertCount(1, data_get($entity->fresh()->metadata, 'review_history'));

        $this->actingAs($admin)->postJson(route('admin.entities.review', $entity), ['decision' => 'approve'])
            ->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_second_delivery_enqueue_failure_rolls_back_decision_inbox_and_first_job(): void
    {
        [$admin, $entity, $owner] = $this->createRegistration();
        $inserts = 0;
        DB::connection()->beforeExecuting(function (string $query) use (&$inserts): void {
            if (str_starts_with($query, 'insert into "jobs"') && ++$inserts === 2) {
                throw new RuntimeException('Controlled second delivery enqueue failure.');
            }
        });

        $this->actingAs($admin)->post(route('admin.entities.review', $entity), ['decision' => 'reject'])
            ->assertStatus(500);

        $this->assertSame(2, $inserts);
        $this->assertSame('pending_review', $entity->fresh()->status);
        $this->assertSame('pending_review', $owner->fresh()->status);
        $this->assertNull(data_get($entity->fresh()->metadata, 'review_history'));
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('notification_logs', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_another_database_queue_connection_is_rejected_without_committing_review(): void
    {
        [$admin, $entity, $owner] = $this->createRegistration();
        config([
            'database.connections.delivery_other' => config('database.connections.sqlite'),
            'queue.connections.database.connection' => 'delivery_other',
        ]);

        $this->actingAs($admin)->post(route('admin.entities.review', $entity), ['decision' => 'approve'])
            ->assertStatus(500);

        $this->assertSame('pending_review', $entity->fresh()->status);
        $this->assertSame('pending_review', $owner->fresh()->status);
        $this->assertNull(data_get($entity->fresh()->metadata, 'review_history'));
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_undefined_mailer_failure_is_recorded_after_retries_and_can_be_retried_after_configuration_repair(): void
    {
        [$admin, $entity, $owner] = $this->createRegistration();
        config(['mail.default' => 'SMTP']);

        $this->actingAs($admin)->post(route('admin.entities.review', $entity), ['decision' => 'reject'])
            ->assertRedirect(route('admin.entities.show', $entity));
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('jobs', 2);

        $this->runNextDelivery(); // Undefined mailer fails without changing the committed decision.
        $this->runNextDelivery(); // Missing phone is skipped without contacting an SMS provider.
        foreach (range(1, 2) as $attempt) {
            $this->travel(16)->seconds();
            $this->runNextDelivery();
        }

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $owner->id, 'channel' => 'mail', 'status' => 'failed']);
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $owner->id, 'channel' => 'sms', 'status' => 'skipped']);
        $failedJob = DB::table('failed_jobs')->first();
        $this->assertStringContainsString('Mailer [SMTP] is not defined.', $failedJob->exception);

        config(['mail.default' => 'array']);
        $this->artisan('queue:retry', ['id' => [$failedJob->uuid]])->assertExitCode(0);
        $this->runNextDelivery();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $owner->id, 'channel' => 'mail', 'status' => 'sent']);
        $this->assertSame('rejected', $entity->fresh()->status);
        $this->assertSame('rejected', $owner->fresh()->status);
        $this->assertCount(1, data_get($entity->fresh()->metadata, 'review_history'));
    }

    #[DataProvider('decisions')]
    public function test_queued_payload_keeps_only_required_entity_snapshot_without_identity_or_owner_secrets(string $decision, string $status): void
    {
        [$admin, $entity, $owner] = $this->createRegistration();
        DB::table('entities')->where('id', $entity->id)->update([
            'national_id' => 'PRIVATE-IDENTITY-9383',
            'metadata' => json_encode(['private_note' => 'UNRELATED-METADATA-9383']),
        ]);
        $this->assertNotEmpty($owner->getRawOriginal('password'));
        $this->assertNotEmpty($owner->remember_token);

        $this->actingAs($admin)->post(route('admin.entities.review', $entity), ['decision' => $decision])
            ->assertRedirect(route('admin.entities.show', $entity));

        foreach (DB::table('jobs')->get() as $job) {
            $serialized = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR)['data']['command'];
            foreach ([$owner->getRawOriginal('password'), $owner->remember_token, 'PRIVATE-IDENTITY-9383', 'UNRELATED-METADATA-9383'] as $privateValue) {
                $this->assertStringNotContainsString($privateValue, $serialized);
            }
        }
        $queuedJob = unserialize(json_decode(DB::table('jobs')->first()->payload, true, flags: JSON_THROW_ON_ERROR)['data']['command']);
        $notification = $queuedJob->notification;
        $before = $notification->toArray($owner);
        $beforeMail = $notification->toMail($owner);
        $beforeSms = $notification->toSms($owner);
        $this->assertSame('en', $notification->locale);
        $this->assertSame($entity->id, $before['entity_id']);
        $this->assertStringContainsString('Delivery Verification School', $before['body']);
        if ($decision !== 'approve') {
            $this->assertStringContainsString(__('app.statuses.'.$status), implode(' ', $beforeMail->introLines));
        }

        // A queued decision must not change meaning if the record changes later.
        DB::table('entities')->where('id', $entity->id)->update([
            'name_en' => 'Later renamed school', 'name_ar' => 'Later renamed school', 'status' => 'pending_review',
        ]);
        URL::forceRootUrl('https://queue-worker.invalid');
        $restored = unserialize(serialize($notification));
        $this->assertSame($before, $restored->toArray($owner));
        $this->assertSame($beforeMail->introLines, $restored->toMail($owner)->introLines);
        $this->assertSame($beforeMail->actionUrl, $restored->toMail($owner)->actionUrl);
        $this->assertSame($beforeSms, $restored->toSms($owner));
    }

    private function runNextDelivery(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'default', '--once' => true, '--sleep' => 0])
            ->assertExitCode(0);
    }

    private function createRegistration(): array
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);
        // Explicit beforeCommit must win even if a deployment enables after_commit.
        config([
            'queue.default' => 'database',
            'queue.connections.database.connection' => config('database.default'),
            'queue.connections.database.after_commit' => true,
        ]);
        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $entity = Entity::query()->create([
            'group_id' => Group::query()->where('code', 'organizations')->firstOrFail()->id,
            'name_en' => 'Delivery Verification School', 'name_ar' => 'Delivery Verification School',
            'registration_type' => 'school', 'registration_no' => 'DELIVERY-VERIFICATION', 'status' => 'pending_review',
        ]);
        $owner = User::factory()->create([
            'username' => 'delivery-verification-owner', 'email' => 'delivery-verification@example.invalid',
            'phone' => null, 'registration_type' => 'school', 'status' => 'pending_review',
        ]);
        $entity->users()->attach($owner, ['is_primary' => true, 'status' => 'active', 'joined_at' => now()]);

        return [$admin, $entity, $owner];
    }
}
