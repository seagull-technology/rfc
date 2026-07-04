@php
    $title = __('app.admin.applications.title');
    $breadcrumb = __('app.admin.navigation.applications');
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

        .admin-applications-index-layout .applications-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 1rem;
        }

        .admin-applications-index-layout .applications-status-card {
            --status-color: #6f1f1b;
            --status-bg: rgba(111, 31, 27, .08);
            height: 100%;
            min-height: 158px;
            border: 1px solid #e2e7ef;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
            overflow: hidden;
        }

        .admin-applications-index-layout .applications-status-card::before {
            content: "";
            display: block;
            height: 4px;
            background: var(--status-color);
        }

        .admin-applications-index-layout .applications-status-card .card-body {
            min-height: 154px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 1rem;
            text-align: initial;
        }

        .admin-applications-index-layout .applications-status-card.is-warning {
            --status-color: #9f883a;
            --status-bg: rgba(159, 136, 58, .14);
        }

        .admin-applications-index-layout .applications-status-card.is-danger {
            --status-color: #ce0812;
            --status-bg: rgba(206, 8, 18, .1);
        }

        .admin-applications-index-layout .applications-status-card.is-success {
            --status-color: #198754;
            --status-bg: rgba(25, 135, 84, .1);
        }

        .admin-applications-index-layout .applications-status-label {
            color: #667085;
            font-size: .94rem;
            font-weight: 800;
            line-height: 1.35;
            min-height: 2.55rem;
        }

        .admin-applications-index-layout .applications-status-icon {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--status-color);
            background: var(--status-bg);
        }

        .admin-applications-index-layout .applications-status-card .counter {
            color: #111827;
            font-size: clamp(2rem, 2.8vw, 2.85rem);
            font-weight: 900;
            line-height: 1;
        }

        .admin-applications-index-layout .applications-status-card.is-danger .counter {
            color: #6f1f1b;
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

        .admin-applications-index-layout .admin-applications-table-scroll .row {
            margin-right: 0;
            margin-left: 0;
        }

        .admin-applications-index-layout .admin-applications-table {
            min-width: 760px;
            table-layout: fixed;
            width: 100%;
        }

        .admin-applications-index-layout .admin-applications-table thead th,
        .admin-applications-index-layout .admin-applications-table tbody td {
            white-space: normal;
            word-break: break-word;
        }

        .admin-applications-index-layout .admin-applications-table thead th,
        .admin-applications-index-layout .admin-applications-table tbody td {
            vertical-align: middle;
        }

        .admin-applications-index-layout .admin-applications-table tbody td:first-child,
        .admin-applications-index-layout .admin-applications-table thead th:first-child {
            text-align: center;
        }

        .admin-applications-index-layout .admin-applications-table thead th:last-child,
        .admin-applications-index-layout .admin-applications-table tbody td:last-child {
            text-align: center;
            white-space: nowrap;
            word-break: normal;
        }

        .admin-applications-index-layout .admin-applications-actions-cell .list-user-action {
            justify-content: center;
        }

        .admin-applications-index-layout .application-authority-signals {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            justify-content: center;
            margin-top: .5rem;
        }

        .admin-applications-index-layout .application-authority-signals .badge {
            font-size: .72rem;
            white-space: normal;
        }

        @media (max-width: 1199.98px) {
            .admin-applications-index-layout .applications-status-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .admin-applications-index-layout .applications-status-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .admin-applications-index-layout .applications-status-grid {
                grid-template-columns: 1fr;
            }

            .admin-applications-index-layout .nav-pills {
                flex-wrap: wrap;
                gap: .5rem;
                overflow-x: visible;
            }

            .admin-applications-index-layout .nav-pills .nav-item {
                flex: 1 1 100%;
                min-width: 0;
            }

            .admin-applications-index-layout .nav-pills .nav-link {
                padding: 1rem !important;
                white-space: normal;
                word-break: normal;
            }
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

        <div class="col-12">
            @php
                $statusCards = [
                    [
                        'label' => __('app.admin.workflow_states.needs_admin_review'),
                        'value' => $stats['needs_admin_review'],
                        'icon' => 'ph-clipboard-text',
                        'class' => '',
                    ],
                    [
                        'label' => __('app.admin.workflow_states.waiting_on_applicant'),
                        'value' => $stats['waiting_on_applicant'],
                        'icon' => 'ph-user-focus',
                        'class' => 'is-danger',
                    ],
                    [
                        'label' => __('app.admin.workflow_states.waiting_authorities'),
                        'value' => $stats['waiting_authorities'],
                        'icon' => 'ph-buildings',
                        'class' => 'is-warning',
                    ],
                    [
                        'label' => __('app.admin.authority_escalations.metrics.due_soon_requests'),
                        'value' => $stats['due_soon_authorities'],
                        'icon' => 'ph-timer',
                        'class' => 'is-warning',
                    ],
                    [
                        'label' => __('app.admin.authority_escalations.metrics.overdue_requests'),
                        'value' => $stats['overdue_authorities'],
                        'icon' => 'ph-warning-octagon',
                        'class' => 'is-danger',
                    ],
                    [
                        'label' => __('app.admin.workflow_states.ready_final_decision'),
                        'value' => $stats['ready_final_decision'],
                        'icon' => 'ph-seal-check',
                        'class' => 'is-success',
                    ],
                ];
            @endphp
            <div class="applications-status-grid">
                @foreach ($statusCards as $statusCard)
                    <div class="card applications-status-card {{ $statusCard['class'] }}">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div class="applications-status-label">{{ $statusCard['label'] }}</div>
                                <span class="applications-status-icon rounded">
                                    <i class="ph {{ $statusCard['icon'] }} fs-4"></i>
                                </span>
                            </div>
                            <div class="counter">{{ $statusCard['value'] }}</div>
                        </div>
                    </div>
                @endforeach
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
                                                <col style="width: 5%">
                                                <col style="width: 15%">
                                                <col style="width: 27%">
                                                <col style="width: 18%">
                                                <col style="width: 15%">
                                                <col style="width: 10%">
                                                <col style="width: 10%">
                                            </colgroup>
                                            <thead>
                                                <tr class="ligth">
                                                    <th>#</th>
                                                    <th>{{ __('app.admin.applications.request_number') }}</th>
                                                    <th>{{ __('app.applications.project_name') }}</th>
                                                    <th>{{ __('app.admin.applications.applicant') }}</th>
                                                    <th>{{ __('app.admin.applications.submitted_at') }}</th>
                                                    <th>{{ __('app.applications.status') }}</th>
                                                    <th>{{ __('app.admin.applications.audit_action') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($rows as $application)
                                                    <tr>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td>{{ $application->code ?: __('app.dashboard.not_available') }}</td>
                                                        <td>{{ $application->project_name }}</td>
                                                        <td>{{ $application->submittedBy?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                                        <td>{{ optional($application->submitted_at ?? $application->created_at)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                                        <td>
                                                            <span class="badge bg-{{ $statusClass($application->status) }}">{{ $application->localizedStatus() }}</span>
                                                            @php
                                                                $applicationSignals = $applicationAuthoritySignals->get($application->getKey(), collect());
                                                            @endphp
                                                            @if ($applicationSignals->isNotEmpty())
                                                                <div class="application-authority-signals">
                                                                    @foreach ($applicationSignals->take(2) as $signal)
                                                                        <span class="badge bg-{{ $signal['is_overdue'] ? 'danger' : ($signal['is_due_soon'] ? 'warning text-dark' : 'secondary') }}">{{ $signal['label'] }}</span>
                                                                        @if ($signal['is_escalated'])
                                                                            <span class="badge bg-dark">{{ __('app.admin.authority_escalations.escalated_badge') }}</span>
                                                                        @endif
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </td>
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
                                                        <td colspan="7">{{ __('app.admin.applications.empty_state') }}</td>
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
