<?php

/**
 * CLI-only appendix regression checks against deployed Laravel code/database.
 * Usage: php verify-staging-workflows.php --app-root="C:\inetpub\rfc" --confirm-staging --locale=ar
 * Repeat with --locale=en. No migrations, seeding, outbound delivery or persistent fixtures.
 */

declare(strict_types=1);

namespace RfcWorkflowVerification;

use App\Models\Application as FilmApplication;
use App\Models\Entity;
use App\Models\FilmingLocationType;
use App\Models\Governorate;
use App\Models\Group;
use App\Models\Nationality;
use App\Models\ReleaseMethod;
use App\Models\ScoutingRequest;
use App\Models\User;
use App\Models\WorkCategory;
use App\Services\SmsService;
use App\Support\JordanBusinessDays;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Monolog\Handler\NullHandler;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

final class VerificationFailure extends \RuntimeException {}

final class WorkflowVerifier
{
    private array $report = ['passed' => false, 'checks' => [], 'database_rolled_back' => false, 'temporary_files_removed' => false];

    private array $cookies = [];

    private string $step = 'preflight';

    private string $marker;

    private string $temporaryRoot;

    private User $user;

    private Entity $entity;

    public function __construct(private Application $app, private ?\Closure $checkpoint = null)
    {
        $this->marker = 'workflow-verification-'.bin2hex(random_bytes(8));
        $this->temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$this->marker;
    }

