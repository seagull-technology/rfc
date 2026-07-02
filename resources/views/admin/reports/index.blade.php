@php
    $title = __('app.reports.title');
    $breadcrumb = __('app.admin.navigation.reports');
    $filters = $report['filters'];
    $options = $report['options'];
    $charts = $report['charts'];
    $facts = $report['facts'];
    $exportQuery = request()->except('dataset');
    $formatMoney = static fn ($value): string => number_format((float) $value, 2).' JOD';
    $formatNumber = static fn ($value): string => number_format((float) $value);
    $formatMatrixValue = static fn (array $matrix, $value): string => ($matrix['value_format'] ?? 'number') === 'money'
        ? $formatMoney($value)
        : $formatNumber($value);

    $reportTypes = [
        'overview' => [
            'label' => __('app.reports.views.overview'),
            'description' => __('app.reports.views.overview_hint'),
            'icon' => 'ph-chart-line-up',
            'datasets' => ['summary', 'applications', 'locations'],
        ],
        'analysis' => [
            'label' => __('app.reports.views.analysis'),
            'description' => __('app.reports.views.analysis_hint'),
            'icon' => 'ph-chart-scatter',
            'datasets' => ['cross_analysis', 'all'],
        ],
        'economic' => [
            'label' => __('app.reports.views.economic'),
            'description' => __('app.reports.views.economic_hint'),
            'icon' => 'ph-currency-circle-dollar',
            'datasets' => ['spend', 'summary'],
        ],
        'locations' => [
            'label' => __('app.reports.views.locations'),
            'description' => __('app.reports.views.locations_hint'),
            'icon' => 'ph-map-pin-area',
            'datasets' => ['locations', 'summary'],
        ],
        'crew' => [
            'label' => __('app.reports.views.crew'),
            'description' => __('app.reports.views.crew_hint'),
            'icon' => 'ph-users-three',
            'datasets' => ['crew', 'summary'],
        ],
        'equipment' => [
            'label' => __('app.reports.views.equipment'),
            'description' => __('app.reports.views.equipment_hint'),
            'icon' => 'ph-toolbox',
            'datasets' => ['equipment', 'summary'],
        ],
        'approvals' => [
            'label' => __('app.reports.views.approvals'),
            'description' => __('app.reports.views.approvals_hint'),
            'icon' => 'ph-checks',
            'datasets' => ['approvals', 'summary'],
        ],
    ];

    $activeReport = (string) request('report', 'overview');
    $activeReport = array_key_exists($activeReport, $reportTypes) ? $activeReport : 'overview';
    $exportDatasets = collect($report['export_datasets'])->keyBy('key');
    $activeExportDatasets = collect($reportTypes[$activeReport]['datasets'])
        ->map(fn (string $key) => $exportDatasets->get($key))
        ->filter()
        ->values();
    $allExportDataset = $exportDatasets->get('all');

    if ($allExportDataset && ! $activeExportDatasets->contains('key', 'all')) {
        $activeExportDatasets->push($allExportDataset);
    }

    $applicationFacts = collect($facts['applications']);
    $locationFacts = collect($facts['locations']);
    $crewFacts = collect($facts['crew']);
    $equipmentFacts = collect($facts['equipment']);
    $approvalFacts = collect($facts['approvals']);
    $spendFacts = collect($facts['spend']);
    $crossAnalysis = collect($report['cross_analysis'] ?? []);

    $totalSpend = $spendFacts->sum('allocated_spend');
    $crewTotal = $crewFacts->sum('count');
    $equipmentQuantity = $equipmentFacts->sum('quantity');
    $equipmentValue = $equipmentFacts->sum('total_value');
    $activeLocations = $locationFacts->where('timing', 'active')->count();
    $futureLocations = $locationFacts->where('timing', 'future')->count();
    $completedLocations = $locationFacts->where('timing', 'completed')->count();
    $topGovernorate = collect($charts['activity_by_governorate'])->first()['label'] ?? __('app.dashboard.not_available');
    $approvalStatusChart = $approvalFacts
        ->countBy('status_label')
        ->map(fn (int $value, string $label): array => ['label' => $label, 'value' => $value])
        ->values();
    $approvalResponseChart = $approvalFacts
        ->filter(fn (array $row): bool => is_numeric($row['response_hours']))
        ->groupBy('authority')
        ->map(fn ($rows, string $label): array => ['label' => $label, 'value' => round(collect($rows)->avg('response_hours'), 1)])
        ->sortByDesc('value')
        ->values();

    $summaryCards = match ($activeReport) {
        'analysis' => [
            ['label' => __('app.reports.cards.cross_dimensions'), 'value' => $formatNumber($crossAnalysis->count()), 'hint' => __('app.reports.cards.cross_dimensions_hint')],
            ['label' => __('app.reports.cards.applications'), 'value' => $formatNumber($applicationFacts->count()), 'hint' => __('app.reports.cards.filtered_scope_hint')],
            ['label' => __('app.reports.cards.report_records'), 'value' => $formatNumber($locationFacts->count() + $crewFacts->count() + $equipmentFacts->count() + $approvalFacts->count() + $spendFacts->count()), 'hint' => __('app.reports.cards.report_records_hint')],
        ],
        'economic' => [
            ['label' => __('app.reports.cards.total_local_spend'), 'value' => $formatMoney($totalSpend), 'hint' => __('app.reports.cards.total_local_spend_hint')],
            ['label' => __('app.reports.cards.applications'), 'value' => $formatNumber($applicationFacts->count()), 'hint' => __('app.reports.cards.filtered_scope_hint')],
            ['label' => __('app.reports.cards.top_governorate'), 'value' => $topGovernorate, 'hint' => __('app.reports.cards.top_governorate_hint')],
        ],
        'locations' => [
            ['label' => __('app.reports.kpis.active_locations'), 'value' => $formatNumber($activeLocations), 'hint' => __('app.reports.location_timing.active')],
            ['label' => __('app.reports.kpis.future_locations'), 'value' => $formatNumber($futureLocations), 'hint' => __('app.reports.location_timing.future')],
            ['label' => __('app.reports.cards.completed_locations'), 'value' => $formatNumber($completedLocations), 'hint' => __('app.reports.location_timing.completed')],
        ],
        'crew' => [
            ['label' => __('app.reports.cards.crew_members'), 'value' => $formatNumber($crewTotal), 'hint' => __('app.reports.cards.filtered_scope_hint')],
            ['label' => __('app.reports.production_scope.local'), 'value' => $formatNumber($crewFacts->where('scope', 'local')->sum('count')), 'hint' => __('app.reports.charts.crew_scope')],
            ['label' => __('app.reports.production_scope.foreign'), 'value' => $formatNumber($crewFacts->where('scope', 'foreign')->sum('count')), 'hint' => __('app.reports.charts.crew_scope')],
        ],
        'equipment' => [
            ['label' => __('app.reports.metrics.equipment_quantity'), 'value' => $formatNumber($equipmentQuantity), 'hint' => __('app.reports.cards.filtered_scope_hint')],
            ['label' => __('app.reports.cards.equipment_value'), 'value' => $formatMoney($equipmentValue), 'hint' => __('app.reports.columns.total_value')],
            ['label' => __('app.reports.cards.equipment_categories'), 'value' => $formatNumber(collect($charts['equipment_categories'])->count()), 'hint' => __('app.reports.charts.equipment_categories')],
        ],
        'approvals' => [
            ['label' => __('app.reports.kpis.pending_approvals'), 'value' => $formatNumber(data_get($report, 'kpis.approvals_tracker.pending', 0)), 'hint' => __('app.approvals.statuses.pending')],
            ['label' => __('app.reports.kpis.approved_approvals'), 'value' => $formatNumber(data_get($report, 'kpis.approvals_tracker.approved', 0)), 'hint' => __('app.approvals.statuses.approved')],
            ['label' => __('app.reports.kpis.avg_response'), 'value' => data_get($report, 'kpis.approvals_tracker.average_response_hours') ?? __('app.dashboard.not_available'), 'hint' => __('app.reports.columns.response_hours')],
        ],
        default => [
            ['label' => __('app.reports.kpis.received_applications'), 'value' => $formatNumber(data_get($report, 'kpis.received_applications', 0)), 'hint' => __('app.reports.cards.received_hint')],
            ['label' => __('app.reports.kpis.pending_applications'), 'value' => $formatNumber(data_get($report, 'kpis.pending_applications', 0)), 'hint' => __('app.reports.cards.pending_hint')],
            ['label' => __('app.reports.kpis.active_production_shoots'), 'value' => $formatNumber(data_get($report, 'kpis.active_production_shoots', 0)), 'hint' => __('app.reports.cards.active_shoots_hint')],
        ],
    };
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('page_layout_class', 'admin-reports-layout')

