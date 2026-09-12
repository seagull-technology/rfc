<?php

/**
 * Standalone CLI-only staging check. Keep outside public/; no application deployment needed.
 * C:\php\php.exe C:\Deploy\verify-public-configuration-limits.php --staging-test --app-path=C:\inetpub\rfc --expected-app-url=https://filmjordan.jo
 * Interrupted-run cleanup: same arguments plus --cleanup=vl-<16 hexadecimal characters>.
 * Uses three temporary staff actors and public HTTPS requests; skips login/OTP deliberately.
 * Runtime about five minutes. Does not clear quotas, alter config, or send notifications.
 * Cookies/passwords/session IDs stay in memory; the private manifest contains fixture IDs only.
 */

use App\Models\Entity;
use App\Models\Group;
use App\Models\ReleaseMethod;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Bootstrap before declaring framework subclasses when this file is run standalone.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $publicLimitOptions = getopt('', ['staging-test', 'app-path:', 'expected-app-url:', 'cleanup:']);
    if (! array_key_exists('staging-test', $publicLimitOptions)
        || ($publicLimitOptions['expected-app-url'] ?? null) !== 'https://filmjordan.jo') {
        fwrite(STDERR, "Refusing: explicit --staging-test and --expected-app-url=https://filmjordan.jo are required.\n");
        exit(2);
    }
    try {
        $root = realpath($publicLimitOptions['app-path'] ?? '');
        if (! $root || ! is_file($root.'/artisan')) {
            throw new RuntimeException;
        }
        require $root.'/vendor/autoload.php';
        $app = require $root.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        if (rtrim((string) config('app.url'), '/') !== 'https://filmjordan.jo'
            || str_starts_with(str_replace('\\', '/', __FILE__), str_replace('\\', '/', realpath(public_path())).'/')) {
            throw new RuntimeException;
        }
    } catch (Throwable) {
        fwrite(STDERR, "Application bootstrap/target guard failed; no public requests were sent.\n");
        exit(2);
    }
}

final class RfcPublicLimitsFailure extends RuntimeException {}

/** Derive the framework's exact named keys without incrementing or clearing them. */
final class RfcPublicLimitsKeys extends ThrottleRequests
{
    private array $captured = [];

    public function forActor(User $actor, string $ip): array
    {
        $request = Request::create('https://filmjordan.jo/ar/control-panel/work-release-lookups', 'POST', server: ['REMOTE_ADDR' => $ip]);
        $request->setUserResolver(fn () => $actor);
        $this->handle($request, fn () => new Response, 'configuration-mutation');

        return $this->captured;
    }

    protected function handleRequest($request, Closure $next, array $limits)
    {
        $this->captured = $limits;

        return new Response;
    }
}

final class RfcPublicLimitsHttp
{
    public const ORIGIN = 'https://filmjordan.jo';

    public const INDEX = '/ar/control-panel/work-release-lookups';

    public const AGENT = 'RFC Staging Limit Verification';