    public function run(): array
    {
        $connection = null;
        $transactionStarted = false;

        try {
            $this->require(! $this->app->runningUnitTests(), 'Use a non-testing application environment so CSRF middleware remains active.');
            $this->require(! $this->app->isDownForMaintenance(), 'Application is under maintenance; finish deployment first.');
            $this->installIsolation();
            $connection = DB::connection();
            $this->require($connection->transactionLevel() === 0, 'An existing database transaction prevents this isolated run.');
            $driver = $connection->getDriverName();
            $this->require(in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite', 'sqlsrv'], true), 'Unsupported database driver.');
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $tables = $connection->select("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
                foreach ($tables as $table) {
                    $this->require(strcasecmp((string) $table->ENGINE, 'InnoDB') === 0, 'Nontransactional database table detected; no fixtures were created.');
                }
            }
            foreach ([new Entity, new User, new FilmApplication, new ScoutingRequest] as $model) {
                $this->require($model->getConnection() === $connection, 'Workflow models use more than one database connection.');
            }
            $this->report['database_driver'] = $driver;
            $this->report['php_version'] = PHP_VERSION;
            $this->report['locale'] = $this->app->getLocale();
            $this->report['scope'] = 'In-process deployed HTTP kernel; isolated session/cache/storage; delivery faked; fixture transaction rolled back. Browser JavaScript, IIS/gateway and distributed behavior are not tested.';
            $connection->beginTransaction();
            $transactionStarted = true;
            $this->createFixture();
            $this->verifyWorkflow('applications', FilmApplication::class);
            $this->verifyWorkflow('scouting-requests', ScoutingRequest::class);
            $this->verifyOmittedScoutingFields();
            $this->require($connection->transactionLevel() === 1, 'Unexpected transaction nesting after requests.');
            $this->report['passed'] = true;
        } catch (\Throwable $exception) {
            $this->report['failed_step'] = $this->step;
            $this->report['error_location'] = basename($exception->getFile()).':'.$exception->getLine();
            $this->report['error'] = $exception instanceof VerificationFailure
                ? $exception->getMessage()
                : 'Unexpected '.get_class($exception).'; inspect the runner privately without sharing database credentials.';
        } finally {
            if ($transactionStarted) {
                try {
                    $connection->rollBack(0);
                    $this->report['database_rolled_back'] = $connection->transactionLevel() === 0;
                    $this->report['fixture_records_absent'] = ! Entity::withTrashed()->where('code', $this->marker)->exists()
                        && ! User::withTrashed()->where('username', $this->marker)->exists();
                } catch (\Throwable) {
                    $this->report['cleanup_error'] = 'Database rollback could not be verified; inspect this run before retrying.';
                }
            }
            try {
                if (is_dir($this->temporaryRoot)) {
                    $this->app['files']->deleteDirectory($this->temporaryRoot);
                }
                $this->report['temporary_files_removed'] = ! file_exists($this->temporaryRoot);
            } catch (\Throwable) {
                $this->report['cleanup_error'] = 'Temporary file cleanup failed.';
            }
            if ($transactionStarted && (! ($this->report['database_rolled_back'] ?? false)
                || ! ($this->report['fixture_records_absent'] ?? false))) {
                $this->report['passed'] = false;
            }
            if (! $this->report['temporary_files_removed']) {
                $this->report['passed'] = false;
            }
        }

        return $this->report;
    }

    private function installIsolation(): void
    {
        $this->require(mkdir($this->temporaryRoot, 0700), 'Cannot create the private temporary directory.');
        mkdir($this->temporaryRoot.'/views', 0700);
        config([
            'session.driver' => 'array',
            'session.lottery' => [0, 100],
            'cache.default' => 'array',
            'cache.limiter' => 'array',
            'permission.cache.store' => 'array',
            'view.compiled' => $this->temporaryRoot.'/views',
            'logging.default' => 'workflow_verification',
            'logging.channels.workflow_verification' => ['driver' => 'monolog', 'handler' => NullHandler::class],
        ]);
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        Cache::purge('array');
        $originalLimiter = $this->app->make(RateLimiter::class);
        $isolatedLimiter = new RateLimiter(Cache::store('array'));
        $limiterDefinitions = (new \ReflectionProperty($originalLimiter, 'limiters'))->getValue($originalLimiter);
        foreach ($limiterDefinitions as $name => $callback) {
            $isolatedLimiter->for($name, $callback);
        }
        \Illuminate\Support\Facades\RateLimiter::swap($isolatedLimiter);
        $this->app->forgetInstance(PermissionRegistrar::class);
        $this->app->forgetInstance('blade.compiler');
        $this->app['view.engine.resolver']->forget('blade');
        Log::forgetChannel();
        Storage::set('local', Storage::build(['driver' => 'local', 'root' => $this->temporaryRoot.'/files', 'throw' => true]));
        Notification::fake();
        Mail::fake();
        Bus::fake();
        Http::fake(fn () => throw new VerificationFailure('Unexpected outbound HTTP attempt blocked.'));
        Http::preventStrayRequests();
        $this->app->instance(SmsService::class, new class extends SmsService
        {
            public function send(string $text, string $to): array
            {
                throw new VerificationFailure('Unexpected direct SMS attempt blocked.');
            }
        });
        URL::forceRootUrl((string) config('app.url'));
        URL::forceScheme(parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https');
    }

    private function createFixture(): void
    {
        $this->step = 'create-isolated-fixture';
        $group = Group::query()->where('code', 'organizations')->first();
        $this->require($group !== null, 'Organizations group is missing; no seeding was performed.');
        $logo = 'verification/logo.png';
        Storage::disk('local')->put($logo, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1sAAAAASUVORK5CYII='));
        $this->entity = Entity::query()->create([
            'group_id' => $group->getKey(), 'code' => $this->marker,
            'name_en' => 'Workflow Verification Studio', 'name_ar' => 'Workflow Verification Studio',
            'registration_no' => $this->marker, 'registration_type' => 'company', 'status' => 'active',
            'email' => $this->marker.'@example.invalid', 'phone' => '0000000000',
            'metadata' => ['workflow_verification' => $this->marker, 'address' => 'Verification address', 'fax' => '0000000000', 'logo_path' => $logo, 'logo_mime' => 'image/png'],
        ]);
        $this->user = User::query()->create([
            'name' => 'Workflow Verification Applicant', 'username' => $this->marker,
            'email' => $this->marker.'@example.invalid', 'phone' => '0000000000',
            'registration_type' => 'company', 'status' => 'active', 'password' => bin2hex(random_bytes(32)),
        ]);
        $this->user->entities()->attach($this->entity, ['is_primary' => true, 'status' => 'active', 'joined_at' => now()]);
        $registrar = $this->app->make(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->entity->getKey());
        try {
            $this->user->assignRole('applicant_owner');
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }

    private function verifyWorkflow(string $workflow, string $modelClass): void
    {
        $create = route($workflow.'.create');
        $store = route($workflow.'.store');
        $logo = route('entities.logo', $this->entity).'?v=workflow-verification';
        $this->step = $workflow.'.create-page';
        $response = $this->request('GET', $create);
        $this->status($response, 200);
        $this->require($response->headers->get('Referrer-Policy') === 'no-referrer', 'Expected no-referrer policy missing.');
        $this->check('create-page');

        $this->step = $workflow.'.csrf-enforced';
        $this->status($this->request('POST', $store, [], [], [], false), 419);
        $this->check('csrf-enforced');
        $this->status($this->request('GET', $create), 200);

        foreach ([['Sec-Fetch-Dest' => 'image'], []] as $imageHeaders) {
            $this->step = $workflow.'.invalid-save-after-logo'.($imageHeaders === [] ? '-legacy' : '-modern');
            $this->status($this->request('GET', $logo, [], [], $imageHeaders), 200);
            $response = $this->request('POST', $store, ['project_summary' => 'Retain workflow input']);
            $this->redirect($response, $create);
            $errors = session('errors');
            $this->require($errors !== null && $errors->has('project_name'), 'Missing project-name validation error.');
            $message = $errors->first('project_name');
            $oldInput = session('_old_input');
            $this->require(is_array($oldInput) && ($oldInput['project_summary'] ?? null) === 'Retain workflow input', 'Invalid save lost submitted input.');
            $this->status($this->request('GET', $logo, [], [], $imageHeaders), 200);
            $this->require(session('errors')?->has('project_name') === true && session('_old_input') === $oldInput, 'Background logo request consumed errors or input.');
            $response = $this->request('GET', $create);
            $this->status($response, 200);
            $this->require(str_contains($response->getContent(), e($message)), 'Redirected form does not render the validation error.');
            $this->require(! $modelClass::query()->where('entity_id', $this->entity->getKey())->exists(), 'Invalid save created a record.');
            $this->status($this->request('GET', $create), 200);
            $this->check('invalid-save-redirect-input-errors');
        }

        $payload = $workflow === 'applications' ? $this->applicationPayload() : $this->scoutingPayload();
        $this->step = $workflow.'.corrected-save';
        $files = $workflow === 'scouting-requests' ? ['story_file' => $this->pdf()] : ['work_content_summary_attachment' => $this->pdf()];
        $response = $this->request('POST', $store, $payload, $files);
        $record = $modelClass::query()->where('entity_id', $this->entity->getKey())->first();
        $this->require($record !== null, 'Corrected save did not create a draft. Validation fields: '.$this->errorKeys());
        $this->redirect($response, route($workflow.'.show', $record));
        $this->require($record->status === 'draft', 'New record did not remain a draft.');
        $attachmentPath = $workflow === 'scouting-requests' ? $record->story_file_path : data_get($record->metadata, 'annex.work_content_summary.attachment_path');
        $this->require(is_string($attachmentPath) && Storage::disk('local')->exists($attachmentPath), 'Corrected save did not store the test attachment.');
        $this->status($this->request('GET', route($workflow.'.show', $record)), 200);
        $this->check('corrected-save-with-upload');

        $this->step = $workflow.'.edit-save';
        $this->status($this->request('GET', route($workflow.'.edit', $record)), 200);
        $payload['project_name'] .= ' revised';
        $response = $this->request('POST', route($workflow.'.update', $record), $payload);
        $this->redirect($response, route($workflow.'.show', $record));
        $record->refresh();
        $this->require($record->project_name === $payload['project_name'] && $record->status === 'draft', 'Edit did not persist while retaining draft state. Fields: '.$this->errorKeys());
        $this->check('edit-save');

        $this->step = $workflow.'.submit';
        $response = $this->request('POST', route($workflow.'.submit', $record));
        $this->redirect($response, route($workflow.'.show', $record));
        $record->refresh();
        $this->require($record->status === 'submitted', 'Submission did not reach submitted state. Fields: '.$this->errorKeys());
        $this->require($record->statusHistory()->where('status', 'submitted')->exists(), 'Submitted status history missing.');
        $this->status($this->request('GET', route($workflow.'.show', $record)), 200);
        $this->check('submit-with-delivery-suppressed');
    }

    private function request(string $method, string $url, array $payload = [], array $files = [], array $headers = [], bool $csrf = true): Response
    {
        Auth::guard('web')->setUser($this->user->fresh());
        if ($method !== 'GET' && $csrf) {
            $payload['_token'] = session()->token();
        }
        $request = Request::create($url, $method, $payload, $this->cookies, $files, ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'text/html']);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }
        $kernel = $this->app->make(Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = $cookie->getValue();
        }

        return $response;
    }

    private function verifyOmittedScoutingFields(): void
    {
        $payload = $this->scoutingPayload();
        $payload['project_name'] = 'Workflow Verification Optional Fields';
        foreach (['production_start_date', 'production_end_date', 'story_text', 'production_type_other'] as $key) {
            unset($payload[$key]);
        }
        $this->step = 'scouting-requests.omitted-fields-create';
        $response = $this->request('POST', route('scouting-requests.store'), $payload);
        $record = ScoutingRequest::query()->where('entity_id', $this->entity->getKey())
            ->where('project_name', $payload['project_name'])->first();
        $this->require($record !== null, 'Omitting optional fields prevented draft creation. Fields: '.$this->errorKeys());
        $this->redirect($response, route('scouting-requests.show', $record));
        $this->check('optional-fields-omitted-create');
        $this->step = 'scouting-requests.omitted-fields-update';
        $payload['project_name'] .= ' revised';
        $response = $this->request('POST', route('scouting-requests.update', $record), $payload);
        $this->redirect($response, route('scouting-requests.show', $record));
        $record->refresh();
        $this->require($record->project_name === $payload['project_name'] && $record->status === 'draft'
            && $record->production_start_date === null && $record->production_end_date === null
            && $record->story_text === null && data_get($record->metadata, 'production.type_other') === null,
            'Omitted optional fields did not save as null on the draft.');
        $this->check('optional-fields-omitted-update');
    }

    private function applicationPayload(): array
    {
        $work = WorkCategory::defaultCode();
        $summaryWords = WorkCategory::workSummaryMinWordsFor($work);
        $this->require($summaryWords >= 1 && $summaryWords <= 10000, 'Work summary size exceeds the bounded fixture limit.');
        $this->require(in_array('jordanian', Nationality::activeCodesFor(Nationality::USAGE_PROJECT), true), 'Local Jordanian project option is unavailable.');
        [$governorate, $location, $start] = $this->location();
        $date = fn (int $days): string => $start->copy()->addDays($days)->toDateString();

        return [
            'project_name' => 'Workflow Verification Production', 'project_nationalities' => ['jordanian'], 'project_nationality' => 'jordanian',
            'work_category' => $work, 'release_method' => ReleaseMethod::defaultCode(),
            'planned_start_date' => $date(5), 'planned_end_date' => $date(8),
            'schedule_phases' => ['preparation' => ['start_date' => $date(0), 'end_date' => $date(4)], 'wrap' => ['start_date' => $date(9), 'end_date' => $date(10)], 'post_production' => ['start_date' => $date(11), 'end_date' => $date(12)]],
            'estimated_crew_count' => 1, 'estimated_budget' => 100, 'local_spend_estimate' => 50,
            'project_summary' => 'Isolated workflow verification.', 'producer_name' => 'Workflow Verification Applicant',
            'production_company_name' => 'Workflow Verification Studio', 'contact_address' => 'Verification address',
            'contact_phone' => '0000000000', 'contact_email' => $this->marker.'@example.invalid',
            'director_name' => 'Verification Director', 'director_nationality' => Nationality::activeCodesFor(Nationality::USAGE_DIRECTOR)[0], 'director_email' => 'director@example.invalid',
            'filming_locations' => [['governorate' => $governorate, 'location_name' => 'Verification location', 'address' => 'Verification address', 'nature' => 'Test location', 'location_type' => $location, 'start_date' => $date(5), 'end_date' => $date(8)]],
            'safety_guidelines_acknowledged' => '1', 'production_terms_accepted' => '1',
            'work_content_summary_synopsis' => implode(' ', array_fill(0, $summaryWords, 'تصوير')),
            'work_content_summary_confirmed' => '1',
        ];
    }

    private function scoutingPayload(): array
    {
        [$governorate, $location, $start] = $this->location();
        $date = fn (int $days): string => $start->copy()->addDays($days)->toDateString();
        $nationality = Nationality::activeCodesFor(Nationality::USAGE_DIRECTOR)[0];

        return [
            'project_name' => 'Workflow Verification Scouting', 'project_nationality' => 'jordanian',
            'producer_name' => 'Verification Producer', 'producer_nationality' => $nationality,
            'production_company_name' => 'Workflow Verification Studio', 'producer_phone' => '0000000000', 'producer_mobile' => '0000000000', 'producer_fax' => '0000000000',
            'producer_email' => 'producer@example.invalid', 'contact_address' => 'Verification address',
            'liaison_name' => 'Verification Liaison', 'liaison_job_title' => 'Coordinator', 'liaison_email' => 'liaison@example.invalid', 'liaison_mobile' => '0000000000',
            'production_types' => [WorkCategory::defaultCode()], 'production_type_other' => null, 'scout_start_date' => $date(0), 'scout_end_date' => $date(2),
            'production_start_date' => $date(10), 'production_end_date' => $date(12), 'project_summary' => 'Isolated workflow verification.', 'story_text' => 'Test story.',
            'locations' => [['governorate' => $governorate, 'location_type' => $location, 'location_name' => 'Verification location', 'location_description' => 'Isolated test location.', 'start_date' => $date(0), 'end_date' => $date(2)]],
            'crew' => [['name' => 'Verification Crew Member', 'job_title' => 'Researcher', 'nationality' => $nationality, 'national_id_passport' => 'VERIFICATION-ONLY']],
        ];
    }

    private function location(): array
    {
        $governorate = Governorate::activeCodes()[0] ?? null;
        $location = FilmingLocationType::activeCodesForGovernorate($governorate)[0] ?? null;
        $this->require($governorate !== null && $location !== null, 'Active governorate/location lookup required; no lookup records were changed.');
        $days = (int) FilmingLocationType::query()->where('code', $location)->value('approval_days');
        $this->require($days <= 1000, 'Approval lead time exceeds the bounded fixture limit.');
        $start = JordanBusinessDays::addBusinessDays(JordanBusinessDays::today(), max(30, $days + 10));

        return [$governorate, $location, $start];
    }

    private function pdf(): UploadedFile
    {
        $path = $this->temporaryRoot.'/'.bin2hex(random_bytes(5)).'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");

        return new UploadedFile($path, 'workflow-verification.pdf', 'application/pdf', UPLOAD_ERR_OK, true);
    }

    private function status(Response $response, int $expected): void
    {
        $this->require($response->getStatusCode() === $expected, 'Expected HTTP '.$expected.', received '.$response->getStatusCode().'. Fields: '.$this->errorKeys());
    }

    private function redirect(Response $response, string $target): void
    {
        $this->status($response, 302);
        $this->require($response->headers->get('Location') === $target, 'Redirect did not return to the expected form/detail page. Fields: '.$this->errorKeys());
    }

    private function errorKeys(): string
    {
        return implode(', ', session('errors')?->keys() ?? []);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new VerificationFailure($message);
        }
    }

    private function check(string $name): void
    {
        $this->report['checks'][] = ['step' => $this->step, 'check' => $name, 'passed' => true];
        if ($this->checkpoint !== null) {
            ($this->checkpoint)($this->step);
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
    $options = getopt('', ['app-root:', 'confirm-staging', 'locale:']);
    $root = $options['app-root'] ?? null;
    $locale = $options['locale'] ?? 'ar';
    if (! isset($options['confirm-staging']) || ! is_string($root) || ! in_array($locale, ['ar', 'en'], true)
        || ! is_file($root.'/artisan') || ! is_file($root.'/vendor/autoload.php')) {
        fwrite(STDERR, "Usage: php verify-staging-workflows.php --app-root=APP_PATH --confirm-staging --locale=ar|en\n");
        exit(64);
    }
    try {
        require $root.'/vendor/autoload.php';
        putenv('ROUTING_LOCALE='.$locale);
        $app = require $root.'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $app->setLocale($locale);
        $result = (new WorkflowVerifier($app))->run();
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        exit($result['passed'] ? 0 : 1);
    } catch (\Throwable $exception) {
        fwrite(STDERR, 'Workflow verification could not initialize: '.get_class($exception).' at '.basename($exception->getFile()).':'.$exception->getLine().". No success is claimed.\n");
        exit(1);
    }
}
