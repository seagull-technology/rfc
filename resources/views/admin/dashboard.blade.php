@php
    $title = __('app.admin.dashboard.title');
    $breadcrumb = __('app.admin.navigation.dashboard');
    $notificationItems = $reviewQueue->take(5);
    $profileEntityName = $entity?->displayName() ?? __('app.dashboard.no_entity');
    $profileEmail = $admin->email;
    $statusClass = static fn (?string $status): string => match ($status) {
        'approved' => 'success',
        'under_review', 'submitted', 'pending_review' => 'warning',
        'rejected' => 'danger',
        'draft' => 'secondary',
        default => 'info',
    };
    $translateOrFallback = static function (string $translationKey, string $fallback): string {
        $translated = __($translationKey);

        return $translated === $translationKey ? $fallback : $translated;
    };
    $formatFallback = static fn (?string $value): string => filled($value) ? str((string) $value)->replace('_', ' ')->title()->toString() : __('app.dashboard.not_available');
    $applicationsByCategoryChart = $chartData['applications_by_category']
        ->map(fn (int $count, string $key): array => ['label' => $translateOrFallback('app.applications.work_categories.'.$key, $formatFallback($key)), 'value' => $count])
        ->values();
    $applicationsByReleaseChart = $chartData['applications_by_release_method']
        ->map(fn (int $count, string $key): array => ['label' => $translateOrFallback('app.applications.release_methods.'.$key, $formatFallback($key)), 'value' => $count])
        ->values();
    $registrationBreakdownChart = $chartData['registrations_by_type']
        ->map(fn (int $count, string $key): array => ['label' => __('app.registration_types.'.$key), 'value' => $count])
        ->values();
    $approvalDurationChart = $chartData['approval_duration_by_authority']
        ->map(fn (array $row): array => ['label' => $translateOrFallback('app.applications.required_approval_options.'.$row['code'], $formatFallback($row['code'])), 'value' => $row['average_hours']])
        ->values();
    $monthlyApplicationLabels = $chartData['monthly_applications']->pluck('label')->values();
    $monthlyApplicationCounts = $chartData['monthly_applications']->pluck('count')->values();
    $registrationTotal = max(1, (int) $stats['registrations']);
    $applicationTotal = max(1, (int) $stats['applications']);
    $userTotal = max(1, (int) $stats['users']);
    $individualPercent = number_format(($stats['individuals'] / $registrationTotal) * 100, 2);
    $organizationPercent = number_format(($stats['organizations'] / $registrationTotal) * 100, 2);
    $approvedApplicationPercent = number_format(($stats['approved_applications'] / $applicationTotal) * 100, 2);
    $activeUserPercent = number_format(($stats['active_users'] / $userTotal) * 100, 2);
    $pendingReviewPercent = number_format(($stats['pending_reviews'] / $registrationTotal) * 100, 2);
    $workflowTotal = max(1, (int) ($stats['workflow_needs_admin_review'] + $stats['workflow_waiting_applicant'] + $stats['workflow_waiting_authorities'] + $stats['workflow_ready_final_decision'] + $stats['workflow_assign_reviewer']));
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('page_layout_class', 'admin-template-layout')

