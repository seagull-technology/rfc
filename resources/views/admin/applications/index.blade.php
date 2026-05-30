@php
    $title = __('app.admin.applications.title');
    $breadcrumb = __('app.admin.navigation.applications');
    $checkpointClass = static fn (string $key): string => match ($key) {
        'ready_final_decision' => 'success',
        'waiting_on_applicant' => 'danger',
        'waiting_authorities', 'assign_reviewer' => 'warning',
        'draft' => 'secondary',
        default => 'info',
    };
    $checkpointMeta = static fn ($application): array => \App\Support\AdminWorkflowState::applicationCheckpoint($application);
    $statusClass = static fn (string $status): string => match ($status) {
        'draft' => 'secondary',
        'submitted' => 'warning',
        'under_review' => 'info',
        'needs_clarification' => 'danger',
        'approved' => 'success',
        'rejected' => 'dark',
        default => 'secondary',
    };
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('page_layout_class', 'admin-applications-index-layout')

@push('styles')
    <style>
        .admin-applications-index-layout {
            padding-top: 0;
        }

        .admin-applications-index-layout .card {
            margin-bottom: 0;
        }

        .admin-applications-index-layout > .row > [class*="col-"] {
            margin-bottom: 1.5rem;
        }

        .admin-applications-index-layout .card-header {
            padding-bottom: 0;
        }

        .admin-applications-index-layout .nav-pills .nav-link {
            white-space: nowrap;
        }

        .admin-applications-index-layout .nav-pills {
            flex-wrap: nowrap;
            overflow-x: auto;
        }

        .admin-applications-index-layout .nav-pills .nav-item {
            min-width: 220px;
        }

        .admin-applications-index-layout .admin-applications-table-scroll {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
        }

        .admin-applications-index-layout .admin-applications-table {
            min-width: 1280px;
            table-layout: fixed;
            width: 100%;
        }

        .admin-applications-index-layout .admin-applications-table thead th,
        .admin-applications-index-layout .admin-applications-table tbody td {
            white-space: normal;
            vertical-align: top;
            word-break: break-word;
        }

        .admin-applications-index-layout .admin-applications-table tbody td:first-child,
        .admin-applications-index-layout .admin-applications-table thead th:first-child {
            text-align: center;
        }

        .admin-applications-index-layout .response-flag {
            margin-top: .5rem;
        }

        .admin-applications-index-layout .response-flag .small {
            display: block;
            margin-top: .35rem;
            white-space: normal;
            word-break: break-word;
        }

        .admin-applications-index-layout .responsibility-stack {
            display: grid;
            gap: .5rem;
        }

        .admin-applications-index-layout .responsibility-row {
            white-space: normal;
            word-break: break-word;
        }

        .admin-applications-index-layout .responsibility-row .badge {
            margin-top: .2rem;
        }

        .admin-applications-index-layout .admin-applications-actions-cell .list-user-action {
            justify-content: center;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center">
                <h2 class="episode-playlist-title wp-heading-inline mb-0">
                    <span class="position-relative">{{ __('app.admin.applications.directory_title') }}</span>
                </h2>
                <a class="btn btn-primary" href="{{ route('admin.applications.export', request()->query()) }}">{{ __('app.reports.export_current') }}</a>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.applications.index') }}" class="row g-3 align-items-end">
                        <div class="col-lg-8">
                            <label class="form-label" for="q">{{ __('app.admin.filters.search_label') }}</label>
                            <input id="q" name="q" type="text" class="form-control bg-white" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.applications.search_placeholder') }}">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="status">{{ __('app.applications.status') }}</label>
                            <select id="status" name="status" class="form-control bg-white">
                                @foreach (['all', 'draft', 'submitted', 'under_review', 'needs_clarification', 'approved', 'rejected'] as $status)
                                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status === 'all' ? __('app.admin.filters.all_option') : __('app.statuses.'.$status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button class="btn btn-danger" type="submit">{{ __('app.admin.filters.apply_action') }}</button>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.applications.index') }}">{{ __('app.admin.filters.clear_action') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted">{{ __('app.admin.workflow_states.assign_reviewer') }}</div>
                    <h2 class="counter">{{ $stats['assign_reviewer'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted">{{ __('app.admin.workflow_states.waiting_on_applicant') }}</div>
                    <h2 class="counter">{{ $stats['waiting_on_applicant'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted">{{ __('app.admin.workflow_states.waiting_authorities') }}</div>
                    <h2 class="counter">{{ $stats['waiting_authorities'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted">{{ __('app.admin.authority_escalations.metrics.overdue_requests') }}</div>
                    <h2 class="counter text-danger">{{ $stats['overdue_authorities'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted">{{ __('app.admin.workflow_states.ready_final_decision') }}</div>
                    <h2 class="counter">{{ $stats['ready_final_decision'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-12">
            <ul class="nav nav-pills mb-0 nav-fill" id="pills-tab-1" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link p-4 fontSize20 active" id="pills-home-tab-fill" data-bs-toggle="pill" href="#pills-home-fill" role="tab" aria-selected="true">
                        {{ __('app.admin.applications.open_requests') }}
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link p-4 fontSize20" id="pills-profile-tab-fill" data-bs-toggle="pill" href="#pills-profile-fill" role="tab" aria-selected="false" tabindex="-1">
                        {{ __('app.admin.applications.previous_requests') }}
                    </a>
                </li>
            </ul>

            <div class="tab-content" id="pills-tabContent-1">
                @foreach (['home' => $openApplications, 'profile' => $closedApplications] as $pane => $rows)
                    <div class="tab-pane fade {{ $pane === 'home' ? 'show active' : '' }} border p-5" id="pills-{{ $pane }}-fill" role="tabpanel">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="streamit-wraper-table">
                                    <div class="table-view table-space admin-applications-table-scroll">
                                        <table id="{{ $pane === 'home' ? 'seasonTable' : 'seasonTableArchive' }}" class="data-tables table custom-table data-table-one custom-table-height admin-applications-table" role="grid" data-toggle="data-table">
                                            <colgroup>
                                                <col style="width: 64px">
                                                <col style="width: 126px">
                                                <col style="width: 210px">
                                                <col style="width: 150px">
                                                <col style="width: 276px">
                                                <col style="width: 140px">
                                                <col style="width: 120px">
                                                <col style="width: 110px">
                                                <col style="width: 84px">
                                            </colgroup>
                                            <thead>
                                                <tr class="ligth">
                                                    <th>#</th>
                                                    <th>{{ __('app.admin.applications.application') }}</th>
                                                    <th>{{ __('app.applications.project_name') }}</th>
                                                    <th>{{ __('app.admin.applications.applicant') }}</th>
                                                    <th>{{ __('app.admin.applications.responsibility_title') }}</th>
                                                    <th>{{ __('app.admin.dashboard.workflow_checkpoint') }}</th>
                                                    <th>{{ __('app.applications.updated_at') }}</th>
                                                    <th>{{ __('app.applications.status') }}</th>
                                                    <th>{{ __('app.admin.applications.actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($rows as $application)
                                                    @php($checkpoint = $checkpointMeta($application))
                                                    @php($applicantResponse = $applicantResponses[$application->getKey()] ?? ['active' => false])
                                                    @php($responsibility = $responsibilitySummaries[$application->getKey()] ?? ['rfc_owner' => __('app.workflow.unassigned'), 'authority_items' => collect()])
                                                    <tr>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td>{{ $application->code ?: __('app.dashboard.not_available') }}</td>
                                                        <td>
                                                            {{ $application->project_name }}<br>
                                                            <span class="text-muted">{{ $application->entity?->displayName() ?? __('app.dashboard.not_available') }}</span>
                                                            @if ($applicantResponse['active'])
                                                                <div class="response-flag">
                                                                    <span class="badge bg-primary">{{ $applicantResponse['title'] }}</span>
                                                                    <span class="small text-muted">{{ $applicantResponse['summary'] }}</span>
                                                                </div>
                                                            @endif
                                                        </td>
                                                        <td>{{ $application->submittedBy?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                                        <td>
                                                            <div class="responsibility-stack">
                                                                <div class="responsibility-row">
                                                                    <span class="fw-semibold">{{ __('app.admin.applications.responsibility_rfc') }}:</span>
                                                                    <span class="text-muted">{{ $responsibility['rfc_owner'] }}</span>
                                                                </div>
                                                                <div class="responsibility-row">
                                                                    <span class="fw-semibold">{{ __('app.admin.applications.responsibility_authorities') }}:</span>
                                                                    @if ($responsibility['authority_items']->isNotEmpty())
                                                                        <div class="mt-1">
                                                                            @foreach ($responsibility['authority_items'] as $authorityItem)
                                                                                <div class="text-muted">
                                                                                    {{ $authorityItem['authority'] }}:
                                                                                    {{ $authorityItem['owner'] }}
                                                                                    <span class="badge bg-{{ $authorityItem['is_shared'] ? 'primary' : 'warning' }}">{{ $authorityItem['status'] }}</span>
                                                                                    @if ($authorityItem['signal_label'])
                                                                                        <span class="badge bg-{{ $authorityItem['is_overdue'] ? 'danger' : 'secondary' }}">{{ $authorityItem['signal_label'] }}</span>
                                                                                    @endif
                                                                                    @if ($authorityItem['is_escalated'])
                                                                                        <span class="badge bg-dark">{{ __('app.admin.authority_escalations.escalated_badge') }}</span>
                                                                                    @endif
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    @else
                                                                        <span class="text-muted">{{ __('app.admin.applications.responsibility_authorities_resolved') }}</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><span class="badge bg-{{ $checkpointClass($checkpoint['key']) }}">{{ $checkpoint['label'] }}</span></td>
                                                        <td>{{ optional($application->submitted_at ?? $application->created_at)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                                        <td><span class="badge bg-{{ $statusClass($application->status) }}">{{ $application->localizedStatus() }}</span></td>
                                                        <td class="admin-applications-actions-cell">
                                                            <div class="flex align-items-center list-user-action">
                                                                <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('admin.applications.show', $application) }}">
                                                                    <span class="btn-inner">
                                                                        <i class="ph ph-eye fs-6"></i>
                                                                    </span>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="9">{{ __('app.admin.applications.empty_state') }}</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