    public static function allowedRedirect(?string $location): bool
    {
        if (! is_string($location) || preg_match('/[\x00-\x20\\\\]/', $location)) {
            return false;
        }
        $url = str_starts_with($location, '/') && ! str_starts_with($location, '//')
            ? self::ORIGIN.$location : $location;
        $parts = parse_url($url);

        return is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && ($parts['host'] ?? '') === 'filmjordan.jo' && ($parts['port'] ?? 443) === 443
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['fragment'])
            && ($parts['path'] ?? '') === self::INDEX;
    }

    public function __invoke(string $cookie, string $path, ?array $payload = null): array
    {
        if ($path !== self::INDEX && ! preg_match('#^'.preg_quote(self::INDEX, '#').'/release-methods/[1-9][0-9]*/update$#D', $path)) {
            throw new RfcPublicLimitsFailure('Refusing an unexpected request path.');
        }
        $curl = curl_init(self::ORIGIN.$path);
        $headers = [];
        $bytes = 0;
        try {
            curl_setopt_array($curl, [
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROXY => '', CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25,
                CURLOPT_HTTPHEADER => ['Cookie: '.$cookie, 'Accept: text/html', 'Origin: '.self::ORIGIN],
                CURLOPT_REFERER => self::ORIGIN.self::INDEX,
                CURLOPT_USERAGENT => self::AGENT,
                CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                    if (preg_match('/^(Location|Retry-After):\s*(.*?)\r?\n$/i', $line, $match)) {
                        $headers[strtolower($match[1])][] = $match[2];
                    }

                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => static function ($handle, string $body) use (&$bytes): int {
                    $bytes += strlen($body);

                    return $bytes <= 8_388_608 ? strlen($body) : 0;
                },
            ]);
            if ($payload !== null) {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($payload, '', '&', PHP_QUERY_RFC3986));
            }
            if (curl_exec($curl) === false) {
                throw new RfcPublicLimitsFailure('Verified HTTPS request failed (transport code '.curl_errno($curl).'); no retry attempted.');
            }
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $location = $headers['location'][0] ?? null;
            if (count($headers['location'] ?? []) > 1 || ($location !== null && ! self::allowedRedirect($location))) {
                throw new RfcPublicLimitsFailure('Unexpected redirect rejected without following it.');
            }

            return ['status' => $status, 'location' => $location, 'retry_after' => ctype_digit($headers['retry-after'][0] ?? '') ? (int) $headers['retry-after'][0] : null];
        } finally {
            curl_close($curl);
        }
    }
}

final class RfcPublicConfigurationLimitsVerifier
{
    private array $manifest;

    private array $sessions = [];

    private array $events = [];

    private readonly Closure $transport;

    private readonly Closure $wait;

    private readonly Closure $progress;

    public function __construct(public readonly string $directory, public readonly string $runId, ?Closure $transport = null, ?Closure $wait = null, ?Closure $progress = null)
    {
        $this->ensure((bool) preg_match('/^vl-[a-f0-9]{16}$/D', $runId), 'Invalid run marker.');
        $this->transport = $transport ?? Closure::fromCallable(new RfcPublicLimitsHttp);
        $this->progress = $progress ?? static function (string $message): void {
            echo $message.PHP_EOL;
        };
        $this->wait = $wait ?? function (): void {
            foreach ([25, 20, 20] as $seconds) {
                ($this->progress)('Waiting for the next per-user minute window; fixtures remain isolated.');
                sleep($seconds);
            }
        };
        $this->manifest = ['schema' => 'rfc-public-limits-v1', 'run_id' => $runId, 'app_url' => rtrim((string) config('app.url'), '/'), 'entity_id' => null, 'actors' => [], 'session_store' => $this->sessionStore()];
    }

    public function preflight(): void
    {
        $this->ensure(config('session.driver') === 'database' && config('auth.defaults.guard') === 'web', 'Database sessions and the web guard are required.');
        $this->ensure(app()->make(RateLimiter::class)->limiter('configuration-mutation') !== null, 'The configuration limiter is missing.');
        $this->ensure(config('permission.teams') === true && config('permission.column_names.team_foreign_key') === 'entity_id', 'Unexpected permission-team configuration.');
        $this->ensure(config('cache.default') !== 'array', 'A shared persistent cache is required.');
        $role = Role::query()->where('name', 'platform_admin')->where('guard_name', 'web')->whereNull('entity_id')->first();
        $group = Group::query()->where('code', 'admins')->first();
        $this->ensure($role !== null && $group !== null && $group->roles()->whereKey($role->getKey())->exists(), 'Existing platform administrator role/group is required.');
        $this->ensure($role->hasPermissionTo('settings.manage') && $role->hasPermissionTo('access.admin-panel'), 'The existing role lacks required permissions.');
    }

