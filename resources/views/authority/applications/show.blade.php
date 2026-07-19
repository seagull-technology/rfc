@php
    $title = $application->project_name;
    $applicationEntityLogoUrl = \App\Support\EntityLogo::url($application->entity, 'images/OIP.jpeg');
    $metadata = $application->metadata ?? [];
    $producer = data_get($metadata, 'producer', []);
    $director = data_get($metadata, 'director', []);
    $international = data_get($metadata, 'international', []);
    $requirements = data_get($metadata, 'requirements', []);
    $formattedBudget = $application->estimated_budget ? number_format((float) $application->estimated_budget, 2) : __('app.dashboard.not_available');
    $requiredApprovals = collect(data_get($requirements, 'required_approvals', []))
        ->map(fn ($approval) => __('app.applications.required_approval_options.'.$approval))
        ->join('، ') ?: __('app.applications.no_required_approvals');
    $asDate = static function ($value): ?\Carbon\CarbonInterface {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value;
        }

        if (blank($value)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    };
    $rfcDecision = (array) data_get($metadata, 'rfc_decision', []);
    $rfcDecisionStatus = data_get($rfcDecision, 'status');
    $rfcDecisionNote = data_get($rfcDecision, 'note') ?: $application->review_note;
    $rfcOfficialBooksPreparedAt = $asDate(
        data_get($rfcDecision, 'official_books_prepared_at')
            ?: data_get($rfcDecision, 'facilitation_issued_at')
    );
    $rfcDate = $rfcOfficialBooksPreparedAt
        ?? $asDate(data_get($rfcDecision, 'decided_at'))
        ?? $asDate($application->reviewed_at)
        ?? $asDate($application->submitted_at)
        ?? $asDate($application->created_at);
    $rfcTimelineStatus = match (true) {
        $rfcDecisionStatus === 'rejected', $application->status === 'rejected' => 'rejected',
        $rfcDecisionStatus === 'returned', $application->status === 'needs_clarification' => 'needs_clarification',
        $rfcDecisionStatus === 'accepted' || $rfcOfficialBooksPreparedAt !== null => 'approved',
        in_array($application->status, ['submitted', 'under_review'], true) => 'under_review',
        default => $application->status,
    };
    $rfcTimelineStatusLabel = match (true) {
        $rfcDecisionStatus === 'accepted' || $rfcOfficialBooksPreparedAt !== null => __('app.rfc_decision.statuses.accepted'),
        $rfcDecisionStatus === 'returned' => __('app.rfc_decision.statuses.returned'),
        $rfcDecisionStatus === 'rejected' => __('app.rfc_decision.statuses.rejected'),
        default => $application->localizedStatus(),
    };
    $rfcTimelineNote = match (true) {
        $rfcOfficialBooksPreparedAt !== null => __('app.rfc_decision.history.official_books_prepared'),
        $rfcDecisionStatus === 'accepted' => __('app.rfc_decision.history.accepted'),
        $rfcDecisionStatus === 'returned' || $rfcDecisionStatus === 'rejected' => $rfcDecisionNote,
        default => $application->localizedStage(),
    };

    $decisionBadgeClass = match ($currentApproval->status) {
        'approved' => 'success',
        'rejected' => 'danger',
        'changes_requested' => 'warning text-dark',
        'in_review' => 'warning',
        default => 'secondary',
    };
    $canResolveAuthorityDecision = auth()->user()?->can('applications.approve') ?? false;
    $approvalIsResolved = in_array($currentApproval->status, ['approved', 'rejected'], true);
    $approvalIsWaitingApplicant = $currentApproval->status === 'changes_requested';
    $statusClass = static fn (?string $status): string => match ($status) {
        'draft' => 'secondary',
        'submitted', 'pending_review', 'pending' => 'warning',
        'under_review', 'in_review' => 'info',
        'needs_clarification', 'changes_requested' => 'warning',
        'approved', 'issued' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $timelineEvents = collect([
        [
            'label' => __('app.contact_center.stations.rfc'),
            'date' => $rfcDate,
            'status' => $rfcTimelineStatus,
            'status_label' => $rfcTimelineStatusLabel,
            'note' => $rfcTimelineNote,
            'meta' => null,
        ],
    ]);

    $authorityApprovals
        ->groupBy(fn ($approval): string => $approval->entity_id ? 'entity-'.$approval->entity_id : 'code-'.$approval->authority_code)
        ->map(fn ($group) => $group
            ->sortByDesc(fn ($approval): int => ($asDate($approval->decided_at ?? $approval->updated_at ?? $approval->created_at)?->timestamp ?? 0))
            ->first())
        ->sortBy(fn ($approval): int => $approval->id)
        ->each(function ($approval) use ($timelineEvents, $asDate) {
            $timelineEvents->push([
                'label' => $approval->localizedAuthority(),
                'date' => $asDate($approval->decided_at ?? $approval->assigned_at ?? $approval->updated_at ?? $approval->created_at),
                'status' => $approval->status,
                'status_label' => $approval->localizedStatus(),
                'note' => $approval->note,
                'meta' => null,
            ]);
        });

    if ($application->finalDecisionIssued()) {
        $timelineEvents->push([
            'label' => __('app.final_decision.title'),
            'date' => $asDate($application->final_decision_issued_at),
            'status' => $application->final_decision_status === 'rejected' ? 'rejected' : 'approved',
            'status_label' => __('app.final_decision.issued_summary'),
            'note' => filled($application->final_permit_number)
                ? __('app.final_decision.history.issued', [
                    'decision' => __('app.statuses.'.($application->final_decision_status ?: 'approved')),
                    'permit_number' => $application->final_permit_number,
                ])
                : $application->final_decision_note,
            'meta' => null,
        ]);
    }

    $timelineEvents = $timelineEvents
        ->sortBy(fn (array $event): int => $event['date']?->timestamp ?? PHP_INT_MAX)
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

        .authority-request-show-layout .authority-request-table-scroll {
            overflow-x: auto;
        }

        .authority-request-show-layout .authority-detail-table {
            table-layout: fixed;
            min-width: 900px;
            width: 100%;
        }

        .authority-request-show-layout .authority-detail-table.documents-table {
            width: 100%;
        }

        .authority-request-show-layout .authority-detail-table th,
        .authority-request-show-layout .authority-detail-table td {
            vertical-align: top;
            white-space: normal;
            word-break: break-word;
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

        .authority-request-show-layout .authority-decision-summary {
            display: grid;
            gap: .5rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-bottom: 1rem;
        }

        .authority-request-show-layout .authority-decision-summary-item {
            background: #f8f9fa;
            border-inline-start: 3px solid rgba(111, 29, 23, .45);
            min-width: 0;
            padding: .75rem;
        }

        .authority-request-show-layout .authority-decision-summary-item small {
            color: #667085;
            display: block;
            margin-bottom: .25rem;
        }

        .authority-request-show-layout .authority-decision-choices {
            display: grid;
            gap: .5rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .authority-request-show-layout .authority-decision-choice {
            align-items: center;
            display: flex;
            gap: .5rem;
            justify-content: flex-start;
            min-height: 3.25rem;
            padding: .65rem;
            text-align: start;
            white-space: normal;
        }

        .authority-request-show-layout .authority-decision-choice i {
            flex: 0 0 auto;
            font-size: 1.2rem;
        }

        .authority-request-show-layout .authority-decision-dependent[hidden] {
            display: none !important;
        }

        .authority-request-show-layout .authority-correction-items {
            display: grid;
            gap: .75rem;
        }

        .authority-request-show-layout .authority-correction-heading {
            align-items: flex-start;
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            justify-content: space-between;
        }

        .authority-request-show-layout .authority-correction-item {
            background: #f8f9fa;
            border-inline-start: 3px solid #c38a14;
            padding: .75rem;
        }

        .authority-request-show-layout .authority-correction-item-grid {
            display: grid;
            gap: .65rem;
            grid-template-columns: 1fr;
        }

        .authority-request-show-layout .authority-correction-remove {
            justify-self: end;
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

        .authority-request-show-layout .correspondence-recipient-options {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .5rem;
        }

        .authority-request-show-layout .correspondence-recipient-options .btn {
            align-items: center;
            display: flex;
            justify-content: center;
            min-height: 3.5rem;
            white-space: normal;
        }

        @media (max-width: 991.98px) {
            .authority-request-show-layout .authority-hero-card {
                margin: 0 .75rem 1rem;
            }

            .authority-request-show-layout .documents-table {
                width: 100%;
            }

            .authority-request-show-layout .approval-overview-table {
                min-width: 760px;
            }

            .authority-request-show-layout .correspondence-recipient-options {
                grid-template-columns: 1fr;
            }

            .authority-request-show-layout .authority-decision-summary,
            .authority-request-show-layout .authority-decision-choices,
            .authority-request-show-layout .authority-correction-item-grid {
                grid-template-columns: 1fr;
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
                        <img src="{{ $applicationEntityLogoUrl }}" alt="User-Profile" class="theme-color-default-img img-fluid rounded-pill avatar-100" loading="lazy">
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
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.project_nationality') }}:</span><span class="ms-2">{{ $application->projectNationalityLabels() }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.work_category') }}:</span><span class="ms-2">{{ \App\Models\WorkCategory::labelFor($application->work_category) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.production_company_name') }}:</span><span class="ms-2">{{ data_get($producer, 'production_company_name', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_address') }}:</span><span class="ms-2">{{ data_get($producer, 'contact_address', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_phone') }}:</span><span class="ms-2">{{ data_get($producer, 'contact_phone', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_mobile') }}:</span><span class="ms-2">{{ data_get($producer, 'contact_mobile', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_fax') }}:</span><span class="ms-2">{{ data_get($producer, 'contact_fax', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_email') }}:</span><span class="ms-2">{{ data_get($producer, 'contact_email', __('app.dashboard.not_available')) }}</span></div>
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
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.director_nationality') }}:</span><span class="ms-2">{{ \App\Models\Nationality::labelFor(data_get($director, 'director_nationality')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.director_email') }}:</span><span class="ms-2">{{ data_get($director, 'director_email', __('app.dashboard.not_available')) }}</span></div>
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
                                                            @if (filled($event['meta']))
                                                                <p class="mb-0 text-muted timeline-note">{{ $event['meta'] }}</p>
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
                                        <div class="table-responsive rounded py-4 authority-request-table-scroll">
                                            <table class="table table-striped mb-0 documents-table authority-detail-table approval-overview-table">
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
                                            <div class="authority-attached-annex-summary" data-authority-annex-sections="{{ collect($authorityAnnexSections ?? [])->join(',') }}">
                                                @include('applications.partials.documents-applicant', [
                                                    'application' => $application,
                                                    'documents' => $documents,
                                                    'onlySections' => $authorityAnnexSections ?? [],
                                                    'hideEmptySections' => true,
                                                ])
                                            </div>

                                            @if ($documents->isNotEmpty())
                                                <div class="row mt-4">
                                                    <div class="table-responsive rounded py-4 authority-request-table-scroll">
                                                        <table class="table table-striped mb-0 documents-table authority-detail-table authority-documents-table">
                                                            <colgroup>
                                                                <col style="width: 34%;">
                                                                <col style="width: 18%;">
                                                                <col style="width: 18%;">
                                                                <col style="width: 30%;">
                                                            </colgroup>
                                                            <thead>
                                                                <tr>
                                                                    <th>{{ __('app.documents.title_label') }}</th>
                                                                    <th>{{ __('app.documents.file') }}</th>
                                                                    <th>{{ __('app.documents.last_action') }}</th>
                                                                    <th>{{ __('app.documents.status') }}</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($documents as $document)
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
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
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
                                <div class="authority-decision-summary">
                                    <div class="authority-decision-summary-item">
                                        <small>{{ __('app.authority.applications.current_decision') }}</small>
                                        <span class="badge bg-{{ $decisionBadgeClass }}">{{ $currentApproval->localizedStatus() }}</span>
                                    </div>
                                    <div class="authority-decision-summary-item">
                                        <small>{{ __('app.authority.applications.response_window') }}</small>
                                        @if ($approvalIsWaitingApplicant)
                                            <strong>{{ __('app.authority_change_requests.waiting_applicant_short') }}</strong>
                                        @elseif ($approvalSlaSignal['label'] ?? null)
                                            <span class="badge bg-{{ ($approvalSlaSignal['is_overdue'] ?? false) ? 'danger' : (($approvalSlaSignal['is_due_soon'] ?? false) ? 'warning text-dark' : 'secondary') }}">{{ $approvalSlaSignal['label'] }}</span>
                                        @else
                                            <span>{{ __('app.admin.authority_escalations.unconfigured_badge') }}</span>
                                        @endif
                                        @if (! $approvalIsWaitingApplicant && ($approvalSlaSignal['is_escalated'] ?? false))
                                            <span class="badge bg-dark ms-1">{{ __('app.admin.authority_escalations.escalated_badge') }}</span>
                                        @endif
                                        @if (! $approvalIsWaitingApplicant && ($approvalSlaSignal['due_at'] ?? null))
                                            <div class="small text-muted mt-1">
                                                {{ __('app.admin.authority_escalations.due_at_label', ['date' => $approvalSlaSignal['due_at']->format('Y-m-d h:i A')]) }}
                                            </div>
                                            <div
                                                class="small mt-1"
                                                data-sla-countdown
                                                data-due-at="{{ $approvalSlaSignal['due_at']->toIso8601String() }}"
                                                data-remaining-template="{{ __('app.admin.authority_escalations.countdown_remaining') }}"
                                                data-overdue-template="{{ __('app.admin.authority_escalations.countdown_overdue') }}"
                                            ></div>
                                        @endif
                                    </div>
                                    <div class="authority-decision-summary-item">
                                        <small>{{ __('app.authority.applications.last_updated') }}</small>
                                        <strong>{{ ($currentApproval->decided_at ?? $currentApproval->updated_at)?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</strong>
                                    </div>
                                </div>

                                @if ($currentApproval->note || $currentApproval->response_attachment_path)
                                    <div class="authority-decision-current mb-3">
                                    @if ($currentApproval->note)
                                        <div class="small text-muted">{{ __('app.authority.applications.current_note_label') }}</div>
                                        <div class="mt-1">{{ $currentApproval->note }}</div>
                                    @endif
                                    @if ($currentApproval->response_attachment_path)
                                        <div class="small text-muted {{ $currentApproval->note ? 'mt-3' : '' }}">{{ __('app.approvals.response_book') }}</div>
                                        <div class="mt-1 d-flex flex-wrap align-items-center gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('authority.applications.approvals.attachment.download', [$application, $currentApproval]) }}">
                                                <i class="ph ph-download-simple me-1"></i>{{ __('app.approvals.response_book_download') }}
                                            </a>
                                            <span class="text-muted">{{ $currentApproval->response_attachment_name ?: __('app.approvals.response_book') }}</span>
                                        </div>
                                        @if ($currentApproval->response_attachment_uploaded_at)
                                            <div class="small text-muted mt-1">{{ __('app.approvals.response_book_uploaded_at', ['date' => $currentApproval->response_attachment_uploaded_at->format('Y-m-d H:i')]) }}</div>
                                        @endif
                                    @endif
                                </div>
                                @endif

                                @if (! $approvalIsWaitingApplicant && $authorityChangeRequests->where('status', \App\Models\ApplicationAuthorityChangeRequest::STATUS_RESUBMITTED)->isNotEmpty())
                                    @include('applications.partials.authority-change-requests', [
                                        'changeRequestViewer' => 'authority',
                                        'changeRequestItems' => $authorityChangeRequests,
                                    ])
                                @endif

                                @if ($approvalIsWaitingApplicant)
                                    <div class="alert alert-warning">
                                        <div class="fw-semibold">{{ __('app.authority_change_requests.waiting_applicant_title') }}</div>
                                        <div class="small mt-1">{{ __('app.authority_change_requests.waiting_applicant_body') }}</div>
                                    </div>
                                    <div class="authority-correction-items">
                                        @foreach ($authorityChangeRequests->where('status', \App\Models\ApplicationAuthorityChangeRequest::STATUS_REQUESTED) as $item)
                                            <div class="authority-correction-item">
                                                <div class="d-flex justify-content-between align-items-start gap-2">
                                                    <strong>{{ $item->section_label }}</strong>
                                                    <span class="badge bg-warning text-dark">{{ __('app.authority_change_requests.statuses.requested') }}</span>
                                                </div>
                                                <div class="small mt-2" style="white-space: pre-line;">{{ $item->details }}</div>
                                                @if ($item->attachment_path)
                                                    <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('authority.applications.change-requests.attachment.download', [$application, $item]) }}">
                                                        <i class="ph ph-paperclip me-1"></i>{{ __('app.authority_change_requests.download_attachment') }}
                                                    </a>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif ($approvalIsResolved)
                                    <div class="alert alert-secondary mb-0">{{ __('app.authority.applications.reviewer_decision_locked') }}</div>
                                @else
                                    @if ($currentApproval->status === 'pending')
                                        <form method="POST" action="{{ route('authority.applications.approval.update', $application) }}" class="mb-3">
                                            @csrf
                                            <input type="hidden" name="status" value="in_review">
                                            <button class="btn btn-outline-primary w-100" type="submit">
                                                <i class="ph ph-list-checks me-2"></i>{{ __('app.authority.applications.start_review_action') }}
                                            </button>
                                        </form>
                                    @endif

                                    @if ($canResolveAuthorityDecision)
                                    <form method="POST" action="{{ route('authority.applications.approval.update', $application) }}" class="authority-decision-panel" enctype="multipart/form-data" data-authority-decision-form>
                                        @csrf
                                        <div class="mb-3">
                                            <div class="fw-semibold">{{ __('app.authority.applications.choose_decision') }}</div>
                                            <div class="small text-muted">{{ __('app.authority.applications.choose_decision_intro') }}</div>
                                        </div>
                                        <div class="authority-decision-choices mb-3">
                                            <input class="btn-check" id="authority_decision_approved" name="status" type="radio" value="approved" @checked(old('status') === 'approved') required>
                                            <label class="btn btn-outline-success authority-decision-choice" for="authority_decision_approved">
                                                <i class="ph ph-seal-check"></i><span>{{ __('app.authority.applications.approve_action') }}</span>
                                            </label>

                                            <input class="btn-check" id="authority_decision_changes_requested" name="status" type="radio" value="changes_requested" @checked(old('status') === 'changes_requested') required>
                                            <label class="btn btn-outline-warning authority-decision-choice" for="authority_decision_changes_requested">
                                                <i class="ph ph-pencil-simple-line"></i><span>{{ __('app.authority.applications.request_changes_action') }}</span>
                                            </label>

                                            <input class="btn-check" id="authority_decision_rejected" name="status" type="radio" value="rejected" @checked(old('status') === 'rejected') required>
                                            <label class="btn btn-outline-danger authority-decision-choice" for="authority_decision_rejected">
                                                <i class="ph ph-x-circle"></i><span>{{ __('app.authority.applications.reject_action') }}</span>
                                            </label>
                                        </div>

                                        <div class="authority-decision-dependent mb-3" data-decision-panel="note" hidden>
                                            <label class="form-label" for="authority_decision_note">{{ __('app.authority.applications.approval_note') }} <span class="text-danger" data-note-required hidden>*</span></label>
                                            <textarea id="authority_decision_note" name="note" rows="4" class="form-control" placeholder="{{ __('app.authority.applications.note_placeholder') }}">{{ old('note') }}</textarea>
                                        </div>

                                        <div class="authority-decision-dependent mb-3" data-decision-panel="approved" hidden>
                                            <label class="form-label" for="response_attachment">{{ __('app.approvals.response_book') }} <span class="text-danger">*</span></label>
                                            <input id="response_attachment" name="response_attachment" type="file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                            <div class="form-text">{{ __('app.approvals.response_book_hint') }}</div>
                                        </div>

                                        <div class="authority-decision-dependent mb-3" data-decision-panel="changes_requested" hidden>
                                            <div class="authority-correction-heading mb-2">
                                                <div>
                                                    <div class="fw-semibold">{{ __('app.authority_change_requests.items_title') }}</div>
                                                    <div class="small text-muted">{{ __('app.authority_change_requests.items_intro') }}</div>
                                                </div>
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-add-correction>
                                                    <i class="ph ph-plus me-1"></i>{{ __('app.authority_change_requests.add_item') }}
                                                </button>
                                            </div>
                                            <div class="authority-correction-items" data-correction-items>
                                                @foreach (old('change_requests', [['section_key' => '', 'details' => '']]) as $index => $changeRequest)
                                                    <div class="authority-correction-item" data-correction-item>
                                                        <div class="authority-correction-item-grid">
                                                            <div>
                                                                <label class="form-label">{{ __('app.authority_change_requests.section') }}</label>
                                                                <select class="form-select" name="change_requests[{{ $index }}][section_key]" data-field="section_key" data-correction-required disabled>
                                                                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                                                    @foreach ($correctionSectionOptions as $sectionKey => $sectionLabel)
                                                                        <option value="{{ $sectionKey }}" @selected(data_get($changeRequest, 'section_key') === $sectionKey)>{{ $sectionLabel }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="form-label">{{ __('app.authority_change_requests.details') }}</label>
                                                                <textarea class="form-control" name="change_requests[{{ $index }}][details]" rows="2" data-field="details" data-correction-required disabled>{{ data_get($changeRequest, 'details') }}</textarea>
                                                            </div>
                                                            <button class="btn btn-sm btn-outline-danger authority-correction-remove" type="button" data-remove-correction title="{{ __('app.authority_change_requests.remove_item') }}">
                                                                <i class="ph ph-trash"></i>
                                                            </button>
                                                        </div>
                                                        <div class="mt-2">
                                                            <label class="form-label">{{ __('app.authority_change_requests.attachment') }}</label>
                                                            <input class="form-control" name="change_requests[{{ $index }}][attachment]" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png" data-field="attachment" data-correction-input disabled>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <button class="btn btn-danger w-100" type="submit">
                                            <i class="ph ph-floppy-disk-back me-2"></i>{{ __('app.authority.applications.save_decision') }}
                                        </button>

                                        <template data-correction-template>
                                            <div class="authority-correction-item" data-correction-item>
                                                <div class="authority-correction-item-grid">
                                                    <div>
                                                        <label class="form-label">{{ __('app.authority_change_requests.section') }}</label>
                                                        <select class="form-select" data-field="section_key" data-correction-required disabled>
                                                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                                            @foreach ($correctionSectionOptions as $sectionKey => $sectionLabel)
                                                                <option value="{{ $sectionKey }}">{{ $sectionLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="form-label">{{ __('app.authority_change_requests.details') }}</label>
                                                        <textarea class="form-control" rows="2" data-field="details" data-correction-required disabled></textarea>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-danger authority-correction-remove" type="button" data-remove-correction title="{{ __('app.authority_change_requests.remove_item') }}">
                                                        <i class="ph ph-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="mt-2">
                                                    <label class="form-label">{{ __('app.authority_change_requests.attachment') }}</label>
                                                    <input class="form-control" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png" data-field="attachment" data-correction-input disabled>
                                                </div>
                                            </div>
                                        </template>
                                    </form>
                                    @else
                                        <div class="alert alert-info mb-0">{{ __('app.authority.applications.reviewer_resolution_hint') }}</div>
                                    @endif
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
                                                        <span>{{ __('app.correspondence.recipient') }}: {{ $message->localizedRecipientType() }}</span>
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
                                        <fieldset class="mb-4" data-correspondence-recipient-selector>
                                            <legend class="form-label mb-2">{{ __('app.correspondence.recipient') }} <span class="text-danger">*</span></legend>
                                            <div class="correspondence-recipient-options" role="group" aria-label="{{ __('app.correspondence.recipient') }}">
                                                <input class="btn-check" id="authority_correspondence_recipient_rfc" name="recipient_type" type="radio" value="rfc" required @checked(old('recipient_type', 'rfc') === 'rfc')>
                                                <label class="btn btn-outline-primary py-3" for="authority_correspondence_recipient_rfc">
                                                    <i class="ph ph-buildings me-2"></i>{{ __('app.correspondence.recipients.rfc') }}
                                                </label>

                                                <input class="btn-check" id="authority_correspondence_recipient_applicant" name="recipient_type" type="radio" value="applicant" required @checked(old('recipient_type') === 'applicant')>
                                                <label class="btn btn-outline-primary py-3" for="authority_correspondence_recipient_applicant">
                                                    <i class="ph ph-user me-2"></i>{{ __('app.correspondence.recipients.applicant') }}
                                                </label>

                                                <input class="btn-check" id="authority_correspondence_recipient_all" name="recipient_type" type="radio" value="all" required @checked(old('recipient_type') === 'all')>
                                                <label class="btn btn-outline-primary py-3" for="authority_correspondence_recipient_all">
                                                    <i class="ph ph-users-three me-2"></i>{{ __('app.correspondence.recipients.all') }}
                                                </label>
                                            </div>
                                            <div class="form-text">{{ __('app.correspondence.recipient_help') }}</div>
                                        </fieldset>
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
                                            <div class="col-md-4">
                                                <label class="form-label">{{ __('app.correspondence.sender') }}</label>
                                                <div class="form-control bg-light authority-message-readonly">{{ $message->sender_name }}</div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">{{ __('app.correspondence.sender_type') }}</label>
                                                <div class="form-control bg-light authority-message-readonly">{{ $message->localizedSenderType() }}</div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">{{ __('app.correspondence.recipient') }}</label>
                                                <div class="form-control bg-light authority-message-readonly">{{ $message->localizedRecipientType() }}</div>
                                            </div>
                                            <div class="col-md-2">
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('[data-authority-decision-form]');

            if (!form) {
                return;
            }

            const decisionInputs = Array.from(form.querySelectorAll('input[name="status"]'));
            const notePanel = form.querySelector('[data-decision-panel="note"]');
            const approvedPanel = form.querySelector('[data-decision-panel="approved"]');
            const changesPanel = form.querySelector('[data-decision-panel="changes_requested"]');
            const noteInput = form.querySelector('[name="note"]');
            const noteRequiredMark = form.querySelector('[data-note-required]');
            const responseAttachment = form.querySelector('[name="response_attachment"]');
            const correctionItems = form.querySelector('[data-correction-items]');
            const correctionTemplate = form.querySelector('[data-correction-template]');
            const addCorrectionButton = form.querySelector('[data-add-correction]');

            const selectedDecision = function () {
                return decisionInputs.find(function (input) {
                    return input.checked;
                })?.value || '';
            };

            const reindexCorrections = function () {
                if (!correctionItems) {
                    return;
                }

                correctionItems.querySelectorAll('[data-correction-item]').forEach(function (item, index) {
                    item.querySelectorAll('[data-field]').forEach(function (field) {
                        field.name = 'change_requests[' + index + '][' + field.dataset.field + ']';
                    });
                });
            };

            const setCorrectionFieldsEnabled = function (enabled) {
                if (!correctionItems) {
                    return;
                }

                correctionItems.querySelectorAll('[data-correction-required]').forEach(function (field) {
                    field.disabled = !enabled;
                    field.required = enabled;
                });
                correctionItems.querySelectorAll('[data-correction-input]').forEach(function (field) {
                    field.disabled = !enabled;
                });
            };

            const syncDecisionPanels = function () {
                const decision = selectedDecision();
                const isApproved = decision === 'approved';
                const isChangesRequested = decision === 'changes_requested';
                const noteIsRequired = isChangesRequested || decision === 'rejected';

                if (notePanel) {
                    notePanel.hidden = decision === '';
                }
                if (approvedPanel) {
                    approvedPanel.hidden = !isApproved;
                }
                if (changesPanel) {
                    changesPanel.hidden = !isChangesRequested;
                }
                if (noteInput) {
                    noteInput.required = noteIsRequired;
                }
                if (noteRequiredMark) {
                    noteRequiredMark.hidden = !noteIsRequired;
                }
                if (responseAttachment) {
                    responseAttachment.disabled = !isApproved;
                    responseAttachment.required = isApproved;
                }

                setCorrectionFieldsEnabled(isChangesRequested);
            };

            decisionInputs.forEach(function (input) {
                input.addEventListener('change', syncDecisionPanels);
            });

            addCorrectionButton?.addEventListener('click', function () {
                if (!correctionItems || !correctionTemplate) {
                    return;
                }

                correctionItems.appendChild(correctionTemplate.content.cloneNode(true));
                reindexCorrections();
                setCorrectionFieldsEnabled(selectedDecision() === 'changes_requested');
            });

            correctionItems?.addEventListener('click', function (event) {
                const removeButton = event.target.closest('[data-remove-correction]');

                if (!removeButton) {
                    return;
                }

                const item = removeButton.closest('[data-correction-item]');
                const items = correctionItems.querySelectorAll('[data-correction-item]');

                if (items.length === 1) {
                    item.querySelectorAll('select, textarea, input[type="file"]').forEach(function (field) {
                        field.value = '';
                    });
                    return;
                }

                item.remove();
                reindexCorrections();
            });

            reindexCorrections();
            syncDecisionPanels();
        });
    </script>
@endpush
