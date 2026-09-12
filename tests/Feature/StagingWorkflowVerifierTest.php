<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Entity;
use App\Models\ScoutingRequest;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RfcWorkflowVerification\VerificationFailure;
use RfcWorkflowVerification\WorkflowVerifier;
use Tests\TestCase;

require_once dirname(__DIR__, 2).'/scripts/verify-staging-workflows.php';

class StagingWorkflowVerifierTest extends TestCase
{
    #[DataProvider('locales')]
    public function test_runner_exercises_both_workflows_and_rolls_back_every_fixture(string $locale): void
    {
        $this->refreshApplicationWithLocale($locale);
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->seed(AccessControlSeeder::class);
        $before = [User::count(), Entity::count(), Application::count(), ScoutingRequest::count(), DB::table('notifications')->count()];
        $this->app->detectEnvironment(fn () => 'production');
        $this->withoutExceptionHandling([ValidationException::class, TokenMismatchException::class]);

        $result = (new WorkflowVerifier($this->app))->run();

        $this->assertTrue($result['passed'], json_encode($result));
        $this->assertTrue($result['database_rolled_back']);
        $this->assertTrue($result['fixture_records_absent']);
        $this->assertTrue($result['temporary_files_removed']);
        $this->assertCount(16, $result['checks']);
        $this->assertSame($before, [User::count(), Entity::count(), Application::count(), ScoutingRequest::count(), DB::table('notifications')->count()]);
    }

    public static function locales(): array
    {
        return [['en'], ['ar']];
    }

    public function test_runner_rolls_back_and_cleans_files_after_a_failure_after_upload(): void
    {
        $this->refreshApplicationWithLocale('en');
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->seed(AccessControlSeeder::class);
        $before = [User::count(), Entity::count(), Application::count(), ScoutingRequest::count()];
        $this->app->detectEnvironment(fn () => 'production');
        $this->withoutExceptionHandling([ValidationException::class, TokenMismatchException::class]);

        $result = (new WorkflowVerifier($this->app, function (string $step): void {
            if ($step === 'scouting-requests.corrected-save') {
                throw new VerificationFailure('Deliberate cleanup verification failure.');
            }
        }))->run();

        $this->assertFalse($result['passed']);
        $this->assertSame('Deliberate cleanup verification failure.', $result['error'], json_encode($result));
        $this->assertTrue($result['database_rolled_back']);
        $this->assertTrue($result['fixture_records_absent']);
        $this->assertTrue($result['temporary_files_removed']);
        $this->assertSame($before, [User::count(), Entity::count(), Application::count(), ScoutingRequest::count()]);
    }
}
