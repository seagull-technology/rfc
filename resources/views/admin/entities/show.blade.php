@php
    $title = $entity->displayName();
    $breadcrumb = __('app.admin.navigation.entities');
    $statusClass = static fn (?string $status): string => match ($status) {
        'active' => 'success',
        'pending_review' => 'warning',
        'needs_completion' => 'info',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $registrationDocumentName = data_get($entity->metadata, 'registration_document_name');
    $registrationDocumentMime = data_get($entity->metadata, 'registration_document_mime');
    $studentGender = data_get($entity->metadata, 'gender');
    $profileStats = $entityAnalytics['stats'];
    $chartData = $entityAnalytics['charts'];
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
    $memberSinceYear = optional($entity->created_at)->format('Y') ?: now()->format('Y');
    $routingStatusClass = static fn (bool $isActive): string => $isActive ? 'success' : 'secondary';
    $canManageAuthorityRouting = auth()->user()?->can('settings.manage') ?? false;
    $canManageEntityMembers = auth()->user()?->can('entities.manage') ?? false;
    $delegationMembersById = $authorityDelegationMembers->keyBy('id');
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('page_layout_class', 'admin-entity-show-layout py-0')

@push('styles')
    <style>
        .admin-entity-show-layout {
            padding-top: 0;
        }

        .admin-entity-show-layout .card {
            margin-bottom: 1.5rem;
        }

        .admin-entity-show-layout .entity-profile-card {
            margin-bottom: 1.5rem;
        }

        .admin-entity-show-layout .entity-chart-card .card-body {
            min-height: 310px;
        }

        .admin-entity-show-layout .entity-chart-surface {
            min-height: 240px;
        }

        .admin-entity-show-layout .entity-empty-chart {
            min-height: 240px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .admin-entity-show-layout .card-header {
            padding-bottom: 0;
        }

        .admin-entity-show-layout table thead th,
        .admin-entity-show-layout table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .admin-entity-show-layout .admin-entity-table-scroll {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
        }

        .admin-entity-show-layout .admin-entity-table {
            table-layout: fixed;
            width: 100%;
        }

        .admin-entity-show-layout .admin-entity-projects-table {
            min-width: 860px;
        }

        .admin-entity-show-layout .admin-entity-authority-delegation-table {
            min-width: 960px;
        }

        .admin-entity-show-layout .admin-entity-authority-routing-table,
        .admin-entity-show-layout .admin-entity-authority-workload-table {
            min-width: 1280px;
        }

        .admin-entity-show-layout .admin-entity-review-history-table {
            min-width: 760px;
        }

        .admin-entity-show-layout .admin-entity-members-table {
            min-width: 980px;
        }

        .admin-entity-show-layout .admin-entity-role-history-table {
            min-width: 1020px;
        }

        .admin-entity-show-layout .admin-entity-table thead th,
        .admin-entity-show-layout .admin-entity-table tbody td {
            white-space: normal;
            vertical-align: top;
            word-break: break-word;
        }

        .admin-entity-show-layout .badge.bg-primary-subtle.text-dark {
            border: 1px solid rgba(0, 0, 0, 0.08);
        }
    </style>
@endpush

@section('content')
    <div class="card entity-profile-card">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <img src="{{ asset('images/OIP.jpeg') }}" class="rounded-circle" width="70" alt="entity">
                <div>
                    <h4 class="mb-0">{{ $entity->displayName() }}</h4>
                    <small class="text-muted">{{ data_get($entity->metadata, 'description', __('app.admin.entities.show_intro', ['group' => $entity->group?->displayName() ?? __('app.dashboard.not_available')])) }}</small>
                </div>
            </div>

            <div class="text-end">
                <span class="badge bg-{{ $statusClass($entity->status) }}">{{ $entity->localizedStatus() }}</span>
                @if ($entity->trashed())
                    <div class="mt-1"><span class="badge bg-danger">{{ __('app.admin.entities.deleted_label') }}</span></div>
                @endif
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
                <div class="card">
                    <div class="card-body">
                        <h6>{{ $metric['label'] }}</h6>
                        <h3>{{ $metric['value'] }}</h3>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($isAuthorityEntity)
        <div class="row mt-3 g-3">
            @foreach ([
                ['label' => __('app.admin.entities.authority_metrics.routing_rules_total'), 'value' => $authorityOperations['routing_rules_total']],
                ['label' => __('app.admin.entities.authority_metrics.routing_rules_active'), 'value' => $authorityOperations['routing_rules_active']],
                ['label' => __('app.admin.entities.authority_metrics.approvals_total'), 'value' => $authorityOperations['approvals_total']],
                ['label' => __('app.admin.entities.authority_metrics.approvals_pending'), 'value' => $authorityOperations['approvals_pending']],
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
    @endif

    <div class="row mt-4 g-3">
        <div class="col-md-6">
            <div class="card entity-chart-card">
                <div class="card-header">{{ __('app.admin.entities.profile_charts.applications_by_type') }}</div>
                <div class="card-body">
                    <div id="entity-chart-type" class="entity-chart-surface"></div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card entity-chart-card">
                <div class="card-header">{{ __('app.admin.entities.profile_charts.budget_by_project') }}</div>
                <div class="card-body">
                    <div id="entity-chart-budget" class="entity-chart-surface"></div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card entity-chart-card">
                <div class="card-header">{{ __('app.admin.entities.profile_charts.applications_by_month') }}</div>
                <div class="card-body">
                    <div id="entity-chart-months" class="entity-chart-surface"></div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card entity-chart-card">
                <div class="card-header">{{ __('app.admin.entities.profile_charts.crew_by_project') }}</div>
                <div class="card-body">
                    <div id="entity-chart-crew" class="entity-chart-surface"></div>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card entity-chart-card">
                <div class="card-header">{{ __('app.admin.entities.profile_charts.authority_response_average') }}</div>
                <div class="card-body">
                    <div id="entity-chart-authority-response" class="entity-chart-surface"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">{{ __('app.admin.entities.profile_previous_projects') }}</div>
        <div class="card-body">
            <div class="table-responsive border rounded py-3 admin-entity-table-scroll">
                <table class="table mb-0 admin-entity-table admin-entity-projects-table">
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
                        @forelse ($entityApplications as $project)
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
    </div>

    <div class="row mt-4">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.entities.summary_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <span class="badge bg-{{ $statusClass($entity->status) }}">{{ $entity->localizedStatus() }}</span>
                        <span class="badge bg-dark-subtle text-dark">{{ $entity->localizedRegistrationType() }}</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.entities.group') }}</small>
                            <div>{{ $entity->group?->displayName() ?? __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.admin.entities.code') }}</small>
                            <div>{{ $entity->code ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        @if ($entity->registration_type === 'student')
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.admin.users.national_id') }}</small>
                                <div>{{ $entity->national_id ?: __('app.dashboard.not_available') }}</div>
                            </div>
                        @else
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.registration_number') }}</small>
                                <div>{{ $entity->registration_no ?: __('app.dashboard.not_available') }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.organization_national_id') }}</small>
                                <div>{{ $entity->national_id ?: __('app.dashboard.not_available') }}</div>
                            </div>
                        @endif
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.auth.email') }}</small>
                            <div>{{ $entity->email ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.auth.mobile_number') }}</small>
                            <div>{{ $entity->phone ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        @if ($entity->registration_type === 'student')
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.birth_date') }}</small>
                                <div>{{ data_get($entity->metadata, 'birth_date', __('app.dashboard.not_available')) }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.gender') }}</small>
                                <div>{{ $studentGender ? __('app.auth.gender_options.'.$studentGender) : __('app.dashboard.not_available') }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.nationality') }}</small>
                                <div>{{ data_get($entity->metadata, 'nationality', __('app.dashboard.not_available')) }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.university_name') }}</small>
                                <div>{{ data_get($entity->metadata, 'university_name', __('app.dashboard.not_available')) }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.major') }}</small>
                                <div>{{ data_get($entity->metadata, 'major', __('app.dashboard.not_available')) }}</div>
                            </div>
                        @else
                            <div class="col-12">
                                <small class="text-muted d-block">{{ __('app.dashboard.address') }}</small>
                                <div>{{ data_get($entity->metadata, 'address', __('app.dashboard.not_available')) }}</div>
                            </div>
                            <div class="col-12">
                                <small class="text-muted d-block">{{ __('app.dashboard.organization_description') }}</small>
                                <div>{{ data_get($entity->metadata, 'description', __('app.dashboard.not_available')) }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.entities.actions') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap">
                        @if (data_get($entity->metadata, 'registration_document_path'))
                            <a class="btn btn-outline-primary" href="{{ route('admin.entities.registration-document', $entity->getKey()) }}">{{ __('app.admin.entities.download_registration_document') }}</a>
                        @endif
                        @if ($entity->trashed())
                            <form method="POST" action="{{ route('admin.entities.restore', $entity->getKey()) }}">
                                @csrf
                                <button class="btn btn-outline-success" type="submit">{{ __('app.admin.entities.restore_action') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.entities.status', $entity->getKey()) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $entity->status === 'active' ? 'inactive' : 'active' }}">
                                <button class="btn btn-outline-warning" type="submit">
                                    {{ $entity->status === 'active' ? __('app.admin.entities.deactivate_action') : __('app.admin.entities.activate_action') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.entities.delete', $entity->getKey()) }}">
                                @csrf
                                <button class="btn btn-outline-danger" type="submit">{{ __('app.admin.entities.delete_action') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            @if ($isAuthorityEntity)
                <div class="card">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.entities.authority_ops_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="text-muted mb-3">{{ __('app.admin.entities.authority_ops_intro') }}</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.admin.entities.authority_metrics.average_hours') }}</small>
                                <div>
                                    {{ $authorityOperations['average_hours'] !== null ? number_format((float) $authorityOperations['average_hours'], 1) : __('app.dashboard.not_available') }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.admin.entities.members_count') }}</small>
                                <div>{{ $members->count() }}</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a class="btn btn-outline-primary" href="{{ route('admin.approval-routing.index', ['target_entity_id' => $entity->getKey()]) }}">{{ __('app.admin.entities.open_routing_rules_action') }}</a>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.approval-routing.index', ['target_entity_id' => $entity->getKey(), 'is_active' => '1']) }}">{{ __('app.admin.entities.open_active_routing_rules_action') }}</a>
                        </div>
                    </div>
                </div>
            @endif

            @if ($entity->isRegistrationReviewable())
                <div class="card">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.entities.registration_details_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.dashboard.registration_type') }}</small>
                                <div>{{ $entity->localizedRegistrationType() }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.registration_number') }}</small>
                                <div>{{ $entity->registration_no ?: __('app.dashboard.not_available') }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.email') }}</small>
                                <div>{{ $entity->email ?: __('app.dashboard.not_available') }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.mobile_number') }}</small>
                                <div>{{ $entity->phone ?: __('app.dashboard.not_available') }}</div>
                            </div>
                            <div class="col-12">
                                <small class="text-muted d-block">{{ __('app.dashboard.address') }}</small>
                                <div>{{ data_get($entity->metadata, 'address', __('app.dashboard.not_available')) }}</div>
                            </div>
                            <div class="col-12">
                                <small class="text-muted d-block">{{ __('app.dashboard.organization_description') }}</small>
                                <div>{{ data_get($entity->metadata, 'description', __('app.dashboard.not_available')) }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.admin.entities.registration_document_name') }}</small>
                                <div>{{ $registrationDocumentName ?: __('app.dashboard.not_available') }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.admin.entities.registration_document_type') }}</small>
                                <div>{{ $registrationDocumentMime ?: __('app.dashboard.not_available') }}</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row g-3">
                            <div class="col-12">
                                <h4 class="h6 mb-0">{{ __('app.dashboard.account_owner') }}</h4>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.full_name') }}</small>
                                <div>{{ $primaryOwner?->displayName() ?? __('app.dashboard.not_available') }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.admin.entities.status') }}</small>
                                <div>{{ $primaryOwner?->localizedStatus() ?? __('app.dashboard.not_available') }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.email') }}</small>
                                <div>{{ $primaryOwner?->email ?: __('app.dashboard.not_available') }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ __('app.auth.mobile_number') }}</small>
                                <div>{{ $primaryOwner?->phone ?: __('app.dashboard.not_available') }}</div>
                            </div>
                        </div>

                        @if ($reviewData !== [])
                            <hr class="my-4">

                            <div class="row g-3">
                                <div class="col-12">
                                    <h4 class="h6 mb-0">{{ __('app.admin.entities.review_title') }}</h4>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block">{{ __('app.admin.entities.review_decision') }}</small>
                                    <div>{{ __('app.statuses.'.($entity->status ?? 'pending_review')) }}</div>
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
            @endif
        </div>

        <div class="col-xl-7">
            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.entities.edit_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.entities.update', $entity->getKey()) }}" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label for="group_id" class="form-label">{{ __('app.admin.entities.group') }}</label>
                            <select id="group_id" name="group_id" class="form-select" required>
                                @foreach ($groups as $groupOption)
                                    <option value="{{ $groupOption->id }}" @selected(old('group_id', $entity->group_id) == $groupOption->id)>{{ $groupOption->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">{{ __('app.admin.entities.status') }}</label>
                            <select id="status" name="status" class="form-select" required>
                                @foreach (['active', 'inactive', 'pending_review', 'needs_completion', 'rejected'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $entity->status) === $status)>{{ __('app.statuses.'.$status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="name_en" class="form-label">{{ __('app.admin.entities.name_en') }}</label>
                            <input id="name_en" name="name_en" type="text" class="form-control" value="{{ old('name_en', $entity->name_en) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="name_ar" class="form-label">{{ __('app.admin.entities.name_ar') }}</label>
                            <input id="name_ar" name="name_ar" type="text" class="form-control" value="{{ old('name_ar', $entity->name_ar) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="code" class="form-label">{{ __('app.admin.entities.code') }}</label>
                            <input id="code" name="code" type="text" class="form-control" value="{{ old('code', $entity->code) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="registration_no" class="form-label">{{ __('app.auth.registration_number') }}</label>
                            <input id="registration_no" name="registration_no" type="text" class="form-control" value="{{ old('registration_no', $entity->registration_no) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="national_id" class="form-label">{{ __('app.auth.organization_national_id') }}</label>
                            <input id="national_id" name="national_id" type="text" class="form-control" value="{{ old('national_id', $entity->national_id) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">{{ __('app.auth.email') }}</label>
                            <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $entity->email) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">{{ __('app.auth.mobile_number') }}</label>
                            <input id="phone" name="phone" type="text" class="form-control" value="{{ old('phone', $entity->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="address" class="form-label">{{ __('app.dashboard.address') }}</label>
                            <input id="address" name="address" type="text" class="form-control" value="{{ old('address', data_get($entity->metadata, 'address')) }}">
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">{{ __('app.dashboard.organization_description') }}</label>
                            <textarea id="description" name="description" rows="4" class="form-control">{{ old('description', data_get($entity->metadata, 'description')) }}</textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-danger" type="submit">{{ __('app.admin.entities.update_action') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            @if ($entity->isRegistrationReviewable())
                <div class="card">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.entities.review_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.entities.review', $entity->getKey()) }}" class="row g-3">
                            @csrf
                            <div class="col-md-5">
                                <label for="decision" class="form-label">{{ __('app.admin.entities.review_decision') }}</label>
                                <select id="decision" name="decision" class="form-select" required>
                                    <option value="approve">{{ __('app.admin.entities.review_actions.approve') }}</option>
                                    <option value="needs_completion">{{ __('app.admin.entities.review_actions.needs_completion') }}</option>
                                    <option value="reject">{{ __('app.admin.entities.review_actions.reject') }}</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label for="note" class="form-label">{{ __('app.admin.entities.review_note') }}</label>
                                <input id="note" name="note" type="text" class="form-control" value="{{ old('note') ?: ($reviewData['note'] ?? '') }}">
                            </div>
                            <div class="col-12 d-flex gap-2 flex-wrap">
                                <button class="btn btn-danger" type="submit">{{ __('app.admin.entities.review_submit') }}</button>
                                @if (data_get($entity->metadata, 'registration_document_path'))
                                    <a class="btn btn-outline-primary" href="{{ route('admin.entities.registration-document', $entity->getKey()) }}">{{ __('app.admin.entities.download_registration_document') }}</a>
                                @endif
                                @if (in_array($entity->registration_type, ['ngo', 'school'], true) && in_array($entity->status, ['needs_completion', 'rejected'], true))
                                    @php($signedCompletionUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('registration.completion.link.edit', now()->addDays(7), ['entity' => $entity->getKey()]))
                                    <a class="btn btn-outline-warning" href="{{ $signedCompletionUrl }}" target="_blank" rel="noopener">{{ __('app.admin.entities.open_completion_link') }}</a>
                                @endif
                            </div>
                            @if ($reviewData !== [])
                                <div class="col-12 text-muted">
                                    {{ __('app.admin.entities.review_latest', [
                                        'decision' => __('app.statuses.'.($entity->status ?? 'pending_review')),
                                        'date' => $reviewData['reviewed_at'] ?? __('app.dashboard.not_available'),
                                    ]) }}
                                </div>
                            @endif
                            @if (in_array($entity->registration_type, ['ngo', 'school'], true) && in_array($entity->status, ['needs_completion', 'rejected'], true))
                                @php($signedCompletionUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('registration.completion.link.edit', now()->addDays(7), ['entity' => $entity->getKey()]))
                                <div class="col-12">
                                    <label class="form-label">{{ __('app.admin.entities.completion_link_label') }}</label>
                                    <input type="text" class="form-control" value="{{ $signedCompletionUrl }}" readonly>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            @endif

            @if ($canManageEntityMembers)
                <div class="card">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.entities.add_member_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.entities.members.store', $entity->getKey()) }}" class="row g-3">
                            @csrf
                            <div class="col-md-6">
                                <label for="user_id" class="form-label">{{ __('app.admin.entities.member_user') }}</label>
                                <select id="user_id" name="user_id" class="form-select" required>
                                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->displayName() }} - {{ $user->email }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="roles" class="form-label">{{ __('app.admin.entities.member_roles') }}</label>
                                <select id="roles" name="roles[]" class="form-select select2-basic-multiple" multiple required>
                                    @foreach ($allowedRoles as $role)
                                        <option value="{{ $role->name }}" @selected(in_array($role->name, old('roles', []), true))>{{ __('app.roles.'.$role->name) }}</option>
                                    @endforeach
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
                                <button class="btn btn-danger" type="submit">{{ __('app.admin.entities.add_member_action') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        @if ($isAuthorityEntity)
            @if ($canManageEntityMembers)
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="iq-header-title">
                                <h3 class="card-title">{{ __('app.admin.entities.authority_delegation_title') }}</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="text-muted mb-4">{{ __('app.admin.entities.authority_delegation_intro') }}</div>
                            <div class="table-responsive border rounded py-3 admin-entity-table-scroll">
                                <table class="table mb-0 admin-entity-table admin-entity-authority-delegation-table">
                                    <colgroup>
                                        <col style="width: 240px">
                                        <col style="width: 300px">
                                        <col style="width: 420px">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>{{ __('app.admin.approval_routing.approval_code') }}</th>
                                            <th>{{ __('app.admin.entities.authority_delegation_current') }}</th>
                                            <th>{{ __('app.admin.entities.authority_delegation_update') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($authorityDelegationCodes as $approvalCode)
                                            @php($currentDelegate = $delegationMembersById->get($authorityDelegationMap[$approvalCode] ?? null))
                                            <tr>
                                                <td>{{ __('app.applications.required_approval_options.'.$approvalCode) }}</td>
                                                <td>
                                                    @if ($currentDelegate)
                                                        {{ $currentDelegate->displayName() }}<br>
                                                        <span class="text-muted">{{ $currentDelegate->email }}</span>
                                                    @else
                                                        <span class="text-muted">{{ __('app.admin.entities.authority_delegation_shared_inbox') }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <form method="POST" action="{{ route('admin.entities.authority-delegation.update', $entity->getKey()) }}" class="row g-2 align-items-center">
                                                        @csrf
                                                        <input type="hidden" name="approval_code" value="{{ $approvalCode }}">
                                                        <div class="col-md-8">
                                                            <select name="assigned_user_id" class="form-select">
                                                                <option value="">{{ __('app.admin.entities.authority_delegation_shared_inbox') }}</option>
                                                                @foreach ($authorityDelegationMembers as $member)
                                                                    <option value="{{ $member->getKey() }}" @selected((int) old('assigned_user_id', $authorityDelegationMap[$approvalCode] ?? 0) === $member->getKey())>{{ $member->displayName() }} - {{ $member->email }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <button class="btn btn-outline-primary w-100" type="submit">{{ __('app.admin.entities.authority_delegation_save_action') }}</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3">{{ __('app.admin.entities.authority_delegation_empty') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="iq-header-title">
                                <h3 class="card-title">{{ __('app.admin.entities.authority_quick_rule_title') }}</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="text-muted mb-4">{{ __('app.admin.entities.authority_quick_rule_intro') }}</div>
                            <form method="POST" action="{{ route('admin.entities.authority-routing.store', $entity->getKey()) }}" class="row g-3">
                                @csrf
                                <div class="col-md-5">
                                    <label for="authority-rule-name" class="form-label">{{ __('app.admin.approval_routing.name') }}</label>
                                    <input id="authority-rule-name" name="name" type="text" class="form-control" value="{{ old('name') }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="authority-rule-approval" class="form-label">{{ __('app.admin.approval_routing.approval_code') }}</label>
                                    <select id="authority-rule-approval" name="approval_code" class="form-select" required>
                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                        @foreach (['public_security', 'digital_economy', 'environment', 'municipalities', 'airports', 'drones', 'heritage', 'customs', 'military_border'] as $approvalCode)
                                            <option value="{{ $approvalCode }}" @selected(old('approval_code') === $approvalCode)>{{ __('app.applications.required_approval_options.'.$approvalCode) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="authority-rule-priority" class="form-label">{{ __('app.admin.approval_routing.priority') }}</label>
                                    <input id="authority-rule-priority" name="priority" type="number" min="1" max="9999" class="form-control" value="{{ old('priority', 100) }}" required>
                                </div>
                                <div class="col-md-2">
                                    <label for="authority-rule-active" class="form-label">{{ __('app.admin.approval_routing.status') }}</label>
                                    <select id="authority-rule-active" name="is_active" class="form-select">
                                        <option value="1" @selected(old('is_active', '1') === '1')>{{ __('app.admin.approval_routing.active_label') }}</option>
                                        <option value="0" @selected(old('is_active') === '0')>{{ __('app.statuses.inactive') }}</option>
                                    </select>
                                </div>
                                <div class="col-12 d-flex gap-2 flex-wrap">
                                    <button class="btn btn-danger" type="submit">{{ __('app.admin.approval_routing.create_action') }}</button>
                                    <a class="btn btn-outline-secondary" href="{{ route('admin.approval-routing.create') }}">{{ __('app.admin.entities.authority_open_advanced_routing_action') }}</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.entities.authority_routing_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive border rounded py-3 admin-entity-table-scroll">
                            <table class="table mb-0 admin-entity-table admin-entity-authority-routing-table">
                                <colgroup>
                                    <col style="width: 300px">
                                    <col style="width: 190px">
                                    <col style="width: 110px">
                                    <col style="width: 130px">
                                    <col style="width: 130px">
                                    <col style="width: 170px">
                                    <col style="width: 250px">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>{{ __('app.admin.approval_routing.name') }}</th>
                                        <th>{{ __('app.admin.approval_routing.approval_code') }}</th>
                                        <th>{{ __('app.admin.approval_routing.priority') }}</th>
                                        <th>{{ __('app.admin.approval_routing.status') }}</th>
                                        <th>{{ __('app.admin.entities.authority_usage_count') }}</th>
                                        <th>{{ __('app.admin.entities.authority_last_used_at') }}</th>
                                        <th>{{ __('app.admin.entities.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($authorityRoutingRules as $rule)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.approval-routing.show', $rule) }}">{{ $rule->name }}</a><br>
                                                <span class="text-muted">
                                                    {{ __('app.admin.approval_routing.audit_by_label') }}:
                                                    {{ $rule->latestAudit?->changedBy?->displayName() ?? __('app.dashboard.not_available') }}
                                                </span>
                                            </td>
                                            <td>{{ $rule->localizedApproval() }}</td>
                                            <td>{{ $rule->priority }}</td>
                                            <td><span class="badge bg-{{ $routingStatusClass((bool) $rule->is_active) }}">{{ $rule->is_active ? __('app.admin.approval_routing.active_label') : __('app.statuses.inactive') }}</span></td>
                                            <td>{{ $rule->authority_approvals_count }}</td>
                                            <td>
                                                {{ filled($rule->authority_approvals_max_updated_at)
                                                    ? \Illuminate\Support\Carbon::parse($rule->authority_approvals_max_updated_at)->format('Y-m-d H:i')
                                                    : __('app.dashboard.not_available') }}
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.approval-routing.show', $rule) }}">{{ __('app.admin.dashboard.table_action') }}</a>
                                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.approval-routing.edit', $rule) }}">{{ __('app.admin.approval_routing.update_action') }}</a>
                                                    @if ($canManageAuthorityRouting)
                                                        <form method="POST" action="{{ route('admin.entities.authority-routing.status', [$entity->getKey(), $rule]) }}">
                                                            @csrf
                                                            <input type="hidden" name="is_active" value="{{ $rule->is_active ? 0 : 1 }}">
                                                            <button class="btn btn-sm btn-outline-warning" type="submit">
                                                                {{ $rule->is_active ? __('app.admin.approval_routing.deactivate_action') : __('app.admin.approval_routing.activate_action') }}
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="{{ route('admin.entities.authority-routing.delete', [$entity->getKey(), $rule]) }}">
                                                            @csrf
                                                            <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('app.admin.approval_routing.delete_action') }}</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">{{ __('app.admin.entities.authority_routing_empty') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.entities.authority_workload_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive border rounded py-3 admin-entity-table-scroll">
                            <table class="table mb-0 admin-entity-table admin-entity-authority-workload-table">
                                <colgroup>
                                    <col style="width: 290px">
                                    <col style="width: 240px">
                                    <col style="width: 190px">
                                    <col style="width: 130px">
                                    <col style="width: 160px">
                                    <col style="width: 170px">
                                    <col style="width: 100px">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>{{ __('app.authority.applications.application') }}</th>
                                        <th>{{ __('app.admin.approval_routing.approval_code') }}</th>
                                        <th>{{ __('app.admin.entities.authority_delegation_current') }}</th>
                                        <th>{{ __('app.applications.status') }}</th>
                                        <th>{{ __('app.applications.updated_at') }}</th>
                                        <th>{{ __('app.admin.entities.reviewed_by') }}</th>
                                        <th>{{ __('app.admin.entities.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentAuthorityApprovals as $approval)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.applications.show', $approval->application) }}">{{ $approval->application?->project_name ?? __('app.dashboard.not_available') }}</a><br>
                                                <span class="text-muted">{{ $approval->application?->entity?->displayName() ?? __('app.dashboard.not_available') }}</span>
                                            </td>
                                            <td>
                                                {{ __('app.applications.required_approval_options.'.$approval->authority_code) }}<br>
                                                <span class="text-muted">{{ $approval->routingRule?->name ?? __('app.dashboard.not_available') }}</span>
                                            </td>
                                            <td>{{ $approval->assignedTo?->displayName() ?? __('app.workflow.unassigned') }}</td>
                                            <td><span class="badge bg-{{ $statusClass($approval->status) }}">{{ $approval->localizedStatus() }}</span></td>
                                            <td>{{ optional($approval->updated_at)->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</td>
                                            <td>{{ $approval->reviewedBy?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.applications.show', $approval->application) }}">{{ __('app.authority.applications.open_action') }}</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">{{ __('app.admin.entities.authority_workload_empty') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($entity->isRegistrationReviewable())
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.entities.review_history_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive border rounded py-3 admin-entity-table-scroll">
                            <table class="table mb-0 admin-entity-table admin-entity-review-history-table">
                                <colgroup>
                                    <col style="width: 280px">
                                    <col style="width: 480px">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>{{ __('app.admin.entities.review_title') }}</th>
                                        <th>{{ __('app.admin.entities.review_note') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($reviewHistory as $historyItem)
                                        <tr>
                                            <td>
                                                {{ __('app.admin.entities.review_actions.'.data_get($historyItem, 'decision', 'needs_completion')) }}<br>
                                                <span class="text-muted">{{ data_get($historyItem, 'reviewed_at', __('app.dashboard.not_available')) }}</span><br>
                                                <span class="text-muted">{{ $reviewerNames[data_get($historyItem, 'reviewed_by_user_id')] ?? __('app.dashboard.not_available') }}</span>
                                            </td>
                                            <td>{{ data_get($historyItem, 'note', __('app.dashboard.not_available')) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2">{{ __('app.admin.entities.review_history_empty') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.entities.members_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive border rounded py-3 admin-entity-table-scroll">
                        <table class="table mb-0 admin-entity-table admin-entity-members-table">
                            <colgroup>
                                <col style="width: 310px">
                                <col style="width: 360px">
                                <col style="width: 310px">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>{{ __('app.admin.users.name') }}</th>
                                    <th>{{ __('app.admin.entities.member_role') }}</th>
                                    <th>{{ __('app.admin.entities.member_membership') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $member)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.users.show', $member['user']->getKey()) }}">{{ $member['user']->displayName() }}</a><br>
                                            <span class="text-muted">{{ $member['user']->email }}</span><br>
                                            <span class="text-muted">{{ $member['user']->pivot?->job_title ?: __('app.dashboard.not_available') }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2 flex-wrap">
                                                @forelse ($member['roles'] as $roleName)
                                                    <div class="d-inline-flex align-items-center gap-2">
                                                        <span class="badge bg-primary-subtle text-dark">{{ __('app.roles.'.$roleName) }}</span>
                                                        @if ($canManageEntityMembers)
                                                            <form method="POST" action="{{ route('admin.entities.members.roles.delete', [$entity->getKey(), $member['user']->getKey(), $roleName]) }}">
                                                                @csrf
                                                                <button class="btn btn-sm btn-outline-danger py-0 px-2" type="submit">{{ __('app.admin.entities.remove_role_action') }}</button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                @empty
                                                    {{ __('app.dashboard.no_roles') }}
                                                @endforelse
                                            </div>
                                            <span class="text-muted d-inline-block mt-2">{{ $member['user']->pivot?->is_primary ? __('app.admin.yes') : __('app.admin.no') }}</span>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="badge bg-{{ ($member['user']->pivot?->status ?? 'active') === 'active' ? 'success' : 'secondary' }}">
                                                    {{ __('app.statuses.'.($member['user']->pivot?->status ?? 'active')) }}
                                                </span>
                                            </div>
                                            <div class="text-muted small mt-2">
                                                {{ __('app.admin.entities.member_joined_at') }}:
                                                {{ filled($member['user']->pivot?->joined_at)
                                                    ? \Illuminate\Support\Carbon::parse($member['user']->pivot?->joined_at)->format('Y-m-d')
                                                    : __('app.dashboard.not_available') }}
                                            </div>
                                            <div class="text-muted small">
                                                {{ __('app.admin.entities.member_left_at') }}:
                                                {{ filled($member['user']->pivot?->left_at)
                                                    ? \Illuminate\Support\Carbon::parse($member['user']->pivot?->left_at)->format('Y-m-d')
                                                    : __('app.dashboard.not_available') }}
                                            </div>
                                            @if ($canManageEntityMembers)
                                                <div class="d-flex gap-2 flex-wrap mt-3">
                                                    @if (! $member['user']->pivot?->is_primary)
                                                        <form method="POST" action="{{ route('admin.entities.members.primary', [$entity->getKey(), $member['user']->getKey()]) }}">
                                                            @csrf
                                                            <button class="btn btn-sm btn-outline-primary" type="submit">{{ __('app.admin.entities.set_primary_action') }}</button>
                                                        </form>
                                                    @endif
                                                    <form method="POST" action="{{ route('admin.entities.members.status', [$entity->getKey(), $member['user']->getKey()]) }}">
                                                        @csrf
                                                        <input type="hidden" name="status" value="{{ ($member['user']->pivot?->status ?? 'active') === 'active' ? 'inactive' : 'active' }}">
                                                        <button class="btn btn-sm btn-outline-warning" type="submit">
                                                            {{ ($member['user']->pivot?->status ?? 'active') === 'active'
                                                                ? __('app.admin.entities.deactivate_member_action')
                                                                : __('app.admin.entities.activate_member_action') }}
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.entities.members.delete', [$entity->getKey(), $member['user']->getKey()]) }}">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('app.admin.entities.remove_member_action') }}</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.entities.role_history_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive border rounded py-3 admin-entity-table-scroll">
                        <table class="table mb-0 admin-entity-table admin-entity-role-history-table">
                            <colgroup>
                                <col style="width: 220px">
                                <col style="width: 270px">
                                <col style="width: 150px">
                                <col style="width: 220px">
                                <col style="width: 160px">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>{{ __('app.admin.entities.member_role') }}</th>
                                    <th>{{ __('app.admin.users.name') }}</th>
                                    <th>{{ __('app.admin.entities.role_history_action') }}</th>
                                    <th>{{ __('app.admin.entities.role_history_by') }}</th>
                                    <th>{{ __('app.admin.entities.role_history_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($roleAssignmentAudits as $audit)
                                    <tr>
                                        <td><span class="badge bg-primary-subtle text-dark">{{ __('app.roles.'.$audit->role_name) }}</span></td>
                                        <td>
                                            @if ($audit->user)
                                                <a href="{{ route('admin.users.show', $audit->user->getKey()) }}">{{ $audit->user->displayName() }}</a><br>
                                                <span class="text-muted">{{ $audit->user->email ?: __('app.dashboard.not_available') }}</span>
                                            @else
                                                {{ __('app.dashboard.not_available') }}
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $audit->action === 'added' ? 'success' : 'danger' }}">{{ $audit->localizedAction() }}</span>
                                        </td>
                                        <td>{{ $audit->changedBy?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                        <td>{{ optional($audit->created_at)->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">{{ __('app.admin.entities.role_history_empty') }}</td>
                                    </tr>
                                @endforelse
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
        window.addEventListener('load', function () {
            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const chartNoDataText = @json(__('app.admin.dashboard.chart_no_data'));
            const palette = ['#5e1d19', '#4b1714', '#38120f', '#1f0908', '#7a2a21', '#06b6d4'];

            const renderEmptyState = function (selector) {
                const element = document.querySelector(selector);
                if (!element) {
                    return false;
                }

                element.innerHTML = '<div class="entity-empty-chart">' + chartNoDataText + '</div>';
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
            const budgetSeriesLabel = @json(__('app.admin.entities.profile_charts.budget_by_project'));
            const requestsSeriesLabel = @json(__('app.admin.entities.profile_charts.applications_by_month'));
            const crewSeriesLabel = @json(__('app.admin.entities.profile_charts.crew_by_project'));
            const authoritySeriesLabel = @json(__('app.admin.entities.profile_charts.authority_response_average'));

            renderChart('#entity-chart-type', typeData.length > 0, {
                chart: { type: 'donut', height: 260 },
                series: typeData.map(item => item.value),
                labels: typeData.map(item => item.label),
                colors: palette,
                legend: { position: 'bottom' },
                dataLabels: { enabled: false }
            });

            renderChart('#entity-chart-budget', budgetData.length > 0, {
                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                series: [{ name: budgetSeriesLabel, data: budgetData.map(item => item.value) }],
                xaxis: { categories: budgetData.map(item => item.label) },
                colors: ['#5e1d19'],
                plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
                dataLabels: { enabled: false }
            });

            renderChart('#entity-chart-months', monthCounts.some(value => value > 0), {
                chart: { type: 'area', height: 260, toolbar: { show: false } },
                series: [{ name: requestsSeriesLabel, data: monthCounts }],
                xaxis: { categories: monthLabels },
                colors: ['#38120f'],
                stroke: { curve: 'smooth', width: 3 },
                dataLabels: { enabled: false },
                fill: {
                    type: 'gradient',
                    gradient: { opacityFrom: 0.35, opacityTo: 0.05 }
                }
            });

            renderChart('#entity-chart-crew', crewData.length > 0, {
                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                series: [{ name: crewSeriesLabel, data: crewData.map(item => item.value) }],
                xaxis: { categories: crewData.map(item => item.label) },
                colors: ['#7a2a21'],
                plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
                dataLabels: { enabled: false }
            });

            renderChart('#entity-chart-authority-response', authorityData.length > 0, {
                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                series: [{ name: authoritySeriesLabel, data: authorityData.map(item => item.value) }],
                xaxis: { categories: authorityData.map(item => item.label) },
                colors: ['#1f0908'],
                plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '45%' } },
                dataLabels: { enabled: false }
            });
        });
    </script>
@endpush