    public function createFixtures(): void
    {
        $this->preflight();
        $this->ensure(! file_exists($this->directory) && mkdir($this->directory, 0700, true), 'Cannot create a new private run directory.');
        Notification::fake();
        Mail::fake();
        Bus::fake();
        $password = Hash::make(bin2hex(random_bytes(48)));
        DB::transaction(function () use ($password): void {
            $entity = Entity::query()->create(['group_id' => Group::query()->where('code', 'admins')->firstOrFail()->getKey(), 'code' => $this->runId, 'name_en' => 'Temporary limit verification', 'name_ar' => 'Temporary limit verification', 'status' => 'active', 'registration_type' => 'staff', 'metadata' => ['public_limit_verification' => $this->runId]]);
            $this->manifest['entity_id'] = $entity->getKey();
            $registrar = app(PermissionRegistrar::class);
            $originalTeam = $registrar->getPermissionsTeamId();
            $registrar->setPermissionsTeamId($entity->getKey());
            try {
                for ($number = 1; $number <= 3; $number++) {
                    $code = $this->runId.'-'.$number;
                    $user = User::query()->create(['name' => 'Temporary limit verifier '.$number, 'username' => $code, 'email' => $code.'@example.invalid', 'phone' => null, 'national_id' => null, 'status' => 'active', 'registration_type' => 'staff', 'password' => $password]);
                    $entity->users()->attach($user->getKey(), ['is_primary' => true, 'status' => 'active', 'joined_at' => now()]);
                    $user->assignRole('platform_admin');
                    $method = ReleaseMethod::query()->create(['code' => $code, 'name_en' => 'Temporary limit verification '.$number, 'name_ar' => 'Temporary limit verification '.$number, 'is_active' => false, 'sort_order' => 0]);
                    $this->manifest['actors'][] = ['number' => $number, 'user_id' => $user->getKey(), 'method_id' => $method->getKey(), 'code' => $code];
                }
                $this->writeJson('manifest.json', $this->manifest);
            } finally {
                $registrar->setPermissionsTeamId($originalTeam);
            }
        });

        foreach ($this->manifest['actors'] as $actor) {
            $this->createSession($actor);
        }
    }

    private function createSession(array $actor): void
    {
        app('session')->forgetDrivers();
        $store = app('session')->driver();
        $store->start();
        $guard = Auth::guard('web');
        $previousUser = $guard->user();
        $guard->setUser(User::query()->findOrFail($actor['user_id']));
        try {
            $store->put([$guard->getName() => $actor['user_id'], 'current_entity_id' => $this->manifest['entity_id'], 'public_limit_verification' => $this->runId]);
            $store->save();
            $this->ensure((int) $this->sessionQuery()->where('id', $store->getId())->value('user_id') === $actor['user_id'], 'Temporary session ownership was not recorded.');
            $cookieName = (string) config('session.cookie');
            $middleware = new EncryptCookies(app('encrypter'));
            $this->ensure(! $middleware->isDisabled($cookieName), 'Session cookie encryption must remain enabled.');
            $response = $middleware->handle(Request::create(RfcPublicLimitsHttp::ORIGIN), function () use ($cookieName, $store): Response {
                $response = new Response;
                $response->headers->setCookie(new Cookie($cookieName, $store->getId()));

                return $response;
            });
            $this->sessions[$actor['number']] = ['id' => $store->getId(), 'token' => $store->token(), 'cookie' => $cookieName.'='.rawurlencode($response->headers->getCookies()[0]->getValue())];
        } finally {
            $previousUser ? $guard->setUser($previousUser) : $guard->forgetUser();
        }
    }

