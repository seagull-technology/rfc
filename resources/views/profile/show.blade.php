@php
    $title = $entity->displayName();
    $entityStatusClass = static fn (?string $status): string => match ($status) {
        'active' => 'success',
        'pending_review' => 'warning',
        'needs_completion' => 'info',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $requestStatusClass = static fn (?string $status): string => match ($status) {
        'submitted', 'under_review' => 'warning',
        'approved' => 'success',
        'rejected', 'needs_clarification' => 'danger',
        default => 'secondary',
    };
    $profileStats = $entityAnalytics['stats'];
    $chartData = $entityAnalytics['charts'];
    $translateOrFallback = static function (string $translationKey, string $fallback): string {
        $translated = __($translationKey);

        return $translated === $translationKey ? $fallback : $translated;
    };
    $formatFallback = static fn (?string $value): string => filled($value) ? str((string) $value)->replace('_', ' ')->title()->toString() : __('app.dashboard.not_available');
    $applicationsByTypeChart = collect($chartData['applications_by_type'])
        ->map(fn (int $count, string $key): array => ['label' => $translateOrFallback('app.applications.work_categories.'.$key, $formatFallback($key)), 'value' => $count])
        ->values();
    $budgetByProjectChart = collect($chartData['budget_by_project'])->values();
    $applicationsByMonthLabels = collect($chartData['applications_by_month'])->pluck('label')->values();
    $applicationsByMonthCounts = collect($chartData['applications_by_month'])->pluck('count')->values();
    $crewByProjectChart = collect($chartData['crew_by_project'])->values();
    $authorityResponseChart = collect($chartData['authority_response_average'])
        ->map(fn (array $row): array => ['label' => $translateOrFallback('app.applications.required_approval_options.'.$row['code'], $formatFallback($row['code'])), 'value' => $row['average_hours']])
        ->values();
    $memberSinceYear = optional($entity->created_at)->format('Y') ?: now()->format('Y');
@endphp

@extends('layouts.portal-dashboard', ['title' => $title])

@section('page_layout_class', 'portal-profile-layout py-0')

@push('styles')
    <style>
        .portal-profile-layout {
            padding-top: 0;
        }

        .portal-profile-layout .card {
            margin-bottom: 1.5rem;
        }

        .portal-profile-layout .portal-summary-card .card-body {
            padding: 1.5rem 1.75rem;
        }

        .portal-profile-layout .portal-summary-card img {
            width: 70px;
            height: 70px;
            object-fit: cover;
        }

        .portal-profile-layout .portal-profile-stat-card .card-body {
            padding: 1.25rem 1.35rem;
        }

        .portal-profile-layout .portal-profile-chart-card .card-body {
            min-height: 310px;
            padding: 1.25rem 1.35rem 1rem;
        }

        .portal-profile-layout .portal-chart-surface {
            min-height: 240px;
        }

        .portal-profile-layout .portal-empty-chart {
            min-height: 240px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .portal-profile-layout .card-header {
            font-size: 1rem;
            font-weight: 600;
            padding: 1rem 1.35rem 0;
        }

        .portal-profile-layout .portal-profile-projects .card-header {
            padding-bottom: 1rem;
        }

        .portal-profile-layout table thead th,
        .portal-profile-layout table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .portal-profile-layout .portal-profile-table-scroll {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
        }

        .portal-profile-layout .portal-profile-table {
            min-width: 860px;
            table-layout: fixed;
            width: 100%;
        }

        .portal-profile-layout .portal-profile-table thead th,
        .portal-profile-layout .portal-profile-table tbody td {
            white-space: normal;
            vertical-align: top;
            word-break: break-word;
        }

        .portal-profile-layout .portal-profile-stat-card h6 {
            margin-bottom: .5rem;
            font-size: .95rem;
            font-weight: 600;
        }

        .portal-profile-layout .portal-profile-projects .card-body {
            padding-top: 0;
        }

        .portal-profile-layout .portal-profile-stat-card h3 {
            margin-bottom: 0;
        }

        .portal-profile-layout .portal-chart-grid + .portal-chart-grid {
            margin-top: 1rem;
        }

        @media (max-width: 767.98px) {
            .portal-profile-layout .portal-summary-card .card-body {
                padding: 1.25rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="card portal-summary-card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <img src="{{ asset('images/OIP.jpeg') }}" class="rounded-circle" width="70" alt="entity">
                <div>
                    <h4 class="mb-0">{{ $entity->displayName() }}</h4>
                    <small class="text-muted">{{ data_get($entity->metadata, 'description', $entity->localizedRegistrationType()) }}</small>
                </div>
            </div>

            <div class="text-end">
                <span class="badge bg-{{ $entityStatusClass($entity->status) }}">{{ $entity->localizedStatus() }}</span>
                <div class="text-muted mt-1">{{ __('app.admin.entities.profile_member_since', ['year' => $memberSinceYear]) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        @foreach ([
            ['label' => __('app.admin.entities.profile_metrics.production_requests'), 'value' => $profileStats['production_requests']],
            ['label' => __('app.admin.entities.profile_metrics.scouting_requests'), 'value' => $profileStats['scouting_requests']],
            ['label' => __('app.admin.entities.profile_metrics.previous_projects'), 'value' => $profileStats['previous_projects']],
            ['label' => __('app.admin.entities.profile_metrics.approval_average'), 'value' => $profileStats['approval_average'].'%'],
        ] as $metric)
            <div class="col-md-3">
                <div class="card portal-profile-stat-card">
                    <div class="card-body">
                        <h6>{{ $metric['label'] }}</h6>
                        <h3>{{ $metric['value'] }}</h3>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row mt-4 portal-chart-grid">
        <div class="col-md-6">
            <div class="card portal-profile-chart-card">
                <div class="card-header">{{ __('app.admin.entities.profile_charts.applications_by_type') }}</div>
                <div class="card-body"><div id="chartType" class="portal-chart-surface"></div></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card portal-profile-chart-card">
                <div class="card-header">{{ __('app.admin.entities.profile_charts.budget_by_project') }}</div>
                <div class="card-body"><div id="chartBudget" class="portal-chart-surface"></div></div>
            </div>
        </div>
    </div>

    <div class="row portal-chart-grid">
        <div class="col-md-6 mt-3">
            <div class="card portal-profile-chart-card">
                <div class="card-header">{{ __('app.admin.entities.profile_charts.applications_by_month') }}</div>
                <div class="card-body"><div id="chartMonths" class="portal-chart-surface"></div></div>
            </div>
        </div>

        <div class="col-md-6 mt-3">
            <div class="card portal-profile-chart-card">
                <div class="card-header">{{ __('app.admin.entities.profile_charts.crew_by_project') }}</div>
                <div class="card-body"><div id="chartActors" class="portal-chart-surface"></div></div>
            </div>
        </div>
    </div>

    <div class="row portal-chart-grid">
        <div class="col-md-12 mt-3">
            <div class="card portal-profile-chart-card">
                <div class="card-header">{{ __('app.admin.entities.profile_charts.authority_response_average') }}</div>
                <div class="card-body"><div id="chartGovResponse" class="portal-chart-surface"></div></div>
            </div>
        </div>
    </div>

    <div class="card mt-4 portal-profile-projects">
        <div class="card-header">{{ __('app.admin.entities.profile_previous_projects') }}</div>
        <div class="card-body">
            <div class="table-responsive portal-profile-table-scroll">
                <table class="table portal-profile-table portal-profile-projects-table">
                    <colgroup>
                        <col style="width: 280px">
                        <col style="width: 180px">
                        <col style="width: 170px">
                        <col style="width: 130px">
                        <col style="width: 100px">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>{{ __('app.applications.project_name') }}</th>
                            <th>{{ __('app.applications.work_category') }}</th>
                            <th>{{ __('app.applications.estimated_budget') }}</th>
                            <th>{{ __('app.applications.status') }}</th>
                            <th>{{ __('app.admin.entities.profile_project_year') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($previousProjects as $project)
                            <tr>
                                <td><a href="{{ route('applications.show', $project) }}">{{ $project->project_name }}</a></td>
                                <td>{{ $translateOrFallback('app.applications.work_categories.'.$project->work_category, $formatFallback($project->work_category)) }}</td>
                                <td>{{ $project->estimated_budget ? number_format((float) $project->estimated_budget, 2) : __('app.dashboard.not_available') }}</td>
                                <td><span class="badge bg-{{ $requestStatusClass($project->status) }}">{{ $project->localizedStatus() }}</span></td>
                                <td>{{ optional($project->created_at)->format('Y') ?: __('app.dashboard.not_available') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">{{ __('app.applications.empty_state') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const renderEmptyState = function (selector, message) {
                const element = document.querySelector(selector);

                if (element) {
                    element.innerHTML = '<div class="portal-empty-chart">' + message + '</div>';
                }
            };

            const applicationsByTypeChart = @json($applicationsByTypeChart);
            const budgetByProjectChart = @json($budgetByProjectChart);
            const applicationsByMonthLabels = @json($applicationsByMonthLabels);
            const applicationsByMonthCounts = @json($applicationsByMonthCounts);
            const crewByProjectChart = @json($crewByProjectChart);
            const authorityResponseChart = @json($authorityResponseChart);
            const emptyMessage = @json(__('app.admin.dashboard.chart_no_data'));
            const palette = ['#5e1d19', '#4b1714', '#38120f', '#1f0908', '#7a2a21', '#c4a5a1'];

            if (applicationsByTypeChart.length > 0) {
                new ApexCharts(document.querySelector('#chartType'), {
                    chart: { type: 'donut', height: 300 },
                    series: applicationsByTypeChart.map((row) => row.value),
                    labels: applicationsByTypeChart.map((row) => row.label),
                    colors: palette,
                    legend: { position: 'bottom' }
                }).render();
            } else {
                renderEmptyState('#chartType', emptyMessage);
            }

            if (budgetByProjectChart.length > 0) {
                new ApexCharts(document.querySelector('#chartBudget'), {
                    chart: { type: 'bar', height: 300, toolbar: { show: false } },
                    series: [{ name: @json(__('app.admin.entities.profile_charts.budget_by_project')), data: budgetByProjectChart.map((row) => row.value) }],
                    xaxis: { categories: budgetByProjectChart.map((row) => row.label) },
                    colors: ['#5e1d19'],
                    dataLabels: { enabled: true }
                }).render();
            } else {
                renderEmptyState('#chartBudget', emptyMessage);
            }

            if (applicationsByMonthCounts.length > 0) {
                new ApexCharts(document.querySelector('#chartMonths'), {
                    chart: { type: 'line', height: 300, toolbar: { show: false } },
                    series: [{ name: @json(__('app.admin.entities.profile_charts.applications_by_month')), data: applicationsByMonthCounts }],
                    xaxis: { categories: applicationsByMonthLabels },
                    stroke: { curve: 'smooth' },
                    colors: ['#5e1d19']
                }).render();
            } else {
                renderEmptyState('#chartMonths', emptyMessage);
            }

            if (crewByProjectChart.length > 0) {
                new ApexCharts(document.querySelector('#chartActors'), {
                    chart: { type: 'bar', height: 300, toolbar: { show: false } },
                    series: [{ name: @json(__('app.admin.entities.profile_charts.crew_by_project')), data: crewByProjectChart.map((row) => row.value) }],
                    xaxis: { categories: crewByProjectChart.map((row) => row.label) },
                    colors: ['#5e1d19'],
                    dataLabels: { enabled: true }
                }).render();
            } else {
                renderEmptyState('#chartActors', emptyMessage);
            }

            if (authorityResponseChart.length > 0) {
                new ApexCharts(document.querySelector('#chartGovResponse'), {
                    chart: { type: 'radar', height: 350, toolbar: { show: false } },
                    series: [{ name: @json(__('app.admin.entities.profile_charts.authority_response_average')), data: authorityResponseChart.map((row) => row.value) }],
                    labels: authorityResponseChart.map((row) => row.label),
                    colors: ['#5e1d19']
                }).render();
            } else {
                renderEmptyState('#chartGovResponse', emptyMessage);
            }
        });
    </script>
@endpush
