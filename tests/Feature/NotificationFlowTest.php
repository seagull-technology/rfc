<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_badge_is_dynamic_dropdown_is_limited_and_all_notifications_are_available(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $this->createUnreadNotifications($admin, 9);

        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'));

        $dashboard
            ->assertOk()
            ->assertSee('data-notification-count>9<', false)
            ->assertSeeText('See all notifications')
            ->assertSee(route('notifications.index'), false);

        $this->assertSame(5, substr_count($dashboard->getContent(), 'class="iq-sub-card text-start admin-notification-item"'));

        $index = $this->actingAs($admin)->get(route('notifications.index'));

        $index
            ->assertOk()
            ->assertSeeText('All notifications')
            ->assertSeeText('Notification 1')
            ->assertSeeText('Notification 9');

        $this->assertSame(9, substr_count($index->getContent(), 'data-notification-list-item'));
    }

    public function test_opening_notification_dropdown_can_mark_every_notification_as_read(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'superadmin@rfc.local')->firstOrFail();
        $this->createUnreadNotifications($admin, 9);

        $response = $this->actingAs($admin)->postJson(route('notifications.read'));

        $response
            ->assertOk()
            ->assertJson([
                'marked_read' => 9,
                'unread_count' => 0,
            ]);

        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());
    }

    private function createUnreadNotifications(User $user, int $count): void
    {
        foreach (range(1, $count) as $index) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'test_notification',
                'data' => [
                    'type_key' => 'test_notification',
                    'title' => 'Notification '.$index,
                    'body' => 'Notification body '.$index,
                    'route_name' => 'dashboard',
                    'route_parameters' => [],
                ],
                'read_at' => null,
                'created_at' => now()->subMinutes($count - $index),
                'updated_at' => now()->subMinutes($count - $index),
            ]);
        }
    }
}
