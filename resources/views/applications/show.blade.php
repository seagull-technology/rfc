@php
    $title = $application->project_name;
    $applicationEntityLogoUrl = \App\Support\EntityLogo::url($entity, 'images/OIP.jpeg');
    $metadata = $application->metadata ?? [];
    $producer = data_get($metadata, 'producer', []);
    $director = data_get($metadata, 'director', []);
    $international = data_get($metadata, 'international', []);
    $requirements = data_get($metadata, 'requirements', []);
    $schedulePhases = data_get($metadata, 'schedule.phases', []);
    $budgetMeta = data_get($metadata, 'budget', []);
    $formatMoney = static fn ($value): string => filled($value) ? number_format((float) $value, 2) : __('app.dashboard.not_available');
    $formattedBudget = $formatMoney($application->estimated_budget);
    $formattedLocalSpend = $formatMoney(data_get($budgetMeta, 'local_spend_estimate'));
    $canUpdateApplication = $user->can('applications.update.entity')
        || ($user->can('applications.update.own') && (int) $application->submitted_by_user_id === (int) $user->getKey());
    $canSubmitApplication = $user->can('applications.submit')
        && ($user->can('applications.view.entity') || (int) $application->submitted_by_user_id === (int) $user->getKey());
    $foreignProducerApprovalPending = $application->requiresForeignProducerApproval()
        && ! $application->foreignProducerDeclarationIsSigned();
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
    $formatDate = static fn ($value): string => ($asDate($value)?->format('Y-m-d')) ?: __('app.dashboard.not_available');
    $formatDateRange = static function ($start, $end) use ($formatDate): string {
        $startLabel = $formatDate($start);
        $endLabel = $formatDate($end);

        if ($startLabel === __('app.dashboard.not_available') && $endLabel === __('app.dashboard.not_available')) {
            return __('app.dashboard.not_available');
        }

        return $startLabel.' - '.$endLabel;
    };
    $requiredApprovals = collect(data_get($requirements, 'required_approvals', []))
        ->map(fn ($approval) => __('app.applications.required_approval_options.'.$approval))
        ->join('، ') ?: __('app.applications.no_required_approvals');
    $issuedOfficialLetters = $officialLetters
        ->where('status', 'issued')
        ->values();
    $officialLetterForApproval = static function ($approval) use ($issuedOfficialLetters) {
        return $issuedOfficialLetters
            ->filter(function ($letter) use ($approval): bool {
                if ($letter->isApplicantLetter()) {
                    return false;
                }

                if ((int) $letter->application_authority_approval_id === (int) $approval->getKey()) {
                    return true;
                }

                return $approval->entity_id !== null && (int) $letter->target_entity_id === (int) $approval->entity_id;
            })
            ->sortByDesc(fn ($letter): int => ($letter->issued_at ?? $letter->updated_at ?? $letter->created_at)?->timestamp ?? 0)
            ->first();
    };

    $statusBadgeClass = match ($application->status) {
        'draft' => 'secondary',
        'submitted' => 'warning',
        'under_review' => 'info',
        'needs_clarification' => 'danger',
        'approved' => 'success',
        'rejected' => 'dark',
        default => 'secondary',
    };

    $timelineColor = static fn (string $status): string => match ($status) {
        'approved' => 'success',
        'rejected' => 'danger',
        'needs_clarification' => 'danger',
        'submitted', 'under_review', 'in_review' => 'warning',
        default => 'info',
    };
    $rfcDecisionStatus = data_get($metadata, 'rfc_decision.status');
    $rfcDecisionNote = data_get($metadata, 'rfc_decision.note') ?: $application->review_note;
    $rfcOfficialBooksPreparedAt = $asDate(
        data_get($metadata, 'rfc_decision.official_books_prepared_at')
            ?: data_get($metadata, 'rfc_decision.facilitation_issued_at')
    );
    $rfcDate = $rfcOfficialBooksPreparedAt
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
        ->each(function ($approval) use ($timelineEvents, $officialLetterForApproval, $asDate) {
            $approvalLetter = $officialLetterForApproval($approval);

            $timelineEvents->push([
                'label' => $approval->localizedAuthority(),
                'date' => $asDate($approval->decided_at ?? $approval->assigned_at ?? $approval->updated_at ?? $approval->created_at),
                'status' => $approval->status,
                'status_label' => $approval->localizedStatus(),
                'note' => $approval->note,
                'meta' => $approvalLetter?->serial_number,
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
        ->sortBy(fn (array $event) => $event['date']?->timestamp ?? PHP_INT_MAX)
        ->values();
    $latestCorrespondence = $correspondences->first();
    $approvalBadgeClass = static fn (string $status): string => match ($status) {
        'approved' => 'success',
        'rejected' => 'danger',
        'needs_revision' => 'warning',
        default => 'warning',
    };
    $approvalTextClass = static fn (string $status): string => match ($status) {
        'approved' => 'success',
        'rejected' => 'danger',
        default => 'warning',
    };
    $requestState = match ($application->status) {
        'draft' => [
            'title' => __('app.request_state.draft_title'),
            'body' => __('app.request_state.application_draft_body'),
        ],
        'submitted', 'under_review' => [
            'title' => __('app.request_state.review_title'),
            'body' => __('app.request_state.application_review_body'),
        ],
        'needs_clarification' => [
            'title' => __('app.request_state.clarification_title'),
            'body' => __('app.request_state.application_clarification_body'),
        ],
        'approved' => [
            'title' => __('app.request_state.approved_title'),
            'body' => __('app.request_state.application_approved_body'),
        ],
        'rejected' => [
            'title' => __('app.request_state.rejected_title'),
            'body' => __('app.request_state.application_rejected_body'),
        ],
        default => [
            'title' => __('app.request_state.review_title'),
            'body' => __('app.request_state.application_review_body'),
        ],
    };
@endphp

@extends('layouts.portal-dashboard', ['title' => $title])

@section('page_layout_class', 'applicant-request-show-layout py-0')

@push('styles')
    <style>
        .applicant-request-show-layout .request-actions-card {
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .applicant-request-show-layout .profile-tab {
            flex-wrap: nowrap;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: thin;
        }

        .applicant-request-show-layout .profile-tab .nav-link {
            white-space: nowrap;
        }

        .applicant-request-show-layout .profile-content .tab-pane {
            transform: none !important;
        }

        .applicant-request-show-layout .table-responsive {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
        }

        .applicant-request-show-layout .applicant-request-table-scroll {
            overflow-x: auto;
        }

        .applicant-request-show-layout .applicant-approval-table {
            table-layout: fixed;
            min-width: 760px;
            width: 100%;
        }

        .applicant-request-show-layout .applicant-approval-table th,
        .applicant-request-show-layout .applicant-approval-table td {
            vertical-align: top;
            white-space: normal;
            word-break: break-word;
        }

        .applicant-request-show-layout .applicant-correspondence-list {
            display: grid;
            gap: .75rem;
        }

        .applicant-request-show-layout .applicant-correspondence-list .list-group-item {
            border: 1px solid rgba(17, 24, 39, 0.08);
            border-radius: 6px;
            padding: .875rem;
            text-align: start;
        }

        .applicant-request-show-layout .applicant-correspondence-list .list-group-item + .list-group-item {
            border-top-width: 1px;
        }

        .applicant-request-show-layout .applicant-correspondence-summary {
            min-width: 0;
            flex: 1;
        }

        .applicant-request-show-layout .applicant-correspondence-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .25rem .75rem;
        }

        .applicant-request-show-layout .applicant-message-readonly {
            height: auto;
            min-height: 3rem;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .applicant-request-show-layout .applicant-message-body {
            min-height: 9rem;
            white-space: pre-wrap;
        }

        [dir="rtl"] .applicant-request-show-layout .table-responsive {
            direction: ltr;
        }

        [dir="rtl"] .applicant-request-show-layout .table-responsive > .table {
            direction: rtl;
        }

        .applicant-request-show-layout .request-section-card .card-body > div:last-child,
        .applicant-request-show-layout .request-section-card .card-body > p:last-child {
            margin-bottom: 0;
        }

        .applicant-request-show-layout .request-state-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: .75rem 0 .5rem;
        }

        .applicant-request-show-layout .request-state-text {
            color: #6c757d;
            margin-bottom: 0;
            max-width: 58rem;
        }

        .applicant-request-show-layout .request-state-meta {
            border: 1px solid rgba(17, 24, 39, .08);
            border-radius: .5rem;
            height: 100%;
            padding: 1rem;
        }

        .applicant-request-show-layout .request-state-meta-label {
            color: #6c757d;
            display: block;
            font-size: .8125rem;
            font-weight: 600;
            margin-bottom: .5rem;
        }

        .applicant-request-show-layout .request-state-meta-detail {
            color: #6c757d;
            display: block;
            font-size: .875rem;
            margin-top: .5rem;
        }

        .applicant-request-show-layout .applicant-approval-feed {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .applicant-request-show-layout .applicant-approval-feed-item {
            border-bottom: 1px solid rgba(17, 24, 39, .1);
            padding: 1rem 0;
        }

        .applicant-request-show-layout .applicant-approval-feed-item:first-child {
            padding-top: 0;
        }

        .applicant-request-show-layout .applicant-approval-feed-item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .applicant-request-show-layout .applicant-approval-avatar {
            background: #fff;
            border: 1px solid rgba(17, 24, 39, .08);
            flex: 0 0 auto;
            height: 50px;
            object-fit: contain;
            padding: .35rem;
            width: 50px;
        }

        .applicant-request-show-layout .applicant-approval-body {
            color: #6c757d;
            line-height: 1.8;
            margin-top: .75rem;
            padding-inline-start: 4.1rem;
            white-space: pre-line;
        }

        .applicant-request-show-layout .applicant-approval-letter-link img {
            height: 32px;
            width: 32px;
        }

        @media (max-width: 575.98px) {
            .applicant-request-show-layout .applicant-approval-body {
                padding-inline-start: 0;
            }
        }
    </style>
@endpush

@section('hero')
    <div class="card view-request-bg">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">
                <div class="d-flex align-items-center">
                    <div class="profile-img position-relative me-3 mb-3 mb-lg-0 profile-logo profile-logo1">
                        <img src="{{ $applicationEntityLogoUrl }}" alt="User-Profile" class="theme-color-default-img img-fluid rounded-pill avatar-100" loading="lazy">
                    </div>
                    <div>
                        <h4 class="me-2 h4 text-white">{{ $entity->displayName() }}</h4>
                        <h6 class="me-2 text-white">{{ $application->project_name }}</h6>
                        <div class="text-white">{{ $application->code }}</div>
                    </div>
                </div>

                <ul class="d-flex nav nav-pills mb-0 text-center profile-tab" data-toggle="slider-tab" id="profile-pills-tab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active show" data-bs-toggle="tab" href="#profile-request" role="tab" aria-selected="true">{{ __('app.applications.request_tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile-documents" role="tab" aria-selected="false">{{ __('app.documents.tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile-annex" role="tab" aria-selected="false">{{ __('app.applications.annex_tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile-approvals" role="tab" aria-selected="false">{{ __('app.applications.approvals_tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $wrapReportAvailable ? '' : 'disabled' }}" data-bs-toggle="tab" href="#profile-wrap-report" role="tab" aria-selected="false" @if (! $wrapReportAvailable) aria-disabled="true" tabindex="-1" @endif>{{ __('app.wrap_report.tab') }}</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
@endsection

@section('content')
    @include('applications.partials.authority-change-requests', ['changeRequestViewer' => 'applicant'])
    <div class="row">
        <div class="col-sm-12">
            <div class="streamit-wraper-table">
                <div class="row">
                    <div class="col-lg-3">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h2 class="episode-playlist-title wp-heading-inline">
                                <span class="position-relative">{{ __('app.applications.status_timeline_title') }}</span>
                            </h2>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="iq-timeline0 m-0 d-flex align-items-center justify-content-between position-relative">
                            <ul class="list-inline p-0 m-0">
                                @foreach ($timelineEvents as $event)
                                    @php
                                        $eventColor = $timelineColor($event['status']);
                                    @endphp
                                    <li>
                                        <div class="timeline-dots timeline-dot1 border-{{ $eventColor }} text-{{ $eventColor }}"></div>
                                        <h6 class="float-left mb-1 fw-semibold">{{ $event['label'] }}</h6>
                                        @if ($event['date'])
                                            <small class="float-right mt-1">{{ $event['date']->format('Y-m-d') }}</small>
                                        @endif
                                        <div class="d-inline-block w-100">
                                            <p class="mb-0 text-{{ $eventColor }}">{{ $event['status_label'] }}</p>
                                            @if ($event['note'])
                                                <p class="mb-0">{{ $event['note'] }}</p>
                                            @endif
                                            @if ($event['meta'])
                                                <p class="mb-0 text-muted">{{ $event['meta'] }}</p>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="card request-actions-card mb-4">
                    <div class="card-body">
                        <div>
                            <span class="badge bg-{{ $statusBadgeClass }}">{{ $application->localizedStatus() }}</span>
                            <span class="ms-2 text-muted">{{ __('app.applications.request_number') }}: {{ $application->code }}</span>
                            <h3 class="request-state-title">{{ $requestState['title'] }}</h3>
                            <p class="request-state-text">{{ $requestState['body'] }}</p>
                            @if ($application->canBeSubmittedByApplicant() && $canSubmitApplication && $foreignProducerApprovalPending)
                                <div class="alert alert-warning d-flex align-items-start gap-2 mt-3 mb-0" role="alert">
                                    <i class="ph ph-warning-circle fs-4 mt-1" aria-hidden="true"></i>
                                    <div>
                                        <div class="fw-semibold">{{ __('app.applications.foreign_producer_approval_pending_title') }}</div>
                                        <div>{{ __('app.applications.foreign_producer_approval_required') }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mt-4">
                            <div class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-outline-secondary" data-bs-toggle="tab" href="#profile-approvals" role="tab" aria-selected="false">{{ __('app.request_state.open_correspondence') }}</a>
                                @if ($application->canBeEditedByApplicant() && $canUpdateApplication)
                                    <a class="btn btn-light" href="{{ route('applications.edit', $application) }}">{{ __('app.applications.edit_action') }}</a>
                                @endif
                                @if ($application->canBeSubmittedByApplicant() && $canSubmitApplication)
                                    @if ($foreignProducerApprovalPending)
                                        <button class="btn btn-danger" type="button" disabled aria-disabled="true">
                                            {{ __('app.applications.foreign_producer_approval_pending_action') }}
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('applications.submit', $application) }}"
                                            data-application-submit-confirm
                                            data-confirm-title="{{ __('app.applications.submit_confirm_title') }}"
                                            data-confirm-text="{{ __('app.applications.submit_confirm_body') }}"
                                            data-confirm-button="{{ __('app.applications.submit_confirm_confirm') }}"
                                            data-cancel-button="{{ __('app.applications.submit_confirm_cancel') }}">
                                            @csrf
                                            <button class="btn btn-danger" type="submit">{{ __('app.applications.submit_action') }}</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="profile-content tab-content iq-tab-fade-up">
                    <div id="profile-request" class="tab-pane fade active show">
                        <div class="card request-section-card">
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
                                <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_email') }}:</span><span class="ms-2">{{ data_get($producer, 'contact_email', __('app.dashboard.not_available')) }}</span></div>
                                <div class="mb-0"><span class="fw-600">{{ __('app.applications.release_method') }}:</span><span class="ms-2">{{ \App\Models\ReleaseMethod::labelFor($application->release_method) }}</span></div>
                            </div>
                        </div>

                        <div class="card request-section-card">
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
                                        <a href="{{ data_get($director, 'director_profile_url') }}" class="ms-2" target="_blank" rel="noreferrer">{{ __('app.applications.open_profile') }}</a>
                                    @else
                                        <span class="ms-2">{{ __('app.dashboard.not_available') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if (filled(data_get($international, 'international_producer_name')) || filled(data_get($international, 'international_producer_company')))
                            <div class="card request-section-card">
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

                        <div class="card request-section-card">
                            <div class="card-header">
                                <h2 class="episode-playlist-title wp-heading-inline">
                                    <span class="position-relative">{{ __('app.applications.schedule_title') }}</span>
                                </h2>
                            </div>
                            <div class="card-body">
                                <div class="mb-1"><span class="fw-600">{{ __('app.applications.schedule_phases.preparation') }}:</span><span class="ms-2">{{ $formatDateRange(data_get($schedulePhases, 'preparation.start_date'), data_get($schedulePhases, 'preparation.end_date')) }}</span></div>
                                <div class="mb-1"><span class="fw-600">{{ __('app.applications.schedule_phases.shooting') }}:</span><span class="ms-2">{{ $formatDateRange($application->planned_start_date, $application->planned_end_date) }}</span></div>
                                <div class="mb-1"><span class="fw-600">{{ __('app.applications.schedule_phases.wrap') }}:</span><span class="ms-2">{{ $formatDateRange(data_get($schedulePhases, 'wrap.start_date'), data_get($schedulePhases, 'wrap.end_date')) }}</span></div>
                                <div class="mb-0"><span class="fw-600">{{ __('app.applications.schedule_phases.post_production') }}:</span><span class="ms-2">{{ $formatDateRange(data_get($schedulePhases, 'post_production.start_date'), data_get($schedulePhases, 'post_production.end_date')) }}</span></div>
                            </div>
                        </div>

                        <div class="card request-section-card">
                            <div class="card-header">
                                <h2 class="episode-playlist-title wp-heading-inline">
                                    <span class="position-relative">{{ __('app.applications.crew_title') }}</span>
                                </h2>
                            </div>
                            <div class="card-body">
                                <div class="mb-0"><span class="fw-600">{{ __('app.applications.estimated_crew_count') }}:</span><span class="ms-2">{{ $application->estimated_crew_count ?: __('app.dashboard.not_available') }}</span></div>
                            </div>
                        </div>

                        <div class="card request-section-card">
                            <div class="card-header">
                                <h2 class="episode-playlist-title wp-heading-inline">
                                    <span class="position-relative">{{ __('app.applications.budget_title') }}</span>
                                </h2>
                            </div>
                            <div class="card-body">
                                <div class="mb-1"><span class="fw-600">{{ __('app.applications.estimated_budget') }}:</span><span class="ms-2">{{ $formattedBudget }}</span></div>
                                <div class="mb-0"><span class="fw-600">{{ __('app.applications.local_spend_estimate') }}:</span><span class="ms-2">{{ $formattedLocalSpend }}</span></div>
                            </div>
                        </div>
                    </div>

                    <div id="profile-documents" class="tab-pane fade">
                        @include('applications.partials.documents-applicant', ['documents' => $documents])
                    </div>

                    <div id="profile-annex" class="tab-pane fade">
                        @include('applications.partials.annex-applicant', ['documents' => $documents])
                    </div>

                    <div id="profile-approvals" class="tab-pane fade">
                        <div class="card">
                            <div class="card-header">
                                <div class="header-title">
                                    <h2 class="episode-playlist-title wp-heading-inline">
                                        <span class="position-relative">{{ __('app.applications.approvals_title') }}</span>
                                    </h2>
                                </div>
                            </div>
                            <div class="card-body p-3 mb-0">
                                <ul class="applicant-approval-feed">
                                    @forelse ($authorityApprovals as $approval)
                                        @php
                                            $approvalLetter = $officialLetterForApproval($approval);
                                            $approvalStatusClass = $approvalBadgeClass($approval->status);
                                            $approvalDate = $approval->decided_at ?? $approval->updated_at;
                                            $approvalNote = $approval->note
                                                ?: ($approvalLetter?->body ?: data_get($requestOverview, 'authority_progress.summary'));
                                            $approvalDisplayName = $approval->entity?->displayName() ?: $approval->localizedAuthority();
                                        @endphp
                                        <li class="applicant-approval-feed-item">
                                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                                <div class="d-flex align-items-center min-w-0">
                                                    <img src="{{ asset('images/logo.svg') }}"
                                                        class="applicant-approval-avatar rounded-circle img-fluid"
                                                        alt="{{ $approvalDisplayName }}"
                                                        loading="lazy">
                                                    <div class="ms-3 min-w-0">
                                                        <h5 class="mt-2 mb-1">
                                                            <span class="fw-600">{{ $approvalDisplayName }}</span>
                                                        </h5>
                                                        <div class="small text-muted">
                                                            {{ $approvalDate?->format('Y-m-d') ?: __('app.dashboard.not_available') }}
                                                            @if ($approvalLetter?->serial_number)
                                                                <span class="mx-1">|</span>{{ $approvalLetter->serial_number }}
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                    <h6 class="mt-2 mb-0">
                                                        <span class="badge bg-{{ $approvalStatusClass }}">{{ $approval->localizedStatus() }}</span>
                                                    </h6>
                                                    @if ($approvalLetter)
                                                        <button type="button"
                                                            class="btn p-0 applicant-approval-letter-link"
                                                            data-bs-toggle="offcanvas"
                                                            data-bs-target="#applicantOfficialLetterView{{ $approvalLetter->getKey() }}"
                                                            aria-controls="applicantOfficialLetterView{{ $approvalLetter->getKey() }}"
                                                            title="{{ __('app.official_letters.view_action') }}">
                                                            <img src="{{ asset('images/envelope.png') }}" alt="{{ __('app.official_letters.view_action') }}" loading="lazy">
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="applicant-approval-body text-break">
                                                @if ($approvalNote)
                                                    {{ $approvalNote }}
                                                @else
                                                    <span class="text-{{ $approvalTextClass($approval->status) }}">{{ data_get($requestOverview, 'authority_progress.summary') }}</span>
                                                @endif
                                            </div>
                                        </li>
                                    @empty
                                        <li class="text-center text-muted py-4">{{ __('app.applications.no_required_approvals') }}</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>

                        <div class="row g-3 my-4">
                            @foreach (['authority_progress', 'latest_official_step', 'final_decision_readiness'] as $overviewKey)
                                <div class="col-lg-4">
                                    <div class="request-state-meta">
                                        <span class="request-state-meta-label">{{ data_get($requestOverview, $overviewKey.'.label') }}</span>
                                        <div>{{ data_get($requestOverview, $overviewKey.'.summary') }}</div>
                                        @if (filled(data_get($requestOverview, $overviewKey.'.detail')))
                                            <span class="request-state-meta-detail">{{ data_get($requestOverview, $overviewKey.'.detail') }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($application->review_note || $latestCorrespondence)
                            <div class="row g-3 mb-4">
                                @if ($application->review_note)
                                    <div class="col-lg-6">
                                        <div class="request-state-meta">
                                            <span class="request-state-meta-label">{{ __('app.request_state.review_note') }}</span>
                                            <div>{{ $application->review_note }}</div>
                                        </div>
                                    </div>
                                @endif

                                @if ($latestCorrespondence)
                                    <div class="col-lg-6">
                                        <div class="request-state-meta">
                                            <span class="request-state-meta-label">{{ __('app.request_state.latest_correspondence') }}</span>
                                            <div class="fw-semibold">{{ $latestCorrespondence->subject ?: $latestCorrespondence->sender_name }}</div>
                                            <div class="text-muted small mt-1">{{ $latestCorrespondence->localizedSenderType() }} | {{ $latestCorrespondence->created_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div>
                                            <div class="mt-2 text-break">{{ $latestCorrespondence->message }}</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @include('applications.partials.official-letters-applicant', ['officialLetters' => $officialLetters])
                        @include('applications.partials.correspondence-applicant', ['correspondences' => $correspondences])
                        @include('applications.partials.final-decision-applicant')
                    </div>

                    <div id="profile-wrap-report" class="tab-pane fade">
                        @include('applications.partials.wrap-report-applicant', [
                            'wrapReport' => $wrapReport,
                            'wrapReportAvailable' => $wrapReportAvailable,
                            'wrapReportOptions' => $wrapReportOptions,
                        ])
                    </div>
                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('applications.partials.submit-confirmation-script')
@endsection
