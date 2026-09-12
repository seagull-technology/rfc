<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Notifications\Channels\SmsNotificationChannel;
use App\Support\NotificationRecipients;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Mockery;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;

trait ChecksSubmissionDelivery
{
    abstract protected function createSubmissionForDeliveryTest(): array;

    public function test_submission_tls_failure_does_not_skip_other_administrators_or_sms(): void
    {
        [$user, $record, $submitRoute, $showRoute] = $this->createSubmissionForDeliveryTest();
        $admin = $this->configureSubmissionDeliveryTest();
        $anotherAdmin = User::factory()->create(['status' => 'active', 'phone' => null]);
        $admin->entities()->firstOrFail()->users()->attach($anotherAdmin, ['status' => 'active', 'is_primary' => true]);
        $attempts = 0;
        $mail = Mockery::mock(MailChannel::class);
        $mail->shouldReceive('send')->times(3)->andReturnUsing(function () use (&$attempts): void {
            if (++$attempts === 1) {
                throw new TransportException('Unable to connect with STARTTLS: certificate verify failed.');
            }
        });
        $this->app->instance(MailChannel::class, $mail);
        $sms = Mockery::mock(SmsNotificationChannel::class);
        $sms->shouldReceive('send')->twice()->andReturn(['ok' => true, 'stage' => 'sent']);
        $this->app->instance(SmsNotificationChannel::class, $sms);

        $this->actingAs($user)->post($submitRoute)->assertRedirect($showRoute)->assertSessionHasNoErrors();
        $this->assertSame(0, $attempts);
        $this->assertSame('submitted', $record->fresh()->status);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('jobs', 4);
        foreach (range(1, 4) as $delivery) {
            $this->runNextSubmissionDelivery();
        }
        $this->assertSame(2, $attempts);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame(2, DB::table('notification_logs')->where('channel', 'sms')->where('status', 'sent')->count());
        $this->assertSame(1, DB::table('notification_logs')->where('channel', 'mail')->where('status', 'sent')->count());
        $this->travel(16)->seconds();
        $this->runNextSubmissionDelivery();
        $this->assertSame(3, $attempts);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(2, DB::table('notification_logs')->where('channel', 'mail')->where('status', 'sent')->count());
        $this->assertSame(1, $record->statusHistory()->where('status', 'submitted')->count());
    }

