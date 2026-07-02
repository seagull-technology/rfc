<?php

namespace App\Services\Reports;

use App\Models\Application;
use App\Models\ApplicationAuthorityApproval;
use App\Models\Entity;
use App\Models\FilmingLocationType;
use App\Models\FormLookupOption;
use App\Models\Governorate;
use App\Models\ReleaseMethod;
use App\Models\WorkCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductionAnalyticsService
{
    /**
     * @return array<int, array{key:string,label:string}>
     */
    public function exportDatasets(): array
    {
        return [
            ['key' => 'summary', 'label' => __('app.reports.exports.summary')],
            ['key' => 'all', 'label' => __('app.reports.exports.all')],
            ['key' => 'applications', 'label' => __('app.reports.exports.applications')],
            ['key' => 'locations', 'label' => __('app.reports.exports.locations')],
            ['key' => 'crew', 'label' => __('app.reports.exports.crew')],
            ['key' => 'equipment', 'label' => __('app.reports.exports.equipment')],
            ['key' => 'approvals', 'label' => __('app.reports.exports.approvals')],
            ['key' => 'spend', 'label' => __('app.reports.exports.spend')],
            ['key' => 'cross_analysis', 'label' => __('app.reports.exports.cross_analysis')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filtersFromRequest(Request $request): array
    {
        return $this->normalizeFilters($request->only([
            'q',
            'date_from',
            'date_to',
            'status',
            'production_type',
            'production_scope',
            'governorate',
            'location_type',
            'approval_status',
            'approval_entity',
            'equipment_category',
            'gender',
        ]));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $today = now()->startOfDay();
        $applications = $this->filteredApplications($filters);
        $allLocationFacts = $applications->flatMap(fn (Application $application): Collection => $this->locationFactsFor($application, $today))->values();
        $locationFacts = $this->filterLocationFacts(
            $allLocationFacts,
            $filters,
        );
        $crewFacts = $this->filterCrewFacts(
            $applications->flatMap(fn (Application $application): Collection => $this->crewFactsFor($application))->values(),
            $filters,
        );
        $equipmentFacts = $this->filterEquipmentFacts(
            $applications->flatMap(fn (Application $application): Collection => $this->equipmentFactsFor($application))->values(),
            $filters,
        );
        $approvalFacts = $this->filterApprovalFacts(
            $applications->flatMap(fn (Application $application): Collection => $this->approvalFactsFor($application))->values(),
            $filters,
        );
        $spendFacts = $this->filterSpendFacts(
            $this->spendFacts($applications, $allLocationFacts),
            $filters,
        );
        $dashboardLocationFacts = $locationFacts
            ->whereIn('timing', ['active', 'future'])
            ->values();

        return [
            'filters' => $filters,
            'options' => $this->filterOptions(),
            'kpis' => $this->kpis($applications, $approvalFacts, $locationFacts, $today),
            'charts' => [
                'production_types' => $this->productionTypeChart($applications),
                'production_scope' => $this->productionScopeChart($applications),
                'spend_by_production_type' => $this->spendByProductionTypeChart($spendFacts),
                'spend_by_governorate' => $this->spendByGovernorateChart($spendFacts),
                'activity_by_governorate' => $this->activityByGovernorateChart($locationFacts),
                'crew_scope' => $this->crewScopeChart($crewFacts),
                'crew_gender' => $this->crewGenderChart($crewFacts),
                'equipment_categories' => $this->equipmentCategoryChart($equipmentFacts),
                'locations_by_type' => $this->locationsByTypeChart($locationFacts),
            ],
            'map' => $this->governorateActivityMap($dashboardLocationFacts, $spendFacts),
            'tables' => [
                'applications' => $this->applicationTableRows($applications)->take(15)->values(),
                'locations' => $locationFacts->take(15)->values(),
                'approvals' => $approvalFacts->take(15)->values(),
            ],
            'cross_analysis' => $this->crossAnalysis(
                applications: $applications,
                locationFacts: $locationFacts,
                crewFacts: $crewFacts,
                equipmentFacts: $equipmentFacts,
                spendFacts: $spendFacts,
            ),
            'facts' => [
                'applications' => $this->applicationTableRows($applications),
                'locations' => $locationFacts,
                'crew' => $crewFacts,
                'equipment' => $equipmentFacts,
                'approvals' => $approvalFacts,
                'spend' => $spendFacts,
            ],
            'export_datasets' => $this->exportDatasets(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{filename:string,headers:array<int, string>,rows:iterable<int, array<int, mixed>>}
     */
    public function export(string $dataset, array $filters = []): array
    {
        $report = $this->build($filters);
        $dataset = collect($this->exportDatasets())->pluck('key')->contains($dataset) ? $dataset : 'summary';
        $stamp = now()->format('Ymd-His');

        return match ($dataset) {
            'applications' => [
                'filename' => "production-applications-{$stamp}.csv",
                'headers' => [
                    __('app.reports.columns.application'),
                    __('app.reports.columns.project'),
                    __('app.reports.columns.entity'),
                    __('app.reports.columns.status'),
                    __('app.reports.columns.production_type'),
                    __('app.reports.columns.production_scope'),
                    __('app.reports.columns.start_date'),
                    __('app.reports.columns.end_date'),
                    __('app.reports.columns.estimated_budget'),
                    __('app.reports.columns.local_spend'),
                ],
                'rows' => $report['facts']['applications']->map(fn (array $row): array => [
                    $row['code'],
                    $row['project_name'],
                    $row['entity'],
                    $row['status'],
                    $row['production_type'],
                    $row['production_scope'],
                    $row['start_date'],
                    $row['end_date'],
                    $row['estimated_budget'],
                    $row['local_spend'],
                ]),
            ],
            'locations' => [
                'filename' => "production-locations-{$stamp}.csv",
                'headers' => [
                    __('app.reports.columns.application'),
                    __('app.reports.columns.project'),
                    __('app.reports.columns.governorate'),
                    __('app.reports.columns.location_type'),
                    __('app.reports.columns.location_name'),
                    __('app.reports.columns.location_status'),
                    __('app.reports.columns.start_date'),
                    __('app.reports.columns.end_date'),
                ],
                'rows' => $report['facts']['locations']->map(fn (array $row): array => [
                    $row['code'],
                    $row['project_name'],
                    $row['governorate'],
                    $row['location_type'],
                    $row['location_name'],
                    $row['timing_label'],
                    $row['start_date'],
                    $row['end_date'],
                ]),
            ],
            'crew' => [
                'filename' => "production-crew-{$stamp}.csv",
                'headers' => [
                    __('app.reports.columns.application'),
                    __('app.reports.columns.project'),
                    __('app.reports.columns.crew_name'),
                    __('app.reports.columns.role'),
                    __('app.reports.columns.nationality'),
                    __('app.reports.columns.production_scope'),
                    __('app.reports.columns.gender'),
                    __('app.reports.columns.count'),
                ],
                'rows' => $report['facts']['crew']->map(fn (array $row): array => [
                    $row['code'],
                    $row['project_name'],
                    $row['name'],
                    $row['role'],
                    $row['nationality'],
                    $row['scope_label'],
                    $row['gender_label'],
                    $row['count'],
                ]),
            ],
            'equipment' => [
                'filename' => "production-equipment-{$stamp}.csv",
                'headers' => [
                    __('app.reports.columns.application'),
                    __('app.reports.columns.project'),
                    __('app.reports.columns.equipment_item'),
                    __('app.reports.columns.equipment_category'),
                    __('app.reports.columns.quantity'),
                    __('app.reports.columns.total_value'),
                    __('app.reports.columns.source'),
                ],
                'rows' => $report['facts']['equipment']->map(fn (array $row): array => [
                    $row['code'],
                    $row['project_name'],
                    $row['item'],
                    $row['category_label'],
                    $row['quantity'],
                    $row['total_value'],
                    $row['source_label'],
                ]),
            ],
            'approvals' => [
                'filename' => "production-approvals-{$stamp}.csv",
                'headers' => [
                    __('app.reports.columns.application'),
                    __('app.reports.columns.project'),
                    __('app.reports.columns.approval_entity'),
                    __('app.reports.columns.approval_status'),
                    __('app.reports.columns.decided_at'),
                    __('app.reports.columns.response_hours'),
                ],
                'rows' => $report['facts']['approvals']->map(fn (array $row): array => [
                    $row['code'],
                    $row['project_name'],
                    $row['authority'],
                    $row['status_label'],
                    $row['decided_at'],
                    $row['response_hours'],
                ]),
            ],
            'spend' => [
                'filename' => "production-spend-{$stamp}.csv",
                'headers' => [
                    __('app.reports.columns.application'),
                    __('app.reports.columns.project'),
                    __('app.reports.columns.production_type'),
                    __('app.reports.columns.governorate'),
                    __('app.reports.columns.local_spend'),
                    __('app.reports.columns.spend_source'),
                ],
                'rows' => $report['facts']['spend']->map(fn (array $row): array => [
                    $row['code'],
                    $row['project_name'],
                    $row['production_type'],
                    $row['governorate'],
                    $row['allocated_spend'],
                    $row['source_label'],
                ]),
            ],
            'cross_analysis' => [
                'filename' => "production-cross-analysis-{$stamp}.csv",
                'headers' => [
                    __('app.reports.columns.matrix'),
                    __('app.reports.columns.row_dimension'),
                    __('app.reports.columns.row'),
                    __('app.reports.columns.column'),
                    __('app.reports.columns.metric'),
                    __('app.reports.columns.value'),
                ],
                'rows' => $this->crossAnalysisRows($report),
            ],
            'all' => [
                'filename' => "production-all-analytics-{$stamp}.csv",
                'headers' => [
                    __('app.reports.columns.dataset'),
                    __('app.reports.columns.application'),
                    __('app.reports.columns.project'),
                    __('app.reports.columns.dimension'),
                    __('app.reports.columns.detail'),
                    __('app.reports.columns.metric'),
                    __('app.reports.columns.value'),
                    __('app.reports.columns.status'),
                    __('app.reports.columns.entity'),
                    __('app.reports.columns.start_date'),
                    __('app.reports.columns.end_date'),
                    __('app.reports.columns.source'),
                ],
                'rows' => $this->allDataRows($report),
            ],
            default => [
                'filename' => "production-report-summary-{$stamp}.csv",
                'headers' => [
                    __('app.reports.columns.section'),
                    __('app.reports.columns.label'),
                    __('app.reports.columns.value'),
                ],
                'rows' => $this->summaryRows($report),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Application>
     */
    private function filteredApplications(array $filters): Collection
    {
        return Application::query()
            ->with(['entity', 'submittedBy', 'authorityApprovals.entity', 'wrapReport'])
            ->newestFirst()
            ->get()
            ->filter(fn (Application $application): bool => $this->applicationMatchesFilters($application, $filters))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $facts
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filterLocationFacts(Collection $facts, array $filters): Collection
    {
        return $facts
            ->filter(function (array $row) use ($filters): bool {
                if ($filters['governorate'] !== 'all' && $row['governorate_code'] !== $filters['governorate']) {
                    return false;
                }

                if ($filters['location_type'] !== 'all' && $row['location_type_code'] !== $filters['location_type']) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $facts
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filterCrewFacts(Collection $facts, array $filters): Collection
    {
        return $facts
            ->filter(fn (array $row): bool => $filters['gender'] === 'all' || $row['gender'] === $filters['gender'])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $facts
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filterEquipmentFacts(Collection $facts, array $filters): Collection
    {
        return $facts
            ->filter(fn (array $row): bool => $filters['equipment_category'] === 'all' || $row['category'] === $filters['equipment_category'])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $facts
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filterApprovalFacts(Collection $facts, array $filters): Collection
    {
        return $facts
            ->filter(function (array $row) use ($filters): bool {
                if ($filters['approval_status'] !== 'all' && $row['status'] !== $filters['approval_status']) {
                    return false;
                }

                if ($filters['approval_entity'] !== 'all' && (string) $row['entity_id'] !== $filters['approval_entity']) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $facts
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filterSpendFacts(Collection $facts, array $filters): Collection
    {
        return $facts
            ->filter(fn (array $row): bool => $filters['governorate'] === 'all' || $row['governorate_code'] === $filters['governorate'])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        $approvalEntities = ApplicationAuthorityApproval::query()
            ->with('entity')
            ->whereNotNull('entity_id')
            ->get()
            ->pluck('entity')
            ->filter()
            ->unique('id')
            ->sortBy(fn (Entity $entity): string => $entity->displayName())
            ->map(fn (Entity $entity): array => [
                'value' => (string) $entity->getKey(),
                'label' => $entity->displayName(),
            ])
            ->values();

        $equipmentCategories = FormLookupOption::activeForType(FormLookupOption::TYPE_EQUIPMENT_CATEGORY)
            ->map(fn (FormLookupOption $category): array => [
                'value' => $category->code,
                'label' => $category->displayName(),
            ])
            ->values();

        return [
            'statuses' => collect(['all', 'draft', 'submitted', 'under_review', 'needs_clarification', 'approved', 'rejected'])
                ->map(fn (string $status): array => [
                    'value' => $status,
                    'label' => $status === 'all' ? __('app.admin.filters.all_option') : __('app.statuses.'.$status),
                ])
                ->values(),
            'production_types' => WorkCategory::query()
                ->ordered()
                ->get()
                ->map(fn (WorkCategory $category): array => [
                    'value' => $category->code,
                    'label' => $category->displayName(),
                ])
                ->values(),
            'governorates' => Governorate::query()
                ->ordered()
                ->get()
                ->map(fn (Governorate $governorate): array => [
                    'value' => $governorate->code,
                    'label' => $governorate->displayName(),
                ])
                ->values(),
            'location_types' => FilmingLocationType::query()
                ->ordered()
                ->get()
                ->map(fn (FilmingLocationType $locationType): array => [
                    'value' => $locationType->code,
                    'label' => $locationType->displayName(),
                ])
                ->values(),
            'approval_statuses' => collect(['pending', 'in_review', 'approved', 'rejected'])
                ->map(fn (string $status): array => [
                    'value' => $status,
                    'label' => __('app.approvals.statuses.'.$status),
                ])
                ->values(),
            'approval_entities' => $approvalEntities,
            'equipment_categories' => $equipmentCategories,
            'genders' => collect(['male', 'female'])
                ->map(fn (string $gender): array => [
                    'value' => $gender,
                    'label' => __('app.auth.gender_options.'.$gender),
                ])
                ->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $input): array
    {
        $allowedStatus = ['all', 'draft', 'submitted', 'under_review', 'needs_clarification', 'approved', 'rejected'];
        $allowedScope = ['all', 'local', 'foreign'];
        $allowedApprovalStatus = ['all', 'pending', 'in_review', 'approved', 'rejected'];
        $allowedGender = ['all', 'male', 'female'];

        return [
            'q' => trim((string) ($input['q'] ?? '')),
            'date_from' => $this->dateString($input['date_from'] ?? null),
            'date_to' => $this->dateString($input['date_to'] ?? null),
            'status' => in_array(($input['status'] ?? 'all'), $allowedStatus, true) ? (string) ($input['status'] ?? 'all') : 'all',
            'production_type' => filled($input['production_type'] ?? null) ? (string) $input['production_type'] : 'all',
            'production_scope' => in_array(($input['production_scope'] ?? 'all'), $allowedScope, true) ? (string) ($input['production_scope'] ?? 'all') : 'all',
            'governorate' => filled($input['governorate'] ?? null) ? (string) $input['governorate'] : 'all',
            'location_type' => filled($input['location_type'] ?? null) ? (string) $input['location_type'] : 'all',
            'approval_status' => in_array(($input['approval_status'] ?? 'all'), $allowedApprovalStatus, true) ? (string) ($input['approval_status'] ?? 'all') : 'all',
            'approval_entity' => filled($input['approval_entity'] ?? null) ? (string) $input['approval_entity'] : 'all',
            'equipment_category' => filled($input['equipment_category'] ?? null) ? (string) $input['equipment_category'] : 'all',
            'gender' => in_array(($input['gender'] ?? 'all'), $allowedGender, true) ? (string) ($input['gender'] ?? 'all') : 'all',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applicationMatchesFilters(Application $application, array $filters): bool
    {
        if ($filters['q'] !== '') {
            $needle = Str::lower($filters['q']);
            $haystack = Str::lower(implode(' ', [
                $application->code,
                $application->project_name,
                $application->entity?->displayName(),
                $application->submittedBy?->displayName(),
            ]));

            if (! Str::contains($haystack, $needle)) {
                return false;
            }
        }

        if ($filters['status'] !== 'all' && $application->status !== $filters['status']) {
            return false;
        }

        if ($filters['production_type'] !== 'all' && ! in_array($filters['production_type'], $this->productionTypeCodes($application), true)) {
            return false;
        }

        if ($filters['production_scope'] !== 'all' && $this->productionScope($application) !== $filters['production_scope']) {
            return false;
        }

        $locations = $this->locationFactsFor($application, now()->startOfDay());

        if ($filters['governorate'] !== 'all' && ! $locations->contains(fn (array $row): bool => $row['governorate_code'] === $filters['governorate'])) {
            return false;
        }

        if ($filters['location_type'] !== 'all' && ! $locations->contains(fn (array $row): bool => $row['location_type_code'] === $filters['location_type'])) {
            return false;
        }

        if ($filters['approval_status'] !== 'all' && ! $application->authorityApprovals->contains(fn (ApplicationAuthorityApproval $approval): bool => $approval->status === $filters['approval_status'])) {
            return false;
        }

        if ($filters['approval_entity'] !== 'all' && ! $application->authorityApprovals->contains(fn (ApplicationAuthorityApproval $approval): bool => (string) $approval->entity_id === $filters['approval_entity'])) {
            return false;
        }

        if ($filters['equipment_category'] !== 'all' && ! $this->equipmentFactsFor($application)->contains(fn (array $row): bool => $row['category'] === $filters['equipment_category'])) {
            return false;
        }

        if ($filters['gender'] !== 'all' && ! $this->crewFactsFor($application)->contains(fn (array $row): bool => $row['gender'] === $filters['gender'])) {
            return false;
        }

        return $this->applicationOverlapsDateFilter($application, $filters['date_from'], $filters['date_to']);
    }

    private function applicationOverlapsDateFilter(Application $application, ?string $from, ?string $to): bool
    {
        if (! $from && ! $to) {
            return true;
        }

        $range = $this->applicationDateRange($application);
        $start = $range['start'];
        $end = $range['end'];
        $fromDate = $this->parseDate($from);
        $toDate = $this->parseDate($to);

        if (! $start && ! $end) {
            return false;
        }

        $start ??= $end;
        $end ??= $start;

        if ($fromDate && $end->lt($fromDate)) {
            return false;
        }

        if ($toDate && $start->gt($toDate)) {
            return false;
        }

        return true;
    }

    /**
     * @return array{start:?Carbon,end:?Carbon}
     */
    private function applicationDateRange(Application $application): array
    {
        $dates = collect([
            $application->planned_start_date,
            $application->planned_end_date,
        ]);

        foreach ((array) data_get($application->metadata ?? [], 'schedule.phases', []) as $phase) {
            $dates->push($this->parseDate(data_get($phase, 'start_date')));
            $dates->push($this->parseDate(data_get($phase, 'end_date')));
        }

        foreach ((array) data_get($application->metadata ?? [], 'annex.filming_locations', []) as $location) {
            $dates->push($this->parseDate(data_get($location, 'start_date')));
            $dates->push($this->parseDate(data_get($location, 'end_date')));
        }

        $dates = $dates->filter(fn ($date): bool => $date instanceof Carbon)->values();

        return [
            'start' => $dates->min(),
            'end' => $dates->max(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function locationFactsFor(Application $application, Carbon $today): Collection
    {
        $annex = data_get($application->metadata ?? [], 'annex', []);
        $rows = collect();

        foreach ((array) data_get($annex, 'filming_locations', []) as $location) {
            $rows->push($this->locationFact($application, (array) $location, $today, 'filming_locations'));
        }

        foreach ((array) data_get($annex, 'military_border_locations', []) as $location) {
            $rows->push($this->locationFact($application, (array) $location, $today, 'military_border_locations'));
        }

        return $rows
            ->filter(fn (?array $row): bool => $row !== null)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $location
     * @return array<string, mixed>|null
     */
    private function locationFact(Application $application, array $location, Carbon $today, string $source): ?array
    {
        $governorateCode = (string) data_get($location, 'governorate', '');
        $locationTypeCode = (string) data_get($location, 'location_type', '');
        $locationName = (string) data_get($location, 'location_name', '');

        if (blank($governorateCode) && blank($locationTypeCode) && blank($locationName)) {
            return null;
        }

        $start = $this->parseDate(data_get($location, 'start_date')) ?: $application->planned_start_date;
        $end = $this->parseDate(data_get($location, 'end_date')) ?: $application->planned_end_date ?: $start;
        $timing = $this->timingBucket($start, $end, $today);

        return [
            'application_id' => $application->getKey(),
            'code' => $application->code,
            'project_name' => $application->project_name,
            'url' => route('admin.applications.show', $application),
            'production_type_code' => $this->primaryProductionTypeCode($application),
            'production_type' => $this->productionTypeLabel($application),
            'production_scope_code' => $this->productionScope($application),
            'production_scope' => $this->productionScopeLabel($application),
            'release_method' => filled($application->release_method) ? ReleaseMethod::labelFor((string) $application->release_method) : __('app.dashboard.not_available'),
            'governorate_code' => $governorateCode,
            'governorate' => Governorate::labelFor($governorateCode),
            'location_type_code' => $locationTypeCode,
            'location_type' => FilmingLocationType::labelFor($locationTypeCode),
            'location_name' => filled($locationName) ? $locationName : __('app.dashboard.not_available'),
            'nature' => data_get($location, 'nature') ?: data_get($location, 'address') ?: __('app.dashboard.not_available'),
            'start_date' => $start?->format('Y-m-d') ?? '',
            'end_date' => $end?->format('Y-m-d') ?? '',
            'days' => $this->inclusiveDays($start, $end),
            'timing' => $timing,
            'timing_label' => __('app.reports.location_timing.'.$timing),
            'source' => $source,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function crewFactsFor(Application $application): Collection
    {
        $rows = collect((array) data_get($application->metadata ?? [], 'annex.cast_crew', []))
            ->map(function (array $member) use ($application): ?array {
                if (! collect($member)->filter(fn ($value): bool => filled($value))->isNotEmpty()) {
                    return null;
                }

                $nationality = (string) data_get($member, 'nationality', '');
                $scope = $this->crewScopeFromNationality($nationality);
                $gender = (string) data_get($member, 'gender', '');

                return [
                    'application_id' => $application->getKey(),
                    'code' => $application->code,
                    'project_name' => $application->project_name,
                    'url' => route('admin.applications.show', $application),
                    'name' => data_get($member, 'name') ?: __('app.dashboard.not_available'),
                    'role' => data_get($member, 'role') ?: __('app.dashboard.not_available'),
                    'nationality' => filled($nationality) ? Str::of($nationality)->replace('_', ' ')->headline()->toString() : __('app.dashboard.not_available'),
                    'scope' => $scope,
                    'scope_label' => __('app.reports.production_scope.'.$scope),
                    'gender' => in_array($gender, ['male', 'female'], true) ? $gender : null,
                    'gender_label' => in_array($gender, ['male', 'female'], true) ? __('app.auth.gender_options.'.$gender) : __('app.dashboard.not_available'),
                    'count' => 1,
                    'source' => 'annex',
                ];
            })
            ->filter()
            ->values();

        if ($rows->isNotEmpty()) {
            return $rows;
        }

        $payload = $application->wrapReport?->payload ?? [];
        $localCrew = (int) data_get($payload, 'local_crew_count', 0);
        $foreignCrew = (int) data_get($payload, 'foreign_crew_count', 0);

        return collect([
            $localCrew > 0 ? $this->aggregateCrewFact($application, 'local', $localCrew) : null,
            $foreignCrew > 0 ? $this->aggregateCrewFact($application, 'foreign', $foreignCrew) : null,
        ])->filter()->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregateCrewFact(Application $application, string $scope, int $count): array
    {
        return [
            'application_id' => $application->getKey(),
            'code' => $application->code,
            'project_name' => $application->project_name,
            'url' => route('admin.applications.show', $application),
            'name' => __('app.reports.aggregate_row'),
            'role' => __('app.reports.aggregate_row'),
            'nationality' => __('app.reports.production_scope.'.$scope),
            'scope' => $scope,
            'scope_label' => __('app.reports.production_scope.'.$scope),
            'gender' => null,
            'gender_label' => __('app.dashboard.not_available'),
            'count' => $count,
            'source' => 'wrap_report',
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function equipmentFactsFor(Application $application): Collection
    {
        $annex = data_get($application->metadata ?? [], 'annex', []);
        $rows = collect();

        foreach ((array) data_get($annex, 'imported_equipment', []) as $equipment) {
            $rows->push($this->equipmentFact($application, (array) $equipment, 'imported_equipment'));
        }

        foreach ((array) data_get($annex, 'military_border_equipment', []) as $equipment) {
            $rows->push($this->equipmentFact($application, (array) $equipment, 'military_border_equipment'));
        }

        return $rows->filter()->values();
    }

    /**
     * @param  array<string, mixed>  $equipment
     * @return array<string, mixed>|null
     */
    private function equipmentFact(Application $application, array $equipment, string $source): ?array
    {
        if (! collect($equipment)->filter(fn ($value): bool => filled($value))->isNotEmpty()) {
            return null;
        }

        $category = $this->normalizeEquipmentCategory(
            data_get($equipment, 'classification')
                ?: data_get($equipment, 'transport_group')
                ?: ($source === 'military_border_equipment' ? 'military' : 'other')
        );

        return [
            'application_id' => $application->getKey(),
            'code' => $application->code,
            'project_name' => $application->project_name,
            'url' => route('admin.applications.show', $application),
            'production_type_code' => $this->primaryProductionTypeCode($application),
            'production_type' => $this->productionTypeLabel($application),
            'production_scope' => $this->productionScopeLabel($application),
            'item' => data_get($equipment, 'item') ?: data_get($equipment, 'equipment') ?: __('app.dashboard.not_available'),
            'category' => $category,
            'category_label' => $this->equipmentCategoryLabel($category),
            'quantity' => (int) (data_get($equipment, 'quantity') ?: 1),
            'total_value' => (float) (data_get($equipment, 'total_value') ?: 0),
            'source' => $source,
            'source_label' => __('app.reports.equipment_sources.'.$source),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function approvalFactsFor(Application $application): Collection
    {
        return $application->authorityApprovals
            ->map(function (ApplicationAuthorityApproval $approval) use ($application): array {
                $startedAt = $application->submitted_at ?? $approval->created_at ?? $application->created_at;
                $responseHours = ($startedAt && $approval->decided_at)
                    ? round($startedAt->diffInHours($approval->decided_at), 1)
                    : null;

                return [
                    'application_id' => $application->getKey(),
                    'code' => $application->code,
                    'project_name' => $application->project_name,
                    'url' => route('admin.applications.show', $application),
                    'authority' => $approval->localizedAuthority(),
                    'authority_code' => $approval->authority_code,
                    'entity_id' => $approval->entity_id,
                    'status' => $approval->status,
                    'status_label' => $approval->localizedStatus(),
                    'created_at' => $approval->created_at?->format('Y-m-d H:i') ?? '',
                    'decided_at' => $approval->decided_at?->format('Y-m-d H:i') ?? '',
                    'response_hours' => $responseHours,
                ];
            })
            ->sortByDesc(fn (array $row): string => $row['created_at'])
            ->values();
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @param  Collection<int, array<string, mixed>>  $locationFacts
     * @return Collection<int, array<string, mixed>>
     */
    private function spendFacts(Collection $applications, Collection $locationFacts): Collection
    {
        return $applications
            ->flatMap(function (Application $application) use ($locationFacts): Collection {
                $spend = $this->applicationSpend($application);
                $governorateRows = $locationFacts
                    ->where('application_id', $application->getKey())
                    ->filter(fn (array $row): bool => filled($row['governorate_code']))
                    ->groupBy('governorate_code')
                    ->map(function (Collection $rows): array {
                        $first = $rows->first();

                        return [
                            'governorate_code' => $first['governorate_code'],
                            'governorate' => $first['governorate'],
                            'weight' => max(1, $rows->sum('days')),
                        ];
                    })
                    ->values();

                if ($governorateRows->isEmpty()) {
                    $governorateRows = collect([[
                        'governorate_code' => '',
                        'governorate' => __('app.dashboard.not_available'),
                        'weight' => 1,
                    ]]);
                }

                $totalWeight = max(1, $governorateRows->sum('weight'));

                return $governorateRows->map(fn (array $governorate): array => [
                    'application_id' => $application->getKey(),
                    'code' => $application->code,
                    'project_name' => $application->project_name,
                    'url' => route('admin.applications.show', $application),
                    'production_type_code' => $this->primaryProductionTypeCode($application),
                    'production_type' => $this->productionTypeLabel($application),
                    'production_scope' => $this->productionScopeLabel($application),
                    'governorate_code' => $governorate['governorate_code'],
                    'governorate' => $governorate['governorate'],
                    'allocated_spend' => round($spend['amount'] * ($governorate['weight'] / $totalWeight), 2),
                    'source' => $spend['source'],
                    'source_label' => __('app.reports.spend_sources.'.$spend['source']),
                ]);
            })
            ->values();
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @param  Collection<int, array<string, mixed>>  $approvalFacts
     * @param  Collection<int, array<string, mixed>>  $locationFacts
     * @return array<string, mixed>
     */
    private function kpis(Collection $applications, Collection $approvalFacts, Collection $locationFacts, Carbon $today): array
    {
        $received = $applications->filter(fn (Application $application): bool => $application->status !== 'draft')->count();
        $pending = $applications->filter(fn (Application $application): bool => in_array($application->status, ['submitted', 'under_review', 'needs_clarification'], true))->count();
        $activeShoots = $applications->filter(fn (Application $application): bool => $this->applicationIsActiveShoot($application, $today))->count();
        $pendingApprovals = $approvalFacts->whereIn('status', ['pending', 'in_review'])->count();
        $approvedApprovals = $approvalFacts->where('status', 'approved')->count();
        $rejectedApprovals = $approvalFacts->where('status', 'rejected')->count();

        return [
            'received_applications' => $received,
            'pending_applications' => $pending,
            'active_production_shoots' => $activeShoots,
            'future_locations' => $locationFacts->where('timing', 'future')->count(),
            'active_locations' => $locationFacts->where('timing', 'active')->count(),
            'approvals_tracker' => [
                'pending' => $pendingApprovals,
                'approved' => $approvedApprovals,
                'rejected' => $rejectedApprovals,
                'total' => $approvalFacts->count(),
                'average_response_hours' => $this->average($approvalFacts->pluck('response_hours')->filter()),
            ],
        ];
    }

    private function applicationIsActiveShoot(Application $application, Carbon $today): bool
    {
        if (in_array($application->status, ['draft', 'rejected'], true)) {
            return false;
        }

        $shooting = data_get($application->metadata ?? [], 'schedule.phases.shooting', []);
        $start = $this->parseDate(data_get($shooting, 'start_date')) ?: $application->planned_start_date;
        $end = $this->parseDate(data_get($shooting, 'end_date')) ?: $application->planned_end_date;

        return $start instanceof Carbon
            && $end instanceof Carbon
            && $today->betweenIncluded($start->copy()->startOfDay(), $end->copy()->startOfDay());
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return Collection<int, array<string, mixed>>
     */
    private function productionTypeChart(Collection $applications): Collection
    {
        return $applications
            ->flatMap(fn (Application $application): array => $this->productionTypeCodes($application))
            ->countBy()
            ->map(fn (int $value, string $code): array => [
                'label' => WorkCategory::labelFor($code),
                'value' => $value,
            ])
            ->sortByDesc('value')
            ->values();
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return Collection<int, array<string, mixed>>
     */
    private function productionScopeChart(Collection $applications): Collection
    {
        return $applications
            ->groupBy(fn (Application $application): string => $this->productionScope($application))
            ->map(fn (Collection $rows, string $scope): array => [
                'label' => __('app.reports.production_scope.'.$scope),
                'value' => $rows->count(),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $spendFacts
     * @return Collection<int, array<string, mixed>>
     */
    private function spendByProductionTypeChart(Collection $spendFacts): Collection
    {
        return $spendFacts
            ->groupBy('production_type')
            ->map(fn (Collection $rows, string $label): array => [
                'label' => $label,
                'value' => round($rows->sum('allocated_spend'), 2),
            ])
            ->sortByDesc('value')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $spendFacts
     * @return Collection<int, array<string, mixed>>
     */
    private function spendByGovernorateChart(Collection $spendFacts): Collection
    {
        return $spendFacts
            ->groupBy('governorate')
            ->map(fn (Collection $rows, string $label): array => [
                'label' => $label,
                'value' => round($rows->sum('allocated_spend'), 2),
            ])
            ->sortByDesc('value')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $locationFacts
     * @return Collection<int, array<string, mixed>>
     */
    private function activityByGovernorateChart(Collection $locationFacts): Collection
    {
        return $locationFacts
            ->groupBy('governorate')
            ->map(fn (Collection $rows, string $label): array => [
                'label' => $label,
                'value' => $rows->unique('application_id')->count(),
                'locations' => $rows->count(),
            ])
            ->sortByDesc('value')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $crewFacts
     * @return Collection<int, array<string, mixed>>
     */
    private function crewScopeChart(Collection $crewFacts): Collection
    {
        return $crewFacts
            ->groupBy('scope')
            ->map(fn (Collection $rows, string $scope): array => [
                'label' => __('app.reports.production_scope.'.$scope),
                'value' => $rows->sum('count'),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $crewFacts
     * @return Collection<int, array<string, mixed>>
     */
    private function crewGenderChart(Collection $crewFacts): Collection
    {
        return $crewFacts
            ->filter(fn (array $row): bool => filled($row['gender']))
            ->groupBy('gender')
            ->map(fn (Collection $rows, string $gender): array => [
                'label' => __('app.auth.gender_options.'.$gender),
                'value' => $rows->sum('count'),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $equipmentFacts
     * @return Collection<int, array<string, mixed>>
     */
    private function equipmentCategoryChart(Collection $equipmentFacts): Collection
    {
        return $equipmentFacts
            ->groupBy('category')
            ->map(fn (Collection $rows, string $category): array => [
                'label' => $this->equipmentCategoryLabel($category),
                'value' => $rows->sum('quantity'),
                'total_value' => round($rows->sum('total_value'), 2),
            ])
            ->sortByDesc('value')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $locationFacts
     * @return Collection<int, array<string, mixed>>
     */
    private function locationsByTypeChart(Collection $locationFacts): Collection
    {
        return $locationFacts
            ->groupBy('location_type')
            ->map(fn (Collection $rows, string $label): array => [
                'label' => $label,
                'value' => $rows->count(),
                'projects' => $rows->unique('application_id')->count(),
            ])
            ->sortByDesc('value')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $locationFacts
     * @param  Collection<int, array<string, mixed>>  $spendFacts
     * @return Collection<int, array<string, mixed>>
     */
    private function governorateActivityMap(Collection $locationFacts, Collection $spendFacts): Collection
    {
        return $locationFacts
            ->groupBy('governorate_code')
            ->map(function (Collection $rows, string $code) use ($spendFacts): array {
                $first = $rows->first();
                $spend = $spendFacts
                    ->where('governorate_code', $code)
                    ->sum('allocated_spend');

                return [
                    'code' => $code,
                    'label' => $first['governorate'],
                    'projects' => $rows->unique('application_id')->count(),
                    'locations' => $rows->count(),
                    'active' => $rows->where('timing', 'active')->count(),
                    'future' => $rows->where('timing', 'future')->count(),
                    'completed' => $rows->where('timing', 'completed')->count(),
                    'spend' => round($spend, 2),
                    'work_types' => $rows
                        ->groupBy('production_type')
                        ->map(fn (Collection $group, string $label): array => ['label' => $label, 'value' => $group->unique('application_id')->count()])
                        ->sortByDesc('value')
                        ->values()
                        ->all(),
                    'release_methods' => $rows
                        ->groupBy('release_method')
                        ->map(fn (Collection $group, string $label): array => ['label' => $label, 'value' => $group->unique('application_id')->count()])
                        ->sortByDesc('value')
                        ->values()
                        ->all(),
                    'production_scope' => $rows
                        ->groupBy('production_scope')
                        ->map(fn (Collection $group, string $label): array => ['label' => $label, 'value' => $group->unique('application_id')->count()])
                        ->sortByDesc('value')
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc(fn (array $row): int => ($row['active'] * 10_000) + ($row['future'] * 100) + $row['locations'])
            ->values();
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @param  Collection<int, array<string, mixed>>  $locationFacts
     * @param  Collection<int, array<string, mixed>>  $crewFacts
     * @param  Collection<int, array<string, mixed>>  $equipmentFacts
     * @param  Collection<int, array<string, mixed>>  $spendFacts
     * @return array<string, array<string, mixed>>
     */
    private function crossAnalysis(Collection $applications, Collection $locationFacts, Collection $crewFacts, Collection $equipmentFacts, Collection $spendFacts): array
    {
        $productionFacts = $applications
            ->flatMap(function (Application $application): array {
                return collect($this->productionTypeCodes($application))
                    ->map(fn (string $code): array => [
                        'application_id' => $application->getKey(),
                        'production_type_code' => $code,
                        'production_type' => WorkCategory::labelFor($code),
                        'production_scope_code' => $this->productionScope($application),
                        'production_scope' => $this->productionScopeLabel($application),
                    ])
                    ->all();
            })
            ->values();

        return [
            'production_type_scope' => $this->buildCrossMatrix(
                title: __('app.reports.cross_analysis.production_type_scope.title'),
                description: __('app.reports.cross_analysis.production_type_scope.description'),
                rowHeading: __('app.reports.columns.production_type'),
                valueHeading: __('app.reports.metrics.projects'),
                facts: $productionFacts,
                rowKeyField: 'production_type_code',
                rowLabelField: 'production_type',
                columnKeyField: 'production_scope_code',
                columnLabelField: 'production_scope',
                uniqueApplications: true,
            ),
            'spend_type_governorate' => $this->buildCrossMatrix(
                title: __('app.reports.cross_analysis.spend_type_governorate.title'),
                description: __('app.reports.cross_analysis.spend_type_governorate.description'),
                rowHeading: __('app.reports.columns.production_type'),
                valueHeading: __('app.reports.metrics.local_spend'),
                facts: $spendFacts,
                rowKeyField: 'production_type_code',
                rowLabelField: 'production_type',
                columnKeyField: 'governorate_code',
                columnLabelField: 'governorate',
                sumField: 'allocated_spend',
                valueFormat: 'money',
            ),
            'locations_governorate_type' => $this->buildCrossMatrix(
                title: __('app.reports.cross_analysis.locations_governorate_type.title'),
                description: __('app.reports.cross_analysis.locations_governorate_type.description'),
                rowHeading: __('app.reports.columns.governorate'),
                valueHeading: __('app.reports.metrics.locations'),
                facts: $locationFacts,
                rowKeyField: 'governorate_code',
                rowLabelField: 'governorate',
                columnKeyField: 'location_type_code',
                columnLabelField: 'location_type',
            ),
            'activity_governorate_scope' => $this->buildCrossMatrix(
                title: __('app.reports.cross_analysis.activity_governorate_scope.title'),
                description: __('app.reports.cross_analysis.activity_governorate_scope.description'),
                rowHeading: __('app.reports.columns.governorate'),
                valueHeading: __('app.reports.metrics.projects'),
                facts: $locationFacts,
                rowKeyField: 'governorate_code',
                rowLabelField: 'governorate',
                columnKeyField: 'production_scope_code',
                columnLabelField: 'production_scope',
                uniqueApplications: true,
            ),
            'crew_scope_gender' => $this->buildCrossMatrix(
                title: __('app.reports.cross_analysis.crew_scope_gender.title'),
                description: __('app.reports.cross_analysis.crew_scope_gender.description'),
                rowHeading: __('app.reports.columns.production_scope'),
                valueHeading: __('app.reports.metrics.crew_members'),
                facts: $crewFacts,
                rowKeyField: 'scope',
                rowLabelField: 'scope_label',
                columnKeyField: 'gender',
                columnLabelField: 'gender_label',
                sumField: 'count',
            ),
            'equipment_category_type' => $this->buildCrossMatrix(
                title: __('app.reports.cross_analysis.equipment_category_type.title'),
                description: __('app.reports.cross_analysis.equipment_category_type.description'),
                rowHeading: __('app.reports.columns.equipment_category'),
                valueHeading: __('app.reports.metrics.equipment_quantity'),
                facts: $equipmentFacts,
                rowKeyField: 'category',
                rowLabelField: 'category_label',
                columnKeyField: 'production_type_code',
                columnLabelField: 'production_type',
                sumField: 'quantity',
            ),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $facts
     * @return array<string, mixed>
     */
    private function buildCrossMatrix(
        string $title,
        string $description,
        string $rowHeading,
        string $valueHeading,
        Collection $facts,
        string $rowKeyField,
        string $rowLabelField,
        string $columnKeyField,
        string $columnLabelField,
        ?string $sumField = null,
        string $valueFormat = 'number',
        bool $uniqueApplications = false,
    ): array {
        $columns = $this->matrixDimensionItems($facts, $columnKeyField, $columnLabelField);
        $rowItems = $this->matrixDimensionItems($facts, $rowKeyField, $rowLabelField);

        $rows = $rowItems
            ->map(function (array $rowItem) use ($facts, $columns, $rowKeyField, $columnKeyField, $sumField, $uniqueApplications): array {
                $rowFacts = $facts->filter(fn (array $row): bool => $this->matrixKey(data_get($row, $rowKeyField)) === $rowItem['key']);
                $values = [];

                foreach ($columns as $column) {
                    $cellFacts = $rowFacts->filter(fn (array $row): bool => $this->matrixKey(data_get($row, $columnKeyField)) === $column['key']);
                    $values[$column['key']] = $this->matrixValue($cellFacts, $sumField, $uniqueApplications);
                }

                return [
                    'key' => $rowItem['key'],
                    'label' => $rowItem['label'],
                    'values' => $values,
                    'total' => $this->matrixValue($rowFacts, $sumField, $uniqueApplications),
                ];
            })
            ->filter(fn (array $row): bool => (float) $row['total'] > 0)
            ->values();

        return [
            'title' => $title,
            'description' => $description,
            'row_heading' => $rowHeading,
            'value_heading' => $valueHeading,
            'value_format' => $valueFormat,
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $facts
     * @return Collection<int, array{key:string,label:string}>
     */
    private function matrixDimensionItems(Collection $facts, string $keyField, string $labelField): Collection
    {
        return $facts
            ->map(fn (array $row): array => [
                'key' => $this->matrixKey(data_get($row, $keyField)),
                'label' => filled(data_get($row, $labelField)) ? (string) data_get($row, $labelField) : __('app.dashboard.not_available'),
            ])
            ->unique('key')
            ->sortBy('label')
            ->values();
    }

    private function matrixKey(mixed $value): string
    {
        return filled($value) ? (string) $value : '__not_available';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $facts
     */
    private function matrixValue(Collection $facts, ?string $sumField, bool $uniqueApplications): float|int
    {
        if ($uniqueApplications) {
            return $facts->pluck('application_id')->filter()->unique()->count();
        }

        if ($sumField) {
            return round((float) $facts->sum($sumField), 2);
        }

        return $facts->count();
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return Collection<int, array<string, mixed>>
     */
    private function applicationTableRows(Collection $applications): Collection
    {
        return $applications->map(function (Application $application): array {
            $spend = $this->applicationSpend($application);

            return [
                'application_id' => $application->getKey(),
                'code' => $application->code,
                'project_name' => $application->project_name,
                'url' => route('admin.applications.show', $application),
                'entity' => $application->entity?->displayName() ?? __('app.dashboard.not_available'),
                'status' => $application->localizedStatus(),
                'production_type' => $this->productionTypeLabel($application),
                'production_scope' => $this->productionScopeLabel($application),
                'start_date' => $application->planned_start_date?->format('Y-m-d') ?? '',
                'end_date' => $application->planned_end_date?->format('Y-m-d') ?? '',
                'estimated_budget' => (float) ($application->estimated_budget ?: 0),
                'local_spend' => $spend['amount'],
                'local_spend_source' => __('app.reports.spend_sources.'.$spend['source']),
            ];
        })->values();
    }

    /**
     * @param  array<string, mixed>  $report
     * @return Collection<int, array<int, mixed>>
     */
    private function crossAnalysisRows(array $report): Collection
    {
        return collect($report['cross_analysis'])
            ->flatMap(function (array $matrix): Collection {
                return collect($matrix['rows'])
                    ->flatMap(function (array $row) use ($matrix): Collection {
                        return collect($matrix['columns'])
                            ->map(fn (array $column): array => [
                                $matrix['title'],
                                $matrix['row_heading'],
                                $row['label'],
                                $column['label'],
                                $matrix['value_heading'],
                                $row['values'][$column['key']] ?? 0,
                            ]);
                    });
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $report
     * @return Collection<int, array<int, mixed>>
     */
    private function allDataRows(array $report): Collection
    {
        $rows = collect();

        foreach ($this->summaryRows($report) as $summaryRow) {
            $rows->push([
                __('app.reports.exports.summary'),
                '',
                '',
                $summaryRow[0],
                $summaryRow[1],
                __('app.reports.columns.value'),
                $summaryRow[2],
                '',
                '',
                '',
                '',
                '',
            ]);
        }

        foreach ($report['facts']['applications'] as $row) {
            $rows->push([
                __('app.reports.exports.applications'),
                $row['code'],
                $row['project_name'],
                $row['production_type'],
                $row['production_scope'],
                __('app.reports.columns.local_spend'),
                $row['local_spend'],
                $row['status'],
                $row['entity'],
                $row['start_date'],
                $row['end_date'],
                $row['local_spend_source'],
            ]);
        }

        foreach ($report['facts']['locations'] as $row) {
            $rows->push([
                __('app.reports.exports.locations'),
                $row['code'],
                $row['project_name'],
                $row['governorate'],
                $row['location_type'],
                __('app.reports.metrics.locations'),
                1,
                $row['timing_label'],
                '',
                $row['start_date'],
                $row['end_date'],
                $row['source'],
            ]);
        }

        foreach ($report['facts']['crew'] as $row) {
            $rows->push([
                __('app.reports.exports.crew'),
                $row['code'],
                $row['project_name'],
                $row['scope_label'],
                $row['gender_label'],
                __('app.reports.metrics.crew_members'),
                $row['count'],
                '',
                '',
                '',
                '',
                $row['source'],
            ]);
        }

        foreach ($report['facts']['equipment'] as $row) {
            $rows->push([
                __('app.reports.exports.equipment'),
                $row['code'],
                $row['project_name'],
                $row['category_label'],
                $row['production_type'],
                __('app.reports.metrics.equipment_quantity'),
                $row['quantity'],
                '',
                '',
                '',
                '',
                $row['source_label'].' | '.$row['total_value'].' JOD',
            ]);
        }

        foreach ($report['facts']['approvals'] as $row) {
            $rows->push([
                __('app.reports.exports.approvals'),
                $row['code'],
                $row['project_name'],
                $row['authority'],
                $row['status_label'],
                __('app.reports.columns.response_hours'),
                $row['response_hours'],
                $row['status_label'],
                $row['authority'],
                $row['created_at'],
                $row['decided_at'],
                '',
            ]);
        }

        foreach ($report['facts']['spend'] as $row) {
            $rows->push([
                __('app.reports.exports.spend'),
                $row['code'],
                $row['project_name'],
                $row['production_type'],
                $row['governorate'],
                __('app.reports.columns.local_spend'),
                $row['allocated_spend'],
                '',
                '',
                '',
                '',
                $row['source_label'],
            ]);
        }

        foreach ($this->crossAnalysisRows($report) as $row) {
            $rows->push([
                __('app.reports.exports.cross_analysis'),
                '',
                '',
                $row[0],
                $row[2].' / '.$row[3],
                $row[4],
                $row[5],
                '',
                '',
                '',
                '',
                $row[1],
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return Collection<int, array<int, mixed>>
     */
    private function summaryRows(array $report): Collection
    {
        $rows = collect([
            ['metrics', __('app.reports.kpis.received_applications'), $report['kpis']['received_applications']],
            ['metrics', __('app.reports.kpis.pending_applications'), $report['kpis']['pending_applications']],
            ['metrics', __('app.reports.kpis.active_production_shoots'), $report['kpis']['active_production_shoots']],
            ['metrics', __('app.reports.kpis.active_locations'), $report['kpis']['active_locations']],
            ['metrics', __('app.reports.kpis.future_locations'), $report['kpis']['future_locations']],
            ['metrics', __('app.reports.kpis.pending_approvals'), $report['kpis']['approvals_tracker']['pending']],
            ['metrics', __('app.reports.kpis.approved_approvals'), $report['kpis']['approvals_tracker']['approved']],
            ['metrics', __('app.reports.kpis.rejected_approvals'), $report['kpis']['approvals_tracker']['rejected']],
        ]);

        foreach ($report['charts']['production_types'] as $row) {
            $rows->push(['production_types', $row['label'], $row['value']]);
        }

        foreach ($report['charts']['spend_by_governorate'] as $row) {
            $rows->push(['spend_by_governorate', $row['label'], $row['value']]);
        }

        foreach ($report['charts']['activity_by_governorate'] as $row) {
            $rows->push(['activity_by_governorate', $row['label'], $row['value']]);
        }

        return $rows;
    }

    /**
     * @return array{amount:float,source:string}
     */
    private function applicationSpend(Application $application): array
    {
        $wrapSpend = data_get($application->wrapReport?->payload ?? [], 'total_local_spending_jod');

        if (is_numeric($wrapSpend)) {
            return [
                'amount' => (float) $wrapSpend,
                'source' => 'wrap_report',
            ];
        }

        $localEstimate = data_get($application->metadata ?? [], 'budget.local_spend_estimate');

        if (is_numeric($localEstimate)) {
            return [
                'amount' => (float) $localEstimate,
                'source' => 'local_spend_estimate',
            ];
        }

        return [
            'amount' => (float) ($application->estimated_budget ?: 0),
            'source' => 'estimated_budget',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function productionTypeCodes(Application $application): array
    {
        $codes = (array) data_get($application->metadata ?? [], 'project.work_categories', []);

        if (blank($codes)) {
            $codes = [$application->work_category ?: WorkCategory::defaultCode()];
        }

        return array_values(array_filter(array_map('strval', $codes)));
    }

    private function primaryProductionTypeCode(Application $application): string
    {
        return $this->productionTypeCodes($application)[0] ?? WorkCategory::defaultCode();
    }

    private function productionTypeLabel(Application $application): string
    {
        return WorkCategory::labelFor($this->primaryProductionTypeCode($application));
    }

    private function productionScope(Application $application): string
    {
        $nationality = Str::lower((string) $application->project_nationality);

        return in_array($nationality, ['jordanian', 'jordan', 'jo', 'local'], true) ? 'local' : 'foreign';
    }

    private function productionScopeLabel(Application $application): string
    {
        return __('app.reports.production_scope.'.$this->productionScope($application));
    }

    private function crewScopeFromNationality(?string $nationality): string
    {
        $normalized = Str::lower((string) $nationality);

        return in_array($normalized, ['jordanian', 'jordan', 'jo', 'local', 'اردني'], true) ? 'local' : 'foreign';
    }

    private function timingBucket(?Carbon $start, ?Carbon $end, Carbon $today): string
    {
        if (! $start && ! $end) {
            return 'unscheduled';
        }

        $start ??= $end;
        $end ??= $start;

        if ($today->lt($start->copy()->startOfDay())) {
            return 'future';
        }

        if ($today->gt($end->copy()->startOfDay())) {
            return 'completed';
        }

        return 'active';
    }

    private function inclusiveDays(?Carbon $start, ?Carbon $end): int
    {
        if (! $start || ! $end) {
            return 1;
        }

        return max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
    }

    private function normalizeEquipmentCategory(mixed $category): string
    {
        $category = Str::of((string) $category)
            ->lower()
            ->replace([' ', '-'], '_')
            ->toString();

        $aliases = [
            'camera' => 'camera_equipment',
            'lighting' => 'light_equipment',
            'light' => 'light_equipment',
            'sound' => 'sound_equipment',
            'drone' => 'aerial_drone',
            'areal_drone' => 'aerial_drone',
            'aerial_drone' => 'aerial_drone',
            'wireless' => 'wireless_equipment',
            'traveler' => 'other',
            'shipping' => 'other',
            'military' => 'military_wardrobe',
        ];

        $category = $aliases[$category] ?? $category;

        return in_array($category, FormLookupOption::activeCodesForType(FormLookupOption::TYPE_EQUIPMENT_CATEGORY), true)
            ? $category
            : 'other';
    }

    private function equipmentCategoryLabel(string $category): string
    {
        $label = FormLookupOption::labelFor(FormLookupOption::TYPE_EQUIPMENT_CATEGORY, $category);

        if ($label !== Str::of($category)->replace('_', ' ')->headline()->toString()) {
            return $label;
        }

        $key = 'app.reports.equipment_categories.'.$category;
        $translation = __($key);

        return $translation === $key
            ? Str::of($category)->replace('_', ' ')->headline()->toString()
            : $translation;
    }

    private function average(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        return round((float) $values->avg(), 1);
    }

    private function dateString(mixed $value): ?string
    {
        $date = $this->parseDate($value);

        return $date?->format('Y-m-d');
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
