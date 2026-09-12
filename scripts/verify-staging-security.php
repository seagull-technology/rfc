<?php

/**
 * CLI-only internal staging verification; never place this file under public/.
 *
 * Verify (fixtures are removed after checks):
 * php verify-staging-security.php --staging-test --app-path=C:\inetpub\rfc --expected-app-url=https://filmjordan.jo
 * Optional: --samples=3 (1..10), --keep-fixtures, --skip-concurrency.
 * Cleanup an interrupted/retained run using its printed run ID:
 * php verify-staging-security.php --staging-test --app-path=C:\inetpub\rfc --expected-app-url=https://filmjordan.jo --cleanup=sv-<16 hexadecimal characters>
 *
 * Results and exact fixture IDs remain in storage/app/private/security-verification/<run-id>/.
 * No passwords, tokens, real identifiers or connection credentials are printed.
 * This calls Laravel controllers with isolated sessions, not the public HTTP gateway.
 * It does not prove route authorization, CSRF, gateway timing or real SMS delivery.
 */

use App\Http\Controllers\Admin\EntityManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetOtpController;
use App\Http\Controllers\CompanyEmployeeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationCompletionController;
use App\Jobs\SendPasswordResetOtp;
use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

final class RfcStagingVerificationFailure extends RuntimeException {}

final class RfcStagingSecurityVerifier
{
    private array $manifest;

    private array $checks = [];

    private array $timings = [];

    public function __construct(public readonly string $directory, public readonly string $runId, private readonly bool $verbose = false)
    {
        if (! preg_match('/^sv-[a-f0-9]{16}$/D', $runId)) {
            throw new RuntimeException('Invalid run ID.');
        }

        $this->manifest = ['version' => 1, 'run_id' => $runId, 'app_url' => rtrim(config('app.url'), '/'), 'actor_id' => null, 'fixtures' => []];
    }

    public static function blockOutboundDelivery(): void
    {
        Notification::fake();
        Mail::fake();
        Bus::fake();
        Http::fake(fn () => throw new RuntimeException('Outbound HTTP blocked in verification process.'));
        Http::preventStrayRequests();
        app()->instance(SmsService::class, new class extends SmsService
        {
            public function __construct() {}

            public function send(string $text, string $to): array
            {
                throw new RuntimeException('Direct SMS blocked in verification process.');
            }
        });
    }

