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
    $profileChangeStatusClass = static fn (?string $status): string => match ($status) {
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
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
        ->map(fn (int $count, string $key): array => ['label' => \App\Models\WorkCategory::labelFor($key), 'value' => $count])
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

        .portal-profile-layout .portal-profile-form-card .card-body {
            padding: 1.35rem;
        }

        .portal-profile-layout .portal-profile-official-field {
            border: 1px solid #e5e9f0;
            background: #f8f9fb;
            padding: .9rem 1rem;
            min-height: 78px;
        }

        .portal-profile-layout .portal-profile-logo-preview {
            width: 96px;
            height: 96px;
            object-fit: contain;
            background: #fff;
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
                <img src="{{ $profileLogoUrl }}" class="rounded-circle" width="70" alt="entity">
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

    @if ($canManageEntityProfile)
        <div class="row g-3 mb-4">
            <div class="col-xl-6">
                <div class="card portal-profile-form-card h-100">
                    <div class="card-header">{{ __('app.profile.account_settings_title') }}</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('profile.account.update') }}" class="row g-3">
                            @csrf
                            <div class="col-md-6">
                                <label for="profile_email" class="form-label">{{ __('app.auth.email') }}</label>
                                <input id="profile_email" name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="profile_phone" class="form-label">{{ __('app.auth.mobile_number') }}</label>
                                <input id="profile_phone" name="phone" type="text" class="form-control" value="{{ old('phone', $user->phone) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label for="profile_current_password" class="form-label">{{ __('app.profile.current_password') }}</label>
                                <input id="profile_current_password" name="current_password" type="password" class="form-control" autocomplete="current-password">
                            </div>
                            <div class="col-md-4">
                                <label for="profile_password" class="form-label">{{ __('app.profile.new_password') }}</label>
                                <input id="profile_password" name="password" type="password" class="form-control" autocomplete="new-password">
                            </div>
                            <div class="col-md-4">
                                <label for="profile_password_confirmation" class="form-label">{{ __('app.profile.confirm_password') }}</label>
                                <input id="profile_password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password">
                            </div>
                            <div class="col-12">
                                <small class="text-muted">{{ __('app.profile.password_hint') }}</small>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-danger" type="submit">{{ __('app.profile.save_account_action') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card portal-profile-form-card h-100">
                    <div class="card-header">{{ __('app.profile.contact_settings_title') }}</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('profile.contact.update') }}" enctype="multipart/form-data" class="row g-3">
                            @csrf
                            <div class="col-md-6">
                                <label for="entity_email" class="form-label">{{ __('app.profile.entity_email') }}</label>
                                <input id="entity_email" name="email" type="email" class="form-control" value="{{ old('email', $entity->email) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="entity_phone" class="form-label">{{ __('app.profile.entity_phone') }}</label>
                                <input id="entity_phone" name="phone" type="text" class="form-control" value="{{ old('phone', $entity->phone) }}" required>
                            </div>
                            <div class="col-12">
                                <label for="entity_address" class="form-label">{{ __('app.dashboard.address') }}</label>
                                <input id="entity_address" name="address" type="text" class="form-control" value="{{ old('address', data_get($entity->metadata, 'address')) }}">
                            </div>
                            <div class="col-md-6">
                                <label for="entity_website_url" class="form-label">{{ __('app.profile.website_url') }}</label>
                                <input id="entity_website_url" name="website_url" type="url" class="form-control" value="{{ old('website_url', data_get($entity->metadata, 'website_url')) }}">
                            </div>
                            <div class="col-md-6">
                                <label for="entity_logo" class="form-label">{{ __('app.profile.logo') }}</label>
                                <input id="entity_logo" name="logo" type="file" class="form-control" accept="image/png">
                                <small class="text-muted">{{ __('app.profile.logo_hint') }}</small>
                            </div>
                            <div class="col-12 d-flex align-items-center gap-3">
                                <img src="{{ $profileLogoUrl }}" alt="{{ __('app.profile.logo') }}" class="portal-profile-logo-preview border p-2">
                                <div class="text-muted">{{ data_get($entity->metadata, 'logo_name', __('app.profile.logo_empty')) }}</div>
                            </div>
                            <div class="col-12">
                                <label for="entity_description" class="form-label">{{ __('app.dashboard.organization_description') }}</label>
                                <textarea id="entity_description" name="description" rows="3" class="form-control">{{ old('description', data_get($entity->metadata, 'description')) }}</textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-danger" type="submit">{{ __('app.profile.save_contact_action') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card portal-profile-form-card">
                    <div class="card-header">{{ __('app.profile.official_data_title') }}</div>
                    <div class="card-body">
                        <div class="row g-3 mb-4">
                            @foreach ($profileOfficialFields as $field => $definition)
                                <div class="col-md-4">
                                    <div class="portal-profile-official-field">
                                        <small class="text-muted d-block">{{ $definition['label'] }}</small>
                                        <strong>
                                            @if ($field === 'gender' && filled($definition['current']))
                                                {{ __('app.auth.gender_options.'.$definition['current']) }}
                                            @else
                                                {{ $definition['current'] ?: __('app.dashboard.not_available') }}
                                            @endif
                                        </strong>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($pendingProfileChangeRequest)
                            <div class="alert alert-warning mb-0">{{ __('app.profile.official_change_pending_notice') }}</div>
                        @else
                            <form method="POST" action="{{ route('profile.official-change-request.store') }}" class="row g-3">
                                @csrf
                                @foreach ($profileOfficialFields as $field => $definition)
                                    <div class="col-md-4">
                                        <label for="official_{{ $field }}" class="form-label">{{ $definition['label'] }}</label>
                                        @if ($definition['type'] === 'gender')
                                            <select id="official_{{ $field }}" name="{{ $field }}" class="form-select">
                                                <option value="">{{ __('app.auth.select_placeholder') }}</option>
                                                @foreach (['male', 'female'] as $gender)
                                                    <option value="{{ $gender }}" @selected(old($field, $definition['current']) === $gender)>{{ __('app.auth.gender_options.'.$gender) }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input id="official_{{ $field }}" name="{{ $field }}" type="{{ $definition['type'] === 'number' ? 'number' : ($definition['type'] === 'date' ? 'date' : 'text') }}" class="form-control" value="{{ old($field, $definition['current']) }}" @if (in_array($field, ['name_en', 'name_ar'], true)) required @endif>
                                        @endif
                                    </div>
                                @endforeach
                                <div class="col-12">
                                    <label for="official_note" class="form-label">{{ __('app.profile.change_note') }}</label>
                                    <textarea id="official_note" name="note" rows="3" class="form-control">{{ old('note') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-danger" type="submit">{{ __('app.profile.submit_official_change_action') }}</button>
                                </div>
                            </form>
                        @endif

                        <hr class="my-4">

                        <div class="table-responsive portal-profile-table-scroll">
                            <table class="table portal-profile-table mb-0">
                                <colgroup>
                                    <col style="width: 130px">
                                    <col style="width: 180px">
                                    <col style="width: 360px">
                                    <col style="width: 260px">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>{{ __('app.applications.status') }}</th>
                                        <th>{{ __('app.profile.requested_at') }}</th>
                                        <th>{{ __('app.profile.changed_fields') }}</th>
                                        <th>{{ __('app.profile.review_note') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($profileChangeRequests as $changeRequest)
                                        <tr>
                                            <td><span class="badge bg-{{ $profileChangeStatusClass($changeRequest['status'] ?? null) }}">{{ __('app.profile.change_statuses.'.($changeRequest['status'] ?? 'pending')) }}</span></td>
                                            <td>{{ $changeRequest['requested_at'] ?? __('app.dashboard.not_available') }}</td>
                                            <td>
                                                @foreach ((array) ($changeRequest['fields'] ?? []) as $change)
                                                    <div class="mb-1">
                                                        <strong>{{ $change['label'] ?? '' }}:</strong>
                                                        {{ $change['current'] ?: __('app.dashboard.not_available') }}
                                                        <span class="text-muted">&rarr;</span>
                                                        {{ $change['requested'] ?: __('app.dashboard.not_available') }}
                                                    </div>
                                                @endforeach
                                            </td>
                                            <td>{{ $changeRequest['review_note'] ?? $changeRequest['note'] ?? __('app.dashboard.not_available') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">{{ __('app.profile.official_change_empty') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

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
                                <td>{{ \App\Models\WorkCategory::labelFor($project->work_category) }}</td>
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
