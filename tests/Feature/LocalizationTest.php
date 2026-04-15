<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_switch_to_arabic_and_see_rtl_login_page(): void
    {
        $this->refreshApplicationWithLocale('ar');

        $response = $this->get('/ar/login');
        $englishLoginUrl = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL('en', route('login', [], false), [], true);
        $arabicLoginUrl = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL('ar', route('login', [], false), [], true);

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('تسجيل الدخول');
        $response->assertSee('إنشاء حساب');
        $response->assertSee($englishLoginUrl, false);
        $response->assertDontSee($arabicLoginUrl, false);
    }

    public function test_authenticated_user_can_view_dashboard_in_arabic(): void
    {
        $this->refreshApplicationWithLocale('ar');

        $this->seed(AccessControlSeeder::class);

        $entity = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();

        $user = User::query()->create([
            'name' => 'Arabic User',
            'username' => 'arabic_user',
            'email' => 'arabic@example.com',
            'national_id' => '5566778899',
            'phone' => '0792223344',
            'status' => 'active',
            'password' => Hash::make('password123'),
        ]);

        $entity->users()->attach($user->getKey(), [
            'is_primary' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($entity->getKey());
        $user->assignRole('rfc_reviewer');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $response = $this
            ->withSession(['current_entity_id' => $entity->getKey()])
            ->actingAs($user)
            ->get('/ar/dashboard');

        $response->assertRedirect(route('admin.dashboard'));

        $dashboardResponse = $this
            ->withSession(['current_entity_id' => $entity->getKey()])
            ->actingAs($user)
            ->get('/ar/admin');

        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('الهيئة الملكية الأردنية للأفلام');
        $dashboardResponse->assertSee(__('app.admin.applications.title'));
        $dashboardResponse->assertSee(__('app.admin.applications.intro'));
        $dashboardResponse->assertDontSeeText('This is the temporary testing dashboard');
    }
}
