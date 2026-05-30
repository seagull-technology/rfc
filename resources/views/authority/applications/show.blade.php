@php
    $title = $application->project_name;
    $metadata = $application->metadata ?? [];
    $producer = data_get($metadata, 'producer', []);
    $director = data_get($metadata, 'director', []);
    $international = data_get($metadata, 'international', []);
    $requirements = data_get($metadata, 'requirements', []);
    $formattedBudget = $application->estimated_budget ? number_format((float) $application->estimated_budget, 2) : __('app.dashboard.not_available');
    $requiredApprovals = collect(data_get($requirements, 'required_approvals', []))
        ->map(fn ($approval) => __('app.applications.required_approval_options.'.$approval))
        ->join('، ') ?: __('app.applications.no_required_approvals');

    $decisionBadgeClass = match ($currentApproval->status) {
        'approved' => 'success',
        'rejected' => 'danger',
        'in_review' => 'warning',
        default => 'secondary',
    };
    $canResolveAuthorityDecision = auth()->user()?->can('applications.approve') ?? false;
    $approvalIsResolved = in_array($currentApproval->status, ['approved', 'rejected'], true);
    $statusClass = static fn (?string $status): string => match ($status) {
        'draft' => 'secondary',
        'submitted', 'pending_review', 'pending' => 'warning',
        'under_review', 'in_review' => 'info',
        'needs_clarification' => 'warning',
        'approved', 'issued' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $timelineEvents = collect();

    foreach ($statusHistory as $event) {
        $timelineEvents->push([
            'label' => $event->user?->displayName() ?? __('app.admin.authority_escalations.system_actor'),
            'date' => $event->happened_at,
            'status' => $event->status,
            'status_label' => $event->localizedStatus(),
            'note' => $event->note,
        ]);
    }

    $hasCurrentApprovalHistory = $statusHistory->contains(
        fn ($event): bool => (int) data_get($event->metadata, 'approval_id') === (int) $currentApproval->getKey()
    );

    if (! $hasCurrentApprovalHistory) {
        $timelineEvents->push([
            'label' => $currentApproval->localizedAuthority(),
            'date' => $currentApproval->updated_at,
            'status' => $currentApproval->status,
            'status_label' => $currentApproval->localizedStatus(),
            'note' => __('app.authority.applications.current_decision'),
        ]);
    }

    $timelineEvents = $timelineEvents
        ->sortByDesc(fn (array $event): int => $event['date']?->timestamp ?? 0)
        ->values();
@endphp

@extends('layouts.authority-dashboard', ['title' => $title])

@section('page_layout_class', 'authority-request-show-layout py-0')

@push('styles')
    <style>
        .authority-request-show-layout {
            padding-top: 0;
        }

        .authority-request-show-layout .card {
            margin-bottom: 1.5rem;
        }

        .authority-request-show-layout .card-header {
            padding-bottom: 0;
        }

        .authority-request-show-layout .profile-content .tab-pane {
            transform: none !important;
        }

        .authority-request-show-layout .profile-tab,
        .authority-hero-card .profile-tab {
            flex-wrap: nowrap;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: thin;
        }

        .authority-request-show-layout .profile-tab .nav-link,
        .authority-hero-card .profile-tab .nav-link {
            white-space: nowrap;
        }

        .authority-request-show-layout .table-responsive {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
        }

        [dir="rtl"] .authority-request-show-layout .table-responsive {
            direction: ltr;
        }

        [dir="rtl"] .authority-request-show-layout .table-responsive > .table {
            direction: rtl;
        }

        .authority-request-show-layout .documents-table {
            width: 88%;
            margin: auto;
        }

        .authority-request-show-layout .authority-hero-card {
            margin: 0 1rem 1.5rem;
        }

        .authority-request-show-layout .authority-hero-card .card-body {
            padding: 1.5rem;
        }

        .authority-request-show-layout .request-pane-card .card-body > div:last-child,
        .authority-request-show-layout .request-pane-card .card-body > p:last-child {
            margin-bottom: 0 !important;
        }

        .authority-request-show-layout .approval-signal {
            border: 1px solid var(--bs-primary-border-subtle);
            background: var(--bs-primary-bg-subtle);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .authority-request-show-layout .approval-signal.signal-danger {
            border-color: var(--bs-danger-border-subtle);
            background: var(--bs-danger-bg-subtle);
        }

        .authority-request-show-layout .approval-signal.signal-warning {
            border-color: var(--bs-warning-border-subtle);
            background: var(--bs-warning-bg-subtle);
        }

        .authority-request-show-layout .approval-sla-banner {
            border: 1px solid rgba(17, 24, 39, 0.08);
            background: #f8f9fa;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .authority-request-show-layout .approval-sla-banner.overdue {
            border-color: var(--bs-danger-border-subtle);
            background: var(--bs-danger-bg-subtle);
        }

        .authority-request-show-layout .authority-decision-panel {
            border: 1px solid rgba(17, 24, 39, 0.08);
            border-radius: 6px;
            background: #fff;
            padding: 1rem;
        }

        .authority-request-show-layout .authority-decision-current {
            border: 1px solid rgba(17, 24, 39, 0.08);
            border-radius: 6px;
            background: #f8f9fa;
            padding: .875rem;
        }

        .authority-request-show-layout .authority-decision-actions {
            display: grid;
            gap: .75rem;
        }

        .authority-request-show-layout .authority-decision-action {
            align-items: flex-start;
            border-radius: 6px;
            justify-content: flex-start;
            min-height: 0;
            padding: .875rem;
            text-align: start;
            white-space: normal;
        }

        .authority-request-show-layout .authority-decision-action i {
            font-size: 1.25rem;
            line-height: 1.2;
            margin-top: .125rem;
        }

        .authority-request-show-layout .authority-decision-action span {
            line-height: 1.45;
        }

        .authority-request-show-layout .timeline-note {
            color: #6c757d;
            line-height: 1.7;
        }

        .authority-request-show-layout .authority-procedures-timeline li {
            padding-bottom: 1.25rem;
        }

        .authority-request-show-layout .approval-overview-table {
            table-layout: fixed;
            width: 100%;
        }

        .authority-request-show-layout .approval-overview-table th,
        .authority-request-show-layout .approval-overview-table td {
            vertical-align: top;
            white-space: normal;
            word-break: break-word;
        }

        .authority-request-show-layout .approval-overview-table .approval-status-cell {
            min-width: 8rem;
        }

        .authority-request-show-layout .official-letters-table {
            table-layout: fixed;
            width: 100%;
        }

        .authority-request-show-layout .official-letters-table th,
        .authority-request-show-layout .official-letters-table td {
            vertical-align: top;
            white-space: normal;
            word-break: break-word;
        }

        .authority-request-show-layout .official-letters-table .official-letter-action-cell {
            text-align: center;
        }

        .authority-request-show-layout .authority-correspondence-list {
            display: grid;
            gap: .75rem;
        }

        .authority-request-show-layout .authority-correspondence-list .list-group-item {
            border: 1px solid rgba(17, 24, 39, 0.08);
            border-radius: 6px;
            padding: .875rem;
            text-align: start;
        }

        .authority-request-show-layout .authority-correspondence-list .list-group-item + .list-group-item {
            border-top-width: 1px;
        }

        .authority-request-show-layout .authority-correspondence-summary {
            flex: 1;
            min-width: 0;
        }

        .authority-request-show-layout .authority-correspondence-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem .75rem;
        }

        .authority-request-show-layout .authority-message-readonly {
            height: auto;
            line-height: 1.7;
            min-height: 42px;
        }

        .authority-request-show-layout .authority-message-body {
            min-height: 140px;
            white-space: pre-line;
        }

        @media (max-width: 991.98px) {
            .authority-request-show-layout .authority-hero-card {
                margin: 0 .75rem 1rem;
            }

            .authority-request-show-layout .documents-table {
                width: 100%;
            }

            .authority-request-show-layout .approval-overview-table,
            .authority-request-show-layout .official-letters-table {
                min-width: 760px;
            }
        }
    </style>
@endpush

@section('hero')
    <div class="card view-request-bg authority-hero-card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="profile-img position-relative me-3 mb-3 mb-lg-0 profile-logo profile-logo1">
                        <img src="{{ asset('images/OIP.jpeg') }}" alt="User-Profile" class="theme-color-default-img img-fluid rounded-pill avatar-100" loading="lazy">
                    </div>
                    <div>
                        <h4 class="me-2 h4 text-white">{{ $application->entity?->displayName() }}</h4>
                        <h6 class="me-2 text-white">{{ $application->project_name }}</h6>
                        <div class="text-white">{{ $application->code }}</div>
                    </div>
                </div>

                <ul class="d-flex nav nav-pills mb-0 text-center profile-tab" data-toggle="slider-tab" id="profile-pills-tab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active show" data-bs-toggle="tab" href="#authority-request" role="tab" aria-selected="true">{{ __('app.authority.applications.request_tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#authority-procedures" role="tab" aria-selected="false">{{ __('app.authority.applications.procedures_tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#authority-approvals" role="tab" aria-selected="false">{{ __('app.applications.approvals_tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#authority-documents" role="tab" aria-selected="false">{{ __('app.documents.tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#authority-official-letters" role="tab" aria-selected="false">{{ __('app.official_letters.tab') }}</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="streamit-wraper-table">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="profile-content tab-content iq-tab-fade-up">
                            <div id="authority-request" class="tab-pane fade active show">
                                <div class="card request-pane-card">
                                    <div class="card-header">
                                        <div class="header-title">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.applications.project_information') }}</span>
                                            </h2>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.project_name') }}:</span><span class="ms-2">{{ $application->project_name }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.project_nationality') }}:</span><span class="ms-2">{{ __('app.applications.project_nationalities.'.$application->project_nationality) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.work_category') }}:</span><span class="ms-2">{{ __('app.applications.work_categories.'.$application->work_category) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.production_company_name') }}:</span><span class="ms-2">{{ data_get($producer, 'production_company_name', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_address') }}:</span><span class="ms-2">{{ data_get($producer, 'contact_address', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_phone') }}:</span><span class="ms-2">{{ data_get($producer, 'contact_phone', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_mobile') }}:</span><span class="ms-2">{{ data_get($producer, 'contact_mobile', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_fax') }}:</span><span class="ms-2">{{ data_get($producer, 'contact_fax', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_email') }}:</span><span class="ms-2">{{ data_get($producer, 'contact_email', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.liaison_name') }}:</span><span class="ms-2">{{ data_get($producer, 'liaison_name', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.liaison_position') }}:</span><span class="ms-2">{{ data_get($producer, 'liaison_position', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.liaison_email') }}:</span><span class="ms-2">{{ data_get($producer, 'liaison_email', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-0"><span class="fw-600">{{ __('app.applications.liaison_mobile') }}:</span><span class="ms-2">{{ data_get($producer, 'liaison_mobile', __('app.dashboard.not_available')) }}</span></div>
                                    </div>
                                </div>

                                <div class="card request-pane-card">
                                    <div class="card-header">
                                        <div class="header-title">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.applications.director_information') }}</span>
                                            </h2>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.director_name') }}:</span><span class="ms-2">{{ data_get($director, 'director_name', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.director_nationality') }}:</span><span class="ms-2">{{ data_get($director, 'director_nationality', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-0"><span class="fw-600">{{ __('app.applications.director_profile_url') }}:</span>
                                            @if (filled(data_get($director, 'director_profile_url')))
                                                <a href="{{ data_get($director, 'director_profile_url') }}" class="ms-2" target="_blank" rel="noreferrer">{{ data_get($director, 'director_profile_url') }}</a>
                                            @else
                                                <span class="ms-2">{{ __('app.dashboard.not_available') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if (filled(data_get($international, 'international_producer_name')) || filled(data_get($international, 'international_producer_company')))
                                    <div class="card request-pane-card">
                                        <div class="card-header">
                                            <div class="header-title">
                                                <h2 class="episode-playlist-title wp-heading-inline">
                                                    <span class="position-relative">{{ __('app.applications.international_project_information') }}</span>
                                                </h2>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-1"><span class="fw-600">{{ __('app.applications.international_producer_name') }}:</span><span class="ms-2">{{ data_get($international, 'international_producer_name', __('app.dashboard.not_available')) }}</span></div>
                                            <div class="mb-0"><span class="fw-600">{{ __('app.applications.international_producer_company') }}:</span><span class="ms-2">{{ data_get($international, 'international_producer_company', __('app.dashboard.not_available')) }}</span></div>
                                        </div>
                                    </div>
                                @endif

                                <div class="card request-pane-card">
                                    <div class="card-header">
                                        <h2 class="episode-playlist-title wp-heading-inline">
                                            <span class="position-relative">{{ __('app.applications.schedule_title') }}</span>
                                        </h2>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.planned_start_date') }}:</span><span class="ms-2">{{ optional($application->planned_start_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                                        <div class="mb-0"><span class="fw-600">{{ __('app.applications.planned_end_date') }}:</span><span class="ms-2">{{ optional($application->planned_end_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                                    </div>
                                </div>

                                <div class="card request-pane-card">
                                    <div class="card-header">
                                        <h2 class="episode-playlist-title wp-heading-inline">
                                            <span class="position-relative">{{ __('app.applications.crew_title') }}</span>
                                        </h2>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-0">{{ __('app.applications.estimated_crew_count') }}: <span class="ms-2">{{ $application->estimated_crew_count ?: __('app.dashboard.not_available') }}</span></div>
                                    </div>
                                </div>

                                <div class="card request-pane-card">
                                    <div class="card-header">
                                        <h2 class="episode-playlist-title wp-heading-inline">
                                            <span class="position-relative">{{ __('app.applications.summary_title') }}</span>
                                        </h2>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0" style="line-height: 1.8;">{{ $application->project_summary }}</p>
                                    </div>
                                </div>

                                <div class="card request-pane-card">
                                    <div class="card-header">
                                        <h2 class="episode-playlist-title wp-heading-inline">
                                            <span class="position-relative">{{ __('app.applications.budget_title') }}</span>
                                        </h2>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-0"><span class="fw-600">{{ __('app.applications.estimated_budget') }}:</span><span class="ms-2">{{ $formattedBudget }}</span></div>
                                    </div>
                                </div>

                                <div class="card request-pane-card">
                                    <div class="card-header">
                                        <h2 class="episode-playlist-title wp-heading-inline">
                                            <span class="position-relative">{{ __('app.applications.annex_title') }}</span>
                                        </h2>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <span class="fw-600 d-block mb-2">{{ __('app.applications.required_approvals') }}</span>
                                            <div>{{ $requiredApprovals }}</div>
                                        </div>
                                        <div class="mb-0">
                                            <span class="fw-600 d-block mb-2">{{ __('app.applications.supporting_notes') }}</span>
                                            <div>{{ data_get($requirements, 'supporting_notes', __('app.applications.annex_empty_state')) }}</div>
                                        </div>
                                        <div class="border-top mt-4 pt-4">
                                            @include('applications.partials.annex-summary', ['application' => $application])
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="authority-procedures" class="tab-pane fade">
                                <div class="card request-pane-card">
                                    <div class="card-header">
                                        <div class="header-title">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.applications.status_timeline_title') }}</span>
                                            </h2>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="iq-timeline0 m-0 d-flex align-items-center justify-content-between position-relative authority-procedures-timeline">
                                            <ul class="list-inline p-0 m-0">
                                                @forelse ($timelineEvents as $event)
                                                    @php
                                                        $eventColor = $statusClass($event['status']);
                                                    @endphp
                                                    <li>
                                                        <div class="timeline-dots timeline-dot1 border-{{ $eventColor }} text-{{ $eventColor }}"></div>
                                                        <h6 class="float-left mb-1 fw-semibold">{{ $event['label'] }}</h6>
                                                        @if ($event['date'])
                                                            <small class="float-right mt-1 d-flex align-items-center gap-2">
                                                                <span><i class="ph-fill ph-calendar me-1"></i>{{ $event['date']->format('Y-m-d') }}</span>
                                                                <span><i class="ph-fill ph-clock me-1"></i>{{ $event['date']->format('H:i') }}</span>
                                                            </small>
                                                        @endif
                                                        <div class="d-inline-block w-100">
                                                            <p class="mb-0 text-{{ $eventColor }}">{{ $event['status_label'] }}</p>
                                                            @if (filled($event['note']))
                                                                <p class="mb-0 timeline-note">{{ $event['note'] }}</p>
                                                            @endif
                                                        </div>
                                                    </li>
                                                @empty
                                                    <li class="text-muted">{{ __('app.authority.applications.timeline_empty') }}</li>
                                                @endforelse
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="authority-approvals" class="tab-pane fade">
                                <div class="card request-pane-card">
                                    <div class="card-header">
                                        <div class="header-title">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.applications.approvals_title') }}</span>
                                            </h2>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped mb-0 documents-table approval-overview-table">
                                                <colgroup>
                                                    <col style="width: 23%;">
                                                    <col style="width: 27%;">
                                                    <col style="width: 14%;">
                                                    <col style="width: 16%;">
                                                    <col style="width: 20%;">
                                                </colgroup>
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('app.applications.authority') }}</th>
                                                        <th>{{ __('app.authority.applications.approval_type') }}</th>
                                                        <th>{{ __('app.documents.last_action') }}</th>
                                                        <th>{{ __('app.applications.status') }}</th>
                                                        <th>{{ __('app.applications.decision_note') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($authorityApprovals as $approval)
                                                        @php
                                                            $approvalColor = $statusClass($approval->status);
                                                            $approvalIcon = match ($approval->status) {
                                                                'approved' => 'ph-check',
                                                                'rejected' => 'ph-x-circle',
                                                                'in_review' => 'ph-clock',
                                                                default => 'ph-hourglass-medium',
                                                            };
                                                        @endphp
                                                        <tr>
                                                            <td>{{ $approval->entity?->displayName() ?? $approval->localizedAuthority() }}</td>
                                                            <td>{{ $approval->localizedAuthority() }}</td>
                                                            <td>{{ ($approval->decided_at ?? $approval->updated_at)?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                                            <td class="approval-status-cell">
                                                                <div class="text-{{ $approvalColor }}">
                                                                    <i class="ph-fill {{ $approvalIcon }} fa-xl me-2 lh-lg"></i>
                                                                    {{ $approval->localizedStatus() }}
                                                                </div>
                                                            </td>
                                                            <td>{{ $approval->note ?: __('app.dashboard.not_available') }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="5" class="text-center text-muted py-4">{{ __('app.applications.no_required_approvals') }}</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="authority-documents" class="tab-pane fade">
                                <div class="card request-pane-card">
                                    <div class="card-body">
                                        <div class="form-card text-start pb-4">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.documents.title') }}</span>
                                            </h2>

                                            <div class="row">
                                                <div class="table-responsive mt-4">
                                                    <table class="table table-striped mb-0 documents-table">
                                                        <thead>
                                                            <tr>
                                                                <th>{{ __('app.documents.title_label') }}</th>
                                                                <th>{{ __('app.documents.file') }}</th>
                                                                <th>{{ __('app.documents.last_action') }}</th>
                                                                <th>{{ __('app.documents.status') }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @forelse ($documents as $document)
                                                                @php
                                                                    $documentClass = match ($document->status) {
                                                                        'approved' => 'text-success',
                                                                        'needs_revision' => 'text-warning',
                                                                        'rejected' => 'text-danger',
                                                                        default => 'text-secondary',
                                                                    };
                                                                @endphp
                                                                <tr>
                                                                    <td>
                                                                        <div class="d-flex align-items-center">
                                                                            <img class="rounded img-fluid avatar-40 me-3 bg-primary-subtle" src="{{ asset('images/clapboard.png') }}" alt="document" loading="lazy">
                                                                            <h6>{{ $document->title }}</h6>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <a class="btn btn-danger" href="{{ route('authority.applications.documents.download', [$application, $document]) }}">
                                                                            <i class="ph ph-eye fs-6 me-2"></i>{{ __('app.documents.download_action') }}
                                                                        </a>
                                                                    </td>
                                                                    <td>
                                                                        <span class="text-danger">{{ __('app.documents.last_action') }} :</span>
                                                                        <p>{{ ($document->reviewed_at ?? $document->created_at)?->format('Y-m-d') }}</p>
                                                                    </td>
                                                                    <td>
                                                                        <div class="{{ $documentClass }}">
                                                                            @if ($document->status === 'approved')
                                                                                <i class="ph-fill ph-check fa-xl me-2 lh-lg"></i>
                                                                            @elseif ($document->status === 'needs_revision')
                                                                                <i class="ph-fill ph-note-pencil fa-xl me-2 lh-lg"></i>
                                                                            @else
                                                                                <i class="ph-fill ph-clock fa-xl me-2 lh-lg"></i>
                                                                            @endif
                                                                            {{ $document->localizedStatus() }}
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td colspan="4" class="text-center text-muted py-4">{{ __('app.documents.empty_state') }}</td>
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

                            <div id="authority-official-letters" class="tab-pane fade">
                                @include('authority.applications.partials.official-letters', ['officialLetters' => $officialLetters])
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card request-pane-card">
                            <div class="card-header">
                                <div class="header-title">
                                    <h2 class="episode-playlist-title wp-heading-inline">
                                        <span class="position-relative">{{ __('app.authority.applications.approval_tab') }}</span>
                                    </h2>
                                </div>
                            </div>
                            <div class="card-body">
                                @if (($approvalSignal['active'] ?? false) && $approvalSignal['label'])
                                    <div class="approval-signal signal-{{ $approvalSignal['class'] }}">
                                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                            <div>
                                                <span class="badge bg-{{ $approvalSignal['class'] }}">{{ $approvalSignal['label'] }}</span>
                                                @if ($approvalSignal['summary'])
                                                    <div class="small mt-2">{{ $approvalSignal['summary'] }}</div>
                                                @endif
                                            </div>
                                            @if ($approvalSignal['at'])
                                                <div class="small text-muted">{{ __('app.authority.applications.signal_received_at') }}: {{ $approvalSignal['at']->format('Y-m-d H:i') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                                <div class="approval-sla-banner {{ ($approvalSlaSignal['is_overdue'] ?? false) ? 'overdue' : '' }}">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                        <div>
                                            @if ($approvalSlaSignal['label'] ?? null)
                                                <span class="badge bg-{{ ($approvalSlaSignal['is_overdue'] ?? false) ? 'danger' : 'secondary' }}">{{ $approvalSlaSignal['label'] }}</span>
                                            @else
                                                <span class="badge bg-light text-dark">{{ __('app.admin.authority_escalations.unconfigured_badge') }}</span>
                                            @endif
                                            @if ($approvalSlaSignal['is_escalated'] ?? false)
                                                <span class="badge bg-dark">{{ __('app.admin.authority_escalations.escalated_badge') }}</span>
                                            @endif
                                            <div class="small mt-2">{{ __('app.authority.applications.sla_banner_summary') }}</div>
                                        </div>
                                        @if ($approvalSlaSignal['due_at'] ?? null)
                                            <div>
                                                <div class="small text-muted">{{ __('app.admin.authority_escalations.due_at_label', ['date' => $approvalSlaSignal['due_at']->format('Y-m-d h:i A')]) }}</div>
                                                <div
                                                    class="small text-muted mt-1"
                                                    data-sla-countdown
                                                    data-due-at="{{ $approvalSlaSignal['due_at']->toIso8601String() }}"
                                                    data-remaining-template="{{ __('app.admin.authority_escalations.countdown_remaining') }}"
                                                    data-overdue-template="{{ __('app.admin.authority_escalations.countdown_overdue') }}"
                                                ></div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="authority-decision-current mb-3">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                        <div>
                                            <small class="text-muted d-block">{{ __('app.authority.applications.current_decision') }}</small>
                                            <span class="badge bg-{{ $decisionBadgeClass }}">{{ $currentApproval->localizedStatus() }}</span>
                                        </div>
                                        @if ($currentApproval->decided_at)
                                            <div class="small text-muted">{{ $currentApproval->decided_at->format('Y-m-d H:i') }}</div>
                                        @endif
                                    </div>
                                    @if ($currentApproval->note)
                                        <div class="small text-muted mt-3">{{ __('app.authority.applications.current_note_label') }}</div>
                                        <div class="mt-1">{{ $currentApproval->note }}</div>
                                    @endif
                                </div>
                                @if (! $canResolveAuthorityDecision && $approvalIsResolved)
                                    <div class="alert alert-secondary mb-0">{{ __('app.authority.applications.reviewer_decision_locked') }}</div>
                                @else
                                    <form method="POST" action="{{ route('authority.applications.approval.update', $application) }}" class="authority-decision-panel">
                                        @csrf
                                        <div class="mb-3">
                                            <div class="fw-semibold">{{ __('app.authority.applications.decision_actions_title') }}</div>
                                            <div class="small text-muted">{{ __('app.authority.applications.decision_actions_intro') }}</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="note">{{ __('app.authority.applications.approval_note') }}</label>
                                            <textarea id="note" name="note" rows="5" class="form-control" placeholder="{{ __('app.authority.applications.note_placeholder') }}">{{ old('note', $currentApproval->note) }}</textarea>
                                        </div>
                                        <div class="authority-decision-actions">
                                            <button class="btn btn-warning authority-decision-action d-flex gap-2" type="submit" name="status" value="in_review">
                                                <i class="ph ph-list-checks"></i>
                                                <span>
                                                    <span class="fw-semibold d-block">{{ __('app.authority.applications.start_review_action') }}</span>
                                                    <span class="small d-block">{{ __('app.authority.applications.start_review_description') }}</span>
                                                </span>
                                            </button>
                                            @if ($canResolveAuthorityDecision)
                                                <button class="btn btn-success authority-decision-action d-flex gap-2" type="submit" name="status" value="approved">
                                                    <i class="ph ph-seal-check"></i>
                                                    <span>
                                                        <span class="fw-semibold d-block">{{ __('app.authority.applications.approve_action') }}</span>
                                                        <span class="small d-block">{{ __('app.authority.applications.approve_description') }}</span>
                                                    </span>
                                                </button>
                                                <button class="btn btn-outline-danger authority-decision-action d-flex gap-2" type="submit" name="status" value="rejected">
                                                    <i class="ph ph-x-circle"></i>
                                                    <span>
                                                        <span class="fw-semibold d-block">{{ __('app.authority.applications.reject_action') }}</span>
                                                        <span class="small d-block">{{ __('app.authority.applications.reject_description') }}</span>
                                                    </span>
                                                </button>
                                            @else
                                                <div class="alert alert-info mb-0">{{ __('app.authority.applications.reviewer_resolution_hint') }}</div>
                                            @endif
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div class="card request-pane-card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                    <div class="header-title">
                                        <h2 class="episode-playlist-title wp-heading-inline">
                                            <span class="position-relative">{{ __('app.correspondence.title') }}</span>
                                        </h2>
                                    </div>
                                    <button class="btn btn-danger" type="button" data-bs-toggle="offcanvas" data-bs-target="#authorityCorrespondenceCreate" aria-controls="authorityCorrespondenceCreate">
                                        <i class="fa-solid fa-plus me-2"></i>{{ __('app.correspondence.new_message_action') }}
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="list-group authority-correspondence-list">
                                    @forelse ($correspondences as $message)
                                        <button class="list-group-item list-group-item-action" type="button" data-bs-toggle="offcanvas" data-bs-target="#authorityCorrespondenceView{{ $message->getKey() }}" aria-controls="authorityCorrespondenceView{{ $message->getKey() }}" aria-label="{{ __('app.correspondence.view_message_action') }}: {{ $message->subject ?: __('app.correspondence.message_fallback_subject') }}">
                                            <div class="d-flex justify-content-between align-items-start gap-3">
                                                <div class="authority-correspondence-summary text-start">
                                                    <div class="fw-semibold text-break">{{ $message->subject ?: __('app.correspondence.message_fallback_subject') }}</div>
                                                    <div class="small text-muted authority-correspondence-meta">
                                                        <span>{{ $message->sender_name }}</span>
                                                        <span>{{ $message->localizedSenderType() }}</span>
                                                        <span>{{ $message->created_at?->format('Y-m-d H:i') }}</span>
                                                    </div>
                                                </div>
                                                <span class="btn btn-sm btn-icon btn-info-subtle rounded" title="{{ __('app.correspondence.view_message_action') }}">
                                                    <i class="ph ph-eye fs-6"></i>
                                                </span>
                                            </div>
                                        </button>
                                    @empty
                                        <div class="text-muted border rounded p-3">{{ __('app.correspondence.empty_state') }}</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="authorityCorrespondenceCreate">
                            <div class="offcanvas-header">
                                <h2 class="episode-playlist-title wp-heading-inline">
                                    <span class="position-relative">{{ __('app.correspondence.new_message_title') }}</span>
                                </h2>
                                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.official_letters.close_action') }}"></button>
                            </div>
                            <form method="POST" action="{{ route('authority.applications.correspondence.store', $application) }}" enctype="multipart/form-data">
                                <div class="offcanvas-body">
                                    @csrf
                                    <div class="section-form">
                                        <div class="mb-3">
                                            <label class="form-label" for="authority_correspondence_subject">{{ __('app.correspondence.subject') }}</label>
                                            <input id="authority_correspondence_subject" name="subject" type="text" class="form-control" value="{{ old('subject') }}" placeholder="{{ __('app.correspondence.subject_placeholder') }}">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="authority_correspondence_message">{{ __('app.correspondence.message') }}</label>
                                            <textarea id="authority_correspondence_message" name="message" rows="6" class="form-control" required placeholder="{{ __('app.correspondence.message_placeholder') }}">{{ old('message') }}</textarea>
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label" for="authority_correspondence_attachment">{{ __('app.correspondence.attachment') }}</label>
                                            <input id="authority_correspondence_attachment" name="attachment" type="file" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="offcanvas-footer border-top">
                                    <div class="d-flex gap-3 p-3 justify-content-end">
                                        <button class="btn btn-danger d-flex align-items-center gap-2" type="submit">
                                            <i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.correspondence.send_action') }}
                                        </button>
                                        <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas">
                                            <i class="ph ph-caret-double-left"></i>{{ __('app.official_letters.close_action') }}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        @foreach ($correspondences as $message)
                            <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="authorityCorrespondenceView{{ $message->getKey() }}">
                                <div class="offcanvas-header">
                                    <h2 class="episode-playlist-title wp-heading-inline">
                                        <span class="position-relative">{{ __('app.correspondence.message_content_title') }}</span>
                                    </h2>
                                    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('app.official_letters.close_action') }}"></button>
                                </div>
                                <div class="offcanvas-body">
                                    <div class="section-form">
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('app.correspondence.subject') }}</label>
                                            <div class="form-control bg-light authority-message-readonly">{{ $message->subject ?: __('app.correspondence.message_fallback_subject') }}</div>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">{{ __('app.correspondence.sender') }}</label>
                                                <div class="form-control bg-light authority-message-readonly">{{ $message->sender_name }}</div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">{{ __('app.correspondence.sender_type') }}</label>
                                                <div class="form-control bg-light authority-message-readonly">{{ $message->localizedSenderType() }}</div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">{{ __('app.correspondence.sent_at') }}</label>
                                                <div class="form-control bg-light authority-message-readonly">{{ $message->created_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div>
                                            </div>
                                        </div>
                                        <div class="mb-3 mt-3">
                                            <label class="form-label">{{ __('app.correspondence.message') }}</label>
                                            <div class="form-control bg-light text-break authority-message-readonly authority-message-body">{{ $message->message }}</div>
                                        </div>
                                        @if ($message->attachment_path)
                                            <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('authority.applications.correspondence.download', [$application, $message]) }}">
                                                <i class="ph ph-file-arrow-down"></i>{{ __('app.correspondence.download_attachment') }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                                <div class="offcanvas-footer border-top">
                                    <div class="d-flex gap-3 p-3 justify-content-end">
                                        <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas">
                                            <i class="ph ph-caret-double-left"></i>{{ __('app.official_letters.close_action') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.sla-countdown-script')
@endpush
