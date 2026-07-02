<div class="row g-3 dashboard-demo2-shell">
    <div class="col-12">
        <div class="dashboard-section-heading mb-0">
            <div>
                <div class="dashboard-section-kicker">{{ __('app.admin.dashboard.operations_kicker') }}</div>
                <h3 class="mb-0">{{ __('app.admin.dashboard.operational_kpis_title') }}</h3>
            </div>
            <div class="text-muted fw-semibold">{{ __('app.reports.map.title') }}</div>
        </div>
    </div>

    <div class="col-12">
        <div class="card dashboard-demo2-filter">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.dashboard') }}" class="row g-3 align-items-end" data-dashboard-filter>
                    <div class="col-xl-3 col-md-6">
                        <label class="form-label" for="dashboard-q">{{ __('app.admin.filters.search_label') }}</label>
                        <input id="dashboard-q" name="q" type="text" class="form-control bg-white" value="{{ data_get($dashboardFilters, 'q', '') }}" placeholder="{{ __('app.reports.filters.search_placeholder') }}">
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label class="form-label" for="dashboard-date-from">{{ __('app.reports.filters.date_from') }}</label>
                        <input id="dashboard-date-from" name="date_from" type="date" class="form-control bg-white" value="{{ data_get($dashboardFilters, 'date_from', '') }}">
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label class="form-label" for="dashboard-date-to">{{ __('app.reports.filters.date_to') }}</label>
                        <input id="dashboard-date-to" name="date_to" type="date" class="form-control bg-white" value="{{ data_get($dashboardFilters, 'date_to', '') }}">
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label class="form-label" for="dashboard-production-type">{{ __('app.reports.filters.production_type') }}</label>
                        <select id="dashboard-production-type" name="production_type" class="form-control bg-white">
                            <option value="all">{{ __('app.admin.filters.all_option') }}</option>
                            @foreach (($dashboardFilterOptions['production_types'] ?? []) as $option)
                                <option value="{{ $option['value'] }}" @selected(data_get($dashboardFilters, 'production_type') === $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label class="form-label" for="dashboard-production-scope">{{ __('app.reports.filters.production_scope') }}</label>
                        <select id="dashboard-production-scope" name="production_scope" class="form-control bg-white">
                            @foreach (['all', 'local', 'foreign'] as $scope)
                                <option value="{{ $scope }}" @selected(data_get($dashboardFilters, 'production_scope') === $scope)>{{ $scope === 'all' ? __('app.admin.filters.all_option') : __('app.reports.production_scope.'.$scope) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-auto col-md-6">
                        <button type="submit" class="btn btn-danger px-4">{{ __('app.admin.filters.apply_action') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8 dashboard-equal-column dashboard-operational-kpi-column">
        <div class="row g-3 dashboard-operational-kpi-grid">
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div class="rounded p-3 bg-primary-subtle">
                                <i class="ph ph-files fs-4"></i>
                            </div>
                            <div>
                                <span>{{ __('app.reports.kpis.received_applications') }}</span>
                            </div>
                        </div>
                        <div class="text-center">
                            <h2 class="counter">{{ $formatDashboardNumber(data_get($operationalKpis, 'received_applications', 0)) }}</h2>
                            <div>
                                <span>{{ __('app.admin.dashboard.live_data_label') }}</span>
                            </div>
                        </div>
                    </div>
                    <div id="dashboard-chart-requests"></div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="dashboard-operational-stack">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-itmes-center">
                                <div>
                                    <div class="p-3 rounded bg-primary-subtle">
                                        <i class="ph ph-hourglass-medium fs-3"></i>
                                    </div>
                                </div>
                                <div>
                                    <h1 class="counter">{{ $formatDashboardNumber(data_get($operationalKpis, 'pending_applications', 0)) }}</h1>
                                    <p class="mb-0">{{ __('app.reports.kpis.pending_applications') }}</p>
                                </div>
                                <div>
                                    <div class="badge bg-primary">
                                        <i class="ph ph-chart-line-up"></i>
                                        <span>{{ __('app.admin.dashboard.live_data_label') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-itmes-center">
                                <div>
                                    <div class="p-3 rounded bg-primary-subtle">
                                        <i class="ph ph-video-camera fs-3"></i>
                                    </div>
                                </div>
                                <div>
                                    <h1 class="counter">{{ $formatDashboardNumber(data_get($operationalKpis, 'active_production_shoots', 0)) }}</h1>
                                    <p class="mb-0">{{ __('app.reports.kpis.active_production_shoots') }}</p>
                                </div>
                                <div>
                                    <div class="badge bg-primary">
                                        <i class="ph ph-chart-line-up"></i>
                                        <span>{{ __('app.admin.dashboard.live_data_label') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div class="rounded p-3 bg-primary-subtle">
                                <i class="ph ph-checks fs-4"></i>
                            </div>
                            <div>
                                <span>{{ __('app.reports.kpis.approvals_tracker') }}</span>
                            </div>
                        </div>
                        <div class="text-center">
                            <h2 class="counter">{{ $formatDashboardNumber(data_get($operationalKpis, 'approvals_tracker.pending', 0)) }}</h2>
                            <div>
                                <span>{{ __('app.reports.kpis.pending_approvals_short') }}</span>
                            </div>
                        </div>
                    </div>
                    <div id="dashboard-chart-approvals"></div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="text-center">{{ __('app.reports.kpis.active_locations') }}</div>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div>
                                <h2 class="counter">{{ $formatDashboardNumber(data_get($operationalKpis, 'active_locations', 0)) }}</h2>
                                {{ __('app.reports.map.locations') }}
                            </div>
                            <div class="border bg-danger-subtle rounded p-3">
                                <i class="ph ph-map-pin fs-1"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="progress bg-danger-subtle shadow-none w-100" style="height: 6px">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: {{ min(100, max(5, (int) data_get($operationalKpis, 'active_locations', 0) * 8)) }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="text-center">{{ __('app.reports.kpis.future_locations') }}</div>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div>
                                <h2 class="counter">{{ $formatDashboardNumber(data_get($operationalKpis, 'future_locations', 0)) }}</h2>
                                {{ __('app.reports.map.locations') }}
                            </div>
                            <div class="border bg-primary-subtle rounded p-3">
                                <i class="ph ph-calendar-check fs-1"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="progress bg-primary-subtle shadow-none w-100" style="height: 6px">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ min(100, max(5, (int) data_get($operationalKpis, 'future_locations', 0) * 8)) }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 dashboard-equal-column">
        <div class="card card-block card-height card-dashboard">
            <div class="card-header">
                <div class="iq-header-title">
                    <h3 class="card-title">{{ __('app.admin.dashboard.charts.applications_by_category') }}</h3>
                </div>
            </div>
            <div class="card-body">
                <div id="dashboard-genre-chart" class="d-flex justify-content-center"></div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-block card-height card-dashboard dashboard-map-stats-card">
            <ul class="nav nav-pills mb-3 nav-fill" id="pills-tab-1" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="pills-home-tab-fill" data-bs-toggle="pill" href="#pills-home-fill" role="tab" aria-selected="true">{{ app()->getLocale() === 'ar' ? 'نوع العمل' : 'Work type' }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="pills-profile-tab-fill" data-bs-toggle="pill" href="#pills-profile-fill" role="tab" aria-selected="false">{{ app()->getLocale() === 'ar' ? 'طريقة العرض' : 'Release method' }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="pills-contact-tab-fill" data-bs-toggle="pill" href="#pills-contact-fill" role="tab" aria-selected="false">{{ app()->getLocale() === 'ar' ? 'جنسية المشروع' : 'Project nationality' }}</a>
                </li>
            </ul>
            <div class="tab-content" id="pills-tabContent-1">
                <div class="tab-pane fade show active" id="pills-home-fill">
                    <div class="dashboard-map-stat-pane">
                        <div id="statsWork" class="tab-scroll">
                            @forelse ($dashboardMapStats as $row)
                                <div class="dashboard-map-summary-card operational-governorate-row">
                                    <div class="dashboard-map-summary-header">
                                        <strong class="dashboard-map-summary-name">{{ $row['label'] }}</strong>
                                        <button class="dashboard-map-summary-total border-0" type="button" data-dashboard-map-metric data-governorate="{{ $row['label'] }}" data-metric-type="all" data-metric-label="{{ __('app.admin.dashboard.map_all_projects') }}">
                                            {{ __('app.admin.dashboard.comparison_total') }}
                                            {{ $formatDashboardNumber(collect($row['work_types'])->sum('value')) }}
                                        </button>
                                    </div>
                                    <div class="dashboard-map-metric-grid">
                                        @forelse ($row['work_types'] as $metric)
                                            <button class="dashboard-map-metric-pill" type="button" data-dashboard-map-metric data-governorate="{{ $row['label'] }}" data-metric-type="workType" data-metric-label="{{ $metric['label'] }}">
                                                <span>{{ $metric['label'] }}</span>
                                                <strong>{{ $formatDashboardNumber($metric['value']) }}</strong>
                                            </button>
                                        @empty
                                            <span class="dashboard-map-metric-empty">{{ __('app.admin.dashboard.map_no_breakdown') }}</span>
                                        @endforelse
                                    </div>
                                </div>
                            @empty
                                <div class="dashboard-map-empty">{{ __('app.reports.empty_state') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="pills-profile-fill">
                    <div class="dashboard-map-stat-pane">
                        <div id="statsDisplay" class="tab-scroll"></div>
                    </div>
                </div>
                <div class="tab-pane fade" id="pills-contact-fill">
                    <div class="dashboard-map-stat-pane">
                        <div id="statsNationality" class="tab-scroll"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card card-block card-height card-dashboard dashboard-map-card">
            <div class="map-wrapper">
                <button class="reset-btn" type="button" data-demo2-map-reset>{{ __('app.admin.dashboard.map_reset') }}</button>
                <div id="map" data-demo2-dashboard-map></div>
            </div>
        </div>
    </div>

    @php
        $monthlyComparisonDelta = (int) data_get($monthlyApplicationComparison, 'delta', 0);
        $monthlyComparisonDeltaPercent = (float) data_get($monthlyApplicationComparison, 'delta_percent', 0);
        $monthlyComparisonTrendClass = $monthlyComparisonDelta >= 0 ? 'success' : 'danger';
        $monthlyComparisonSign = $monthlyComparisonDelta > 0 ? '+' : '';
        $dashboardYearQuery = collect(request()->query())->except('year');
    @endphp
    <div class="col-lg-6 dashboard-equal-column">
        <div class="card card-block card-height card-dashboard">
            <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
                <div class="iq-header-title">
                    <h3 class="card-title mb-1">{{ __('app.admin.dashboard.charts.monthly_applications_comparison') }}</h3>
                    <div class="text-muted small">{{ __('app.admin.dashboard.comparison_vs_previous') }}</div>
                </div>
                <form method="GET" action="{{ route('admin.dashboard') }}" class="dashboard-year-form">
                    @foreach ($dashboardYearQuery as $queryKey => $queryValue)
                        @if (is_scalar($queryValue))
                            <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
                        @endif
                    @endforeach
                    <label class="visually-hidden" for="dashboard-comparison-year">{{ __('app.admin.dashboard.comparison_year_label') }}</label>
                    <select id="dashboard-comparison-year" name="year" class="form-control bg-white" onchange="this.form.submit()">
                        @foreach ($dashboardComparisonYears as $yearOption)
                            <option value="{{ $yearOption }}" @selected((int) data_get($monthlyApplicationComparison, 'year') === (int) $yearOption)>{{ $yearOption }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="card-body">
                <div class="dashboard-comparison-strip mb-3">
                    <div class="dashboard-comparison-stat">
                        <span>{{ __('app.admin.dashboard.comparison_selected_year') }} {{ data_get($monthlyApplicationComparison, 'year') }}</span>
                        <strong>{{ $formatDashboardNumber(data_get($monthlyApplicationComparison, 'current_total', 0)) }}</strong>
                    </div>
                    <div class="dashboard-comparison-stat">
                        <span>{{ __('app.admin.dashboard.comparison_previous_year') }} {{ data_get($monthlyApplicationComparison, 'previous_year') }}</span>
                        <strong>{{ $formatDashboardNumber(data_get($monthlyApplicationComparison, 'previous_total', 0)) }}</strong>
                    </div>
                    <div class="dashboard-comparison-stat">
                        <span>{{ __('app.admin.dashboard.comparison_change') }}</span>
                        <strong class="text-{{ $monthlyComparisonTrendClass }}">
                            {{ $monthlyComparisonSign }}{{ $formatDashboardNumber($monthlyComparisonDelta) }}
                            <small>({{ $monthlyComparisonSign }}{{ number_format($monthlyComparisonDeltaPercent, 1) }}%)</small>
                        </strong>
                    </div>
                </div>
                <div id="dashboard-monthly-comparison-chart"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-6 dashboard-equal-column">
        <div class="card card-dashboard">
            <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <div class="iq-header-title">
                    <h3 class="card-title">{{ __('app.admin.dashboard.charts.release_methods') }}</h3>
                </div>
            </div>
            <div class="card-body">
                <div id="dashboard-release-chart"></div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card card-block card-height card-dashboard">
            <div class="card-header">
                <div class="iq-header-title">
                    <h3 class="card-title">{{ __('app.admin.dashboard.charts.approval_duration') }}</h3>
                </div>
            </div>
            <div class="card-body">
                <div id="dashboard-time-chart" class="d-flex align-items-center justify-content-center"></div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="row dashboard-equal-row">
            <div class="col-lg-4 col-md-6">
                <div class="card card-block card-height card-dashboard">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.reports.charts.local_foreign') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="dashboard-scope-chart" class="d-flex align-items-center justify-content-center"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 col-md-6">
                <div class="card card-dashboard">
                    <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.dashboard.recent_requests_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="mt-4 table-responsive">
                            <div class="table-responsive border rounded py-4 admin-dashboard-table-scroll">
                                <table id="datatable" class="table mb-0 admin-dashboard-table admin-recent-requests-table" data-toggle="data-table">
                                    <thead>
                                        <tr class="ligth">
                                            <th>#</th>
                                            <th>{{ __('app.admin.applications.application') }}</th>
                                            <th>{{ __('app.applications.project_name') }}</th>
                                            <th>{{ __('app.admin.applications.entity') }}</th>
                                            <th>{{ __('app.applications.updated_at') }}</th>
                                            <th>{{ __('app.applications.status') }}</th>
                                            <th>{{ __('app.admin.applications.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($recentApplications as $recentApplication)
                                            @php($recentResponse = \App\Support\AdminApplicantResponseState::application($recentApplication))
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $recentApplication->code ?: __('app.dashboard.not_available') }}</td>
                                                <td>
                                                    {{ $recentApplication->project_name }}
                                                    @if ($recentResponse['active'])
                                                        <div class="response-flag">
                                                            <span class="badge bg-primary">{{ $recentResponse['title'] }}</span>
                                                            <span class="small text-muted">{{ $recentResponse['summary'] }}</span>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>{{ $recentApplication->entity?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                                <td>{{ optional($recentApplication->submitted_at ?? $recentApplication->created_at)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                                <td><span class="badge bg-{{ $statusClass($recentApplication->status) }}">{{ $recentApplication->localizedStatus() }}</span></td>
                                                <td>
                                                    <div class="flex align-items-center list-user-action">
                                                        <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('admin.applications.show', $recentApplication) }}">
                                                            <i class="ph ph-eye fs-6"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7">{{ __('app.admin.dashboard.recent_requests_empty') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dashboardMetricProjectsModal" tabindex="-1" aria-labelledby="dashboardMetricProjectsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="dashboardMetricProjectsModalLabel">{{ __('app.admin.dashboard.map_metric_modal_title') }}</h5>
                    <div class="text-muted small" data-dashboard-modal-subtitle></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small mb-3">{{ __('app.admin.dashboard.map_metric_modal_hint') }}</div>
                <div data-dashboard-modal-body></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        window.addEventListener('load', function () {
            const chartPalette = ['#89050c', '#b70710', '#ce0812', '#9f883a', '#6f1f1b', '#bf6b5d'];
            const chartNoDataText = @json(__('app.admin.dashboard.chart_no_data'));
            const monthlyComparison = @json($monthlyApplicationComparison);
            const monthlyLabels = monthlyComparison.labels || @json($monthlyApplicationLabels);
            const monthlyCounts = monthlyComparison.current || @json($monthlyApplicationCounts);
            const previousMonthlyCounts = monthlyComparison.previous || [];
            const categoryData = @json($operationalProductionTypeChart->isNotEmpty() ? $operationalProductionTypeChart->values() : $applicationsByCategoryChart);
            const releaseData = @json($applicationsByReleaseChart);
            const approvalDurationData = @json($approvalDurationChart);
            const scopeData = @json($operationalScopeChart->isNotEmpty() ? $operationalScopeChart->values() : $registrationBreakdownChart);
            const dayLabel = @json(app()->getLocale() === 'ar' ? 'أيام' : 'days');

            const clearAndRenderChart = function (selector, hasData, options) {
                const element = document.querySelector(selector);

                if (!element) {
                    return;
                }

                element.innerHTML = '';

                if (typeof ApexCharts === 'undefined' || !hasData) {
                    element.classList.add('admin-empty-chart');
                    element.textContent = chartNoDataText;
                    return;
                }

                new ApexCharts(element, options).render();
            };

            const numericSeries = function (rows) {
                return rows.map(function (row) {
                    return Number(row.value || 0);
                });
            };

            const labelSeries = function (rows) {
                return rows.map(function (row) {
                    return row.label || '';
                });
            };

            const hasValues = function (values) {
                return values.some(function (value) {
                    return Number(value || 0) > 0;
                });
            };

            const monthlyHasData = hasValues(monthlyCounts);
            const sparklineOptions = function (series) {
                return {
                    series: [{ data: series }],
                    chart: { type: 'area', height: 80, sparkline: { enabled: true }, toolbar: { show: false } },
                    colors: ['#ce0812'],
                    stroke: { curve: 'smooth', width: 2 },
                    fill: { opacity: .18 },
                    tooltip: { enabled: false }
                };
            };

            clearAndRenderChart('#dashboard-chart-requests', monthlyHasData, sparklineOptions(monthlyCounts));
            clearAndRenderChart('#dashboard-chart-approvals', monthlyHasData, sparklineOptions([...monthlyCounts].reverse()));

            clearAndRenderChart('#dashboard-genre-chart', hasValues(numericSeries(categoryData)), {
                series: numericSeries(categoryData),
                labels: labelSeries(categoryData),
                chart: { type: 'donut', height: 315, toolbar: { show: false } },
                colors: chartPalette,
                legend: { position: 'bottom' },
                dataLabels: { enabled: true }
            });

            clearAndRenderChart('#dashboard-monthly-comparison-chart', monthlyHasData || hasValues(previousMonthlyCounts), {
                series: [
                    { name: String(monthlyComparison.year || ''), data: monthlyCounts },
                    { name: String(monthlyComparison.previous_year || ''), data: previousMonthlyCounts }
                ],
                chart: { type: 'area', height: 315, toolbar: { show: false } },
                colors: ['#ce0812', '#9f883a'],
                stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 6] },
                fill: { opacity: [.16, .06] },
                dataLabels: { enabled: false },
                xaxis: { categories: monthlyLabels },
                legend: { position: 'top', horizontalAlign: 'left' },
                tooltip: { shared: true, intersect: false },
                yaxis: { labels: { formatter: function (value) { return Math.round(value); } } }
            });

            clearAndRenderChart('#dashboard-release-chart', hasValues(numericSeries(releaseData)), {
                series: [{ name: @json(__('app.admin.dashboard.charts.release_methods')), data: numericSeries(releaseData) }],
                chart: { type: 'bar', height: 315, toolbar: { show: false } },
                colors: ['#89050c'],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '42%' } },
                dataLabels: { enabled: false },
                xaxis: { categories: labelSeries(releaseData) }
            });

            const approvalBase = Date.UTC({{ now()->year }}, 0, 1);
            const approvalSeries = approvalDurationData.map(function (row, index) {
                const hours = Math.max(1, Number(row.value || 0));
                const start = approvalBase + (index * 36 * 60 * 60 * 1000);

                return { x: row.label || '', y: [start, start + (hours * 60 * 60 * 1000)] };
            });

            clearAndRenderChart('#dashboard-time-chart', approvalSeries.length > 0, {
                series: [{ data: approvalSeries }],
                chart: { type: 'rangeBar', height: 350, zoom: { enabled: false }, toolbar: { show: false } },
                colors: chartPalette,
                plotOptions: { bar: { horizontal: true } },
                dataLabels: {
                    enabled: true,
                    formatter: function (value) {
                        const days = Math.max(1, Math.ceil((value[1] - value[0]) / (1000 * 60 * 60 * 24)));

                        return days + ' ' + dayLabel;
                    }
                },
                xaxis: { type: 'datetime' },
                tooltip: {
                    custom: function ({ seriesIndex, dataPointIndex, w }) {
                        const data = w.config.series[seriesIndex].data[dataPointIndex];
                        const days = Math.max(1, Math.ceil((data.y[1] - data.y[0]) / (1000 * 60 * 60 * 24)));

                        return '<div style="padding:10px"><strong>' + data.x + '</strong><br>' + days + ' ' + dayLabel + '</div>';
                    }
                }
            });

            clearAndRenderChart('#dashboard-scope-chart', hasValues(numericSeries(scopeData)), {
                series: numericSeries(scopeData),
                labels: labelSeries(scopeData),
                chart: { type: 'polarArea', height: 315, toolbar: { show: false } },
                colors: chartPalette,
                legend: { position: 'bottom' },
                stroke: { width: 1, colors: ['#fff'] }
            });

            const mapElement = document.querySelector('[data-demo2-dashboard-map]');

            if (!mapElement) {
                return;
            }

            if (typeof L === 'undefined') {
                mapElement.innerHTML = '<div class="dashboard-map-empty">' + chartNoDataText + '</div>';
                return;
            }

            const dashboardMapUrl = @json($dashboardMapUrl);
            const mapRows = @json($dashboardMapStats);
            const locationFacts = @json($dashboardLocationFacts->values());
            const labels = {
                empty: @json(__('app.reports.empty_state')),
                noBreakdown: @json(__('app.admin.dashboard.map_no_breakdown')),
                active: @json(__('app.reports.location_timing.active')),
                future: @json(__('app.reports.location_timing.future')),
                completed: @json(__('app.reports.location_timing.completed')),
                total: @json(__('app.admin.dashboard.comparison_total')),
                projects: @json(__('app.reports.map.projects')),
                locations: @json(__('app.reports.map.locations')),
                projectType: @json(__('app.reports.filters.production_type')),
                nationality: @json(__('app.reports.filters.production_scope')),
                location: @json(__('app.reports.columns.location_name')),
                timing: @json(__('app.reports.columns.location_status')),
                localScope: @json(__('app.reports.production_scope.local')),
                foreignScope: @json(__('app.reports.production_scope.foreign')),
                allProjects: @json(__('app.admin.dashboard.map_all_projects')),
                modalTitle: @json(__('app.admin.dashboard.map_metric_modal_title')),
                modalEmpty: @json(__('app.admin.dashboard.map_metric_modal_empty')),
                openRequest: @json(__('app.admin.dashboard.map_open_request')),
                requestNumber: @json(__('app.reports.columns.application')),
                project: @json(__('app.reports.columns.project')),
                dateRange: @json(__('app.admin.dashboard.map_date_range')),
                activityScopeHint: @json(__('app.admin.dashboard.map_activity_scope_hint')),
            };
            const codeToGeoName = {
                irbid: 'Irbid',
                madaba: 'Madaba',
                karak: 'Karak',
                tafilah: 'Tafilah',
                aqaba: 'Aqaba',
                balqa: 'Balqa',
                mafraq: 'Mafraq',
                maan: 'Ma`an',
                amman: 'Amman',
                zarqa: 'Zarqa',
                ajloun: 'Ajlun',
                jerash: 'Jarash'
            };
            const geoNameToCode = {
                Irbid: 'irbid',
                Madaba: 'madaba',
                Karak: 'karak',
                Tafilah: 'tafilah',
                Aqaba: 'aqaba',
                Balqa: 'balqa',
                Mafraq: 'mafraq',
                'Ma`an': 'maan',
                "Ma'an": 'maan',
                'Ma’an': 'maan',
                Amman: 'amman',
                Zarqa: 'zarqa',
                Ajlun: 'ajloun',
                Ajloun: 'ajloun',
                Jarash: 'jerash',
                Jerash: 'jerash'
            };
            const governorateCentroids = {
                Irbid: [32.5568, 35.8469],
                Madaba: [31.7195, 35.7936],
                Karak: [31.1853, 35.7048],
                Tafilah: [30.8375, 35.6044],
                Aqaba: [29.5321, 35.0063],
                Balqa: [32.0367, 35.7288],
                Mafraq: [32.3429, 36.2080],
                'Ma`an': [30.1949, 35.7342],
                Amman: [31.9539, 35.9106],
                Zarqa: [32.0728, 36.0870],
                Ajlun: [32.3333, 35.7528],
                Jarash: [32.2769, 35.8993]
            };

            const map = L.map('map').setView([31.24, 36.51], 7);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(map);

            let markersLayer = L.layerGroup().addTo(map);
            let jordanLayer = null;
            let selectedGov = null;
            let activeTab = 'workType';

            const escapeHtml = function (value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            const metricArrayToObject = function (items) {
                return (items || []).reduce(function (carry, item) {
                    carry[item.label || labels.noBreakdown] = Number(item.value || 0);

                    return carry;
                }, {});
            };

            const scopeCount = function (items, kind) {
                return (items || []).reduce(function (total, item) {
                    const label = String(item.label || '').toLowerCase();
                    const isForeign = label.includes('foreign') || label.includes('أجنبي') || label.includes('اجنبي') || label.includes('غير أردني') || label.includes('غير اردني');
                    const isLocal = label.includes('local') || label.includes('محلي') || label.includes('أردني') || label.includes('اردني');

                    if (kind === 'foreign' && isForeign) {
                        return total + Number(item.value || 0);
                    }

                    if (kind === 'local' && isLocal && !isForeign) {
                        return total + Number(item.value || 0);
                    }

                    return total;
                }, 0);
            };

            const projectsData = {};

            mapRows.forEach(function (row) {
                const geoName = codeToGeoName[row.code] || row.label || row.code;
                let localCount = scopeCount(row.production_scope, 'local');
                let foreignCount = scopeCount(row.production_scope, 'foreign');

                if (localCount + foreignCount === 0) {
                    localCount = Number(row.projects || row.locations || 0);
                }

                projectsData[geoName] = {
                    local: localCount,
                    foreign: foreignCount,
                    active: Number(row.active || 0),
                    future: Number(row.future || 0),
                    completed: Number(row.completed || 0),
                    projects: [],
                    workTypes: metricArrayToObject(row.work_types),
                    displayTypes: metricArrayToObject(row.release_methods)
                };
            });

            locationFacts.forEach(function (fact, index) {
                const geoName = codeToGeoName[fact.governorate_code] || fact.governorate;
                const centroid = governorateCentroids[geoName];

                if (!geoName || !centroid) {
                    return;
                }

                if (!projectsData[geoName]) {
                    projectsData[geoName] = { local: 0, foreign: 0, active: 0, future: 0, completed: 0, projects: [], workTypes: {}, displayTypes: {} };
                }

                const offsetSeed = projectsData[geoName].projects.length;
                const latOffset = ((offsetSeed % 5) - 2) * 0.018;
                const lngOffset = (Math.floor(offsetSeed / 5) % 5 - 2) * 0.024;

                projectsData[geoName].projects.push({
                    applicationId: fact.application_id || fact.code || index,
                    code: fact.code || '',
                    name: fact.project_name || fact.location_name || fact.code || '',
                    type: fact.production_type || '',
                    releaseMethod: fact.release_method || '',
                    nationality: fact.production_scope || '',
                    product: fact.location_name || fact.nature || '',
                    startDate: fact.start_date || '',
                    endDate: fact.end_date || '',
                    timingKey: fact.timing || '',
                    timing: fact.timing_label || '',
                    url: fact.url || '',
                    lat: centroid[0] + latOffset,
                    lng: centroid[1] + lngOffset
                });
            });

            function getColor(total) {
                return total > 20 ? '#89050c' :
                    total > 10 ? '#b70710' :
                        total > 0 ? '#ce0812' :
                                '#f0f0f0';
            }

            function renderPins() {
                markersLayer.clearLayers();

                const govs = selectedGov ? [selectedGov] : Object.keys(projectsData);

                govs.forEach(function (gov) {
                    const projects = projectsData[gov]?.projects || [];

                    projects.forEach(function (project) {
                        const marker = L.marker([project.lat, project.lng], {
                            icon: L.divIcon({
                                className: '',
                                html: '<svg width="15" height="41" viewBox="0 0 25 41"><path fill="#9f883a" stroke="#ffffff" stroke-width="2" d="M12.5 0C5.6 0 0 5.6 0 12.5c0 9.7 12.5 28.5 12.5 28.5S25 22.2 25 12.5C25 5.6 19.4 0 12.5 0z"/><circle cx="12.5" cy="12.5" r="5" fill="#fff"/></svg>',
                                iconSize: [15, 15],
                                iconAnchor: [12, 41]
                            })
                        });

                        marker.bindPopup(
                            '<div style="min-width:200px">'
                            + '<strong>' + escapeHtml(project.name) + '</strong><br>'
                            + labels.projectType + ': ' + escapeHtml(project.type) + '<br>'
                            + labels.nationality + ': ' + escapeHtml(project.nationality) + '<br>'
                            + labels.location + ': ' + escapeHtml(project.product) + '<br>'
                            + labels.timing + ': ' + escapeHtml(project.timing)
                            + '</div>'
                        );
                        marker.bindTooltip(project.name);
                        markersLayer.addLayer(marker);
                    });
                });
            }

            function addMask(geoData) {
                const darkLayer = L.rectangle([[-90, -180], [90, 180]], {
                    stroke: false,
                    fillColor: '#000',
                    fillOpacity: 0.45,
                    interactive: false
                }).addTo(map);

                const jordanHighlight = L.geoJSON(geoData, {
                    style: function () {
                        return { fillColor: 'transparent', color: '#fff', weight: 2, fillOpacity: 0 };
                    },
                    interactive: false
                }).addTo(map);

                darkLayer.bringToBack();
                jordanHighlight.bringToFront();
            }

            function style(feature) {
                const name = feature.properties.name;
                const data = projectsData[name] || { local: 0, foreign: 0, active: 0, future: 0, completed: 0 };
                const total = data.active + data.future + data.completed || data.local + data.foreign;

                return { fillColor: getColor(total), weight: 1, color: 'white', fillOpacity: 0.7 };
            }

            function onEachFeature(feature, layer) {
                layer.bindTooltip(feature.properties.name);

                layer.on({
                    click: function () {
                        selectedGov = feature.properties.name;
                        updateStats();
                    }
                });
            }

            function statMetrics(data) {
                if (activeTab === 'workType') {
                    return Object.keys(data.workTypes || {}).map(function (type) {
                        return { label: type, value: data.workTypes[type] };
                    });
                }

                if (activeTab === 'displayType') {
                    return Object.keys(data.displayTypes || {}).map(function (type) {
                        return { label: type, value: data.displayTypes[type] };
                    });
                }

                return [
                    { label: labels.localScope, value: data.local || 0 },
                    { label: labels.foreignScope, value: data.foreign || 0 }
                ];
            }

            const normalizeMetricValue = function (value) {
                return String(value || '').trim().toLowerCase();
            };

            function metricMatchesProject(project, metricType, metricLabel) {
                if (metricType === 'all') {
                    return true;
                }

                const expected = normalizeMetricValue(metricLabel);

                if (metricType === 'workType') {
                    return normalizeMetricValue(project.type) === expected;
                }

                if (metricType === 'displayType') {
                    return normalizeMetricValue(project.releaseMethod) === expected;
                }

                if (metricType === 'nationality') {
                    return normalizeMetricValue(project.nationality) === expected;
                }

                return false;
            }

            function uniqueProjects(projects) {
                const seen = {};

                return projects.reduce(function (carry, project) {
                    const key = project.applicationId || project.code || project.name;

                    if (seen[key]) {
                        if (project.product && !seen[key].locations.includes(project.product)) {
                            seen[key].locations.push(project.product);
                        }

                        return carry;
                    }

                    const normalized = {
                        ...project,
                        locations: project.product ? [project.product] : []
                    };

                    seen[key] = normalized;
                    carry.push(normalized);

                    return carry;
                }, []);
            }

            function renderProjectRows(projects) {
                if (!projects.length) {
                    return '<div class="dashboard-map-empty">' + labels.modalEmpty + '</div>';
                }

                return projects.map(function (project) {
                    const dateRange = [project.startDate, project.endDate].filter(Boolean).join(' - ') || '-';
                    const locations = project.locations.length ? project.locations.join(', ') : '-';
                    const request = project.code || '-';
                    const action = project.url
                        ? '<a class="btn btn-sm btn-danger" href="' + escapeHtml(project.url) + '">' + labels.openRequest + '</a>'
                        : '';

                    return '<div class="dashboard-project-row">'
                        + '<div>'
                        + '<div class="fw-bold">' + escapeHtml(project.name || request) + '</div>'
                        + '<div class="dashboard-project-meta">'
                        + labels.requestNumber + ': ' + escapeHtml(request)
                        + ' | ' + labels.projectType + ': ' + escapeHtml(project.type || '-')
                        + ' | ' + labels.timing + ': ' + escapeHtml(project.timing || '-')
                        + '<br>' + labels.location + ': ' + escapeHtml(locations)
                        + '<br>' + labels.dateRange + ': ' + escapeHtml(dateRange)
                        + '</div>'
                        + '</div>'
                        + '<div class="flex-shrink-0">' + action + '</div>'
                        + '</div>';
                }).join('');
            }

            function showMetricProjects(governorate, metricType, metricLabel) {
                const data = projectsData[governorate] || {};
                const projects = uniqueProjects((data.projects || []).filter(function (project) {
                    return metricMatchesProject(project, metricType, metricLabel);
                }));
                const modalElement = document.getElementById('dashboardMetricProjectsModal');
                const modalBody = modalElement?.querySelector('[data-dashboard-modal-body]');
                const modalSubtitle = modalElement?.querySelector('[data-dashboard-modal-subtitle]');

                if (!modalElement || !modalBody || !modalSubtitle) {
                    return;
                }

                modalSubtitle.textContent = governorate + ' | ' + (metricLabel || labels.allProjects) + ' | ' + labels.activityScopeHint;
                modalBody.innerHTML = renderProjectRows(projects);

                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            }

            function metricPills(metrics, governorate, metricType) {
                if (!metrics.length) {
                    return '<span class="dashboard-map-metric-empty">' + labels.noBreakdown + '</span>';
                }

                return metrics.map(function (metric) {
                    return '<button class="dashboard-map-metric-pill" type="button" data-dashboard-map-metric data-governorate="' + escapeHtml(governorate) + '" data-metric-type="' + escapeHtml(metricType) + '" data-metric-label="' + escapeHtml(metric.label) + '">'
                        + '<span>' + escapeHtml(metric.label) + '</span>'
                        + '<strong>' + escapeHtml(metric.value || 0) + '</strong>'
                        + '</button>';
                }).join('');
            }

            function renderAllGovernorates() {
                const govs = selectedGov ? [selectedGov] : Object.keys(projectsData);
                let html = '';

                govs.forEach(function (gov) {
                    const data = projectsData[gov] || {};
                    const metrics = statMetrics(data);
                    const total = metrics.reduce(function (sum, metric) {
                        return sum + Number(metric.value || 0);
                    }, 0);

                    html += '<div class="dashboard-map-summary-card operational-governorate-row">'
                        + '<div class="dashboard-map-summary-header">'
                        + '<strong class="dashboard-map-summary-name">' + escapeHtml(gov) + '</strong>'
                        + '<button class="dashboard-map-summary-total border-0" type="button" data-dashboard-map-metric data-governorate="' + escapeHtml(gov) + '" data-metric-type="all" data-metric-label="' + escapeHtml(labels.allProjects) + '">' + labels.total + ' ' + escapeHtml(total) + '</button>'
                        + '</div>'
                        + '<div class="dashboard-map-metric-grid">' + metricPills(metrics, gov, activeTab) + '</div>'
                        + '</div>';
                });

                html = html || '<div class="dashboard-map-empty">' + labels.empty + '</div>';

                if (activeTab === 'workType') {
                    document.getElementById('statsWork').innerHTML = html;
                } else if (activeTab === 'displayType') {
                    document.getElementById('statsDisplay').innerHTML = html;
                } else {
                    document.getElementById('statsNationality').innerHTML = html;
                }
            }

            function updateStats() {
                renderAllGovernorates();
                renderPins();
            }

            document.querySelectorAll('#pills-tab-1 a').forEach(function (tab) {
                tab.addEventListener('shown.bs.tab', function (event) {
                    const id = event.target.getAttribute('href');

                    if (id === '#pills-home-fill') {
                        activeTab = 'workType';
                    } else if (id === '#pills-profile-fill') {
                        activeTab = 'displayType';
                    } else {
                        activeTab = 'nationality';
                    }

                    updateStats();
                });
            });

            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-dashboard-map-metric]');

                if (!trigger) {
                    return;
                }

                showMetricProjects(
                    trigger.dataset.governorate || '',
                    trigger.dataset.metricType || 'all',
                    trigger.dataset.metricLabel || labels.allProjects
                );
            });

            const metricModalElement = document.getElementById('dashboardMetricProjectsModal');

            if (metricModalElement) {
                metricModalElement.addEventListener('show.bs.modal', function () {
                    document.body.classList.add('dashboard-metric-modal-open');
                });

                metricModalElement.addEventListener('hidden.bs.modal', function () {
                    document.body.classList.remove('dashboard-metric-modal-open');
                });
            }

            fetch(dashboardMapUrl)
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Map data unavailable');
                    }

                    return response.json();
                })
                .then(function (geoData) {
                    jordanLayer = L.geoJSON(geoData, {
                        style: style,
                        onEachFeature: onEachFeature
                    }).addTo(map);

                    map.fitBounds(jordanLayer.getBounds());
                    addLegend();
                    addMask(geoData);
                    renderAllGovernorates();
                    renderPins();
                })
                .catch(function () {
                    mapElement.innerHTML = '<div class="dashboard-map-empty">' + labels.empty + '</div>';
                });

            function addLegend() {
                const legend = L.control({ position: 'bottomright' });

                legend.onAdd = function () {
                    const div = L.DomUtil.create('div', 'info legend');

                    div.innerHTML += '<h5>' + labels.projects + '</h5>';
                    div.innerHTML += '<i style="background:#89050c"></i> 20+<br>';
                    div.innerHTML += '<i style="background:#b70710"></i> 10-20<br>';
                    div.innerHTML += '<i style="background:#ce0812"></i> 1-5<br>';
                    div.innerHTML += '<i style="background:#f0f0f0"></i> 0<br>';

                    return div;
                };

                legend.addTo(map);
            }

            document.querySelector('[data-demo2-map-reset]')?.addEventListener('click', function () {
                selectedGov = null;

                if (jordanLayer) {
                    map.fitBounds(jordanLayer.getBounds());
                    jordanLayer.setStyle(style);
                }

                updateStats();
                map.closePopup();
            });
        });
    </script>
@endpush