    public function createFixtures(): void
    {
        if (file_exists($this->directory) || ! mkdir($this->directory, 0700, true)) {
            throw new RuntimeException('Run directory already exists or cannot be created.');
        }

        $password = Hash::make(bin2hex(random_bytes(48)));
        DB::transaction(function () use ($password): void {
            $actor = User::query()->create([
                'name' => 'Security verification reviewer', 'username' => $this->runId.'-reviewer',
                'email' => $this->runId.'-reviewer@example.invalid', 'password' => $password,
                'status' => 'active', 'registration_type' => 'staff', 'phone' => null, 'national_id' => null,
            ]);
            $this->manifest['actor_id'] = $actor->getKey();

            $specifications = [
                'student' => ['student', 'active'], 'company' => ['company', 'active'],
                'ngo' => ['ngo', 'active'], 'school' => ['school', 'active'],
                'employee' => ['company', 'active'],
                'blank-company' => ['company', 'active'],
                'review' => ['ngo', 'pending_review'], 'concurrent' => ['ngo', 'pending_review'],
                'completion' => ['ngo', 'needs_completion'],
                'pending' => ['company', 'pending_review'], 'inactive' => ['company', 'inactive'],
                'archived' => ['company', 'inactive'],
            ];

            foreach ($specifications as $tag => [$type, $status]) {
                $code = $this->runId.'-'.$tag;
                $nationalId = $tag === 'blank-company' ? null : 'TEST-'.substr(hash('sha256', $code), 0, 24);
                $registration = $type === 'student' ? null : 'TEST-REG-'.substr(hash('sha256', $code), 0, 24);
                // Nonempty for reset known-account coverage, but normalizes to no dialable digits.
                $phone = 'TEST-'.strtr(bin2hex(random_bytes(10)), '0123456789', 'ghijklmnop');
                $entity = Entity::query()->create([
                    'group_id' => Group::query()->where('code', $type === 'student' ? 'individuals' : 'organizations')->firstOrFail()->getKey(),
                    'code' => $code, 'name_en' => 'Security verification '.$tag, 'name_ar' => 'Security verification '.$tag,
                    'registration_no' => $registration, 'national_id' => $nationalId,
                    'status' => $status, 'registration_type' => $type, 'email' => null, 'phone' => null,
                    'metadata' => ['security_verification' => $this->runId],
                ]);
                $user = User::query()->create([
                    'name' => 'Security verification '.$tag, 'username' => $code.'-owner',
                    'email' => $code.'@example.invalid', 'password' => $password,
                    'status' => $status, 'registration_type' => $type, 'national_id' => $nationalId, 'phone' => $phone,
                ]);
                $entity->users()->attach($user->getKey(), ['is_primary' => true, 'status' => 'active', 'joined_at' => now()]);
                $this->manifest['fixtures'][$tag] = ['entity_id' => $entity->getKey(), 'user_id' => $user->getKey(), 'code' => $code, 'username' => $user->username];

                if ($tag === 'archived') {
                    $user->delete();
                    $entity->delete();
                }
            }

            DB::table('entity_user')->insert([
                'entity_id' => $this->manifest['fixtures']['company']['entity_id'],
                'user_id' => $this->manifest['fixtures']['employee']['user_id'],
                'is_primary' => false, 'status' => 'active', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            // Write IDs before committing, so an interrupted run remains exactly cleanable.
            $this->writeJson('manifest.json', $this->manifest);
        });
    }

    public function loadManifest(): void
    {
        $manifest = json_decode(file_get_contents($this->directory.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        if (($manifest['version'] ?? null) !== 1 || ($manifest['run_id'] ?? null) !== $this->runId
            || ($manifest['app_url'] ?? null) !== rtrim(config('app.url'), '/')) {
            throw new RuntimeException('Manifest does not match this run/application.');
        }
        $this->manifest = $manifest;
    }

    public function verify(int $samples = 3, bool $concurrency = true): array
    {
        $actor = User::query()->findOrFail($this->manifest['actor_id']);
        foreach (['student', 'company', 'ngo', 'school', 'blank-company'] as $tag) {
            $this->check('V02 '.$tag.' admin identity and profile review', function () use ($tag, $actor): void {
                [$entity, $user] = $this->fixture($tag);
                $originalEntityIdentity = [$entity->national_id, $entity->registration_no, $entity->registration_type];
                $originalUserIdentity = [$user->national_id, $user->registration_type];
                foreach (['TEST-REPLACEMENT', null] as $replacement) {
                    if ($replacement !== $entity->national_id) {
                        $this->rejects('national_id', fn () => app(EntityManagementController::class)->update(
                            $this->request('admin.entities.update', [...$this->entityPayload($entity), 'national_id' => $replacement, 'registration_type' => 'staff'], $actor), (string) $entity->getKey()));
                        $this->rejects('national_id', fn () => app(UserManagementController::class)->update(
                            $this->request('admin.users.update', [...$this->userPayload($user), 'national_id' => $replacement, 'registration_type' => 'staff'], $actor), (string) $user->getKey()));
                    }
                    $this->rejects('registration_no', fn () => app(EntityManagementController::class)->update(
                        $this->request('admin.entities.update', [...$this->entityPayload($entity), 'registration_no' => $replacement ?? 'TEST-ALIAS'], $actor), (string) $entity->getKey()));
                }
                $this->rejects('national_id', fn () => $user->fresh()->forceFill(['national_id' => 'TEST-MODEL-REPLACEMENT'])->save());
                $this->rejects('profile', fn () => app(ProfileController::class)->storeOfficialChangeRequest(
                    $this->request('profile.official-change.store', ['name_en' => $entity->name_en, 'name_ar' => $entity->name_ar, 'national_id' => 'TEST-PROFILE-REPLACEMENT', 'registration_no' => 'TEST-PROFILE-REPLACEMENT'], $user)));

                $metadata = $entity->metadata;
                $metadata['profile_change_requests'] = [['id' => 'legacy-test', 'status' => 'pending', 'fields' => [
                    'national_id' => ['requested' => 'TEST-LEGACY-REPLACEMENT'],
                    'registration_no' => ['requested' => 'TEST-LEGACY-REPLACEMENT'],
                ]]];
                $entity->forceFill(['metadata' => $metadata])->save();
                app(EntityManagementController::class)->reviewProfileChangeRequest(
                    $this->request('admin.entities.profile-change-requests.review', ['decision' => 'approve'], $actor), (string) $entity->getKey(), 'legacy-test');
                $this->rejects('profile_change_request', fn () => app(EntityManagementController::class)->reviewProfileChangeRequest(
                    $this->request('admin.entities.profile-change-requests.review', ['decision' => 'reject'], $actor), (string) $entity->getKey(), 'legacy-test'));
                $fresh = $entity->fresh();
                $this->ensure([$fresh->national_id, $fresh->registration_no, $fresh->registration_type] === $originalEntityIdentity, 'Entity identity changed.');
                $this->ensure([$user->fresh()->national_id, $user->fresh()->registration_type] === $originalUserIdentity, 'User identity changed.');
            });
        }

        $this->check('V02 company employee identity', function (): void {
            [, $owner] = $this->fixture('company');
            [, $member] = $this->fixture('employee');
            foreach (['TEST-EMPLOYEE-REPLACEMENT', null] as $replacement) {
                $this->rejects('national_id', fn () => app(CompanyEmployeeController::class)->update($this->request('company.employees.update', [
                    'name' => $member->name, 'phone' => $member->phone, 'national_id' => $replacement,
                    'job_title' => '', 'role' => 'company_viewer',
                ], $owner), (string) $member->getKey()));
            }
            $this->ensure($member->fresh()->national_id === $member->national_id, 'Employee identity changed.');
        });

        $this->check('V03 stale review, entity edit and user status', function () use ($actor): void {
            [$entity, $user] = $this->fixture('review');
            $stale = $this->entityPayload($entity);
            app(EntityManagementController::class)->review($this->request('admin.entities.review', ['decision' => 'approve'], $actor), (string) $entity->getKey());
            foreach (['reject', 'needs_completion', 'approve'] as $decision) {
                $this->rejects('decision', fn () => app(EntityManagementController::class)->review($this->request('admin.entities.review', ['decision' => $decision], $actor), (string) $entity->getKey()));
            }
            $this->rejects('status', fn () => app(EntityManagementController::class)->update($this->request('admin.entities.update', [...$stale, 'name_en' => 'Stale verification edit'], $actor), (string) $entity->getKey()));
            $this->rejects('status', fn () => app(UserManagementController::class)->update($this->request('admin.users.update', $this->userPayload($user), $actor), (string) $user->getKey()));
            $this->ensure($entity->fresh()->status === 'active' && $user->fresh()->status === 'active', 'Review state was overwritten.');
            $this->ensure($entity->fresh()->name_en === $stale['name_en'] && count(data_get($entity->fresh()->metadata, 'review_history', [])) === 1, 'Stale write changed data/history.');
        });

        $this->check('V02/V03 completion identity and current-state guards', function (): void {
            [$entity, $user] = $this->fixture('completion');
            $payload = ['entity_name' => $entity->name_en, 'registration_number' => 'TEST-COMPLETION-REPLACEMENT', 'email' => $user->email, 'phone' => $user->phone, 'address' => 'Verification only', 'description' => ''];
            $this->rejects('registration_number', fn () => app(RegistrationCompletionController::class)->updateViaSignedLink($this->request('registration.completion.link.update', $payload), $entity));
            DB::transaction(function () use ($entity, $user): void {
                $entity->forceFill(['status' => 'active'])->save();
                $user->forceFill(['status' => 'active'])->save();
            });
            try {
                app(RegistrationCompletionController::class)->updateViaSignedLink($this->request('registration.completion.link.update', [...$payload, 'registration_number' => $entity->registration_no]), $entity->fresh());
                throw new RuntimeException('Resolved completion was accepted.');
            } catch (HttpException $exception) {
                $this->ensure($exception->getStatusCode() === 404, 'Unexpected completion status.');
            }
            $this->ensure($entity->fresh()->status === 'active' && $user->fresh()->status === 'active', 'Completion reopened registration.');
        });

        $concurrentResult = $concurrency ? $this->verifyConcurrentReview() : ['status' => 'not_run', 'reason' => 'Explicitly skipped.'];
        $this->verifyAccountResponses($samples);

        $result = [
            'run_id' => $this->runId, 'recorded_at_utc' => gmdate('c'), 'database_driver' => DB::connection()->getDriverName(),
            'scope' => 'Internal Laravel controller requests with isolated sessions and process-local outbound fakes; no public HTTP requests.',
            'supported_identifiers' => ['login' => ['national_id', 'registration_no', 'email', 'username'], 'reset' => ['national_id', 'registration_no'], 'resend' => ['pending reset session']],
            'checks' => $this->checks, 'concurrent_review' => $concurrentResult, 'internal_timing_ms' => $this->timings,
            'limitations' => ['Public gateway timing, route authorization/CSRF, distributed throttles and real notification delivery are not exercised.', 'Registration/Gov lookup proof and interleaved completion validation require separate checks.', 'Timing samples are diagnostic; matching signatures or sample averages do not establish public statistical indistinguishability.'],
        ];
        $result['internal_checks_passed'] = ! in_array('failed', array_column($this->checks, 'status'), true) && ($concurrentResult['status'] ?? null) !== 'failed';
        $this->writeJson('results.json', $result);

        return $result;
    }

    private function verifyAccountResponses(int $samples): void
    {
        $originalEnvironment = app()->environment();
        app()->instance('env', 'production');
        try {
            foreach (['ar', 'en'] as $locale) {
                app()->setLocale($locale);
                foreach (['national_id', 'registration_no', 'email', 'username', 'phone'] as $identifierType) {
                    if ($this->verbose) {
                        echo "Checking internal V06 $locale/$identifierType ($samples samples per state)...\n";
                    }
                    $baseline = [];
                    for ($sample = 0; $sample < $samples; $sample++) {
                        $states = ['active' => 'company', 'pending' => 'pending', 'inactive' => 'inactive', 'archived' => 'archived', 'unknown' => null];
                        if ($sample % 2) {
                            $states = array_reverse($states, true);
                        }
                        foreach ($states as $state => $tag) {
                            [$entity, $user] = $tag ? $this->fixture($tag) : [null, null];
                            $identifier = $tag ? ($identifierType === 'registration_no' ? $entity->registration_no : $user->{$identifierType}) : $this->runId.'-unknown';
                            $this->check("V06 $locale $identifierType $state sample ".($sample + 1), function () use ($locale, $identifierType, $state, $identifier, $user, &$baseline): void {
                                $otp = app(OtpService::class);
                                $request = $this->request('login.store', ['identifier' => $identifier, 'password' => 'Deliberately incorrect verification password']);
                                $started = hrtime(true);
                                $response = app(LoginController::class)->store($request, $otp);
                                $this->recordResponse('login', $locale, $identifierType, $state, $started, $response, $request, $baseline);

                                $request = $this->request('password.otp.send', ['identifier' => $identifier]);
                                $jobsBefore = Bus::dispatched(SendPasswordResetOtp::class)->count();
                                $started = hrtime(true);
                                $response = app(ForgotPasswordController::class)->store($request, $otp);
                                $this->recordResponse('reset', $locale, $identifierType, $state, $started, $response, $request, $baseline);
                                $this->ensure(Bus::dispatched(SendPasswordResetOtp::class)->count() === $jobsBefore + 1, 'Reset did not queue its acknowledgement work.');
                                $expectedUserId = in_array($identifierType, ['national_id', 'registration_no'], true) && $user && ! $user->trashed() ? $user->getKey() : 0;
                                $this->ensure(Bus::dispatched(SendPasswordResetOtp::class)->last()->userId === $expectedUserId, 'Reset queued the wrong account state.');

                                $session = $request->session();
                                $session->forget(['status', 'errors']);
                                $session->put('pending_password_reset_requested_at', now()->subSeconds(OtpService::RESEND_COOLDOWN_SECONDS + 1)->timestamp);
                                $request = $this->request('password.otp.resend', [], null, $session);
                                $started = hrtime(true);
                                $response = app(PasswordResetOtpController::class)->resend($request, $otp);
                                $this->recordResponse('resend', $locale, $identifierType, $state, $started, $response, $request, $baseline);
                            });
                        }
                    }
                }
            }
        } finally {
            app()->instance('env', $originalEnvironment);
        }
    }

    private function recordResponse(string $flow, string $locale, string $identifierType, string $state, int $started, $response, Request $request, array &$baseline): void
    {
        $errors = $request->session()->get('errors');
        $signature = [$response->getStatusCode(), $response->getTargetUrl(), hash('sha256', $response->getContent()), $request->session()->get('status'), $errors?->getBag('default')->getMessages() ?? []];
        $supported = $flow === 'login' ? ['national_id', 'registration_no', 'email', 'username'] : ['national_id', 'registration_no'];
        $lookupState = in_array($identifierType, $supported, true) ? $state : 'unsupported_input';
        $this->timings[$locale][$identifierType][$flow][$lookupState][] = round((hrtime(true) - $started) / 1_000_000, 3);
        $baseline[$flow] ??= $signature;
        $this->ensure($signature === $baseline[$flow], 'Account-state response signature differs.');
    }

    public function concurrentWorker(string $decision): array
    {
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw new RuntimeException('Invalid worker decision.');
        }
        $this->loadManifest();
        [$entity] = $this->fixture('concurrent');
        $actor = User::query()->findOrFail($this->manifest['actor_id']);
        $this->ensure($actor->username === $this->runId.'-reviewer', 'Reviewer marker mismatch.');
        file_put_contents($this->directory.'/ready-'.$decision, 'ready', LOCK_EX);
        $this->waitUntil(fn () => is_file($this->directory.'/start'), 20);
        $started = microtime(true);
        try {
            app(EntityManagementController::class)->review($this->request('admin.entities.review', ['decision' => $decision], $actor), (string) $entity->getKey());
            $result = ['outcome' => 'accepted', 'decision' => $decision];
        } catch (ValidationException $exception) {
            $result = ['outcome' => array_key_exists('decision', $exception->errors()) ? 'rejected_stale' : 'unexpected_validation', 'decision' => $decision];
        } catch (Throwable $exception) {
            $result = ['outcome' => 'error', 'exception_class' => get_class($exception), 'decision' => $decision];
        }
        $result['started_at_unix_ms'] = round($started * 1000, 3);
        $result['duration_ms'] = round((microtime(true) - $started) * 1000, 3);
        $this->writeJson('worker-'.$decision.'.json', $result);

        return $result;
    }

    private function verifyConcurrentReview(): array
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlsrv'], true)) {
            return ['status' => 'not_proven', 'reason' => 'Row-lock contention check requires MySQL/MariaDB, PostgreSQL or SQL Server; current driver is '.$driver.'.'];
        }
        if (! function_exists('proc_open')) {
            return ['status' => 'not_run', 'reason' => 'proc_open is unavailable.'];
        }

        $processes = [];
        try {
            foreach (['approve', 'reject'] as $decision) {
                $process = new Process([PHP_BINARY, __FILE__, '--staging-test', '--app-path='.base_path(), '--expected-app-url='.rtrim(config('app.url'), '/'), '--worker='.$decision, '--run-id='.$this->runId]);
                $process->setTimeout(40);
                $process->start();
                $processes[] = $process;
            }
            $this->waitUntil(fn () => is_file($this->directory.'/ready-approve') && is_file($this->directory.'/ready-reject'), 20);
            DB::transaction(function (): void {
                Entity::query()->whereKey($this->manifest['fixtures']['concurrent']['entity_id'])->lockForUpdate()->firstOrFail();
                file_put_contents($this->directory.'/start', 'start', LOCK_EX);
                usleep(500_000);
            });
            $lockReleasedAt = microtime(true) * 1000;
            foreach ($processes as $process) {
                $this->ensure($process->wait() === 0, 'Concurrent worker failed.');
            }
            $results = array_map(fn (string $decision) => json_decode(file_get_contents($this->directory.'/worker-'.$decision.'.json'), true, flags: JSON_THROW_ON_ERROR), ['approve', 'reject']);
            $outcomes = array_column($results, 'outcome');
            sort($outcomes);
            $this->ensure($outcomes === ['accepted', 'rejected_stale'], 'Concurrent decisions did not serialize.');
            foreach ($results as $worker) {
                $this->ensure($worker['started_at_unix_ms'] < $lockReleasedAt - 100 && $worker['duration_ms'] >= 100, 'Worker overlap during held row lock was not observed.');
            }
            [$entity, $user] = $this->fixture('concurrent');
            $winner = collect($results)->firstWhere('outcome', 'accepted')['decision'];
            $expected = $winner === 'approve' ? 'active' : 'rejected';
            $this->ensure($entity->status === $expected && $user->status === $expected && count(data_get($entity->metadata, 'review_history', [])) === 1, 'Concurrent final state/history mismatch.');

            return ['status' => 'passed', 'method' => 'Two PHP processes released together while parent holds row lock for 500ms.', 'lock_released_at_unix_ms' => round($lockReleasedAt, 3), 'workers' => $results];
        } catch (Throwable $exception) {
            return ['status' => 'failed', 'exception_class' => get_class($exception), 'detail' => $exception instanceof RfcStagingVerificationFailure ? $exception->getMessage() : 'Worker/database operation failed; raw exception details omitted.'];
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(2);
                }
            }
        }
    }

    public function cleanup(): void
    {
        $this->loadManifest();
        DB::transaction(function (): void {
            $entityIds = array_column($this->manifest['fixtures'], 'entity_id');
            $userIds = [...array_column($this->manifest['fixtures'], 'user_id'), $this->manifest['actor_id']];
            foreach ($this->manifest['fixtures'] as $tag => $fixture) {
                $entity = Entity::withTrashed()->lockForUpdate()->find($fixture['entity_id']);
                $user = User::withTrashed()->lockForUpdate()->find($fixture['user_id']);
                if ($entity) {
                    $this->ensure($entity->code === $this->runId.'-'.$tag && data_get($entity->metadata, 'security_verification') === $this->runId, 'Entity cleanup marker mismatch.');
                    $this->ensure(! DB::table('applications')->where('entity_id', $entity->getKey())->exists() && ! DB::table('scouting_requests')->where('entity_id', $entity->getKey())->exists(), 'Fixture has application records; preserve for review.');
                }
                if ($user) {
                    $this->ensure($user->username === $this->runId.'-'.$tag.'-owner' && $user->email === $this->runId.'-'.$tag.'@example.invalid', 'User cleanup marker mismatch.');
                }
            }
            $actor = User::withTrashed()->lockForUpdate()->find($this->manifest['actor_id']);
            if ($actor) {
                $this->ensure($actor->username === $this->runId.'-reviewer' && $actor->email === $this->runId.'-reviewer@example.invalid' && ! DB::table('entity_user')->where('user_id', $actor->getKey())->exists(), 'Reviewer cleanup marker mismatch.');
            }
            // Raw pivots include archived counterparts hidden by Eloquent soft-delete scopes.
            $this->ensure(! DB::table('entity_user')->whereIn('entity_id', $entityIds)->whereNotIn('user_id', $userIds)->exists(), 'Fixture linked to another user.');
            $this->ensure(! DB::table('entity_user')->whereIn('user_id', $userIds)->whereNotIn('entity_id', $entityIds)->exists(), 'Fixture user linked to another entity.');
            // Refuse cascades or SET NULL changes to any later records referencing fixtures.
            foreach (Schema::getTables() as $table) {
                if ($table['name'] === 'entity_user') {
                    continue;
                }
                $tableName = $table['schema_qualified_name'] ?? $table['name'];
                foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
                    $ids = match ($foreignKey['foreign_table']) {
                        'users' => $userIds, 'entities' => $entityIds, default => null,
                    };
                    if ($ids !== null) {
                        $this->ensure(count($foreignKey['columns']) === 1, 'Unsupported cleanup dependency; preserve fixtures.');
                        $this->ensure(! DB::table($tableName)->whereIn($foreignKey['columns'][0], $ids)->exists(), 'Fixture has dependent records; preserve for review.');
                    }
                }
            }
            foreach ($entityIds as $id) {
                Entity::withTrashed()->find($id)?->forceDelete();
            }
            foreach ($userIds as $id) {
                User::withTrashed()->find($id)?->forceDelete();
            }
        });
        $this->writeJson('cleanup.json', ['run_id' => $this->runId, 'status' => 'completed', 'recorded_at_utc' => gmdate('c')]);
    }

    private function fixture(string $tag): array
    {
        $fixture = $this->manifest['fixtures'][$tag];
        $entity = Entity::withTrashed()->findOrFail($fixture['entity_id']);
        $user = User::withTrashed()->findOrFail($fixture['user_id']);
        $this->ensure($entity->code === $this->runId.'-'.$tag && data_get($entity->metadata, 'security_verification') === $this->runId, 'Fixture marker mismatch.');
        $this->ensure($user->username === $this->runId.'-'.$tag.'-owner', 'Fixture owner marker mismatch.');

        return [$entity, $user];
    }

    private function request(string $routeName, array $payload, ?User $actor = null, ?Store $session = null): Request
    {
        $request = Request::create(rtrim(config('app.url'), '/').'/'.app()->getLocale().'/verification', 'POST', $payload, server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'application/json', 'HTTP_USER_AGENT' => 'Internal staging verification']);
        $session ??= new Store('security-verification', new ArraySessionHandler(120));
        $session->start();
        $session->setPreviousUrl(rtrim(config('app.url'), '/').'/'.app()->getLocale().'/sign-in');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $actor);
        $request->setRouteResolver(fn () => (new Route('POST', 'verification', fn () => null))->name($routeName));
        app()->instance('request', $request);
        app()->instance('session.store', $session);
        app('redirect')->setSession($session);
        URL::setRequest($request);
        Auth::forgetGuards();
        if ($actor) {
            Auth::guard()->setUser($actor);
        }

        return $request;
    }

    private function entityPayload(Entity $entity): array
    {
        return ['group_id' => $entity->group_id, 'code' => $entity->code, 'name_en' => $entity->name_en, 'name_ar' => $entity->name_ar, 'registration_no' => $entity->registration_no, 'national_id' => $entity->national_id, 'email' => $entity->email, 'phone' => $entity->phone, 'status' => $entity->status, 'address' => '', 'description' => ''];
    }

    private function userPayload(User $user): array
    {
        return ['name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'phone' => $user->phone, 'national_id' => $user->national_id, 'status' => $user->status];
    }

    private function check(string $name, callable $callback): void
    {
        try {
            $callback();
            $this->checks[] = ['name' => $name, 'status' => 'passed'];
        } catch (Throwable $exception) {
            $this->checks[] = ['name' => $name, 'status' => 'failed', 'exception_class' => get_class($exception), 'detail' => $exception instanceof RfcStagingVerificationFailure ? $exception->getMessage() : 'Controller operation failed; raw exception details omitted.'];
        }
    }

    private function rejects(string $field, callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->ensure(array_key_exists($field, $exception->errors()), 'Expected field-specific rejection missing.');

            return;
        }
        throw new RfcStagingVerificationFailure('Expected validation rejection missing.');
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RfcStagingVerificationFailure($message);
        }
    }

    private function waitUntil(callable $condition, int $seconds): void
    {
        $deadline = microtime(true) + $seconds;
        while (! $condition()) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Worker coordination timeout.');
            }
            usleep(20_000);
            clearstatcache();
        }
    }

    private function writeJson(string $filename, array $data): void
    {
        $temporary = $this->directory.'/'.$filename.'.tmp';
        if (file_put_contents($temporary, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX) === false
            || ! rename($temporary, $this->directory.'/'.$filename)) {
            throw new RuntimeException('Cannot write private verification evidence.');
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $options = getopt('', ['staging-test', 'app-path:', 'expected-app-url:', 'samples:', 'keep-fixtures', 'skip-concurrency', 'cleanup:', 'worker:', 'run-id:']);
    if (! array_key_exists('staging-test', $options) || empty($options['expected-app-url'])) {
        fwrite(STDERR, "Refusing to run: --staging-test and --expected-app-url are required. See usage at the top of this file.\n");
        exit(2);
    }

    try {
        $appPath = realpath($options['app-path'] ?? dirname(__DIR__));
        if (! $appPath || ! is_file($appPath.'/artisan')) {
            throw new RuntimeException('Invalid Laravel application path.');
        }
        require $appPath.'/vendor/autoload.php';
        $app = require $appPath.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        if (rtrim((string) config('app.url'), '/') !== rtrim((string) $options['expected-app-url'], '/')) {
            throw new RuntimeException('Expected application URL does not match configured APP_URL.');
        }
        $public = realpath(public_path());
        if ($public && str_starts_with(str_replace('\\', '/', __FILE__), str_replace('\\', '/', $public).'/')) {
            throw new RuntimeException('The helper must not be located under public/.');
        }
        RfcStagingSecurityVerifier::blockOutboundDelivery();
        $runId = $options['cleanup'] ?? $options['run-id'] ?? 'sv-'.bin2hex(random_bytes(8));
        $directory = storage_path('app/private/security-verification/'.$runId);
        $verifier = new RfcStagingSecurityVerifier($directory, $runId, true);
        if (isset($options['cleanup'])) {
            $verifier->cleanup();
            echo "Exact fixture cleanup completed for $runId.\n";
            exit(0);
        }
        if (isset($options['worker'])) {
            $verifier->concurrentWorker($options['worker']);
            exit(0);
        }
        $samples = filter_var($options['samples'] ?? 3, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
        if ($samples === false) {
            throw new RuntimeException('Samples must be between 1 and 10.');
        }
        $verifier->createFixtures();
        echo "Run $runId: isolated fixtures ready; checking internal controllers (no outbound delivery).\n";
        try {
            $result = $verifier->verify($samples, ! array_key_exists('skip-concurrency', $options));
        } finally {
            if (! array_key_exists('keep-fixtures', $options)) {
                $verifier->cleanup();
            }
        }
        echo ($result['internal_checks_passed'] ? 'Internal checks passed' : 'Internal checks FAILED')."; public closure remains separate.\nEvidence: $directory/results.json\n";
        exit($result['internal_checks_passed'] ? 0 : 1);
    } catch (Throwable $exception) {
        // Do not print SQL/connection details, request secrets or exception traces.
        fwrite(STDERR, 'Verification stopped ('.get_class($exception)."). Retain any printed run ID for exact cleanup.\n");
        exit(1);
    }
}