    public function test_submission_mail_failure_retries_without_repeating_saved_state_or_inbox(): void
    {
        [$user, $record, $submitRoute, $showRoute] = $this->createSubmissionForDeliveryTest();
        $admin = $this->configureSubmissionDeliveryTest();
        config(['mail.default' => 'SMTP']);
        $sms = Mockery::mock(SmsNotificationChannel::class);
        $sms->shouldReceive('send')->once()->andReturn(['ok' => true, 'stage' => 'sent']);
        $this->app->instance(SmsNotificationChannel::class, $sms);

        $this->actingAs($user)->post($submitRoute)->assertRedirect($showRoute)->assertSessionHasNoErrors();
        $this->assertSame('submitted', $record->fresh()->status);
        $this->assertSame(1, $record->statusHistory()->where('status', 'submitted')->count());
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('jobs', 2);
        $this->assertDatabaseMissing('notification_logs', ['channel' => 'mail']);

        $this->runNextSubmissionDelivery(); // Failed mail leaves the submission intact.
        $this->runNextSubmissionDelivery(); // SMS is independent of the mail failure.
        foreach (range(1, 2) as $attempt) {
            $this->travel(16)->seconds();
            $this->runNextSubmissionDelivery();
        }
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $admin->id, 'channel' => 'mail', 'status' => 'failed']);
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $admin->id, 'channel' => 'sms', 'status' => 'sent']);
        $failedJob = DB::table('failed_jobs')->first();
        $this->assertStringContainsString('Mailer [SMTP] is not defined.', $failedJob->exception);

        config(['mail.default' => 'array']);
        $this->artisan('queue:retry', ['id' => [$failedJob->uuid]])->assertExitCode(0);
        $this->runNextSubmissionDelivery();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseHas('notification_logs', ['notifiable_id' => $admin->id, 'channel' => 'mail', 'status' => 'sent']);
        $this->assertSame('submitted', $record->fresh()->status);
        $this->assertSame(1, $record->statusHistory()->where('status', 'submitted')->count());
        $this->assertDatabaseCount('notifications', 1);

        $this->actingAs($user)->post($submitRoute)->assertForbidden();
        $this->assertSame(1, $record->statusHistory()->where('status', 'submitted')->count());
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_later_submission_recipient_enqueue_failure_rolls_back_every_recipient_and_status(): void
    {
        [$user, $record, $submitRoute] = $this->createSubmissionForDeliveryTest();
        $admin = $this->configureSubmissionDeliveryTest();
        $anotherAdmin = User::factory()->create(['status' => 'active', 'phone' => null]);
        $admin->entities()->firstOrFail()->users()->attach($anotherAdmin, ['status' => 'active', 'is_primary' => true]);
        $this->assertCount(2, NotificationRecipients::adminUsers());
        $inserts = 0;
        DB::connection()->beforeExecuting(function (string $query) use (&$inserts): void {
            if (str_starts_with($query, 'insert into "jobs"') && ++$inserts === 3) {
                throw new RuntimeException('Controlled later-recipient delivery enqueue failure.');
            }
        });

        $this->actingAs($user)->post($submitRoute)->assertStatus(500);
        $this->assertSame(3, $inserts);
        $this->assertSame('draft', $record->fresh()->status);
        $this->assertNull($record->fresh()->submitted_at);
        $this->assertSame(0, $record->statusHistory()->where('status', 'submitted')->count());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('notification_logs', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_submission_rejects_a_separate_delivery_database_without_committing(): void
    {
        [$user, $record, $submitRoute] = $this->createSubmissionForDeliveryTest();
        $this->configureSubmissionDeliveryTest();
        config([
            'database.connections.submission_other' => config('database.connections.sqlite'),
            'queue.connections.database.connection' => 'submission_other',
        ]);

        $this->actingAs($user)->post($submitRoute)->assertStatus(500);
        $this->assertSame('draft', $record->fresh()->status);
        $this->assertSame(0, $record->statusHistory()->where('status', 'submitted')->count());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_queued_submission_preserves_message_and_url_without_serializing_private_model_fields(): void
    {
        [$user, $record, $submitRoute, $showRoute] = $this->createSubmissionForDeliveryTest();
        $admin = $this->configureSubmissionDeliveryTest();
        $user->forceFill(['remember_token' => 'PRIVATE-SUBMISSION-TOKEN'])->save();
        $record->forceFill(['metadata' => [...$record->metadata, 'private_note' => 'PRIVATE-SUBMISSION-METADATA']])->save();

        $this->actingAs($user)->post($submitRoute)->assertRedirect($showRoute);
        $this->assertDatabaseCount('jobs', 2);
        foreach (DB::table('jobs')->get() as $job) {
            $serialized = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR)['data']['command'];
            foreach ([$user->getRawOriginal('password'), 'PRIVATE-SUBMISSION-TOKEN', 'PRIVATE-SUBMISSION-METADATA'] as $secret) {
                $this->assertStringNotContainsString($secret, $serialized);
            }
        }
        $notification = unserialize(json_decode(DB::table('jobs')->first()->payload, true, flags: JSON_THROW_ON_ERROR)['data']['command'])->notification;
        $before = $notification->toArray($admin);
        $beforeUrl = $notification->toMail($admin)->actionUrl;
        $beforeSms = $notification->toSms($admin);
        $this->assertSame('en', $notification->locale);
        $this->assertNotEmpty($beforeUrl);
        $this->assertSame(3, $notification->tries);
        $this->assertSame(15, $notification->backoff);
        $this->assertSame(90, $notification->timeout);
        $record->forceFill(['project_name' => 'Later renamed submission'])->save();
        URL::forceRootUrl('https://worker-context.invalid');
        $restored = unserialize(serialize($notification));
        $this->assertSame($before, $restored->toArray($admin));
        $this->assertSame($beforeUrl, $restored->toMail($admin)->actionUrl);
        $this->assertSame($beforeSms, $restored->toSms($admin));
    }

    private function configureSubmissionDeliveryTest(): User
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.connection' => config('database.default'),
            'queue.connections.database.after_commit' => true,
        ]);
        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        NotificationRecipients::adminUsers()->reject(fn (User $recipient) => $recipient->is($admin))
            ->each(fn (User $recipient) => $recipient->forceFill(['status' => 'inactive'])->save());
        $this->assertCount(1, NotificationRecipients::adminUsers());

        return $admin;
    }

    private function runNextSubmissionDelivery(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'default', '--once' => true, '--sleep' => 0])
            ->assertExitCode(0);
    }
}
