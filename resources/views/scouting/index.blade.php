@php
    $title = __('app.scouting.title');
    $activeRequests = $requests->whereIn('status', ['draft', 'submitted', 'under_review', 'needs_clarification'])->values();
    $previousRequests = $requests->whereIn('status', ['approved', 'rejected'])->values();
    $statusClass = static fn (string $status): string => match ($status) {
        'approved' => 'success',
        'rejected' => 'danger',
        'submitted' => 'warning',
        'under_review' => 'warning',
        'needs_clarification' => 'danger',
        default => 'secondary',
    };
@endphp

@extends('layouts.portal-dashboard', ['title' => $title])

@section('page_layout_class', 'scouting-index-layout')

@push('styles')
    <style>
        .scouting-index-layout {
            padding-top: 0 !important;
        }

        .scouting-index-layout .card-header {
            padding-bottom: 0;
        }

        .scouting-index-layout .scouting-tools-card {
            margin-bottom: 1.5rem;
        }

        .scouting-index-layout .scouting-tools-card .card-body {
            padding: 1.25rem 1.5rem;
        }

        .scouting-index-layout .nav-pills .nav-link {
            white-space: nowrap;
        }

        .scouting-index-layout table thead th,
        .scouting-index-layout table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .scouting-index-layout .table-view table tbody td:first-child,
        .scouting-index-layout .table-view table thead th:first-child {
            width: 70px;
        }

        .scouting-index-layout .table-view table tbody td:last-child,
        .scouting-index-layout .table-view table thead th:last-child {
            width: 110px;
        }
    </style>
@endpush

@section('content')
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
        <h2 class="episode-playlist-title wp-heading-inline mb-0">
            <span class="position-relative">{{ __('app.scouting.title') }}</span>
        </h2>
    </div>

    <div class="card scouting-tools-card">
        <div class="card-body">
            <form method="GET" action="{{ route('scouting-requests.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label" for="q">{{ __('app.scouting.search_label') }}</label>
                    <input id="q" name="q" type="text" class="form-control bg-white" value="{{ $filters['q'] }}" placeholder="{{ __('app.scouting.search_placeholder') }}">
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="status">{{ __('app.applications.status') }}</label>
                    <select id="status" name="status" class="form-control bg-white select2-basic-single">
                        @foreach (['all', 'draft', 'submitted', 'under_review', 'needs_clarification', 'approved', 'rejected'] as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status === 'all' ? __('app.applications.all_statuses') : __('app.statuses.'.$status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-4 d-flex gap-2 flex-wrap justify-content-lg-end">
                    <a class="btn btn-danger" href="{{ route('scouting-requests.create') }}"><i class="fa-solid fa-plus me-2"></i>{{ __('app.scouting.create_action') }}</a>
                    <button class="btn btn-primary" type="submit">{{ __('app.applications.apply_filters_action') }}</button>
                    <a class="btn btn-outline-primary" href="{{ route('scouting-requests.index') }}">{{ __('app.applications.clear_filters_action') }}</a>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-pills mb-0 nav-fill" id="scouting-request-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link p-4 fontSize20 active" id="scouting-active-tab" data-bs-toggle="pill" href="#scouting-active-pane" role="tab" aria-selected="true">{{ __('app.scouting.open_requests') }}</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link p-4 fontSize20" id="scouting-previous-tab" data-bs-toggle="pill" href="#scouting-previous-pane" role="tab" aria-selected="false">{{ __('app.scouting.previous_requests') }}</a>
        </li>
    </ul>

    <div class="tab-content" id="scouting-tab-content">
        @foreach (['active' => $activeRequests, 'previous' => $previousRequests] as $tab => $collection)
            <div class="tab-pane fade {{ $tab === 'active' ? 'show active' : '' }} border p-5" id="scouting-{{ $tab }}-pane" role="tabpanel">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="streamit-wraper-table">
                            <div class="table-view table-space">
                                <table class="data-tables table custom-table data-table-one custom-table-height">
                                    <thead>
                                        <tr class="ligth">
                                            <th>#</th>
                                            <th>{{ __('app.applications.request_number') }}</th>
                                            <th>{{ __('app.applications.project_name') }}</th>
                                            <th>{{ __('app.authority.applications.applicant') }}</th>
                                            <th>{{ __('app.applications.submitted_at_label') }}</th>
                                            <th>{{ __('app.applications.status') }}</th>
                                            <th>{{ __('app.applications.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($collection as $requestRecord)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $requestRecord->code }}</td>
                                                <td>{{ $requestRecord->project_name }}</td>
                                                <td>{{ $requestRecord->entity?->displayName() ?? $user->displayName() }}</td>
                                                <td>{{ optional($requestRecord->submitted_at ?? $requestRecord->created_at)->format('Y-m-d') }}</td>
                                                <td><span class="badge bg-{{ $statusClass($requestRecord->status) }}">{{ $requestRecord->localizedStatus() }}</span></td>
                                                <td>
                                                    <div class="flex align-items-center list-user-action">
                                                        <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('scouting-requests.show', $requestRecord) }}">
                                                            <span class="btn-inner"><i class="ph ph-eye fs-6"></i></span>
                                                        </a>
                                                        @if ($requestRecord->canBeEditedByApplicant())
                                                            <a class="btn btn-sm btn-icon btn-secondary-subtle rounded ms-1" href="{{ route('scouting-requests.edit', $requestRecord) }}">
                                                                <span class="btn-inner"><i class="ph ph-pen fs-6"></i></span>
                                                            </a>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7">{{ __('app.scouting.empty_state') }}</td>
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
@endsection