    public function verify(): array
    {
        $deadline = microtime(true) + 900;
        $ips = [];
        foreach ($this->manifest['actors'] as $actor) {
            ($this->progress)('Checking public session for temporary actor '.$actor['number'].'.');
            $response = ($this->transport)($this->sessions[$actor['number']]['cookie'], RfcPublicLimitsHttp::INDEX);
            $this->ensure($response['status'] === 200 && $response['location'] === null, 'Temporary session did not reach the public administration page (HTTP '.$response['status'].').');
            $session = $this->sessionQuery()->where('id', $this->sessions[$actor['number']]['id'])->first();
            $this->ensure($session && (int) $session->user_id === $actor['user_id'] && $session->user_agent === RfcPublicLimitsHttp::AGENT
                && filter_var($session->ip_address, FILTER_VALIDATE_IP) !== false, 'The public session did not record an identifiable client address.');
            $ips[] = $session->ip_address;
        }
        $this->ensure(count(array_unique($ips)) === 1, 'All three public actors must share the same observed client address.');
        $limiter = app(RateLimiter::class);
        $keys = new RfcPublicLimitsKeys($limiter);
        $limits = [];
        foreach ($this->manifest['actors'] as $actor) {
            $limits[$actor['number']] = $keys->forActor(User::query()->findOrFail($actor['user_id']), $ips[0]);
            $this->ensure(array_column($limits[$actor['number']], 'maxAttempts') === [5, 30, 60]
                && array_column($limits[$actor['number']], 'decaySeconds') === [60, 3600, 3600], 'The deployed limiter differs from the expected 5/minute, 30/user/hour, 60/IP/hour policy.');
            foreach ($limits[$actor['number']] as $limit) {
                if ($limiter->attempts($limit->key) !== 0) {
                    throw new RfcPublicLimitsFailure('Quota baseline is not empty; retry after at least '.max(1, $limiter->availableIn($limit->key)).' seconds. No counters were cleared.');
                }
            }
        }
        $this->ensure($limits[1][2]->key === $limits[2][2]->key && $limits[1][2]->key === $limits[3][2]->key, 'Shared-IP keys do not match.');
        $sharedKey = $limits[1][2]->key;
        $accepted = 0;
        for ($round = 1; $round <= 4; $round++) {
            if ($round > 1) {
                ($this->wait)();
            }
            foreach ($this->manifest['actors'] as $actor) {
                for ($withinRound = 1; $withinRound <= 5; $withinRound++) {
                    $this->ensure(microtime(true) < $deadline, 'Verification exceeded its bounded runtime.');
                    $this->ensure($limiter->attempts($sharedKey) === $accepted, 'Another writer or a different cache affected the shared quota; result is inconclusive.');
                    $sequence = ($round - 1) * 5 + $withinRound;
                    $response = $this->write($actor, $sequence);
                    $this->ensure($response['status'] === 302 && RfcPublicLimitsHttp::allowedRedirect($response['location']), 'Expected configuration write was not accepted (HTTP '.$response['status'].').');
                    $this->ensure($this->storedSequence($actor) === $sequence, 'Accepted response did not persist the expected fixture value.');
                    $accepted++;
                    $this->ensure($limiter->attempts($sharedKey) === $accepted && $limiter->attempts($limits[$actor['number']][1]->key) === $sequence, 'Public quota progression differs from the expected isolated writers.');
                    $this->events[] = ['actor' => $actor['number'], 'sequence' => $sequence, 'status' => 302, 'shared_count' => $accepted];
                    ($this->progress)("Accepted $accepted/60; temporary actor ".$actor['number']." value $sequence verified.");
                }
            }
            ($this->progress)("Round $round/4 complete: $accepted accepted writes, each stored value verified.");
        }
        ($this->wait)();
        $this->ensure(microtime(true) < $deadline, 'Verification exceeded its bounded runtime.');
        $this->ensure($limiter->attempts($sharedKey) === 60 && $limiter->attempts($limits[1][0]->key) === 0
            && $limiter->attempts($limits[1][1]->key) === 20, 'Final request would be confounded by another limiter/window.');
        $response = $this->write($this->manifest['actors'][0], 21);
        $this->ensure($response['status'] === 429 && ($response['retry_after'] ?? 0) > 0 && $this->storedSequence($this->manifest['actors'][0]) === 20
            && $limiter->attempts($sharedKey) === 60, 'The 61st request was not rejected without changing the fixture.');
        $this->events[] = ['actor' => 1, 'sequence' => 21, 'status' => 429, 'shared_count' => 60];
        $result = ['run_id' => $this->runId, 'status' => 'passed', 'accepted_writes' => 60, 'rejected_request' => 61, 'independent_actors' => 3, 'same_observed_ip' => true, 'initial_shared_count' => 0, 'retry_after' => $response['retry_after'], 'events' => $this->events, 'scope' => 'Public verified HTTPS requests with three CLI-prepared authenticated sessions; login/OTP and browser JavaScript were not tested.'];
        $this->writeJson('results.json', $result);

        return $result;
    }

