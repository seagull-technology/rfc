<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\ReleaseMethod;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RfcPublicConfigurationLimitsVerifier;
use RfcPublicLimitsFailure;
use RfcPublicLimitsHttp;
use RfcPublicLimitsKeys;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PublicConfigurationLimitsVerifierTest extends TestCase
{
    use RefreshDatabase;

    private array $directories = [];

    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('scripts/verify-public-configuration-limits.php');
    }

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            File::deleteDirectory($directory);
        }
        $this->travelBack();
        parent::tearDown();
    }

    public function test_standalone_cli_requires_explicit_target_before_bootstrap(): void
    {
        $process = new Process([PHP_BINARY, base_path('scripts/verify-public-configuration-limits.php')]);
        $process->run();
        $this->assertSame(2, $process->getExitCode());
        $this->assertStringContainsString('explicit --staging-test', $process->getErrorOutput());
    }

    #[DataProvider('redirectTargets')]
    public function test_redirect_guard_accepts_only_the_same_https_lookup_page(?string $target, bool $allowed): void
    {
        $this->assertSame($allowed, RfcPublicLimitsHttp::allowedRedirect($target));
    }

    public static function redirectTargets(): array
    {
        $path = '/ar/control-panel/work-release-lookups';

        return [[$path, true], ['https://filmjordan.jo'.$path.'?q=verification', true],
            ['//collector.invalid'.$path, false], ['https://collector.invalid'.$path, false],
            ['http://filmjordan.jo'.$path, false], ['https://filmjordan.jo:444'.$path, false],
            ['https://private@filmjordan.jo'.$path, false], [$path.'#fragment', false],
            ['/ar/sign-in', false], [null, false], ['https://filmjordan.jo\\@collector.invalid'.$path, false]];
    }

    public function test_actual_kernel_enforces_61st_shared_ip_write_with_private_sessions_and_exact_cleanup(): void
    {
        $this->prepareApplication();
        $originalUsers = User::withTrashed()->pluck('id')->all();
        $originalEntities = Entity::withTrashed()->pluck('id')->all();
        $runner = $this->runner();
        $runner->createFixtures();
        $manifest = $this->manifest($runner);
        $this->assertCount(3, $manifest['actors']);
        $this->assertSame(3, DB::table('sessions')->count());
        $manifestText = File::get($runner->directory.'/manifest.json');
        foreach (['password', 'cookie', 'session_id', 'token'] as $secretName) {
            $this->assertStringNotContainsString($secretName, $manifestText);
        }

        $result = $runner->verify();
        $this->assertSame('passed', $result['status']);
        $this->assertSame(60, $result['accepted_writes']);
        $this->assertSame(61, $result['rejected_request']);
        $this->assertCount(64, $this->requests); // Three GETs, 60 accepted POSTs, one rejected POST.
        $this->assertSame(429, $this->requests[63]['status']);
        foreach ($manifest['actors'] as $actor) {
            $this->assertSame(20, ReleaseMethod::findOrFail($actor['method_id'])->sort_order);
        }
        $keys = (new RfcPublicLimitsKeys(app(RateLimiter::class)))->forActor(User::findOrFail($manifest['actors'][0]['user_id']), '192.0.2.40');
        $this->assertSame(60, app(RateLimiter::class)->attempts($keys[2]->key));
        $runner->cleanup();
        $runner->cleanup();
        $this->assertSame($originalUsers, User::withTrashed()->pluck('id')->all());
        $this->assertSame($originalEntities, Entity::withTrashed()->pluck('id')->all());
        $this->assertSame(0, ReleaseMethod::whereIn('id', array_column($manifest['actors'], 'method_id'))->count());
        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('notification_logs', 0);
        $this->assertSame(60, app(RateLimiter::class)->attempts($keys[2]->key));
    }

    public function test_nonempty_shared_baseline_aborts_without_a_post_or_clearing_any_quota(): void
    {
        $this->prepareApplication();
        $runner = $this->runner();
        $runner->createFixtures();
        $actor = User::findOrFail($this->manifest($runner)['actors'][0]['user_id']);
        $limits = (new RfcPublicLimitsKeys(app(RateLimiter::class)))->forActor($actor, '192.0.2.40');
        app(RateLimiter::class)->hit($limits[2]->key, 3600);
        try {
            $runner->verify();
            $this->fail('A nonempty shared quota must stop before any mutation.');
        } catch (RfcPublicLimitsFailure $error) {
            $this->assertStringContainsString('Quota baseline is not empty', $error->getMessage());
        }
        $this->assertCount(3, $this->requests);
        $this->assertSame(1, app(RateLimiter::class)->attempts($limits[2]->key));
        $runner->cleanup();
    }

    public function test_interference_aborts_and_cleanup_revokes_sessions_but_preserves_later_dependencies(): void
    {
        $this->prepareApplication();
        $runner = $this->runner(function (): void {
            throw new RfcPublicLimitsFailure('Simulated stopped test.');
        });
        $runner->createFixtures();
        $manifest = $this->manifest($runner);
        Schema::create('public_limit_later_dependency', function ($table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        });
        DB::table('public_limit_later_dependency')->insert(['user_id' => $manifest['actors'][0]['user_id']]);
        try {
            $runner->verify();
            $this->fail('Stopped transport must not report a pass.');
        } catch (RfcPublicLimitsFailure $error) {
            $runner->recordFailure($error);
        }
        try {
            $runner->cleanup();
            $this->fail('A later dependent record must be preserved.');
        } catch (RfcPublicLimitsFailure $error) {
            $this->assertStringContainsString('later dependent records', $error->getMessage());
        }
        $this->assertDatabaseCount('sessions', 0);
        $this->assertNotNull(User::find($manifest['actors'][0]['user_id']));
        $this->assertSame('not_proven', json_decode(File::get($runner->directory.'/results.json'), true)['status']);
        Schema::drop('public_limit_later_dependency');
        $runner->cleanup();
    }

    public function test_bad_csrf_cannot_write_even_with_a_prepared_authenticated_cookie(): void
    {
        $this->prepareApplication();
        $kernel = $this->kernelTransport();
        $runner = $this->runner(function (string $cookie, string $path, ?array $payload = null) use ($kernel): array {
            if ($payload !== null) {
                $payload['_token'] = 'Deliberately incorrect token';
            }

            return $kernel($cookie, $path, $payload);
        });
        $runner->createFixtures();
        try {
            $runner->verify();
            $this->fail('Incorrect CSRF must stop the public write check.');
        } catch (RfcPublicLimitsFailure $error) {
            $this->assertStringContainsString('HTTP 419', $error->getMessage());
        }
        foreach ($this->manifest($runner)['actors'] as $actor) {
            $this->assertSame(0, ReleaseMethod::findOrFail($actor['method_id'])->sort_order);
        }
        $runner->cleanup();
    }

    public function test_an_unrelated_writer_during_the_check_makes_the_result_inconclusive(): void
    {
        $this->prepareApplication();
        $kernel = $this->kernelTransport();
        $runner = null;
        $runner = $this->runner(function (string $cookie, string $path, ?array $payload = null) use ($kernel, &$runner): array {
            $result = $kernel($cookie, $path, $payload);
            if ($payload !== null) {
                $actor = User::findOrFail($this->manifest($runner)['actors'][0]['user_id']);
                $keys = (new RfcPublicLimitsKeys(app(RateLimiter::class)))->forActor($actor, '192.0.2.40');
                app(RateLimiter::class)->hit($keys[2]->key, 3600);
            }

            return $result;
        });
        $runner->createFixtures();
        try {
            $runner->verify();
            $this->fail('Unrelated quota consumption cannot count as an isolated success.');
        } catch (RfcPublicLimitsFailure $error) {
            $this->assertStringContainsString('quota progression differs', $error->getMessage());
        }
        $this->assertCount(4, $this->requests);
        $runner->cleanup();
    }

    public function test_changed_fixture_marker_cannot_delete_an_unrelated_user_or_session(): void
    {
        $this->prepareApplication();
        $runner = $this->runner();
        $runner->createFixtures();
        $original = $this->manifest($runner);
        $unrelated = User::whereNotIn('id', array_column($original['actors'], 'user_id'))->firstOrFail();
        $altered = $original;
        $altered['actors'][0]['user_id'] = $unrelated->getKey();
        File::put($runner->directory.'/manifest.json', json_encode($altered));
        try {
            $runner->cleanup();
            $this->fail('Unrelated user ID must not be cleaned.');
        } catch (RfcPublicLimitsFailure $error) {
            $this->assertStringContainsString('user marker mismatch', $error->getMessage());
        }
        $this->assertNotNull($unrelated->fresh());
        $this->assertDatabaseCount('sessions', 3);
        File::put($runner->directory.'/manifest.json', json_encode($original));
        $runner->cleanup();
    }

    #[DataProvider('laterPermissionTeams')]
    public function test_cleanup_preserves_later_roles_and_permissions_on_other_teams(string $table): void
    {
        $this->prepareApplication();
        $runner = $this->runner();
        $runner->createFixtures();
        $manifest = $this->manifest($runner);
        $id = $manifest['actors'][0]['user_id'];
        $foreignId = $table === 'model_has_roles' ? 'role_id' : 'permission_id';
        $value = DB::table($table === 'model_has_roles' ? 'roles' : 'permissions')->value('id');
        $team = Entity::where('id', '!=', $manifest['entity_id'])->value('id');
        DB::table($table)->insert([$foreignId => $value, 'model_type' => User::class, 'model_id' => $id, 'entity_id' => $team]);
        try {
            $runner->cleanup();
            $this->fail('Later permission associations must survive cleanup.');
        } catch (RfcPublicLimitsFailure $error) {
            $this->assertStringContainsString('permissions outside the temporary entity', $error->getMessage());
        }
        $this->assertNotNull(User::find($id));
        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseHas($table, ['model_id' => $id, 'entity_id' => $team, $foreignId => $value]);
        DB::table($table)->where('model_id', $id)->where('model_type', User::class)->where('entity_id', $team)->delete();
        $runner->cleanup();
    }

    public static function laterPermissionTeams(): array
    {
        return [['model_has_roles'], ['model_has_permissions']];
    }

    public function test_a_429_without_retry_after_cannot_count_as_a_completed_proof(): void
    {
        $this->prepareApplication();
        $kernel = $this->kernelTransport();
        $runner = $this->runner(function (string $cookie, string $path, ?array $payload = null) use ($kernel): array {
            $result = $kernel($cookie, $path, $payload);
            if ($result['status'] === 429) {
                $result['retry_after'] = null;
            }

            return $result;
        });
        $runner->createFixtures();
        try {
            $runner->verify();
            $this->fail('A 429 without Retry-After cannot establish the complete result.');
        } catch (RfcPublicLimitsFailure $error) {
            $this->assertStringContainsString('61st request', $error->getMessage());
        }
        $runner->cleanup();
    }

    private function prepareApplication(): void
    {
        $this->refreshApplicationWithLocale('ar');
        $this->seed(AccessControlSeeder::class);
        config(['app.url' => RfcPublicLimitsHttp::ORIGIN, 'security.trusted_hosts.enforce' => true,
            'security.trusted_hosts.hosts' => ['filmjordan.jo'], 'cache.default' => 'database',
            'session.driver' => 'database', 'session.connection' => null, 'session.table' => 'sessions']);
        app('url')->forceRootUrl(RfcPublicLimitsHttp::ORIGIN);
        $originalLimiter = app(RateLimiter::class);
        $databaseLimiter = new RateLimiter(app('cache')->store('database'));
        foreach (['configuration-mutation', 'authenticated-write'] as $name) {
            $databaseLimiter->for($name, $originalLimiter->limiter($name));
        }
        app()->instance(RateLimiter::class, $databaseLimiter);
        \Illuminate\Support\Facades\RateLimiter::clearResolvedInstance(RateLimiter::class);
        app('session')->forgetDrivers();
        Auth::forgetGuards();
        Http::preventStrayRequests();
        // Keep production CSRF checks active. Transport below is exclusively the local kernel.
        app()->instance('env', 'production');
    }

    private function runner(?\Closure $transport = null): RfcPublicConfigurationLimitsVerifier
    {
        $run = 'vl-'.bin2hex(random_bytes(8));
        $directory = storage_path('framework/testing/public-limits-'.$run);
        $this->directories[] = $directory;

        return new RfcPublicConfigurationLimitsVerifier($directory, $run, $transport ?? $this->kernelTransport(), fn () => $this->travel(65)->seconds(), static function (): void {});
    }

    private function kernelTransport(): \Closure
    {
        return function (string $cookie, string $path, ?array $payload = null): array {
            [$name, $value] = explode('=', $cookie, 2);
            app('session')->forgetDrivers();
            app()->forgetInstance('session.store');
            Auth::forgetGuards();
            app()->forgetInstance('auth.driver');
            $request = Request::create(RfcPublicLimitsHttp::ORIGIN.$path, $payload === null ? 'GET' : 'POST', $payload ?? [], [$name => rawurldecode($value)], [], ['REMOTE_ADDR' => '192.0.2.40', 'HTTP_ACCEPT' => 'text/html', 'HTTP_USER_AGENT' => RfcPublicLimitsHttp::AGENT, 'HTTP_REFERER' => RfcPublicLimitsHttp::ORIGIN.RfcPublicLimitsHttp::INDEX]);
            $kernel = app(Kernel::class);
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
            $this->requests[] = ['path' => $path, 'status' => $response->getStatusCode()];

            return ['status' => $response->getStatusCode(), 'location' => $response->headers->get('Location'), 'retry_after' => (int) $response->headers->get('Retry-After')];
        };
    }

    private function manifest(RfcPublicConfigurationLimitsVerifier $runner): array
    {
        return json_decode(File::get($runner->directory.'/manifest.json'), true);
    }
}