@push('styles')
    <style>
        .admin-reports-layout {
            padding-top: 0;
        }

        .admin-reports-layout .card {
            margin-bottom: 0;
        }

        .admin-reports-layout > .row > [class*="col-"] {
            margin-bottom: 1.5rem;
        }

        .admin-report-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: .85rem;
        }

        .admin-report-type-card {
            display: block;
            height: 100%;
            padding: 1rem;
            border: 1px solid #e2e7ef;
            background: #fff;
            color: inherit;
            text-decoration: none;
        }

        .admin-report-type-card:hover,
        .admin-report-type-card.active {
            border-color: #7a211d;
            color: inherit;
            text-decoration: none;
        }

        .admin-report-type-card.active {
            background: #fff8f7;
        }

        .admin-report-type-icon {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #7a211d;
            background: rgba(122, 33, 29, .08);
        }

        .admin-report-filter-panel {
            position: sticky;
            top: 1rem;
        }

        .admin-report-summary-card {
            border: 1px solid #e2e7ef;
            background: #fff;
            min-height: 122px;
        }

        .admin-report-summary-card .card-body {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 122px;
        }

        .admin-report-chart-card .card-body {
            min-height: 320px;
        }

        .admin-report-chart-surface {
            min-height: 260px;
        }

        .admin-report-empty-chart {
            min-height: 240px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8a93a6;
            border: 1px dashed #d5dbe6;
            background: #f8f9fb;
        }

        .admin-report-table-scroll {
            max-width: 100%;
            overflow-x: auto;
        }

        .admin-report-table {
            min-width: 980px;
            table-layout: fixed;
        }

        .admin-report-table th,
        .admin-report-table td {
            white-space: normal;
            word-break: break-word;
            vertical-align: top;
        }

        .admin-report-matrix-table {
            min-width: 760px;
            table-layout: fixed;
        }

        .admin-report-matrix-table th,
        .admin-report-matrix-table td {
            text-align: center;
            vertical-align: middle;
            white-space: normal;
            word-break: break-word;
        }

        .admin-report-matrix-table th:first-child,
        .admin-report-matrix-table td:first-child {
            text-align: start;
            min-width: 190px;
            font-weight: 700;
        }

        @media (max-width: 1199.98px) {
            .admin-report-type-grid {
                grid-template-columns: repeat(3, minmax(150px, 1fr));
            }

            .admin-report-filter-panel {
                position: static;
            }
        }

        @media (max-width: 767.98px) {
            .admin-report-type-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center px-0">
                <div>
                    <h2 class="episode-playlist-title wp-heading-inline mb-1">
                        <span class="position-relative">{{ $title }}</span>
                    </h2>
                    <div class="text-muted">{{ __('app.reports.intro') }}</div>
                </div>
                @can('reports.export')
                    <form method="GET" action="{{ route('admin.reports.analytics-export') }}" class="d-flex gap-2 flex-wrap align-items-end">
                        @foreach ($exportQuery as $key => $value)
                            @if (is_scalar($value))
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <div>
                            <label class="form-label mb-1" for="dataset">{{ __('app.reports.export_dataset') }}</label>
                            <select id="dataset" name="dataset" class="form-control bg-white">
                                @foreach ($activeExportDatasets as $dataset)
                                    <option value="{{ $dataset['key'] }}">{{ $dataset['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-danger" type="submit">{{ __('app.reports.export_current') }}</button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="col-12">
            <div class="admin-report-type-grid">
                @foreach ($reportTypes as $key => $reportType)
                    <a class="admin-report-type-card {{ $activeReport === $key ? 'active' : '' }}" href="{{ route('admin.reports.index', array_merge(request()->query(), ['report' => $key])) }}">
                        <div class="d-flex align-items-start gap-3">
                            <span class="rounded admin-report-type-icon">
                                <i class="ph {{ $reportType['icon'] }} fs-5"></i>
                            </span>
                            <div>
                                <h5 class="mb-1">{{ $reportType['label'] }}</h5>
                                <div class="text-muted small">{{ $reportType['description'] }}</div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="col-xl-3">
            <div class="card admin-report-filter-panel">
                <div class="card-header">
                    <h3 class="card-title mb-1">{{ __('app.admin.filters.title') }}</h3>
                    <div class="text-muted">{{ __('app.reports.filter_hint') }}</div>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.reports.index') }}" class="row g-3">
                        <input type="hidden" name="report" value="{{ $activeReport }}">
                        <div class="col-12">
                            <label class="form-label" for="q">{{ __('app.admin.filters.search_label') }}</label>
                            <input id="q" name="q" type="text" class="form-control bg-white" value="{{ $filters['q'] }}" placeholder="{{ __('app.reports.filters.search_placeholder') }}">
                        </div>
                        <div class="col-sm-6 col-xl-12">
                            <label class="form-label" for="date_from">{{ __('app.reports.filters.date_from') }}</label>
                            <input id="date_from" name="date_from" type="date" class="form-control bg-white" value="{{ $filters['date_from'] }}">
                        </div>
                        <div class="col-sm-6 col-xl-12">
                            <label class="form-label" for="date_to">{{ __('app.reports.filters.date_to') }}</label>
                            <input id="date_to" name="date_to" type="date" class="form-control bg-white" value="{{ $filters['date_to'] }}">
                        </div>
                        <div class="col-sm-6 col-xl-12">
                            <label class="form-label" for="status">{{ __('app.applications.status') }}</label>
                            <select id="status" name="status" class="form-control bg-white">
                                @foreach ($options['statuses'] as $option)
                                    <option value="{{ $option['value'] }}" @selected($filters['status'] === $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-xl-12">
                            <label class="form-label" for="production_scope">{{ __('app.reports.filters.production_scope') }}</label>
                            <select id="production_scope" name="production_scope" class="form-control bg-white">
                                @foreach (['all', 'local', 'foreign'] as $scope)
                                    <option value="{{ $scope }}" @selected($filters['production_scope'] === $scope)>{{ $scope === 'all' ? __('app.admin.filters.all_option') : __('app.reports.production_scope.'.$scope) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-xl-12">
                            <label class="form-label" for="production_type">{{ __('app.reports.filters.production_type') }}</label>
                            <select id="production_type" name="production_type" class="form-control bg-white">
                                <option value="all">{{ __('app.admin.filters.all_option') }}</option>
                                @foreach ($options['production_types'] as $option)
                                    <option value="{{ $option['value'] }}" @selected($filters['production_type'] === $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-xl-12">
                            <label class="form-label" for="governorate">{{ __('app.scouting.governorate') }}</label>
                            <select id="governorate" name="governorate" class="form-control bg-white">
                                <option value="all">{{ __('app.admin.filters.all_option') }}</option>
                                @foreach ($options['governorates'] as $option)
                                    <option value="{{ $option['value'] }}" @selected($filters['governorate'] === $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-xl-12">
                            <label class="form-label" for="location_type">{{ __('app.applications.annex_fields.location_type') }}</label>
                            <select id="location_type" name="location_type" class="form-control bg-white">
                                <option value="all">{{ __('app.admin.filters.all_option') }}</option>
                                @foreach ($options['location_types'] as $option)
                                    <option value="{{ $option['value'] }}" @selected($filters['location_type'] === $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-xl-12">
                            <label class="form-label" for="approval_status">{{ __('app.reports.filters.approval_status') }}</label>
                            <select id="approval_status" name="approval_status" class="form-control bg-white">
                                <option value="all">{{ __('app.admin.filters.all_option') }}</option>
                                @foreach ($options['approval_statuses'] as $option)
                                    <option value="{{ $option['value'] }}" @selected($filters['approval_status'] === $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-xl-12">
                            <label class="form-label" for="approval_entity">{{ __('app.reports.filters.approval_entity') }}</label>
                            <select id="approval_entity" name="approval_entity" class="form-control bg-white">
                                <option value="all">{{ __('app.admin.filters.all_option') }}</option>
                                @foreach ($options['approval_entities'] as $option)
                                    <option value="{{ $option['value'] }}" @selected($filters['approval_entity'] === $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-xl-12">
                            <label class="form-label" for="equipment_category">{{ __('app.reports.filters.equipment_category') }}</label>
                            <select id="equipment_category" name="equipment_category" class="form-control bg-white">
                                <option value="all">{{ __('app.admin.filters.all_option') }}</option>
                                @foreach ($options['equipment_categories'] as $option)
                                    <option value="{{ $option['value'] }}" @selected($filters['equipment_category'] === $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-xl-12">
                            <label class="form-label" for="gender">{{ __('app.reports.filters.gender') }}</label>
                            <select id="gender" name="gender" class="form-control bg-white">
                                <option value="all">{{ __('app.admin.filters.all_option') }}</option>
                                @foreach ($options['genders'] as $option)
                                    <option value="{{ $option['value'] }}" @selected($filters['gender'] === $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button class="btn btn-danger" type="submit">{{ __('app.reports.generate_action') }}</button>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.reports.index', ['report' => $activeReport]) }}">{{ __('app.admin.filters.clear_action') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-9">
            <div class="row">
                @foreach ($summaryCards as $card)
                    <div class="col-md-4">
                        <div class="card admin-report-summary-card">
                            <div class="card-body">
                                <div class="text-muted">{{ $card['label'] }}</div>
                                <h3 class="mb-0">{{ $card['value'] }}</h3>
                                <div class="small text-muted">{{ $card['hint'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach

                @if ($activeReport === 'overview')
                    <div class="col-xl-6">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.production_types') }}</h3></div>
                            <div class="card-body"><div id="report-chart-production-types" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.local_foreign') }}</h3></div>
                            <div class="card-body"><div id="report-chart-scope" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.spend_by_governorate') }}</h3></div>
                            <div class="card-body"><div id="report-chart-spend-governorate" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.activity_by_governorate') }}</h3></div>
                            <div class="card-body"><div id="report-chart-activity-governorate" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                @elseif ($activeReport === 'economic')
                    <div class="col-xl-6">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.spend_by_production_type') }}</h3></div>
                            <div class="card-body"><div id="report-chart-spend-type" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.spend_by_governorate') }}</h3></div>
                            <div class="card-body"><div id="report-chart-spend-governorate" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                @elseif ($activeReport === 'locations')
                    <div class="col-xl-6">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.activity_by_governorate') }}</h3></div>
                            <div class="card-body"><div id="report-chart-activity-governorate" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.locations_by_type') }}</h3></div>
                            <div class="card-body"><div id="report-chart-locations-type" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                @elseif ($activeReport === 'crew')
                    <div class="col-xl-6">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.crew_scope') }}</h3></div>
                            <div class="card-body"><div id="report-chart-crew-scope" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.crew_gender') }}</h3></div>
                            <div class="card-body"><div id="report-chart-crew-gender" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                @elseif ($activeReport === 'equipment')
                    <div class="col-12">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.equipment_categories') }}</h3></div>
                            <div class="card-body"><div id="report-chart-equipment" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                @elseif ($activeReport === 'approvals')
                    <div class="col-xl-6">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.approval_status') }}</h3></div>
                            <div class="card-body"><div id="report-chart-approval-status" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card admin-report-chart-card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.charts.approval_response') }}</h3></div>
                            <div class="card-body"><div id="report-chart-approval-response" class="admin-report-chart-surface"></div></div>
                        </div>
                    </div>
                @elseif ($activeReport === 'analysis')
                    @foreach ($crossAnalysis as $matrix)
                        <div class="col-12">
                            <div class="card admin-report-matrix-card">
                                <div class="card-header">
                                    <h3 class="card-title mb-1">{{ $matrix['title'] }}</h3>
                                    <div class="text-muted">{{ $matrix['description'] }}</div>
                                </div>
                                <div class="card-body">
                                    <div class="admin-report-table-scroll">
                                        <table class="table table-striped mb-0 admin-report-matrix-table">
                                            <thead>
                                                <tr>
                                                    <th>{{ $matrix['row_heading'] }}</th>
                                                    @foreach ($matrix['columns'] as $column)
                                                        <th>{{ $column['label'] }}</th>
                                                    @endforeach
                                                    <th>{{ __('app.reports.columns.total') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($matrix['rows'] as $row)
                                                    <tr>
                                                        <td>{{ $row['label'] }}</td>
                                                        @foreach ($matrix['columns'] as $column)
                                                            <td>{{ $formatMatrixValue($matrix, $row['values'][$column['key']] ?? 0) }}</td>
                                                        @endforeach
                                                        <td>{{ $formatMatrixValue($matrix, $row['total']) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="{{ count($matrix['columns']) + 2 }}">{{ __('app.reports.empty_state') }}</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="small text-muted mt-3">
                                        {{ __('app.reports.cross_analysis.filtered_note') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

                @if (in_array($activeReport, ['overview', 'analysis'], true))
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.tables.applications') }}</h3></div>
                            <div class="card-body">
                                <div class="admin-report-table-scroll">
                                    <table class="table table-striped mb-0 admin-report-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('app.reports.columns.application') }}</th>
                                                <th>{{ __('app.reports.columns.project') }}</th>
                                                <th>{{ __('app.reports.columns.entity') }}</th>
                                                <th>{{ __('app.reports.columns.status') }}</th>
                                                <th>{{ __('app.reports.columns.production_type') }}</th>
                                                <th>{{ __('app.reports.columns.production_scope') }}</th>
                                                <th>{{ __('app.reports.columns.local_spend') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($report['tables']['applications'] as $row)
                                                <tr>
                                                    <td><a href="{{ $row['url'] }}">{{ $row['code'] }}</a></td>
                                                    <td>{{ $row['project_name'] }}</td>
                                                    <td>{{ $row['entity'] }}</td>
                                                    <td>{{ $row['status'] }}</td>
                                                    <td>{{ $row['production_type'] }}</td>
                                                    <td>{{ $row['production_scope'] }}</td>
                                                    <td>{{ $formatMoney($row['local_spend']) }}<br><span class="text-muted small">{{ $row['local_spend_source'] }}</span></td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="7">{{ __('app.reports.empty_state') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if (in_array($activeReport, ['overview', 'locations'], true))
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.tables.locations') }}</h3></div>
                            <div class="card-body">
                                <div class="admin-report-table-scroll">
                                    <table class="table table-striped mb-0 admin-report-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('app.reports.columns.application') }}</th>
                                                <th>{{ __('app.reports.columns.governorate') }}</th>
                                                <th>{{ __('app.reports.columns.location_type') }}</th>
                                                <th>{{ __('app.reports.columns.location_name') }}</th>
                                                <th>{{ __('app.reports.columns.location_status') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($report['tables']['locations'] as $row)
                                                <tr>
                                                    <td><a href="{{ $row['url'] }}">{{ $row['code'] }}</a><br><span class="text-muted">{{ $row['project_name'] }}</span></td>
                                                    <td>{{ $row['governorate'] }}</td>
                                                    <td>{{ $row['location_type'] }}</td>
                                                    <td>{{ $row['location_name'] }}</td>
                                                    <td>{{ $row['timing_label'] }}<br><span class="text-muted small">{{ $row['start_date'] }} - {{ $row['end_date'] }}</span></td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="5">{{ __('app.reports.empty_state') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($activeReport === 'economic')
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.tables.spend') }}</h3></div>
                            <div class="card-body">
                                <div class="admin-report-table-scroll">
                                    <table class="table table-striped mb-0 admin-report-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('app.reports.columns.application') }}</th>
                                                <th>{{ __('app.reports.columns.production_type') }}</th>
                                                <th>{{ __('app.reports.columns.governorate') }}</th>
                                                <th>{{ __('app.reports.columns.local_spend') }}</th>
                                                <th>{{ __('app.reports.columns.spend_source') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($spendFacts->take(20) as $row)
                                                <tr>
                                                    <td><a href="{{ $row['url'] }}">{{ $row['code'] }}</a><br><span class="text-muted">{{ $row['project_name'] }}</span></td>
                                                    <td>{{ $row['production_type'] }}</td>
                                                    <td>{{ $row['governorate'] }}</td>
                                                    <td>{{ $formatMoney($row['allocated_spend']) }}</td>
                                                    <td>{{ $row['source_label'] }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="5">{{ __('app.reports.empty_state') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($activeReport === 'crew')
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.tables.crew') }}</h3></div>
                            <div class="card-body">
                                <div class="admin-report-table-scroll">
                                    <table class="table table-striped mb-0 admin-report-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('app.reports.columns.application') }}</th>
                                                <th>{{ __('app.reports.columns.crew_name') }}</th>
                                                <th>{{ __('app.reports.columns.role') }}</th>
                                                <th>{{ __('app.reports.columns.nationality') }}</th>
                                                <th>{{ __('app.reports.columns.gender') }}</th>
                                                <th>{{ __('app.reports.columns.count') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($crewFacts->take(20) as $row)
                                                <tr>
                                                    <td><a href="{{ $row['url'] }}">{{ $row['code'] }}</a><br><span class="text-muted">{{ $row['project_name'] }}</span></td>
                                                    <td>{{ $row['name'] }}</td>
                                                    <td>{{ $row['role'] }}</td>
                                                    <td>{{ $row['nationality'] }}<br><span class="text-muted small">{{ $row['scope_label'] }}</span></td>
                                                    <td>{{ $row['gender_label'] }}</td>
                                                    <td>{{ $row['count'] }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6">{{ __('app.reports.empty_state') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($activeReport === 'equipment')
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.tables.equipment') }}</h3></div>
                            <div class="card-body">
                                <div class="admin-report-table-scroll">
                                    <table class="table table-striped mb-0 admin-report-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('app.reports.columns.application') }}</th>
                                                <th>{{ __('app.reports.columns.equipment_item') }}</th>
                                                <th>{{ __('app.reports.columns.equipment_category') }}</th>
                                                <th>{{ __('app.reports.columns.quantity') }}</th>
                                                <th>{{ __('app.reports.columns.total_value') }}</th>
                                                <th>{{ __('app.reports.columns.source') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($equipmentFacts->take(20) as $row)
                                                <tr>
                                                    <td><a href="{{ $row['url'] }}">{{ $row['code'] }}</a><br><span class="text-muted">{{ $row['project_name'] }}</span></td>
                                                    <td>{{ $row['item'] }}</td>
                                                    <td>{{ $row['category_label'] }}</td>
                                                    <td>{{ $row['quantity'] }}</td>
                                                    <td>{{ $formatMoney($row['total_value']) }}</td>
                                                    <td>{{ $row['source_label'] }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6">{{ __('app.reports.empty_state') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($activeReport === 'approvals')
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title mb-0">{{ __('app.reports.tables.approvals') }}</h3></div>
                            <div class="card-body">
                                <div class="admin-report-table-scroll">
                                    <table class="table table-striped mb-0 admin-report-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('app.reports.columns.application') }}</th>
                                                <th>{{ __('app.reports.columns.approval_entity') }}</th>
                                                <th>{{ __('app.reports.columns.approval_status') }}</th>
                                                <th>{{ __('app.reports.columns.response_hours') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($report['tables']['approvals'] as $row)
                                                <tr>
                                                    <td><a href="{{ $row['url'] }}">{{ $row['code'] }}</a><br><span class="text-muted">{{ $row['project_name'] }}</span></td>
                                                    <td>{{ $row['authority'] }}</td>
                                                    <td>{{ $row['status_label'] }}</td>
                                                    <td>{{ $row['response_hours'] ?? __('app.dashboard.not_available') }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4">{{ __('app.reports.empty_state') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const chartNoDataText = @json(__('app.admin.dashboard.chart_no_data'));
            const charts = @json($charts);
            const approvalStatusChart = @json($approvalStatusChart);
            const approvalResponseChart = @json($approvalResponseChart);

            function emptyState(selector) {
                const element = document.querySelector(selector);

                if (element) {
                    element.innerHTML = '<div class="admin-report-empty-chart">' + chartNoDataText + '</div>';
                }
            }

            function rowsHaveValues(rows) {
                return Array.isArray(rows) && rows.some(function (row) { return Number(row.value) > 0; });
            }

            function renderDonut(selector, rows) {
                const element = document.querySelector(selector);

                if (!element) {
                    return;
                }

                if (!rowsHaveValues(rows)) {
                    emptyState(selector);
                    return;
                }

                new ApexCharts(element, {
                    chart: { type: 'donut', height: 260 },
                    series: rows.map(function (row) { return Number(row.value); }),
                    labels: rows.map(function (row) { return row.label; }),
                    legend: { position: 'bottom' },
                    dataLabels: { enabled: false },
                }).render();
            }

            function renderBar(selector, rows, seriesLabel, horizontal, money) {
                const element = document.querySelector(selector);

                if (!element) {
                    return;
                }

                if (!rowsHaveValues(rows)) {
                    emptyState(selector);
                    return;
                }

                new ApexCharts(element, {
                    chart: { type: 'bar', height: 260, toolbar: { show: false } },
                    plotOptions: { bar: { horizontal: !!horizontal, borderRadius: 3 } },
                    series: [{ name: seriesLabel, data: rows.map(function (row) { return Number(row.value); }) }],
                    xaxis: { categories: rows.map(function (row) { return row.label; }) },
                    dataLabels: { enabled: false },
                    tooltip: {
                        y: {
                            formatter: function (value) {
                                return money ? Number(value).toLocaleString() + ' JOD' : Number(value).toLocaleString();
                            }
                        }
                    }
                }).render();
            }

            renderDonut('#report-chart-production-types', charts.production_types);
            renderDonut('#report-chart-scope', charts.production_scope);
            renderBar('#report-chart-spend-type', charts.spend_by_production_type, @json(__('app.reports.metrics.local_spend')), true, true);
            renderBar('#report-chart-spend-governorate', charts.spend_by_governorate, @json(__('app.reports.metrics.local_spend')), true, true);
            renderDonut('#report-chart-crew-scope', charts.crew_scope);
            renderDonut('#report-chart-crew-gender', charts.crew_gender);
            renderBar('#report-chart-equipment', charts.equipment_categories, @json(__('app.reports.metrics.equipment_quantity')), false, false);
            renderBar('#report-chart-activity-governorate', charts.activity_by_governorate, @json(__('app.reports.metrics.projects')), true, false);
            renderBar('#report-chart-locations-type', charts.locations_by_type, @json(__('app.reports.metrics.locations')), true, false);
            renderDonut('#report-chart-approval-status', approvalStatusChart);
            renderBar('#report-chart-approval-response', approvalResponseChart, @json(__('app.reports.columns.response_hours')), true, false);
        });
    </script>
@endpush