    private function write(array $actor, int $sequence): array
    {
        $method = ReleaseMethod::query()->findOrFail($actor['method_id']);
        $this->ensure($method->code === $actor['code'] && ! $method->is_active, 'Fixture lookup marker or inactive state changed.');

        return ($this->transport)($this->sessions[$actor['number']]['cookie'], RfcPublicLimitsHttp::INDEX.'/release-methods/'.$actor['method_id'].'/update', [
            '_token' => $this->sessions[$actor['number']]['token'], 'name_en' => $method->name_en, 'name_ar' => $method->name_ar,
            'sort_order' => $sequence, 'is_active' => '0',
        ]);
    }

    private function storedSequence(array $actor): int
    {
        return (int) ReleaseMethod::query()->findOrFail($actor['method_id'])->sort_order;
    }

    public function cleanup(): void
    {
        if (! is_file($this->directory.'/manifest.json')) {
            return;
        }
        $manifest = json_decode(file_get_contents($this->directory.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->ensure(($manifest['schema'] ?? null) === 'rfc-public-limits-v1' && ($manifest['run_id'] ?? null) === $this->runId
            && ($manifest['app_url'] ?? null) === rtrim((string) config('app.url'), '/') && ($manifest['session_store'] ?? null) === $this->sessionStore(), 'Cleanup manifest/target does not match.');
        $ids = array_column($manifest['actors'], 'user_id');
        $entityId = $manifest['entity_id'];
        // Revoke every session for these exact newly-created users, even if later records block deletion.
        foreach ($manifest['actors'] as $actor) {
            $user = User::withTrashed()->find($actor['user_id']);
            $this->ensure(! $user || ($actor['code'] === $this->runId.'-'.$actor['number'] && $user->username === $actor['code'] && $user->email === $actor['code'].'@example.invalid'), 'Cleanup user marker mismatch.');
        }
        $this->sessionQuery()->whereIn('user_id', $ids)->delete();
        $this->sessions = [];
        DB::transaction(function () use ($manifest, $ids, $entityId): void {
            $entity = Entity::withTrashed()->lockForUpdate()->find($entityId);
            $this->ensure(! $entity || ($entity->code === $this->runId && data_get($entity->metadata, 'public_limit_verification') === $this->runId), 'Cleanup entity marker mismatch.');
            $this->ensure(! DB::table('entity_user')->where('entity_id', $entityId)->whereNotIn('user_id', $ids)->exists()
                && ! DB::table('entity_user')->whereIn('user_id', $ids)->where('entity_id', '!=', $entityId)->exists(), 'Unexpected fixture membership; preserve records.');
            $roleTable = config('permission.table_names.model_has_roles');
            $permissionTable = config('permission.table_names.model_has_permissions');
            foreach ([$roleTable, $permissionTable] as $table) {
                $this->ensure(! DB::table($table)->where('entity_id', $entityId)->where(function ($query) use ($ids): void {
                    $query->where('model_type', '!=', User::class)->orWhereNotIn('model_id', $ids);
                })->exists(), 'Unexpected permissions on the fixture entity; preserve records.');
                $this->ensure(! DB::table($table)->where('model_type', User::class)->whereIn('model_id', $ids)
                    ->where(fn ($query) => $query->where('entity_id', '!=', $entityId)->orWhereNull('entity_id'))->exists(),
                    'Fixture user has later permissions outside the temporary entity; preserve records.');
            }
            foreach (Schema::getTables() as $table) {
                if (in_array($table['name'], ['entity_user', $roleTable, $permissionTable], true)) {
                    continue;
                }
                $tableName = $table['schema_qualified_name'] ?? $table['name'];
                foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
                    $targets = match ($foreignKey['foreign_table']) {
                        'users' => $ids, 'entities' => [$entityId], 'release_methods' => array_column($manifest['actors'], 'method_id'), default => null,
                    };
                    if ($targets !== null) {
                        $this->ensure(count($foreignKey['columns']) === 1 && ! DB::table($tableName)->whereIn($foreignKey['columns'][0], $targets)->exists(), 'Fixture has later dependent records; preserve for review.');
                    }
                }
            }
            foreach ($manifest['actors'] as $actor) {
                $method = ReleaseMethod::query()->lockForUpdate()->find($actor['method_id']);
                $this->ensure(! $method || ($method->code === $actor['code'] && ! $method->is_active), 'Cleanup lookup marker mismatch.');
                $method?->delete();
                User::withTrashed()->find($actor['user_id'])?->forceDelete();
            }
            $entity?->forceDelete();
        });
        $this->writeJson('cleanup.json', ['run_id' => $this->runId, 'status' => 'completed', 'recorded_at_utc' => gmdate('c')]);
    }

    public function recordFailure(Throwable $error): void
    {
        if (is_dir($this->directory)) {
            $this->writeJson('results.json', ['run_id' => $this->runId, 'status' => 'not_proven', 'detail' => $error instanceof RfcPublicLimitsFailure ? $error->getMessage() : 'Unexpected '.get_class($error).'; raw details withheld.', 'events' => $this->events]);
        }
    }

    private function sessionStore(): array
    {
        return ['driver' => config('session.driver'), 'connection' => config('session.connection'), 'table' => config('session.table')];
    }

    private function sessionQuery(): Builder
    {
        return DB::connection(config('session.connection'))->table(config('session.table'));
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RfcPublicLimitsFailure($message);
        }
    }

