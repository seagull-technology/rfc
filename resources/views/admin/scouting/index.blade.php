@php
    $title = __('app.admin.scouting.title');
    $breadcrumb = __('app.admin.navigation.scouting_requests');
    $checkpointMeta = static fn ($requestRecord): array => \App\Support\AdminWorkflowState::scoutingCheckpoint($requestRecord);
    $checkpointClass = static fn (string $key): string => match ($key) {
        'waiting_on_applicant' => 'danger',
        'resolved' => 'success',
        'draft' => 'secondary',
        default => 'info',
    };
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

        .admin-applications-index-layout .tools-card {
            margin-bottom: 1.5rem;
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

        .admin-applications-index-layout table thead th,
        .admin-applications-index-layout table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .admin-applications-index-layout .table-view table tbody td:first-child,
        .admin-applications-index-layout .table-view table thead th:first-child {
            width: 70px;
        }

        .admin-applications-index-layout .response-flag {
            margin-top: .5rem;
        }

        .admin-applications-index-layout .response-flag .small {
            display: block;
            margin-top: .35rem;
            white-space: normal;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
                <h2 class="episode-playlist-title wp-heading-inline mb-0">
                    <span class="position-relative">{{ __('app.admin.scouting.directory_title') }}</span>
                </h2>
            </div>
        </div>

        <div class="col-12">
            <div class="card tools-card">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.scouting-requests.index') }}" class="row g-3 align-items-end">
                        <div class="col-lg-5">
                            <label class="form-label" for="q">{{ __('app.admin.filters.search_label') }}</label>
                            <input id="q" name="q" type="text" class="form-control bg-white" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.scouting.search_placeholder') }}">
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label" for="status">{{ __('app.applications.status') }}</label>
                            <select id="status" name="status" class="form-control bg-white">
                                @foreach (['all', 'draft', 'submitted', 'under_review', 'needs_clarification', 'approved', 'rejected'] as $status)
                                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status === 'all' ? __('app.admin.filters.all_option') : __('app.statuses.'.$status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 d-flex gap-2 flex-wrap justify-content-lg-end">
                            <a class="btn btn-primary" href="{{ route('admin.scouting-requests.export', request()->query()) }}">{{ __('app.reports.export_current') }}</a>
                            <button class="btn btn-danger" type="submit">{{ __('app.admin.filters.apply_action') }}</button>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.scouting-requests.index') }}">{{ __('app.admin.filters.clear_action') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted">{{ __('app.admin.workflow_states.needs_admin_review') }}</div>
                    <h2 class="counter">{{ $stats['needs_admin_review'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted">{{ __('app.admin.workflow_states.waiting_on_applicant') }}</div>
                    <h2 class="counter">{{ $stats['waiting_on_applicant'] }}</h2>
                </div>
            </div>
        </div>

        <div class="col-12">
            <ul class="nav nav-pills mb-0 nav-fill" id="pills-tab-1" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link p-4 fontSize20 active" id="pills-home-tab-fill" data-bs-toggle="pill" href="#pills-home-fill" role="tab" aria-selected="true">
                        {{ __('app.admin.scouting.open_requests') }}
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link p-4 fontSize20" id="pills-profile-tab-fill" data-bs-toggle="pill" href="#pills-profile-fill" role="tab" aria-selected="false" tabindex="-1">
                        {{ __('app.admin.scouting.previous_requests') }}
                    </a>
                </li>
            </ul>

            <div class="tab-content" id="pills-tabContent-1">
                @foreach (['home' => $openRequests, 'profile' => $closedRequests] as $pane => $rows)
                    <div class="tab-pane fade {{ $pane === 'home' ? 'show active' : '' }} border p-5" id="pills-{{ $pane }}-fill" role="tabpanel">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="streamit-wraper-table">
                                    <div class="table-view table-space">
                                        <table class="data-tables table custom-table data-table-one custom-table-height" role="grid" data-toggle="data-table">
                                            <thead>
                                                <tr class="ligth">
                                                    <th>#</th>
                                                    <th>{{ __('app.admin.scouting.request_code') }}</th>
                                                    <th>{{ __('app.applications.project_name') }}</th>
                                                    <th>{{ __('app.admin.scouting.applicant_entity') }}</th>
                                                    <th>{{ __('app.admin.dashboard.workflow_checkpoint') }}</th>
                                                    <th>{{ __('app.admin.scouting.submitted_at') }}</th>
                                                    <th>{{ __('app.applications.status') }}</th>
                                                    <th>{{ __('app.admin.scouting.actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($rows as $requestRecord)
                                                    @php($checkpoint = $checkpointMeta($requestRecord))
                                                    @php($applicantResponse = $applicantResponses[$requestRecord->getKey()] ?? ['active' => false])
                                                    <tr>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td>{{ $requestRecord->code ?: __('app.dashboard.not_available') }}</td>
                                                        <td>
                                                            {{ $requestRecord->project_name }}
                                                            @if ($applicantResponse['active'])
                                                                <div class="response-flag">
                                                                    <span class="badge bg-primary">{{ $applicantResponse['title'] }}</span>
                                                                    <span class="small text-muted">{{ $applicantResponse['summary'] }}</span>
                                                                </div>
                                                            @endif
                                                        </td>
                                                        <td>{{ $requestRecord->entity?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                                        <td><span class="badge bg-{{ $checkpointClass($checkpoint['key']) }}">{{ $checkpoint['label'] }}</span></td>
                                                        <td>{{ optional($requestRecord->submitted_at ?? $requestRecord->created_at)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                                        <td><span class="badge bg-{{ $statusClass($requestRecord->status) }}">{{ $requestRecord->localizedStatus() }}</span></td>
                                                        <td>
                                                            <div class="flex align-items-center list-user-action">
                                                                <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('admin.scouting-requests.show', $requestRecord) }}">
                                                                    <span class="btn-inner">
                                                                        <i class="ph ph-eye fs-6"></i>
                                                                    </span>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="8">{{ __('app.admin.scouting.empty_state') }}</td>
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
