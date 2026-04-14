@php
    $title = $user->displayName();
    $breadcrumb = __('app.admin.navigation.users');
    $statusClass = static fn (?string $status): string => match ($status) {
        'active' => 'success',
        'pending_review' => 'warning',
        'needs_completion' => 'info',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $primaryEntityDocumentName = data_get($primaryEntity?->metadata, 'registration_document_name');
    $primaryEntityDocumentMime = data_get($primaryEntity?->metadata, 'registration_document_mime');
    $reviewData = (array) data_get($primaryEntity?->metadata, 'review', []);
    $profileStats = $userAnalytics['stats'];
    $chartData = $userAnalytics['charts'];
    $translateOrFallback = static function (string $translationKey, string $fallback): string {
        $translated = __($translationKey);

        return $translated === $translationKey ? $fallback : $translated;
    };
    $formatFallback = static fn (?string $value): string => filled($value) ? str((string) $value)->replace('_', ' ')->title()->toString() : __('app.dashboard.not_available');
    $applicationsByTypeChart = $chartData['applications_by_type']
        ->map(fn (int $count, string $key): array => ['label' => $translateOrFallback('app.applications.work_categories.'.$key, $formatFallback($key)), 'value' => $count])
        ->values();
    $budgetByProjectChart = collect($chartData['budget_by_project'])->values();
    $applicationsByMonthLabels = collect($chartData['applications_by_month'])->pluck('label')->values();
    $applicationsByMonthCounts = collect($chartData['applications_by_month'])->pluck('count')->values();
    $crewByProjectChart = collect($chartData['crew_by_project'])->values();
    $authorityResponseChart = collect($chartData['authority_response_average'])
        ->map(fn (array $row): array => ['label' => $translateOrFallback('app.applications.required_approval_options.'.$row['code'], $formatFallback($row['code'])), 'value' => $row['average_hours']])
        ->values();
    $memberSinceYear = optional($user->created_at)->format('Y') ?: now()->format('Y');
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('page_layout_class', 'admin-user-show-layout py-0')

@push('styles')
    <style>
        .admin-user-show-layout {
            padding-top: 0;
        }

        .admin-user-show-layout .card {
            margin-bottom: 1.5rem;
        }

        .admin-user-show-layout .user-profile-card {
            margin-bottom: 1.5rem;
        }

        .admin-user-show-layout .user-chart-card .card-body {
            min-height: 310px;
        }

        .admin-user-show-layout .user-chart-surface {
            min-height: 240px;
        }

        .admin-user-show-layout .user-empty-chart {
            min-height: 240px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .admin-user-show-layout .card-header {
            padding-bottom: 0;
        }

        .admin-user-show-layout table thead th,
        .admin-user-show-layout table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .admin-user-show-layout .badge.bg-primary-subtle.text-dark {
            border: 1px solid rgba(0, 0, 0, 0.08);
        }
    </style>
@endpush

@section('content')
    <div class="card user-profile-card">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <img src="{{ asset('images/OIP.jpeg') }}" class="rounded-circle" width="70" alt="user">
                <div>
                    <h4 class="mb-0">{{ $user->displayName() }}</h4>
                    <small class="text-muted">{{ $primaryEntity?->displayName() ?? __('app.admin.users.show_intro', ['email' => $user->email]) }}</small>
                </div>
            </div>

            <div class="text-end">
                <span class="badge bg-{{ $statusClass($user->status) }}">{{ $user->localizedStatus() }}</span>
                @if ($user->trashed())
                    <div class="mt-1"><span class="badge bg-danger">{{ __('app.admin.users.deleted_label') }}</span></div>
                @endif
                <div class="text-muted mt-1">{{ __('app.admin.users.profile_member_since', ['year' => $memberSinceYear]) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        @foreach ([
            ['label' => __('app.admin.users.profile_metrics.production_requests'), 'value' => $profileStats['production_requests']],
            ['label' => __('app.admin.users.profile_metrics.scouting_requests'), 'value' => $profileStats['scouting_requests']],
            ['label' => __('app.admin.users.profile_metrics.previous_projects'), 'value' => $profileStats['previous_projects']],
            ['label' => __('app.admin.users.profile_metrics.approval_average'), 'value' => $profileStats['approval_average'].'%'],
        ] as $metric)
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6>{{ $metric['label'] }}</h6>
                        <h3>{{ $metric['value'] }}</h3>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row mt-4 g-3">
        <div class="col-md-6">
            <div class="card user-chart-card">
                <div class="card-header">{{ __('app.admin.users.profile_charts.applications_by_type') }}</div>
                <div class="card-body">
                    <div id="user-chart-type" class="user-chart-surface"></div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card user-chart-card">
                <div class="card-header">{{ __('app.admin.users.profile_charts.budget_by_project') }}</div>
                <div class="card-body">
                    <div id="user-chart-budget" class="user-chart-surface"></div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card user-chart-card">
                <div class="card-header">{{ __('app.admin.users.profile_charts.applications_by_month') }}</div>
                <div class="card-body">
                    <div id="user-chart-months" class="user-chart-surface"></div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card user-chart-card">
                <div class="card-header">{{ __('app.admin.users.profile_charts.crew_by_project') }}</div>
                <div class="card-body">
                    <div id="user-chart-crew" class="user-chart-surface"></div>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card user-chart-card">
                <div class="card-header">{{ __('app.admin.users.profile_charts.authority_response_average') }}</div>
                <div class="card-body">
                    <div id="user-chart-authority-response" class="user-chart-surface"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">{{ __('app.admin.users.profile_previous_projects') }}</div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('app.applications.project_name') }}</th>
                        <th>{{ __('app.applications.work_category') }}</th>
                        <th>{{ __('app.applications.estimated_budget') }}</th>
                        <th>{{ __('app.applications.status') }}</th>
                        <th>{{ __('app.admin.users.profile_project_year') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($userApplications as $project)
                        <tr>
                            <td><a href="{{ route('admin.applications.show', $project) }}">{{ $project->project_name }}</a></td>
                            <td>{{ $translateOrFallback('app.applications.work_categories.'.$project->work_category, $formatFallback($project->work_category)) }}</td>
                            <td>{{ $project->estimated_budget ? number_format((float) $project->estimated_budget, 2) : __('app.dashboard.not_available') }}</td>
                            <td><span class="badge bg-{{ $statusClass($project->status) }}">{{ $project->localizedStatus() }}</span></td>
                            <td>{{ optional($project->created_at)->format('Y') ?: __('app.dashboard.not_available') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">{{ __('app.admin.applications.empty_state') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.users.summary_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.users.name') }}</small>
                            <div>{{ $user->displayName() }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.users.username') }}</small>
                            <div>{{ $user->username ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.users.email') }}</small>
                            <div>{{ $user->email }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.users.national_id') }}</small>
                            <div>{{ $user->national_id ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.users.phone') }}</small>
                            <div>{{ $user->phone ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.users.last_login') }}</small>
                            <div>{{ $user->last_login_at?->format('Y-m-d H:i') ?? __('app.dashboard.not_available') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.users.actions') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap">
                        @if ($primaryEntity && $primaryEntity->isRegistrationReviewable() && ! $primaryEntity->trashed())
                            <a class="btn btn-outline-primary" href="{{ route('admin.entities.show', $primaryEntity->getKey()) }}">{{ __('app.admin.users.open_registration_action') }}</a>
                        @endif
                        @if ($user->trashed())
                            <form method="POST" action="{{ route('admin.users.restore', $user->getKey()) }}">
                                @csrf
                                <button class="btn btn-outline-success" type="submit">{{ __('app.admin.users.restore_action') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.users.status', $user->getKey()) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $user->status === 'active' ? 'inactive' : 'active' }}">
                                <button class="btn btn-outline-warning" type="submit">
                                    {{ $user->status === 'active' ? __('app.admin.users.deactivate_action') : __('app.admin.users.activate_action') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.delete', $user->getKey()) }}">
                                @csrf
                                <button class="btn btn-outline-danger" type="submit">{{ __('app.admin.users.delete_action') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.users.registration_details_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.dashboard.registration_type') }}</small>
                            <div>{{ $user->localizedRegistrationType() }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.users.primary_entity') }}</small>
                            <div>
                                @if ($primaryEntity)
                                    <a href="{{ route('admin.entities.show', $primaryEntity->getKey()) }}">{{ $primaryEntity->displayName() }}</a>
                                @else
                                    {{ __('app.dashboard.no_entity') }}
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.entities.group') }}</small>
                            <div>{{ $primaryEntity?->group?->displayName() ?? __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.users.status') }}</small>
                            <div>{{ $user->localizedStatus() }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.auth.registration_number') }}</small>
                            <div>{{ $primaryEntity?->registration_no ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.auth.organization_national_id') }}</small>
                            <div>{{ $primaryEntity?->national_id ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.auth.email') }}</small>
                            <div>{{ $primaryEntity?->email ?: $user->email ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.auth.mobile_number') }}</small>
                            <div>{{ $primaryEntity?->phone ?: $user->phone ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block">{{ __('app.dashboard.address') }}</small>
                            <div>{{ data_get($primaryEntity?->metadata, 'address', __('app.dashboard.not_available')) }}</div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block">{{ __('app.dashboard.organization_description') }}</small>
                            <div>{{ data_get($primaryEntity?->metadata, 'description', __('app.dashboard.not_available')) }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.entities.registration_document_name') }}</small>
                            <div>{{ $primaryEntityDocumentName ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.entities.registration_document_type') }}</small>
                            <div>{{ $primaryEntityDocumentMime ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        @if ($primaryEntity && data_get($primaryEntity->metadata, 'registration_document_path'))
                            <div class="col-12">
                                <a class="btn btn-outline-primary" href="{{ route('admin.entities.registration-document', $primaryEntity->getKey()) }}">
                                    {{ __('app.admin.entities.download_registration_document') }}
                                </a>
                            </div>
                        @endif
                    </div>

                    @if ($reviewData !== [])
                        <hr class="my-4">

                        <div class="row g-3">
                            <div class="col-12">
                                <h4 class="h6 mb-0">{{ __('app.admin.entities.review_title') }}</h4>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">{{ __('app.admin.entities.review_decision') }}</small>
                                <div>{{ __('app.statuses.'.($primaryEntity?->status ?? 'pending_review')) }}</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">{{ __('app.admin.entities.reviewed_at') }}</small>
                                <div>{{ $reviewData['reviewed_at'] ?? __('app.dashboard.not_available') }}</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">{{ __('app.admin.entities.reviewed_by') }}</small>
                                <div>{{ $reviewedByUser?->displayName() ?? __('app.dashboard.not_available') }}</div>
                            </div>
                            <div class="col-12">
                                <small class="text-muted d-block">{{ __('app.admin.entities.review_note') }}</small>
                                <div>{{ $reviewData['note'] ?? __('app.dashboard.not_available') }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.users.edit_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.update', $user->getKey()) }}" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label for="name" class="form-label">{{ __('app.admin.users.name') }}</label>
                            <input id="name" name="name" type="text" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label">{{ __('app.admin.users.username') }}</label>
                            <input id="username" name="username" type="text" class="form-control" value="{{ old('username', $user->username) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">{{ __('app.admin.users.email') }}</label>
                            <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="national_id" class="form-label">{{ __('app.admin.users.national_id') }}</label>
                            <input id="national_id" name="national_id" type="text" class="form-control" value="{{ old('national_id', $user->national_id) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">{{ __('app.admin.users.phone') }}</label>
                            <input id="phone" name="phone" type="text" class="form-control" value="{{ old('phone', $user->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">{{ __('app.admin.users.status') }}</label>
                            <select id="status" name="status" class="form-select" required>
                                @foreach (['active', 'inactive', 'pending_review', 'needs_completion', 'rejected'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $user->status) === $status)>{{ __('app.statuses.'.$status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">{{ __('app.auth.password') }}</label>
                            <input id="password" name="password" type="password" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">{{ __('app.auth.confirm_password') }}</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="form-control">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-danger" type="submit">{{ __('app.admin.users.update_action') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.users.add_membership_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.memberships.store', $user->getKey()) }}" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label for="entity_id" class="form-label">{{ __('app.admin.users.initial_entity') }}</label>
                            <select id="entity_id" name="entity_id" class="form-select" required>
                                <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                @foreach ($entities as $entity)
                                    <option value="{{ $entity->id }}" data-roles="{{ $entity->group->roles->pluck('name')->join(',') }}" @selected(old('entity_id') == $entity->id)>
                                        {{ $entity->displayName() }} ({{ $entity->group?->displayName() }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label">{{ __('app.admin.users.initial_role') }}</label>
                            <select id="role" name="role" class="form-select" required>
                                <option value="">{{ __('app.admin.select_entity_first') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="job_title" class="form-label">{{ __('app.admin.entities.member_job_title') }}</label>
                            <input id="job_title" name="job_title" type="text" class="form-control" value="{{ old('job_title') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="is_primary" class="form-label">{{ __('app.admin.entities.member_primary') }}</label>
                            <select id="is_primary" name="is_primary" class="form-select">
                                <option value="0" @selected(old('is_primary', '0') === '0')>{{ __('app.admin.no') }}</option>
                                <option value="1" @selected(old('is_primary') === '1')>{{ __('app.admin.yes') }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-danger" type="submit">{{ __('app.admin.users.add_membership_action') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.users.memberships_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive border rounded py-3">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('app.admin.entities.name') }}</th>
                                    <th>{{ __('app.admin.entities.member_role') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($memberships as $membership)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.entities.show', $membership['entity']->getKey()) }}">{{ $membership['entity']->displayName() }}</a><br>
                                            <span class="text-muted">{{ $membership['entity']->group?->displayName() ?? __('app.dashboard.not_available') }}</span><br>
                                            <span class="text-muted">{{ $membership['entity']->pivot?->job_title ?: __('app.dashboard.not_available') }}</span>
                                        </td>
                                        <td>
                                            @forelse ($membership['roles'] as $roleName)
                                                <span class="badge bg-primary-subtle text-dark">{{ __('app.roles.'.$roleName) }}</span>
                                            @empty
                                                {{ __('app.dashboard.no_roles') }}
                                            @endforelse
                                            <br>
                                            <span class="text-muted d-inline-block mt-2">{{ $membership['entity']->pivot?->is_primary ? __('app.admin.yes') : __('app.admin.no') }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const entitySelect = document.getElementById('entity_id');
            const roleSelect = document.getElementById('role');
            const selectedRole = @json(old('role'));
            const labels = @json($entities->flatMap(fn ($entity) => $entity->group->roles->pluck('name'))->unique()->mapWithKeys(fn ($roleName) => [$roleName => __('app.roles.'.$roleName)]));

            const populateRoles = () => {
                const selectedOption = entitySelect.options[entitySelect.selectedIndex];
                const roles = (selectedOption?.dataset.roles || '').split(',').filter(Boolean);

                roleSelect.innerHTML = '';

                if (!roles.length) {
                    roleSelect.innerHTML = `<option value="">{{ __('app.admin.select_entity_first') }}</option>`;
                    return;
                }

                roleSelect.innerHTML = `<option value="">{{ __('app.admin.select_placeholder') }}</option>`;

                roles.forEach((roleName) => {
                    const option = document.createElement('option');
                    option.value = roleName;
                    option.textContent = labels[roleName] || roleName;
                    option.selected = selectedRole === roleName;
                    roleSelect.appendChild(option);
                });
            };

            entitySelect.addEventListener('change', populateRoles);
            populateRoles();

            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const chartNoDataText = @json(__('app.admin.dashboard.chart_no_data'));
            const palette = ['#ce0812', '#b70710', '#89050c', '#2e0204', '#f97316', '#06b6d4'];

            const renderEmptyState = function (selector) {
                const element = document.querySelector(selector);
                if (!element) {
                    return false;
                }

                element.innerHTML = '<div class="user-empty-chart">' + chartNoDataText + '</div>';
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
                new ApexCharts(element, options).render();
            };

            const typeData = @json($applicationsByTypeChart);
            const budgetData = @json($budgetByProjectChart);
            const monthLabels = @json($applicationsByMonthLabels);
            const monthCounts = @json($applicationsByMonthCounts);
            const crewData = @json($crewByProjectChart);
            const authorityData = @json($authorityResponseChart);

            renderChart('#user-chart-type', typeData.length > 0, {
                chart: { type: 'donut', height: 260 },
                series: typeData.map(item => item.value),
                labels: typeData.map(item => item.label),
                colors: palette,
                legend: { position: 'bottom' },
                dataLabels: { enabled: false }
            });

            renderChart('#user-chart-budget', budgetData.length > 0, {
                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                series: [{ name: 'Budget', data: budgetData.map(item => item.value) }],
                xaxis: { categories: budgetData.map(item => item.label) },
                colors: ['#ce0812'],
                plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
                dataLabels: { enabled: false }
            });

            renderChart('#user-chart-months', monthCounts.some(value => value > 0), {
                chart: { type: 'area', height: 260, toolbar: { show: false } },
                series: [{ name: 'Requests', data: monthCounts }],
                xaxis: { categories: monthLabels },
                colors: ['#89050c'],
                stroke: { curve: 'smooth', width: 3 },
                dataLabels: { enabled: false },
                fill: {
                    type: 'gradient',
                    gradient: { opacityFrom: 0.35, opacityTo: 0.05 }
                }
            });

            renderChart('#user-chart-crew', crewData.length > 0, {
                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                series: [{ name: 'Crew', data: crewData.map(item => item.value) }],
                xaxis: { categories: crewData.map(item => item.label) },
                colors: ['#f97316'],
                plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
                dataLabels: { enabled: false }
            });

            renderChart('#user-chart-authority-response', authorityData.length > 0, {
                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                series: [{ name: 'Hours', data: authorityData.map(item => item.value) }],
                xaxis: { categories: authorityData.map(item => item.label) },
                colors: ['#2e0204'],
                plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '45%' } },
                dataLabels: { enabled: false }
            });
        })();
    </script>
@endpush