    private function writeJson(string $name, array $data): void
    {
        $path = $this->directory.'/'.$name;
        $oldMask = umask(0077);
        try {
            $this->ensure(file_put_contents($path.'.tmp', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX) !== false && rename($path.'.tmp', $path), 'Private evidence could not be saved.');
            chmod($path, 0600);
        } finally {
            umask($oldMask);
        }
    }
}

if (isset($publicLimitOptions)) {
    $runner = null;
    $cleanupDone = false;
    $run = $publicLimitOptions['cleanup'] ?? 'vl-'.bin2hex(random_bytes(8));
    register_shutdown_function(static function () use (&$runner, &$cleanupDone): void {
        if ($runner && ! $cleanupDone) {
            try {
                $runner->cleanup();
            } catch (Throwable) {
                fwrite(STDERR, "Exact cleanup needs review; retain the printed run marker.\n");
            }
        }
    });
    try {
        if (! extension_loaded('curl') && ! isset($publicLimitOptions['cleanup'])) {
            throw new RfcPublicLimitsFailure('The PHP cURL extension is required for verified HTTPS.');
        }
        $runner = new RfcPublicConfigurationLimitsVerifier(storage_path('app/private/public-limit-verification/'.$run), $run);
        echo "Run $run: public limit verification; exact cleanup is automatic.\n";
        if (isset($publicLimitOptions['cleanup'])) {
            $runner->cleanup();
        } else {
            $runner->createFixtures();
            $runner->verify();
            $runner->cleanup();
        }
        $cleanupDone = true;
        echo 'Completed; sanitized evidence: '.$runner->directory.PHP_EOL;
        exit(0);
    } catch (Throwable $error) {
        try {
            $runner?->recordFailure($error);
        } catch (Throwable) {
            fwrite(STDERR, "Private failure evidence could not be written; retain the run marker.\n");
        }
        fwrite(STDERR, ($error instanceof RfcPublicLimitsFailure ? $error->getMessage() : 'Verification stopped ('.get_class($error).'); raw details withheld.').PHP_EOL);
        exit(1);
    }
}