@push('styles')
    <style>
        .admin-template-layout {
            padding-top: 0;
        }

        .admin-template-layout > .row > [class*="col-"] {
            margin-bottom: 1.5rem;
        }

        .admin-template-layout .card {
            margin-bottom: 0;
        }

        .admin-template-layout .card-header {
            padding-bottom: 0;
        }

        .admin-template-layout .card-dashboard .card-header {
            padding-bottom: 0;
        }

        .admin-template-layout .card-dashboard .card-body > .table-responsive:first-child {
            margin-top: 1rem;
        }

        .admin-template-layout .admin-mini-card .card-body {
            min-height: 178px;
        }

        .admin-template-layout .admin-sparkline {
            min-height: 58px;
        }

        .admin-template-layout .admin-chart-card .card-body {
            min-height: 350px;
        }

        .admin-template-layout .admin-chart-surface {
            min-height: 280px;
        }

        .admin-template-layout .admin-wide-chart .card-body {
            min-height: 320px;
        }

        .admin-template-layout .admin-empty-chart {
            min-height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .admin-template-layout .table thead th,
        .admin-template-layout .table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .admin-template-layout .badge.bg-light.text-dark {
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .admin-template-layout .workflow-queue-item td {
            white-space: normal;
        }

        .admin-template-layout .response-flag {
            margin-top: .5rem;
        }

        .admin-template-layout .response-flag .small {
            display: block;
            margin-top: .35rem;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4 px-0">
                <div>
                    <h2 class="episode-playlist-title wp-heading-inline mb-1">
                        <span class="position-relative">{{ __('app.admin.dashboard.title') }}</span>
                    </h2>
                    <div class="text-muted">{{ __('app.admin.dashboard.intro') }}</div>
                </div>
                <a class="btn btn-primary" href="{{ route('admin.reports.export') }}">{{ __('app.reports.export_dashboard') }}</a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="row">
                <div class="col-lg-3">
                    <div class="card admin-mini-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div class="rounded p-3 bg-primary-subtle">
                                    <i class="ph ph-film-strip fs-3"></i>
                                </div>
                                <div>
                                    <span>{{ __('app.admin.dashboard.metrics.applications') }}</span>
                                </div>
                            </div>
                            <div class="text-center">
                                <h2 class="counter">{{ $stats['applications'] }}</h2>
                                <div>
                                    <span>{{ $approvedApplicationPercent }}%</span>
                                    <span>{{ __('app.statuses.approved') }}</span>
                                </div>
                            </div>
                        </div>
                        <div id="admin-stat-applications" class="admin-sparkline"></div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-itmes-center">
                                <div>
                                    <div class="p-3 rounded bg-primary-subtle">
                                        <i class="ph ph-user fs-2"></i>
                                    </div>
                                </div>
                                <div>
                                    <h1 class="counter">{{ $stats['individuals'] }}</h1>
                                    <p class="mb-0">{{ __('app.admin.dashboard.metrics.individuals') }}</p>
                                </div>
                                <div>
                                    <div class="badge bg-primary">
                                        <span>{{ $individualPercent }}%</span>
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
                                        <i class="ph ph-buildings fs-2"></i>
                                    </div>
                                </div>
                                <div>
                                    <h1 class="counter">{{ $stats['organizations'] }}</h1>
                                    <p class="mb-0">{{ __('app.admin.dashboard.metrics.organizations') }}</p>
                                </div>
                                <div>
                                    <div class="badge bg-primary">
                                        <span>{{ $organizationPercent }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card admin-mini-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div class="rounded p-3 bg-primary-subtle">
                                    <i class="ph ph-users-three fs-3"></i>
                                </div>
                                <div>
                                    <span>{{ __('app.admin.dashboard.metrics.users') }}</span>
                                </div>
                            </div>
                            <div class="text-center">
                                <h2 class="counter">{{ $stats['users'] }}</h2>
                                <div>
                                    <span>{{ $activeUserPercent }}%</span>
                                    <span>{{ __('app.statuses.active') }}</span>
                                </div>
                            </div>
                        </div>
                        <div id="admin-stat-users" class="admin-sparkline"></div>
                    </div>
                </div>

                <div class="col-lg-6 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-center">{{ __('app.admin.dashboard.metrics.pending_reviews') }}</div>
                            <div class="d-flex align-items-center justify-content-between mt-3">
                                <div>
                                    <h2 class="counter">{{ $stats['pending_reviews'] }}</h2>
                                    {{ $pendingReviewPercent }}%
                                </div>
                                <div class="border bg-danger-subtle rounded p-3">
                                    <i class="ph ph-hourglass-medium fs-1"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="progress bg-danger-subtle shadow-none w-100" style="height: 6px">
                                    <div class="progress-bar bg-danger" style="width: {{ $pendingReviewPercent }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-center">{{ __('app.admin.dashboard.metrics.ready_final_decision') }}</div>
                            <div class="d-flex align-items-center justify-content-between mt-3">
                                <div>
                                    <h2 class="counter">{{ $stats['workflow_ready_final_decision'] }}</h2>
                                    {{ number_format(($stats['workflow_ready_final_decision'] / $workflowTotal) * 100, 2) }}%
                                </div>
                                <div class="border bg-primary-subtle rounded p-3">
                                    <i class="ph ph-seal-check fs-1"></i>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="progress bg-primary-subtle shadow-none w-100" style="height: 6px">
                                    <div class="progress-bar bg-primary" style="width: {{ number_format(($stats['workflow_ready_final_decision'] / $workflowTotal) * 100, 2) }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-center">{{ __('app.admin.dashboard.metrics.assign_reviewer') }}</div>
                            <div class="d-flex align-items-center justify-content-between mt-3">
                                <div>
                                    <h2 class="counter">{{ $stats['workflow_assign_reviewer'] }}</h2>
                                    {{ number_format(($stats['workflow_assign_reviewer'] / $workflowTotal) * 100, 2) }}%
                                </div>
                                <div class="border bg-warning-subtle rounded p-3">
                                    <i class="ph ph-user-plus fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-center">{{ __('app.admin.dashboard.metrics.waiting_applicant') }}</div>
                            <div class="d-flex align-items-center justify-content-between mt-3">
                                <div>
                                    <h2 class="counter">{{ $stats['workflow_waiting_applicant'] }}</h2>
                                    {{ number_format(($stats['workflow_waiting_applicant'] / $workflowTotal) * 100, 2) }}%
                                </div>
                                <div class="border bg-danger-subtle rounded p-3">
                                    <i class="ph ph-chat-circle-dots fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-center">{{ __('app.admin.dashboard.metrics.waiting_authorities') }}</div>
                            <div class="d-flex align-items-center justify-content-between mt-3">
                                <div>
                                    <h2 class="counter">{{ $stats['workflow_waiting_authorities'] }}</h2>
                                    {{ number_format(($stats['workflow_waiting_authorities'] / $workflowTotal) * 100, 2) }}%
                                </div>
                                <div class="border bg-info-subtle rounded p-3">
                                    <i class="ph ph-buildings fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-block card-height card-dashboard admin-chart-card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.dashboard.charts.applications_by_category') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div id="admin-category-chart" class="admin-chart-surface d-flex justify-content-center"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card card-block card-height card-dashboard admin-chart-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.dashboard.charts.monthly_applications') }}</h3>
                    </div>
                    <div class="dropdown">
                        <button class="btn custom-btn-dark-dropdown total-revenue" type="button">{{ now()->year }}</button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="admin-monthly-chart" class="admin-chart-surface"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card card-dashboard admin-chart-card">
                <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.dashboard.charts.release_methods') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div id="admin-release-chart" class="admin-chart-surface"></div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card card-block card-height card-dashboard admin-wide-chart">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.dashboard.charts.approval_duration') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div id="admin-authority-duration-chart" class="admin-chart-surface d-flex align-items-center justify-content-center"></div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card card-dashboard">
                <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.dashboard.workflow_queue_title') }}</h3>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="mt-4 table-responsive">
                        <div class="table-responsive border rounded py-4">
                            <table class="table" data-toggle="data-table">
                                <thead>
                                    <tr class="ligth">
                                        <th>#</th>
                                        <th>{{ __('app.admin.dashboard.workflow_type') }}</th>
                                        <th>{{ __('app.applications.project_name') }}</th>
                                        <th>{{ __('app.admin.dashboard.workflow_checkpoint') }}</th>
                                        <th>{{ __('app.applications.updated_at') }}</th>
                                        <th>{{ __('app.admin.applications.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($workflowQueue as $queueItem)
                                        <tr class="workflow-queue-item">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $queueItem['type'] }}</td>
                                            <td>
                                                {{ $queueItem['project_name'] }}<br>
                                                <span class="text-muted">{{ $queueItem['entity'] }}</span>
                                                @if ($queueItem['applicant_response']['active'])
                                                    <div class="response-flag">
                                                        <span class="badge bg-primary">{{ $queueItem['applicant_response']['title'] }}</span>
                                                        <span class="small text-muted">{{ $queueItem['applicant_response']['summary'] }}</span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $queueItem['checkpoint']['class'] }}">{{ $queueItem['checkpoint']['label'] }}</span><br>
                                                <span class="text-muted">{{ $queueItem['status_label'] }}</span>
                                            </td>
                                            <td>{{ $queueItem['updated_at']?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</td>
                                            <td>
                                                <div class="flex align-items-center list-user-action">
                                                    <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ $queueItem['url'] }}">
                                                        <i class="ph ph-eye fs-6"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">{{ __('app.admin.dashboard.workflow_queue_empty') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="card card-block card-height card-dashboard admin-chart-card">
                        <div class="card-header">
                            <div class="iq-header-title">
                                <h3 class="card-title">{{ __('app.admin.dashboard.charts.registrations_breakdown') }}</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="admin-registration-chart" class="admin-chart-surface d-flex align-items-center justify-content-center"></div>
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
                                <div class="table-responsive border rounded py-4">
                                    <table id="admin-recent-requests-table" class="table" data-toggle="data-table">
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
@endsection

@push('scripts')
    <script>
        window.addEventListener('load', function () {
            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const chartNoDataText = @json(__('app.admin.dashboard.chart_no_data'));
            const primaryColor = '#3b82f6';
            const dangerColor = '#b52b1e';
            const successColor = '#198754';
            const orangeColor = '#f97316';
            const mutedPalette = ['#3b82f6', '#f97316', '#ef4444', '#22c55e', '#8b5cf6', '#06b6d4'];

            const renderEmptyState = function (selector) {
                const element = document.querySelector(selector);
                if (!element) {
                    return false;
                }

                element.innerHTML = '<div class="admin-empty-chart">' + chartNoDataText + '</div>';

                return true;
            };

            const renderChart = function (selector, hasData, options) {
                if (!hasData) {
                    renderEmptyState(selector);
                    return;
                }

                const element = document.querySelector(selector);
                if (!element) {
                    return;
                }

                element.innerHTML = '';

                const chart = new ApexCharts(element, options);
                chart.render();
            };

            const monthlyLabels = @json($monthlyApplicationLabels);
            const monthlyCounts = @json($monthlyApplicationCounts);
            const categoryData = @json($applicationsByCategoryChart);
            const releaseData = @json($applicationsByReleaseChart);
            const approvalDurationData = @json($approvalDurationChart);
            const registrationData = @json($registrationBreakdownChart);

            renderChart('#admin-stat-applications', monthlyCounts.some(function (value) { return value > 0; }), {
                chart: {
                    type: 'area',
                    height: 58,
                    sparkline: { enabled: true },
                    toolbar: { show: false }
                },
                series: [{ name: 'Applications', data: monthlyCounts }],
                colors: [primaryColor],
                stroke: { curve: 'smooth', width: 2 },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.4,
                        opacityTo: 0.05
                    }
                },
                tooltip: { x: { show: false } }
            });

            renderChart('#admin-stat-users', monthlyCounts.some(function (value) { return value > 0; }), {
                chart: {
                    type: 'area',
                    height: 58,
                    sparkline: { enabled: true },
                    toolbar: { show: false }
                },
                series: [{ name: 'Users', data: monthlyCounts.map(function (value, index) { return value + index; }) }],
                colors: [dangerColor],
                stroke: { curve: 'smooth', width: 2 },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.35,
                        opacityTo: 0.05
                    }
                },
                tooltip: { x: { show: false } }
            });

            renderChart('#admin-category-chart', categoryData.length > 0, {
                chart: {
                    type: 'donut',
                    height: 300
                },
                series: categoryData.map(function (item) { return item.value; }),
                labels: categoryData.map(function (item) { return item.label; }),
                colors: mutedPalette,
                legend: {
                    position: 'bottom'
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    width: 0
                }
            });

            renderChart('#admin-monthly-chart', monthlyCounts.some(function (value) { return value > 0; }), {
                chart: {
                    type: 'area',
                    height: 300,
                    toolbar: { show: false }
                },
                series: [{ name: 'Applications', data: monthlyCounts }],
                xaxis: {
                    categories: monthlyLabels
                },
                colors: [primaryColor],
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.35,
                        opacityTo: 0.05
                    }
                },
                dataLabels: { enabled: false },
                yaxis: {
                    min: 0,
                    forceNiceScale: true
                },
                grid: {
                    borderColor: 'rgba(129, 129, 129, 0.12)'
                }
            });

            renderChart('#admin-release-chart', releaseData.length > 0, {
                chart: {
                    type: 'bar',
                    height: 300,
                    toolbar: { show: false }
                },
                series: [{
                    name: 'Requests',
                    data: releaseData.map(function (item) { return item.value; })
                }],
                xaxis: {
                    categories: releaseData.map(function (item) { return item.label; })
                },
                colors: [orangeColor],
                plotOptions: {
                    bar: {
                        borderRadius: 6,
                        columnWidth: '45%'
                    }
                },
                dataLabels: { enabled: false },
                grid: {
                    borderColor: 'rgba(129, 129, 129, 0.12)'
                }
            });

            renderChart('#admin-authority-duration-chart', approvalDurationData.length > 0, {
                chart: {
                    type: 'bar',
                    height: 280,
                    toolbar: { show: false }
                },
                series: [{
                    name: 'Hours',
                    data: approvalDurationData.map(function (item) { return item.value; })
                }],
                xaxis: {
                    categories: approvalDurationData.map(function (item) { return item.label; })
                },
                colors: [dangerColor],
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 6,
                        barHeight: '50%'
                    }
                },
                dataLabels: { enabled: false },
                grid: {
                    borderColor: 'rgba(129, 129, 129, 0.12)'
                }
            });

            renderChart('#admin-registration-chart', registrationData.length > 0, {
                chart: {
                    type: 'polarArea',
                    height: 300,
                    toolbar: { show: false }
                },
                series: registrationData.map(function (item) { return item.value; }),
                labels: registrationData.map(function (item) { return item.label; }),
                colors: mutedPalette,
                stroke: {
                    colors: ['#fff']
                },
                legend: {
                    position: 'bottom'
                }
            });
        });
    </script>
@endpush
