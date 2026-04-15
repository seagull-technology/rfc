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
            <table class="table">
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
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.auth.registration_number') }}</small>
                            <div>{{ $entity->registration_no ?: __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">{{ __('app.auth.organization_national_id') }}</small>
                            <div>{{ $entity->national_id ?: __('app.dashboard.not_available') }}</div>
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
                            <label for="role" class="form-label">{{ __('app.admin.entities.member_role') }}</label>
                            <select id="role" name="role" class="form-select" required>
                                <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                @foreach ($allowedRoles as $role)
                                    <option value="{{ $role->name }}" @selected(old('role') === $role->name)>{{ __('app.roles.'.$role->name) }}</option>
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
        </div>

        @if ($entity->isRegistrationReviewable())
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.entities.review_history_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive border rounded py-3">
                            <table class="table mb-0">
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
                    <div class="table-responsive border rounded py-3">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('app.admin.users.name') }}</th>
                                    <th>{{ __('app.admin.entities.member_role') }}</th>
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
                                            @forelse ($member['roles'] as $roleName)
                                                <span class="badge bg-primary-subtle text-dark">{{ __('app.roles.'.$roleName) }}</span>
                                            @empty
                                                {{ __('app.dashboard.no_roles') }}
                                            @endforelse
                                            <br>
                                            <span class="text-muted d-inline-block mt-2">{{ $member['user']->pivot?->is_primary ? __('app.admin.yes') : __('app.admin.no') }}</span>
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
        window.addEventListener('load', function () {
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
                colors: ['#ce0812'],
                plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
                dataLabels: { enabled: false }
            });

            renderChart('#entity-chart-months', monthCounts.some(value => value > 0), {
                chart: { type: 'area', height: 260, toolbar: { show: false } },
                series: [{ name: requestsSeriesLabel, data: monthCounts }],
                xaxis: { categories: monthLabels },
                colors: ['#89050c'],
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
                colors: ['#f97316'],
                plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
                dataLabels: { enabled: false }
            });

            renderChart('#entity-chart-authority-response', authorityData.length > 0, {
                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                series: [{ name: authoritySeriesLabel, data: authorityData.map(item => item.value) }],
                xaxis: { categories: authorityData.map(item => item.label) },
                colors: ['#2e0204'],
                plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '45%' } },
                dataLabels: { enabled: false }
            });
        });
    </script>
@endpush
