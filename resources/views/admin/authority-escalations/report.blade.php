@php
    $title = __('app.admin.authority_escalations.report_title');
    $breadcrumb = __('app.admin.navigation.authority_escalations');
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@push('styles')
    <style>
        .authority-escalation-report-layout {
            padding-top: 0;
        }

        .authority-escalation-report-layout .card {
            margin-bottom: 1.5rem;
        }

        .authority-escalation-report-layout .hero-card .card-body,
        .authority-escalation-report-layout .report-card .card-body,
        .authority-escalation-report-layout .overdue-card .card-body {
            padding: 1.5rem;
        }

        .authority-escalation-report-layout .metric-stack,
        .authority-escalation-report-layout .badge-stack {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .authority-escalation-report-layout .report-meta {
            color: #6c757d;
            font-size: .9rem;
        }

        .authority-escalation-report-layout .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .authority-escalation-report-layout .report-metric {
            background: rgba(255, 255, 255, .8);
            border: 1px solid rgba(0, 0, 0, .06);
            border-radius: .75rem;
            padding: 1rem;
        }

        .authority-escalation-report-layout .report-metric h6 {
            margin-bottom: .5rem;
        }

        .authority-escalation-report-layout .report-list-item + .report-list-item {
            border-top: 1px solid rgba(0, 0, 0, .08);
            margin-top: 1rem;
            padding-top: 1rem;
        }
    </style>
@endpush

@section('page_layout_class', 'authority-escalation-report-layout')

@section('content')
    <div class="row g-3">
        <div class="col-12">
            <div class="card hero-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                                <span class="position-relative">{{ __('app.admin.authority_escalations.report_title') }}</span>
                            </h2>
                            <div class="text-muted">{{ __('app.admin.authority_escalations.report_intro') }}</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-outline-dark" href="{{ route('admin.authority-escalations.index') }}">{{ __('app.admin.authority_escalations.back_to_control') }}</a>
                            <a class="btn btn-danger" href="{{ route('admin.authority-escalations.export', request()->query()) }}">{{ __('app.reports.export_current') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.authority-escalations.report') }}" class="row g-3 align-items-end">
                        <div class="col-lg-3">
                            <label class="form-label">{{ __('app.admin.authority_escalations.activity_window') }}</label>
                            <select class="form-select bg-white" name="window">
                                @foreach ($filters['window_options'] as $windowOption)
                                    <option value="{{ $windowOption }}" @selected($filters['window'] === $windowOption)>
                                        {{ $windowOption === 'all' ? __('app.admin.authority_escalations.all_windows') : __('app.admin.authority_escalations.window_last_days', ['days' => $windowOption]) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">{{ __('app.admin.authority_escalations.authority') }}</label>
                            <select class="form-select bg-white" name="authority">
                                <option value="">{{ __('app.admin.authority_escalations.all_authorities') }}</option>
                                @foreach ($availableAuthorities as $authority)
                                    <option value="{{ $authority->getKey() }}" @selected($filters['authority_id'] === $authority->getKey())>{{ $authority->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-5 d-flex gap-2">
                            <button class="btn btn-dark" type="submit">{{ __('app.admin.authority_escalations.apply_filters') }}</button>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.authority-escalations.report') }}">{{ __('app.admin.authority_escalations.reset_filters') }}</a>
                        </div>
                    </form>
                    <div class="report-meta mt-3">{{ __('app.admin.authority_escalations.window_scope_hint') }}</div>
                </div>
            </div>
        </div>

        @foreach ([
            ['label' => __('app.admin.authority_escalations.metrics.authorities'), 'value' => $stats['authorities']],
            ['label' => __('app.admin.authority_escalations.metrics.live_approvals'), 'value' => $stats['live_approvals']],
            ['label' => __('app.admin.authority_escalations.metrics.due_soon_approvals'), 'value' => $stats['due_soon_approvals']],
            ['label' => __('app.admin.authority_escalations.metrics.overdue_approvals'), 'value' => $stats['overdue_approvals']],
            ['label' => __('app.admin.authority_escalations.metrics.escalated_approvals'), 'value' => $stats['escalated_approvals']],
            ['label' => __('app.admin.authority_escalations.recent_escalations'), 'value' => $stats['recent_escalations']],
            ['label' => __('app.admin.authority_escalations.average_resolution_hours'), 'value' => $stats['average_resolution_hours'] !== null ? number_format($stats['average_resolution_hours'], 1) : __('app.dashboard.not_available')],
        ] as $metric)
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card">
                    <div class="card-body">
                        <h6>{{ $metric['label'] }}</h6>
                        <h3>{{ $metric['value'] }}</h3>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-12">
            <div class="row g-3">
                @forelse ($rows as $row)
                    <div class="col-12">
                        <div class="card report-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                    <div>
                                        <h4 class="mb-1">{{ $row['entity']->displayName() }}</h4>
                                        <div class="report-meta">
                                            {{ __('app.admin.authority_escalations.response_time_days') }}:
                                            {{ $row['response_time_days'] ?? __('app.admin.authority_escalations.unconfigured_badge') }}
                                        </div>
                                    </div>
                                    <div class="badge-stack">
                                        <span class="badge bg-warning-subtle text-dark">{{ __('app.admin.authority_escalations.live_badges.due_soon', ['count' => $row['due_soon_live_approvals']]) }}</span>
                                        <span class="badge bg-danger-subtle text-dark">{{ __('app.admin.authority_escalations.live_badges.overdue', ['count' => $row['overdue_live_approvals']]) }}</span>
                                        <span class="badge bg-dark-subtle text-dark">{{ __('app.admin.authority_escalations.live_badges.escalated', ['count' => $row['escalated_live_approvals']]) }}</span>
                                        <span class="badge bg-info-subtle text-dark">{{ __('app.admin.authority_escalations.live_badges.live', ['count' => $row['live_approvals']]) }}</span>
                                    </div>
                                </div>

                                <div class="badge-stack mb-3">
                                    @forelse ($row['approval_labels'] as $approvalLabel)
                                        <span class="badge bg-secondary-subtle text-dark">{{ $approvalLabel }}</span>
                                    @empty
                                        <span class="text-muted">{{ __('app.admin.authority_escalations.no_approval_codes') }}</span>
                                    @endforelse
                                </div>

                                <div class="report-grid">
                                    <div class="report-metric">
                                        <h6>{{ __('app.admin.authority_escalations.metrics.live_approvals') }}</h6>
                                        <div class="h3 mb-0">{{ $row['live_approvals'] }}</div>
                                    </div>
                                    <div class="report-metric">
                                        <h6>{{ __('app.admin.authority_escalations.metrics.due_soon_approvals') }}</h6>
                                        <div class="h3 mb-0">{{ $row['due_soon_live_approvals'] }}</div>
                                    </div>
                                    <div class="report-metric">
                                        <h6>{{ __('app.admin.authority_escalations.metrics.overdue_approvals') }}</h6>
                                        <div class="h3 mb-0">{{ $row['overdue_live_approvals'] }}</div>
                                    </div>
                                    <div class="report-metric">
                                        <h6>{{ __('app.admin.authority_escalations.shared_inbox_live') }}</h6>
                                        <div class="h3 mb-0">{{ $row['shared_inbox_live_approvals'] }}</div>
                                    </div>
                                    <div class="report-metric">
                                        <h6>{{ __('app.admin.authority_escalations.assigned_live') }}</h6>
                                        <div class="h3 mb-0">{{ $row['assigned_live_approvals'] }}</div>
                                    </div>
                                    <div class="report-metric">
                                        <h6>{{ __('app.admin.authority_escalations.approvals_in_window') }}</h6>
                                        <div class="h3 mb-0">{{ $row['approvals_in_window'] }}</div>
                                    </div>
                                    <div class="report-metric">
                                        <h6>{{ __('app.admin.authority_escalations.resolved_in_window') }}</h6>
                                        <div class="h3 mb-0">{{ $row['resolved_in_window'] }}</div>
                                    </div>
                                    <div class="report-metric">
                                        <h6>{{ __('app.admin.authority_escalations.recent_escalations') }}</h6>
                                        <div class="h3 mb-0">{{ $row['recent_escalations_count'] }}</div>
                                    </div>
                                    <div class="report-metric">
                                        <h6>{{ __('app.admin.authority_escalations.average_resolution_hours') }}</h6>
                                        <div class="h3 mb-0">{{ $row['average_resolution_hours'] !== null ? number_format($row['average_resolution_hours'], 1) : __('app.dashboard.not_available') }}</div>
                                    </div>
                                    <div class="report-metric">
                                        <h6>{{ __('app.admin.authority_escalations.oldest_live_age') }}</h6>
                                        <div class="h3 mb-0">{{ $row['oldest_live_age_hours'] !== null ? number_format($row['oldest_live_age_hours'], 1) : __('app.dashboard.not_available') }}</div>
                                    </div>
                                    <div class="report-metric">
                                        <h6>{{ __('app.admin.authority_escalations.last_escalated_at') }}</h6>
                                        <div class="h6 mb-0">{{ $row['last_escalated_at']?->format('Y-m-d H:i') ?? __('app.dashboard.not_available') }}</div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-3">
                                    <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.authority-escalations.report', ['window' => $filters['window'], 'authority' => $row['entity']->getKey()]) }}">{{ __('app.admin.authority_escalations.view_authority_drilldown') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center text-muted py-4">{{ __('app.admin.authority_escalations.report_empty_state') }}</div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="col-12">
            <div class="card overdue-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('app.admin.authority_escalations.top_due_soon_title') }}</h4>
                            <div class="report-meta">{{ __('app.admin.authority_escalations.top_due_soon_intro') }}</div>
                        </div>
                    </div>

                    @forelse ($recentDueSoonApprovals as $item)
                        <div class="report-list-item">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                <div>
                                    <h5 class="mb-1">{{ $item['approval']->application?->project_name ?? __('app.dashboard.not_available') }}</h5>
                                    <div class="report-meta">
                                        {{ $item['approval']->application?->code ?? __('app.dashboard.not_available') }}
                                        · {{ $item['approval']->localizedAuthority() }}
                                    </div>
                                    <div class="report-meta mt-1">
                                        {{ $item['approval']->assignedTo?->displayName() ?? __('app.admin.applications.authority_shared_inbox') }}
                                        · {{ __('app.admin.authority_escalations.due_at_label', ['date' => optional($item['signal']['due_at'])->format('Y-m-d h:i A') ?? __('app.dashboard.not_available')]) }}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge bg-warning text-dark">{{ $item['signal']['label'] }}</span>
                                    @if ($item['approval']->application)
                                        <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.applications.show', $item['approval']->application) }}">{{ __('app.admin.authority_escalations.open_request') }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">{{ __('app.admin.authority_escalations.no_due_soon_items') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card overdue-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('app.admin.authority_escalations.top_overdue_title') }}</h4>
                            <div class="report-meta">{{ __('app.admin.authority_escalations.top_overdue_intro') }}</div>
                        </div>
                    </div>

                    @forelse ($recentOverdueApprovals as $item)
                        <div class="report-list-item">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                <div>
                                    <h5 class="mb-1">{{ $item['approval']->application?->project_name ?? __('app.dashboard.not_available') }}</h5>
                                    <div class="report-meta">
                                        {{ $item['approval']->application?->code ?? __('app.dashboard.not_available') }}
                                        · {{ $item['approval']->localizedAuthority() }}
                                    </div>
                                    <div class="report-meta mt-1">
                                        {{ $item['approval']->assignedTo?->displayName() ?? __('app.admin.applications.authority_shared_inbox') }}
                                        · {{ __('app.admin.authority_escalations.due_at_label', ['date' => optional($item['signal']['due_at'])->format('Y-m-d h:i A') ?? __('app.dashboard.not_available')]) }}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge bg-danger">{{ $item['signal']['label'] }}</span>
                                    @if ($item['signal']['is_escalated'])
                                        <span class="badge bg-dark">{{ __('app.admin.authority_escalations.escalated_badge') }}</span>
                                    @endif
                                    @if ($item['approval']->application)
                                        <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.applications.show', $item['approval']->application) }}">{{ __('app.admin.authority_escalations.open_request') }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">{{ __('app.admin.authority_escalations.no_overdue_items') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        @if ($selectedAuthority && $selectedAuthorityDetails)
            <div class="col-12">
                <div class="card overdue-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                            <div>
                                <h4 class="mb-1">{{ __('app.admin.authority_escalations.drilldown_title', ['authority' => $selectedAuthority->displayName()]) }}</h4>
                                <div class="report-meta">{{ __('app.admin.authority_escalations.drilldown_intro') }}</div>
                            </div>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.authority-escalations.report', ['window' => $filters['window']]) }}">{{ __('app.admin.authority_escalations.clear_authority_filter') }}</a>
                        </div>

                        <div class="report-list-item pt-0 mt-0 border-0">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                                <h5 class="mb-0">{{ __('app.admin.authority_escalations.live_queue_title') }}</h5>
                                @can('applications.assign')
                                    @if ($selectedAuthorityDetails['bulkAssignableDelegates']->isNotEmpty())
                                        <form id="authority-bulk-assign-form" method="POST" action="{{ route('admin.authority-escalations.bulk-assign', $selectedAuthority) }}" class="d-flex flex-wrap align-items-end gap-2">
                                            @csrf
                                            <input type="hidden" name="window" value="{{ $filters['window'] }}">
                                            <div>
                                                <label class="form-label mb-1">{{ __('app.admin.authority_escalations.bulk_assign_title') }}</label>
                                                <select name="assigned_user_id" class="form-select form-select-sm">
                                                    <option value="">{{ __('app.admin.applications.authority_shared_inbox') }}</option>
                                                    @foreach ($selectedAuthorityDetails['bulkAssignableDelegates'] as $delegate)
                                                        <option value="{{ $delegate->getKey() }}">{{ $delegate->displayName() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label mb-1">{{ __('app.admin.applications.authority_assignment_note') }}</label>
                                                <input name="assignment_note" type="text" class="form-control form-control-sm" placeholder="{{ __('app.admin.authority_escalations.bulk_assign_placeholder') }}">
                                            </div>
                                            <div>
                                                <label class="form-label mb-1 d-block">{{ __('app.admin.authority_escalations.bulk_assign_hint') }}</label>
                                                <button class="btn btn-sm btn-dark" type="submit">{{ __('app.admin.authority_escalations.bulk_assign_action') }}</button>
                                            </div>
                                        </form>
                                    @endif
                                @endcan
                            </div>

                            @forelse ($selectedAuthorityDetails['liveQueue'] as $item)
                                <div class="report-list-item">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                        <div class="d-flex gap-3">
                                            @can('applications.assign')
                                                @if ($selectedAuthorityDetails['bulkAssignableDelegates']->isNotEmpty())
                                                    <div class="pt-1">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            name="approval_ids[]"
                                                            value="{{ $item['approval']->getKey() }}"
                                                            form="authority-bulk-assign-form"
                                                            aria-label="{{ __('app.admin.authority_escalations.select_approval_for_bulk_action') }}"
                                                        >
                                                    </div>
                                                @endif
                                            @endcan
                                            <div>
                                            <h5 class="mb-1">{{ $item['approval']->application?->project_name ?? __('app.dashboard.not_available') }}</h5>
                                            <div class="report-meta">
                                                {{ $item['approval']->application?->code ?? __('app.dashboard.not_available') }}
                                                · {{ $item['approval']->localizedStatus() }}
                                            </div>
                                            <div class="report-meta mt-1">
                                                {{ __('app.admin.authority_escalations.current_owner') }}:
                                                {{ $item['approval']->assignedTo?->displayName() ?? __('app.admin.applications.authority_shared_inbox') }}
                                            </div>
                                            <div class="report-meta mt-1">
                                                {{ __('app.admin.authority_escalations.live_age_label', ['hours' => $item['live_age_hours'] !== null ? number_format($item['live_age_hours'], 1) : __('app.dashboard.not_available')]) }}
                                            </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                                            @if ($item['signal']['label'])
                                                <span class="badge {{ $item['signal']['is_overdue'] ? 'bg-danger' : ($item['signal']['is_due_soon'] ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ $item['signal']['label'] }}</span>
                                            @endif
                                            @if ($item['signal']['is_escalated'])
                                                <span class="badge bg-dark">{{ __('app.admin.authority_escalations.escalated_badge') }}</span>
                                            @endif
                                            @if ($item['signal']['due_at'])
                                                <span class="badge bg-light text-dark border">{{ __('app.admin.authority_escalations.due_at_label', ['date' => $item['signal']['due_at']->format('Y-m-d h:i A')]) }}</span>
                                            @endif
                                            @can('users.view')
                                                @if ($item['approval']->assignedTo)
                                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.show', $item['approval']->assignedTo) }}">{{ __('app.admin.authority_escalations.open_owner_profile') }}</a>
                                                @endif
                                            @endcan
                                            @can('entities.view')
                                                @if ($item['approval']->entity)
                                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.entities.show', $item['approval']->entity) }}">{{ __('app.admin.authority_escalations.open_authority_profile') }}</a>
                                                @endif
                                            @endcan
                                            @if ($item['approval']->application)
                                                <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.applications.show', $item['approval']->application) }}">{{ __('app.admin.authority_escalations.open_request') }}</a>
                                            @endif
                                        </div>
                                    </div>

                                    @can('applications.assign')
                                        @if ($item['approval']->application && $item['assignableDelegates']->isNotEmpty())
                                            <form method="POST" action="{{ route('admin.applications.approvals.assign', [$item['approval']->application, $item['approval']]) }}" class="mt-3 pt-3 border-top">
                                                @csrf
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-xl-4 col-lg-5">
                                                        <label class="form-label">{{ __('app.admin.authority_escalations.quick_reassign_title') }}</label>
                                                        <select name="assigned_user_id" class="form-select form-select-sm">
                                                            <option value="">{{ __('app.admin.applications.authority_shared_inbox') }}</option>
                                                            @foreach ($item['assignableDelegates'] as $delegate)
                                                                <option value="{{ $delegate->getKey() }}" @selected($item['approval']->assigned_user_id === $delegate->getKey())>{{ $delegate->displayName() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-xl-5 col-lg-4">
                                                        <label class="form-label">{{ __('app.admin.applications.authority_assignment_note') }}</label>
                                                        <input name="assignment_note" type="text" class="form-control form-control-sm" placeholder="{{ __('app.admin.authority_escalations.quick_reassign_placeholder') }}">
                                                    </div>
                                                    <div class="col-xl-3 col-lg-3">
                                                        <button class="btn btn-sm btn-outline-secondary w-100" type="submit">{{ __('app.admin.applications.reassign_approval_action') }}</button>
                                                    </div>
                                                </div>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            @empty
                                <div class="text-center text-muted py-4">{{ __('app.admin.authority_escalations.live_queue_empty') }}</div>
                            @endforelse
                        </div>

                        <div class="report-list-item">
                            <h5 class="mb-3">{{ __('app.admin.authority_escalations.escalation_history_title') }}</h5>

                            @forelse ($selectedAuthorityDetails['escalationHistory'] as $event)
                                <div class="report-list-item">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                        <div>
                                            <h5 class="mb-1">{{ $event->application?->project_name ?? __('app.dashboard.not_available') }}</h5>
                                            <div class="report-meta">
                                                {{ $event->application?->code ?? __('app.dashboard.not_available') }}
                                                · {{ $event->happened_at?->format('Y-m-d H:i') ?? __('app.dashboard.not_available') }}
                                            </div>
                                            <div class="report-meta mt-1">{{ $event->note ?: __('app.dashboard.not_available') }}</div>
                                            <div class="report-meta mt-1">
                                                {{ __('app.admin.authority_escalations.escalated_by_label', ['name' => $event->user?->displayName() ?? __('app.admin.authority_escalations.system_actor')]) }}
                                            </div>
                                        </div>
                                        @if ($event->application)
                                            <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.applications.show', $event->application) }}">{{ __('app.admin.authority_escalations.open_request') }}</a>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted py-4">{{ __('app.admin.authority_escalations.escalation_history_empty') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
