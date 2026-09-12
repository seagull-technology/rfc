<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\User;
use App\Services\SmsService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Timebox;
use RfcStagingSecurityVerifier;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StagingSecurityVerifierTest extends TestCase
{
    use RefreshDatabase;

    private array $directories = [];

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('scripts/verify-staging-security.php');
        $this->seed(AccessControlSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            File::deleteDirectory($directory);
        }
        parent::tearDown();
    }

    public function test_cli_refuses_to_boot_or_write_without_explicit_staging_confirmation(): void
    {
        $process = new Process([PHP_BINARY, base_path('scripts/verify-staging-security.php')]);
        $process->run();

        $this->assertSame(2, $process->getExitCode());
        $this->assertStringContainsString('--staging-test and --expected-app-url', $process->getErrorOutput());
    }

    public function test_internal_verification_cleans_only_its_exact_fixtures_and_records_limits(): void
    {
        $originalUsers = User::withTrashed()->pluck('id')->all();
        $originalEntities = Entity::withTrashed()->pluck('id')->all();
        $this->app->instance(Timebox::class, new class extends Timebox
        {
            protected function usleep(int $microseconds) {}
        });
        $verifier = $this->verifier();
        $verifier->createFixtures();
        $manifest = json_decode(File::get($verifier->directory.'/manifest.json'), true);

        $this->assertCount(12, $manifest['fixtures']);
        $this->assertStringNotContainsString('password', File::get($verifier->directory.'/manifest.json'));
        foreach ($manifest['fixtures'] as $fixture) {
            $user = User::withTrashed()->findOrFail($fixture['user_id']);
            $this->assertSame('', preg_replace('/\D+/', '', $user->phone));
        }

        $result = $verifier->verify(samples: 1);
        $failed = array_values(array_filter($result['checks'], fn ($check) => $check['status'] !== 'passed'));
        $this->assertSame([], $failed, json_encode($failed));
        $this->assertTrue($result['internal_checks_passed']);
        $this->assertSame('not_proven', $result['concurrent_review']['status']);
        $this->assertCount(58, $result['checks']);
        $this->assertArrayHasKey('ar', $result['internal_timing_ms']);
        $this->assertArrayHasKey('en', $result['internal_timing_ms']);
        $this->assertArrayHasKey('active', $result['internal_timing_ms']['en']['username']['login']);
        $this->assertArrayHasKey('unsupported_input', $result['internal_timing_ms']['en']['email']['reset']);
        $this->assertArrayNotHasKey('active', $result['internal_timing_ms']['en']['phone']['login']);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('login_otps')->count());
        $this->assertSame(0, DB::table('notification_logs')->count());

        $verifier->cleanup();
        $verifier->cleanup();
        $this->assertSame($originalUsers, User::withTrashed()->pluck('id')->all());
        $this->assertSame($originalEntities, Entity::withTrashed()->pluck('id')->all());
        $this->assertSame('completed', json_decode(File::get($verifier->directory.'/cleanup.json'), true)['status']);
    }

    public function test_cleanup_refuses_an_unrelated_id_and_rolls_back_all_deletions(): void
    {
        $verifier = $this->verifier();
        $verifier->createFixtures();
        $file = $verifier->directory.'/manifest.json';
        $manifest = json_decode(File::get($file), true);
        $original = $manifest;
        $unrelated = Entity::query()->where('code', 'rfc-jordan')->firstOrFail();
        $manifest['fixtures']['company']['entity_id'] = $unrelated->getKey();
        File::put($file, json_encode($manifest));
        $entityCount = Entity::withTrashed()->count();
        $userCount = User::withTrashed()->count();

        try {
            $verifier->cleanup();
            $this->fail('Unrelated entity must not be deleted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('marker mismatch', $exception->getMessage());
        }
        $this->assertSame($entityCount, Entity::withTrashed()->count());
        $this->assertSame($userCount, User::withTrashed()->count());
        $this->assertNotNull($unrelated->fresh());
        File::put($file, json_encode($original));
        $verifier->cleanup();
    }

    public function test_outbound_http_and_sms_are_blocked_in_helper_process(): void
    {
        RfcStagingSecurityVerifier::blockOutboundDelivery();
        foreach ([fn () => app(SmsService::class)->send('Verification', 'TEST'), fn () => Http::post('https://example.invalid')] as $attempt) {
            try {
                $attempt();
                $this->fail('Outbound operation was not blocked.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('blocked', $exception->getMessage());
            }
        }
    }

    public function test_cleanup_preserves_records_added_after_verification(): void
    {
        $verifier = $this->verifier();
        $verifier->createFixtures();
        $manifest = json_decode(File::get($verifier->directory.'/manifest.json'), true);
        Schema::create('verification_cleanup_dependency', function ($table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        });
        DB::table('verification_cleanup_dependency')->insert(['user_id' => $manifest['fixtures']['company']['user_id']]);

        try {
            $verifier->cleanup();
            $this->fail('Cleanup must preserve newly dependent records.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('dependent records', $exception->getMessage());
        }
        $this->assertSame(1, DB::table('verification_cleanup_dependency')->count());
        $this->assertNotNull(User::find($manifest['fixtures']['company']['user_id']));
        Schema::drop('verification_cleanup_dependency');
        $verifier->cleanup();
    }

    public function test_real_cli_bootstrap_checks_and_cleanup_use_an_isolated_database(): void
    {
        $directory = storage_path('framework/testing/security-cli-'.bin2hex(random_bytes(8)));
        $this->directories[] = $directory;
        File::ensureDirectoryExists($directory);
        File::put($directory.'/database.sqlite', '');
        $environment = [
            'APP_ENV' => 'testing', 'APP_URL' => 'https://staging.example.invalid',
            'APP_CONFIG_CACHE' => $directory.'/config.php',
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $directory.'/database.sqlite',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ];
        foreach ([['migrate', '--force'], ['db:seed', '--class=Database\\Seeders\\AccessControlSeeder', '--force']] as $arguments) {
            $process = new Process([PHP_BINARY, base_path('artisan'), ...$arguments], base_path(), $environment);
            $process->setTimeout(60)->run();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }
        $process = new Process([PHP_BINARY, base_path('scripts/verify-staging-security.php'), '--staging-test', '--app-path='.base_path(), '--expected-app-url=https://staging.example.invalid', '--samples=1', '--keep-fixtures'], base_path(), $environment);
        $process->setTimeout(120)->run();
        preg_match('/Run (sv-[a-f0-9]{16}):/', $process->getOutput(), $match);
        if (isset($match[1])) {
            $this->directories[] = storage_path('app/private/security-verification/'.$match[1]);
        }
        $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        $this->assertNotEmpty($match[1] ?? null);
        $evidenceDirectory = storage_path('app/private/security-verification/'.$match[1]);
        $result = json_decode(File::get($evidenceDirectory.'/results.json'), true);
        $this->assertTrue($result['internal_checks_passed'], json_encode(array_filter($result['checks'], fn ($check) => $check['status'] !== 'passed')));
        $this->assertSame('not_proven', $result['concurrent_review']['status']);
        // Test real child bootstrapping on SQLite without claiming row-lock concurrency.
        File::put($evidenceDirectory.'/start', 'start');
        foreach (['approve' => 'accepted', 'reject' => 'rejected_stale'] as $decision => $expected) {
            $worker = new Process([PHP_BINARY, base_path('scripts/verify-staging-security.php'), '--staging-test', '--app-path='.base_path(), '--expected-app-url=https://staging.example.invalid', '--worker='.$decision, '--run-id='.$match[1]], base_path(), $environment);
            $worker->setTimeout(30)->run();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $this->assertSame($expected, json_decode(File::get($evidenceDirectory.'/worker-'.$decision.'.json'), true)['outcome']);
        }
        $cleanup = new Process([PHP_BINARY, base_path('scripts/verify-staging-security.php'), '--staging-test', '--app-path='.base_path(), '--expected-app-url=https://staging.example.invalid', '--cleanup='.$match[1]], base_path(), $environment);
        $cleanup->setTimeout(30)->run();
        $this->assertTrue($cleanup->isSuccessful(), $cleanup->getErrorOutput());
        $this->assertSame('completed', json_decode(File::get($evidenceDirectory.'/cleanup.json'), true)['status']);
        $database = new \PDO('sqlite:'.$directory.'/database.sqlite');
        $this->assertSame(0, (int) $database->query("SELECT COUNT(*) FROM users WHERE username LIKE 'sv-%'")->fetchColumn());
        $this->assertSame(0, (int) $database->query("SELECT COUNT(*) FROM entities WHERE code LIKE 'sv-%'")->fetchColumn());
        $this->assertGreaterThan(0, (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function test_cleanup_preserves_an_unexpected_link_to_an_archived_user(): void
    {
        $verifier = $this->verifier();
        $verifier->createFixtures();
        $manifest = json_decode(File::get($verifier->directory.'/manifest.json'), true);
        $archived = User::factory()->create();
        $archived->delete();
        $entityId = $manifest['fixtures']['company']['entity_id'];
        DB::table('entity_user')->insert(['entity_id' => $entityId, 'user_id' => $archived->getKey(), 'status' => 'active']);
        try {
            $verifier->cleanup();
            $this->fail('Archived relationship must be preserved.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('linked to another user', $exception->getMessage());
        }
        $this->assertDatabaseHas('entity_user', ['entity_id' => $entityId, 'user_id' => $archived->getKey()]);
        $this->assertNotNull(Entity::find($entityId));
        DB::table('entity_user')->where('entity_id', $entityId)->where('user_id', $archived->getKey())->delete();
        $verifier->cleanup();
    }

    private function verifier(): RfcStagingSecurityVerifier
    {
        RfcStagingSecurityVerifier::blockOutboundDelivery();
        $runId = 'sv-'.bin2hex(random_bytes(8));
        $directory = storage_path('framework/testing/security-verification/'.$runId);
        $this->directories[] = $directory;

        return new RfcStagingSecurityVerifier($directory, $runId);
    }
}
