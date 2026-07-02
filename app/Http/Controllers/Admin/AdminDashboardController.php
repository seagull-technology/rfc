<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application as FilmApplication;
use App\Models\ApplicationAuthorityApproval;
use App\Models\Entity;
use App\Models\Group;
use App\Models\Permit;
use App\Models\ReleaseMethod;
use App\Models\ScoutingRequest;
use App\Models\User;
use App\Models\WorkCategory;
use App\Services\Reports\ProductionAnalyticsService;
use App\Support\AdminApplicantResponseState;
use App\Support\AdminWorkflowState;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly ProductionAnalyticsService $productionAnalytics,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $admin = $request->user();
        $entity = $admin->primaryEntity();

        return view('admin.dashboard', [
            'admin' => $admin,
            'entity' => $entity,
            ...$this->buildDashboardData($request),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $dashboardData = $this->buildDashboardData($request);
        $stats = $dashboardData['stats'];
        $chartData = $dashboardData['chartData'];
        $operationalKpis = $dashboardData['productionOperationalReport']['kpis'];
        $rows = [
            ['metrics', __('app.admin.dashboard.metrics.groups'), $stats['groups']],
            ['metrics', __('app.admin.dashboard.metrics.entities'), $stats['entities']],
            ['metrics', __('app.admin.dashboard.metrics.users'), $stats['users']],
            ['metrics', __('app.admin.dashboard.metrics.active_users'), $stats['active_users']],
            ['metrics', __('app.admin.dashboard.metrics.registrations'), $stats['registrations']],
            ['metrics', __('app.admin.dashboard.metrics.individuals'), $stats['individuals']],
            ['metrics', __('app.admin.dashboard.metrics.organizations'), $stats['organizations']],
            ['metrics', __('app.admin.dashboard.metrics.applications'), $stats['applications']],
            ['metrics', __('app.admin.dashboard.metrics.permits'), $stats['permits']],
            ['metrics', __('app.admin.dashboard.metrics.pending_reviews'), $stats['pending_reviews']],
            ['metrics', __('app.admin.dashboard.metrics.active_entities'), $stats['active_entities']],
            ['operational_kpis', __('app.reports.kpis.received_applications'), $operationalKpis['received_applications']],
            ['operational_kpis', __('app.reports.kpis.pending_applications'), $operationalKpis['pending_applications']],
            ['operational_kpis', __('app.reports.kpis.active_production_shoots'), $operationalKpis['active_production_shoots']],
            ['operational_kpis', __('app.reports.kpis.active_locations'), $operationalKpis['active_locations']],
            ['operational_kpis', __('app.reports.kpis.future_locations'), $operationalKpis['future_locations']],
            ['operational_kpis', __('app.reports.kpis.pending_approvals'), $operationalKpis['approvals_tracker']['pending']],
        ];

        foreach ($chartData['applications_by_category'] as $key => $count) {
            $rows[] = ['applications_by_category', WorkCategory::labelFor((string) $key), $count];
        }

        foreach ($chartData['applications_by_release_method'] as $key => $count) {
            $rows[] = ['applications_by_release_method', ReleaseMethod::labelFor((string) $key), $count];
        }

        foreach ($chartData['registrations_by_type'] as $key => $count) {
            $rows[] = ['registrations_by_type', __('app.registration_types.'.$key), $count];
        }

        foreach ($chartData['monthly_applications'] as $row) {
            $rows[] = ['monthly_applications', $row['label'], $row['count']];
        }

        foreach ($chartData['approval_duration_by_authority'] as $row) {
            $rows[] = ['approval_duration_by_authority', __('app.applications.required_approval_options.'.$row['code']), $row['average_hours']];
        }

        return CsvExport::download(
            filename: 'admin-dashboard-summary-'.now()->format('Ymd-His').'.csv',
            headers: ['section', 'label', 'value'],
            rows: $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardData(?Request $request = null): array
    {
        $dashboardFilters = $request
            ? $this->productionAnalytics->filtersFromRequest($request)
            : $this->productionAnalytics->filtersFromRequest(request());
        $productionOperationalReport = $this->productionAnalytics->build($dashboardFilters);
        $applications = FilmApplication::query()
            ->with(['entity', 'submittedBy', 'assignedTo', 'authorityApprovals'])
            ->withMax([
                'statusHistory as last_clarification_at' => fn ($builder) => $builder->where('status', 'needs_clarification'),
            ], 'happened_at')
            ->withMax([
                'correspondences as last_applicant_correspondence_at' => fn ($builder) => $builder->where('sender_type', 'applicant'),
            ], 'created_at')
            ->withMax('documents as last_applicant_document_at', 'created_at')
            ->newestFirst()
            ->get();
        $scoutingRequests = ScoutingRequest::query()
            ->with(['entity', 'submittedBy', 'reviewedBy'])
            ->withMax([
                'statusHistory as last_clarification_at' => fn ($builder) => $builder->where('status', 'needs_clarification'),
            ], 'happened_at')
            ->withMax([
                'correspondences as last_applicant_correspondence_at' => fn ($builder) => $builder->where('sender_type', 'applicant'),
            ], 'created_at')
            ->newestFirst()
            ->get();
        $registrationEntities = Entity::query()
            ->with(['group', 'users'])
            ->whereNotNull('registration_type')
            ->latest()
            ->get();
        $reviewQueue = $registrationEntities
            ->filter(fn (Entity $registrationEntity): bool => $registrationEntity->isRegistrationReviewable())
            ->whereIn('status', ['pending_review', 'needs_completion', 'rejected'])
            ->values();
        $registrationsByType = collect(['student', 'company', 'ngo', 'school'])
            ->mapWithKeys(fn (string $type): array => [
                $type => $registrationEntities
                    ->filter(fn (Entity $registrationEntity): bool => $registrationEntity->registration_type === $type)
                    ->values(),
            ]);

        $availableComparisonYears = collect($applications
            ->map(fn (FilmApplication $application): ?int => $application->created_at?->year)
            ->filter()
            ->all())
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();
        $requestedComparisonYear = $request?->integer('year') ?: now()->year;
        $comparisonYear = $availableComparisonYears->contains($requestedComparisonYear)
            ? $requestedComparisonYear
            : now()->year;
        $previousComparisonYear = $comparisonYear - 1;
        $monthBuckets = collect(range(1, 12))
            ->map(fn (int $month) => now()->copy()->setDate($comparisonYear, $month, 1)->startOfMonth());
        $countApplicationsForMonth = static function (int $year, int $month) use ($applications): int {
            return $applications->filter(
                fn (FilmApplication $application): bool => (int) optional($application->created_at)->format('Y') === $year
                    && (int) optional($application->created_at)->format('n') === $month
            )->count();
        };
        $monthlyCurrentYearCounts = $monthBuckets
            ->map(fn ($month): int => $countApplicationsForMonth($comparisonYear, (int) $month->format('n')))
            ->values();
        $monthlyPreviousYearCounts = $monthBuckets
            ->map(fn ($month): int => $countApplicationsForMonth($previousComparisonYear, (int) $month->format('n')))
            ->values();
        $monthlyApplications = $monthBuckets
            ->map(fn ($month, int $index): array => [
                'label' => $month->translatedFormat('M'),
                'count' => $monthlyCurrentYearCounts[$index],
            ])
            ->values();
        $monthlyCurrentTotal = $monthlyCurrentYearCounts->sum();
        $monthlyPreviousTotal = $monthlyPreviousYearCounts->sum();
        $monthlyDelta = $monthlyCurrentTotal - $monthlyPreviousTotal;
        $monthlyDeltaPercent = $monthlyPreviousTotal > 0
            ? round(($monthlyDelta / $monthlyPreviousTotal) * 100, 1)
            : ($monthlyCurrentTotal > 0 ? 100.0 : 0.0);
        $monthlyApplicationComparison = [
            'year' => $comparisonYear,
            'previous_year' => $previousComparisonYear,
            'labels' => $monthBuckets->map(fn ($month): string => $month->translatedFormat('M'))->values(),
            'current' => $monthlyCurrentYearCounts,
            'previous' => $monthlyPreviousYearCounts,
            'current_total' => $monthlyCurrentTotal,
            'previous_total' => $monthlyPreviousTotal,
            'delta' => $monthlyDelta,
            'delta_percent' => $monthlyDeltaPercent,
        ];
        $applicationsByCategory = $applications
            ->filter(fn (FilmApplication $application): bool => filled($application->work_category))
            ->groupBy(fn (FilmApplication $application): string => (string) $application->work_category)
            ->map(fn ($group) => $group->count())
            ->sortDesc();
        $applicationsByReleaseMethod = $applications
            ->filter(fn (FilmApplication $application): bool => filled($application->release_method))
            ->groupBy(fn (FilmApplication $application): string => (string) $application->release_method)
            ->map(fn ($group) => $group->count())
            ->sortDesc();
        $approvalDurationByAuthority = ApplicationAuthorityApproval::query()
            ->with('application')
            ->whereNotNull('decided_at')
            ->latest('decided_at')
            ->get()
            ->groupBy('authority_code')
            ->map(function ($approvals, string $authorityCode): ?array {
                $durations = $approvals
                    ->map(function (ApplicationAuthorityApproval $approval): ?float {
                        $startedAt = $approval->application?->submitted_at ?? $approval->application?->created_at;

                        if (! $startedAt || ! $approval->decided_at) {
                            return null;
                        }

                        return round($startedAt->diffInHours($approval->decided_at), 1);
                    })
                    ->filter(fn (?float $value): bool => $value !== null);

                if ($durations->isEmpty()) {
                    return null;
                }

                return [
                    'code' => $authorityCode,
                    'average_hours' => round($durations->avg(), 1),
                ];
            })
            ->filter()
            ->sortByDesc('average_hours')
            ->take(6)
            ->values();
        $applicationWorkflowCounts = $applications
            ->map(fn (FilmApplication $application): string => AdminWorkflowState::applicationCheckpoint($application)['key'])
            ->countBy();
        $scoutingWorkflowCounts = $scoutingRequests
            ->map(fn (ScoutingRequest $requestRecord): string => AdminWorkflowState::scoutingCheckpoint($requestRecord)['key'])
            ->countBy();
        $workflowQueue = collect($applications
            ->map(function (FilmApplication $application): array {
                $checkpoint = AdminWorkflowState::applicationCheckpoint($application);
                $applicantResponse = AdminApplicantResponseState::application($application);

                return [
                    'type' => __('app.dashboard.production_request_type'),
                    'code' => $application->code,
                    'project_name' => $application->project_name,
                    'entity' => $application->entity?->displayName() ?? __('app.dashboard.not_available'),
                    'checkpoint' => $checkpoint,
                    'applicant_response' => $applicantResponse,
                    'updated_at' => $application->reviewed_at ?? $application->updated_at ?? $application->submitted_at ?? $application->created_at,
                    'sort_id' => $application->getKey(),
                    'url' => route('admin.applications.show', $application),
                    'status_label' => $application->localizedStatus(),
                ];
            })
            ->all())
            ->merge($scoutingRequests->map(function (ScoutingRequest $requestRecord): array {
                $checkpoint = AdminWorkflowState::scoutingCheckpoint($requestRecord);
                $applicantResponse = AdminApplicantResponseState::scouting($requestRecord);

                return [
                    'type' => __('app.dashboard.scouting_request_type'),
                    'code' => $requestRecord->code,
                    'project_name' => $requestRecord->project_name,
                    'entity' => $requestRecord->entity?->displayName() ?? __('app.dashboard.not_available'),
                    'checkpoint' => $checkpoint,
                    'applicant_response' => $applicantResponse,
                    'updated_at' => $requestRecord->reviewed_at ?? $requestRecord->updated_at ?? $requestRecord->submitted_at ?? $requestRecord->created_at,
                    'sort_id' => $requestRecord->getKey(),
                    'url' => route('admin.scouting-requests.show', $requestRecord),
                    'status_label' => $requestRecord->localizedStatus(),
                ];
            })->all())
            ->where(fn (array $item): bool => ! in_array($item['checkpoint']['key'], ['resolved', 'draft'], true))
            ->sortByDesc(fn (array $item): int => ($item['applicant_response']['active'] ? 10_000_000_000_000_000 : 0) + (($item['updated_at']?->timestamp ?? 0) * 1_000_000) + (int) $item['sort_id'])
            ->take(8)
            ->values();

        return [
            'reviewQueue' => $reviewQueue,
            'workflowQueue' => $workflowQueue,
            'registrationsByType' => $registrationsByType,
            'groups' => Group::query()
                ->with('roles')
                ->withCount('entities')
                ->orderBy('id')
                ->get(),
            'recentUsers' => User::query()
                ->with(['entities.group'])
                ->latest()
                ->take(6)
                ->get(),
            'recentEntities' => Entity::query()
                ->with('group')
                ->withCount('users')
                ->latest()
                ->take(6)
                ->get(),
            'recentApplications' => $applications->take(6)->values(),
            'recentPermits' => Permit::query()
                ->with(['application', 'entity'])
                ->latest('issued_at')
                ->take(6)
                ->get(),
            'chartData' => [
                'applications_by_category' => $applicationsByCategory,
                'applications_by_release_method' => $applicationsByReleaseMethod,
                'monthly_applications' => $monthlyApplications,
                'monthly_application_comparison' => $monthlyApplicationComparison,
                'approval_duration_by_authority' => $approvalDurationByAuthority,
                'registrations_by_type' => $registrationsByType->map(fn ($rows) => $rows->count()),
            ],
            'dashboardComparisonYears' => $availableComparisonYears,
            'productionOperationalReport' => $productionOperationalReport,
            'dashboardFilters' => $productionOperationalReport['filters'],
            'dashboardFilterOptions' => $productionOperationalReport['options'],
            'stats' => [
                'groups' => Group::query()->count(),
                'entities' => Entity::query()->count(),
                'users' => User::query()->count(),
                'applications' => $applications->count(),
                'permits' => Permit::query()->count(),
                'active_users' => User::query()->where('status', 'active')->count(),
                'registrations' => $registrationEntities->count(),
                'individuals' => $registrationEntities->where('registration_type', 'student')->count(),
                'organizations' => $registrationEntities->whereIn('registration_type', ['company', 'ngo', 'school'])->count(),
                'pending_reviews' => $reviewQueue->where('status', 'pending_review')->count(),
                'active_entities' => Entity::query()->where('status', 'active')->count(),
                'approved_applications' => $applications->where('status', 'approved')->count(),
                'workflow_waiting_authorities' => $applicationWorkflowCounts->get('waiting_authorities', 0),
                'workflow_waiting_applicant' => $applicationWorkflowCounts->get('waiting_on_applicant', 0) + $scoutingWorkflowCounts->get('waiting_on_applicant', 0),
                'workflow_ready_final_decision' => $applicationWorkflowCounts->get('ready_final_decision', 0),
                'workflow_needs_admin_review' => $applicationWorkflowCounts->get('needs_admin_review', 0) + $scoutingWorkflowCounts->get('needs_admin_review', 0),
            ],
        ];
    }
}
