<?php

namespace Tests\Unit;

use App\Notifications\Channels\SmsNotificationChannel;
use App\Services\SmsService;
use Illuminate\Notifications\Notification;
use RuntimeException;
use Tests\TestCase;

class RegistrationSmsRetryTest extends TestCase
{
    public function test_opted_in_provider_failure_throws_a_sanitized_exception(): void
    {
        $channel = new SmsNotificationChannel($this->service([
            'ok' => false, 'stage' => 'send_exception',
            'raw' => 'private-provider-details', 'msisdn' => 'private-phone',
        ]));

        try {
            $channel->send($this->recipient('TEST'), $this->notification(true));
            $this->fail('The queue must receive an exception so this channel can retry.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Queued SMS delivery failed.', $exception->getMessage());
            $this->assertStringNotContainsString('private-', $exception->getMessage());
        }
    }

    public function test_missing_phone_and_empty_message_do_not_call_the_provider(): void
    {
        $service = new class extends SmsService
        {
            public function __construct() {}

            public function send(string $text, string $to): array
            {
                throw new RuntimeException('Provider must not be called.');
            }
        };
        $channel = new SmsNotificationChannel($service);

        $this->assertSame('missing_phone', $channel->send($this->recipient(null), $this->notification(true))['stage']);
        $this->assertSame('empty_message', $channel->send($this->recipient('TEST'), $this->notification(true, ''))['stage']);
    }

    public function test_invalid_phone_is_a_terminal_result_for_opted_in_notifications(): void
    {
        $result = ['ok' => false, 'stage' => 'invalid_msisdn', 'msisdn' => ''];
        $channel = new SmsNotificationChannel($this->service($result));

        $this->assertSame($result, $channel->send($this->recipient('0000000000'), $this->notification(true)));
    }

    public function test_unrelated_notifications_keep_the_existing_failure_result(): void
    {
        $result = ['ok' => false, 'stage' => 'send_failed', 'http' => 503];
        $channel = new SmsNotificationChannel($this->service($result));
        $notification = new class extends Notification
        {
            public function toSms(object $notifiable): string
            {
                return 'Ordinary notification';
            }
        };

        $this->assertSame($result, $channel->send($this->recipient('TEST'), $notification));
    }

    public function test_successful_provider_result_is_returned_unchanged(): void
    {
        $result = ['ok' => true, 'stage' => 'sent', 'http' => 200];
        $channel = new SmsNotificationChannel($this->service($result));

        $this->assertSame($result, $channel->send($this->recipient('TEST'), $this->notification(true)));
    }

    private function service(array $result): SmsService
    {
        return new class($result) extends SmsService
        {
            public function __construct(private readonly array $result) {}

            public function send(string $text, string $to): array
            {
                return $this->result;
            }
        };
    }

    private function recipient(?string $phone): object
    {
        return new class($phone)
        {
            public function __construct(private readonly ?string $phone) {}

            public function routeNotificationFor(string $channel, Notification $notification): ?string
            {
                return $this->phone;
            }
        };
    }

    private function notification(bool $retry, string $message = 'Registration verification'): Notification
    {
        return new class($retry, $message) extends Notification
        {
            public function __construct(public bool $retryFailedSmsDelivery, private readonly string $message) {}

            public function toSms(object $notifiable): string
            {
                return $this->message;
            }
        };
    }
}
