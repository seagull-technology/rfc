@php
    $title = $application->project_name;
    $breadcrumb = __('app.admin.navigation.applications');
    $statusClass = static fn (?string $status): string => match ($status) {
        'draft' => 'secondary',
        'submitted', 'pending_review' => 'warning',
        'under_review', 'in_review' => 'info',
        'needs_clarification' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $metadata = $application->metadata ?? [];
    $requirements = data_get($metadata, 'requirements', []);
    $international = data_get($metadata, 'international', []);
    $formattedBudget = $application->estimated_budget ? number_format((float) $application->estimated_budget, 2) : __('app.dashboard.not_available');
    $requiredApprovals = collect(data_get($requirements, 'required_approvals', []))
        ->map(fn ($approval) => __('app.applications.required_approval_options.'.$approval))
        ->join('، ') ?: __('app.applications.no_required_approvals');
    $translateOrFallback = static function (string $translationKey, string $fallback): string {
        $translated = __($translationKey);

        return $translated === $translationKey ? $fallback : $translated;
    };
    $formatFallback = static fn (?string $value): string => filled($value) ? str((string) $value)->replace('_', ' ')->title()->toString() : __('app.dashboard.not_available');
    $timelineEvents = collect();

    foreach ($statusHistory as $event) {
        $timelineEvents->push([
            'label' => __('app.roles.rfc_admin'),
            'date' => $event->happened_at,
            'status' => $event->status,
            'status_label' => $event->localizedStatus(),
            'note' => $event->note,
        ]);
    }

    foreach ($authorityApprovals as $approval) {
        $timelineEvents->push([
            'label' => $approval->localizedAuthority(),
            'date' => $approval->decided_at ?? $approval->updated_at,
            'status' => $approval->status,
            'status_label' => $approval->localizedStatus(),
            'note' => $approval->note,
        ]);
    }

    $timelineEvents = $timelineEvents
        ->sortByDesc(fn (array $event) => $event['date']?->timestamp ?? 0)
        ->values();
    $latestCorrespondence = $correspondences->first();
    $pendingApprovalsCount = $authorityApprovals->whereIn('status', ['pending', 'in_review'])->count();
    $nextCheckpoint = match (true) {
        blank($application->assigned_to_user_id) => __('app.admin_request_state.assign_reviewer_checkpoint'),
        $application->status === 'needs_clarification' => __('app.admin_request_state.await_applicant_checkpoint'),
        $pendingApprovalsCount > 0 => __('app.admin_request_state.pending_approvals_checkpoint', ['count' => $pendingApprovalsCount]),
        $application->canBeFinallyDecided() => __('app.admin_request_state.final_decision_checkpoint'),
        default => __('app.admin_request_state.monitor_checkpoint'),
    };
    $stateTitle = match (true) {
        $application->status === 'needs_clarification' => __('app.admin_request_state.await_applicant_title'),
        $application->status === 'approved' => __('app.admin_request_state.approved_title'),
        $application->status === 'rejected' => __('app.admin_request_state.closed_title'),
        $application->canBeFinallyDecided() => __('app.admin_request_state.final_decision_ready_title'),
        $pendingApprovalsCount > 0 => __('app.admin_request_state.waiting_authorities_title'),
        default => __('app.admin_request_state.review_in_progress_title'),
    };
    $stateBody = match (true) {
        $application->status === 'needs_clarification' => __('app.admin_request_state.application_await_applicant_body'),
        $application->status === 'approved' => __('app.admin_request_state.application_approved_body'),
        $application->status === 'rejected' => __('app.admin_request_state.application_closed_body'),
        $application->canBeFinallyDecided() => __('app.admin_request_state.application_final_decision_body'),
        $pendingApprovalsCount > 0 => __('app.admin_request_state.application_waiting_authorities_body'),
        default => __('app.admin_request_state.application_review_in_progress_body'),
    };

    $documentGroups = $documents
        ->groupBy(fn ($document) => $document->document_type ?: 'other')
        ->map(function ($rows, string $type) use ($translateOrFallback, $formatFallback) {
            return [
                'title' => $translateOrFallback('app.documents.types.'.$type, $formatFallback($type)),
                'items' => $rows->values(),
            ];
        })
        ->values();
    $canAssignReviewer = auth()->user()?->can('applications.assign') ?? false;
    $canReviewApplication = auth()->user()?->can('applications.review') ?? false;
    $canApproveApplication = auth()->user()?->can('applications.approve') ?? false;
    $canManageAuthorityApprovals = $canReviewApplication || $canApproveApplication;
    $authorityApprovalStatuses = $canApproveApplication
        ? ['pending', 'in_review', 'approved', 'rejected']
        : ['pending', 'in_review'];
    $sharedAuthorityInboxLabel = __('app.admin.applications.authority_shared_inbox');
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('page_layout_class', 'admin-application-show-layout py-0')

@push('styles')
    <style>
        .admin-application-show-layout {
            padding-top: 0;
        }

        .admin-application-show-layout .card {
            margin-bottom: 1.5rem;
        }

        .admin-application-show-layout .card-header {
            padding-bottom: 0;
        }

        .admin-application-show-layout .profile-content .card:last-child {
            margin-bottom: 0;
        }

        .admin-application-show-layout .profile-content .tab-pane {
            transform: none !important;
        }

        .official-letter-offcanvas-form {
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            height: 100%;
            min-height: 0;
        }

        .official-letter-offcanvas-form .offcanvas-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
        }

        .official-letter-offcanvas-form .offcanvas-footer {
            flex: 0 0 auto;
        }

        .admin-application-show-layout .profile-tab,
        .application-hero-card .profile-tab {
            flex-wrap: nowrap;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: thin;
        }

        .admin-application-show-layout .profile-tab .nav-link,
        .application-hero-card .profile-tab .nav-link {
            font-size: .9375rem;
            padding-left: .75rem;
            padding-right: .75rem;
            white-space: nowrap;
        }

        .admin-application-show-layout .timeline-note {
            overflow-wrap: anywhere;
        }

        .admin-application-show-layout .table thead th,
        .admin-application-show-layout .table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .admin-application-show-layout .table-responsive {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
        }

        [dir="rtl"] .admin-application-show-layout .table-responsive {
            direction: ltr;
        }

        [dir="rtl"] .admin-application-show-layout .table-responsive > .table {
            direction: rtl;
        }

        .admin-application-show-layout .application-detail-list .mb-1:last-child,
        .admin-application-show-layout .application-detail-list .mb-3:last-child {
            margin-bottom: 0 !important;
        }

        .admin-application-show-layout .application-hero-card {
            margin: 0 1rem 1.5rem;
        }

        .admin-application-show-layout .application-hero-card .card-body {
            padding: 1.5rem;
        }

        .admin-application-show-layout .annex-card .list-group-item {
            background-color: rgba(181, 43, 30, 0.08);
            border-color: rgba(181, 43, 30, 0.12);
        }

        .admin-application-show-layout .request-narrow-table {
            width: 88%;
            margin: auto;
        }

        .admin-application-show-layout .admin-approval-management-table,
        .admin-application-show-layout .admin-official-letters-table {
            table-layout: fixed;
            width: 100%;
        }

        .admin-application-show-layout .admin-approval-management-table {
            min-width: 1180px;
        }

        .admin-application-show-layout .admin-official-letters-table {
            min-width: 1000px;
        }

        .admin-application-show-layout .admin-approval-management-table th,
        .admin-application-show-layout .admin-approval-management-table td,
        .admin-application-show-layout .admin-official-letters-table th,
        .admin-application-show-layout .admin-official-letters-table td {
            vertical-align: top;
            white-space: normal;
            word-break: break-word;
        }

        .admin-application-show-layout .admin-official-letter-action-cell {
            text-align: center;
        }

        .admin-application-show-layout .admin-correspondence-list {
            display: grid;
            gap: .75rem;
        }

        .admin-application-show-layout .admin-correspondence-list .list-group-item {
            border: 1px solid rgba(17, 24, 39, 0.08);
            border-radius: 6px;
            padding: .875rem;
            text-align: start;
        }

        .admin-application-show-layout .admin-correspondence-list .list-group-item + .list-group-item {
            border-top-width: 1px;
        }

        .admin-application-show-layout .admin-correspondence-summary {
            flex: 1;
            min-width: 0;
        }

        .admin-application-show-layout .admin-correspondence-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem .75rem;
        }

        .admin-application-show-layout .admin-message-readonly {
            height: auto;
            line-height: 1.7;
            min-height: 42px;
        }

        .admin-application-show-layout .admin-message-body {
            min-height: 140px;
            white-space: pre-line;
        }

        .admin-application-show-layout .tab-pane > .card:last-child {
            margin-bottom: 0;
        }

        .admin-application-show-layout .admin-state-card .card-body {
            padding: 1.5rem;
        }

        .admin-application-show-layout .admin-state-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: .75rem 0 .5rem;
        }

        .admin-application-show-layout .admin-state-text {
            color: #6c757d;
            margin-bottom: 0;
            max-width: 58rem;
        }

        .admin-application-show-layout .admin-state-meta {
            border: 1px solid rgba(17, 24, 39, 0.08);
            border-radius: .5rem;
            height: 100%;
            padding: 1rem;
        }

        .admin-application-show-layout .admin-state-meta-label {
            color: #6c757d;
            display: block;
            font-size: .8125rem;
            font-weight: 600;
            margin-bottom: .5rem;
        }

        .admin-application-show-layout .authority-sla-summary .card-body {
            padding: 1rem 1.25rem;
        }

        .admin-application-show-layout .authority-sla-badges {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }

        @media (max-width: 991.98px) {
            .admin-application-show-layout .application-hero-card {
                margin: 0 .75rem 1rem;
            }

            .admin-application-show-layout .request-narrow-table {
                width: 100%;
            }
        }
    </style>
@endpush

@section('hero')
    <div class="card view-request-bg application-hero-card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center">
                    <div class="profile-img position-relative me-3 mb-3 mb-lg-0 profile-logo profile-logo1">
                        <img src="{{ asset('images/OIP.jpeg') }}" alt="User-Profile" class="theme-color-default-img img-fluid rounded-pill avatar-100" loading="lazy">
                    </div>
                    <div>
                        <h4 class="me-2 h4 text-white">{{ $application->entity?->displayName() ?? __('app.dashboard.not_available') }}</h4>
                        <h6 class="me-2 text-white">{{ $application->project_name }}</h6>
                    </div>
                </div>
                <ul class="d-flex nav nav-pills mb-0 text-center profile-tab" data-toggle="slider-tab" id="profile-pills-tab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active show" data-bs-toggle="tab" href="#profile-profile" role="tab">{{ __('app.admin.applications.request_tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile-activity" role="tab">{{ __('app.documents.tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile-Annex" role="tab">{{ __('app.admin.applications.annex_tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile-decision" role="tab">{{ __('app.admin.applications.decision_tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile-activity2" role="tab">{{ __('app.admin.applications.approvals_tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile-correspondence" role="tab">{{ __('app.correspondence.tab') }}</a>
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
                                        @forelse ($timelineEvents as $event)
                                            @php($eventColor = $statusClass($event['status']))
                                            <li>
                                                <div class="timeline-dots timeline-dot1 border-{{ $eventColor }} text-{{ $eventColor }}"></div>
                                                <h6 class="float-left mb-1 fw-semibold">{{ $event['label'] }}</h6>
                                                @if ($event['date'])
                                                    <small class="float-right mt-1">{{ $event['date']->format('Y-m-d') }}</small>
                                                @endif
                                                <div class="d-inline-block w-100">
                                                    <p class="mb-0 text-{{ $eventColor }}">{{ $event['status_label'] }}</p>
                                                    @if (filled($event['note']))
                                                        <p class="mb-0 timeline-note">{{ $event['note'] }}</p>
                                                    @endif
                                                </div>
                                            </li>
                                        @empty
                                            <li class="text-muted">{{ __('app.admin.applications.empty_state') }}</li>
                                        @endforelse
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div class="iq-header-title">
                                    <h3 class="card-title">{{ __('app.admin.applications.assignment_title') }}</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <small class="text-muted d-block">{{ __('app.workflow.assigned_reviewer') }}</small>
                                    <div>{{ $application->assignedTo?->displayName() ?? __('app.workflow.unassigned') }}</div>
                                </div>
                                @if ($canAssignReviewer)
                                    <form method="POST" action="{{ route('admin.applications.assign', $application) }}" class="row g-3">
                                        @csrf
                                        <div class="col-12">
                                            <label for="assigned_to_user_id" class="form-label">{{ __('app.workflow.assign_reviewer') }}</label>
                                            <select id="assigned_to_user_id" name="assigned_to_user_id" class="form-select" required>
                                                @foreach ($reviewers as $reviewer)
                                                    <option value="{{ $reviewer->getKey() }}" @selected($application->assigned_to_user_id === $reviewer->getKey())>{{ $reviewer->displayName() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <button class="btn btn-outline-primary" type="submit">{{ __('app.workflow.assign_action') }}</button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="card admin-state-card">
                            <div class="card-body">
                                <div>
                                    <span class="badge bg-{{ $statusClass($application->status) }}">{{ $application->localizedStatus() }}</span>
                                    <span class="ms-2 text-muted">{{ __('app.applications.request_number') }}: {{ $application->code }}</span>
                                    <h3 class="admin-state-title">{{ $stateTitle }}</h3>
                                    <p class="admin-state-text">{{ $stateBody }}</p>
                                </div>

                                <div class="d-flex gap-2 flex-wrap mt-4">
                                    @if ($canReviewApplication || $canApproveApplication)
                                        <a class="btn btn-outline-secondary" data-bs-toggle="tab" href="#profile-decision" role="tab">{{ __('app.admin_request_state.open_review') }}</a>
                                    @endif
                                    @if ($canManageAuthorityApprovals)
                                        <a class="btn btn-outline-secondary" data-bs-toggle="tab" href="#profile-activity2" role="tab">{{ __('app.admin_request_state.open_approvals') }}</a>
                                    @endif
                                    @if ($canReviewApplication)
                                        <a class="btn btn-outline-secondary" data-bs-toggle="tab" href="#profile-correspondence" role="tab">{{ __('app.admin_request_state.open_correspondence') }}</a>
                                    @endif
                                </div>

                                <div class="row g-3 mt-1">
                                    <div class="col-lg-4">
                                        <div class="admin-state-meta">
                                            <span class="admin-state-meta-label">{{ __('app.admin_request_state.next_checkpoint') }}</span>
                                            <div>{{ $nextCheckpoint }}</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="admin-state-meta">
                                            <span class="admin-state-meta-label">{{ __('app.admin_request_state.latest_correspondence') }}</span>
                                            @if ($latestCorrespondence)
                                                <div class="fw-semibold">{{ $latestCorrespondence->subject ?: $latestCorrespondence->sender_name }}</div>
                                                <div class="text-muted small mt-1">{{ $latestCorrespondence->localizedSenderType() }} | {{ $latestCorrespondence->created_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div>
                                                <div class="mt-2 text-break">{{ $latestCorrespondence->message }}</div>
                                            @else
                                                <div>{{ __('app.correspondence.empty_state') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($applicantResponse['active'])
                                        <div class="col-lg-4">
                                            <div class="admin-state-meta">
                                                <span class="admin-state-meta-label">{{ $applicantResponse['title'] }}</span>
                                                <div class="fw-semibold">{{ $applicantResponse['summary'] }}</div>
                                                <div class="text-muted small mt-1">{{ __('app.admin_request_state.response_received_at') }} | {{ $applicantResponse['at']?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="profile-content tab-content iq-tab-fade-up">
                            <div id="profile-profile" class="tab-pane fade active show">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="header-title">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.applications.project_information') }}</span>
                                            </h2>
                                        </div>
                                    </div>
                                    <div class="card-body application-detail-list">
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.project_name') }}:</span><span class="ms-2">{{ $application->project_name }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.project_nationality') }}:</span><span class="ms-2">{{ __('app.applications.project_nationalities.'.$application->project_nationality) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.work_category') }}:</span><span class="ms-2">{{ __('app.applications.work_categories.'.$application->work_category) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.release_method') }}:</span><span class="ms-2">{{ __('app.applications.release_methods.'.$application->release_method) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.production_company_name') }}:</span><span class="ms-2">{{ data_get($metadata, 'producer.production_company_name', $application->entity?->displayName() ?? __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_address') }}:</span><span class="ms-2">{{ data_get($metadata, 'producer.contact_address', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_phone') }}:</span><span class="ms-2">{{ data_get($metadata, 'producer.contact_phone', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_mobile') }}:</span><span class="ms-2">{{ data_get($metadata, 'producer.contact_mobile', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_fax') }}:</span><span class="ms-2">{{ data_get($metadata, 'producer.contact_fax', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_email') }}:</span><span class="ms-2">{{ data_get($metadata, 'producer.contact_email', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.liaison_name') }}:</span><span class="ms-2">{{ data_get($metadata, 'producer.liaison_name', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.liaison_position') }}:</span><span class="ms-2">{{ data_get($metadata, 'producer.liaison_position', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.liaison_email') }}:</span><span class="ms-2">{{ data_get($metadata, 'producer.liaison_email', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-3"><span class="fw-600">{{ __('app.applications.liaison_mobile') }}:</span><span class="ms-2">{{ data_get($metadata, 'producer.liaison_mobile', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-0"><span class="fw-600">{{ __('app.applications.project_summary') }}:</span><span class="ms-2">{{ $application->project_summary ?: __('app.dashboard.not_available') }}</span></div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header">
                                        <div class="header-title">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.applications.director_information') }}</span>
                                            </h2>
                                        </div>
                                    </div>
                                    <div class="card-body application-detail-list">
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.director_name') }}:</span><span class="ms-2">{{ data_get($metadata, 'director.director_name', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.director_nationality') }}:</span><span class="ms-2">{{ data_get($metadata, 'director.director_nationality', __('app.dashboard.not_available')) }}</span></div>
                                        <div class="mb-0"><span class="fw-600">{{ __('app.applications.director_profile_url') }}:</span>
                                            @if (filled(data_get($metadata, 'director.director_profile_url')))
                                                <a href="{{ data_get($metadata, 'director.director_profile_url') }}" class="ms-2" target="_blank" rel="noreferrer">{{ data_get($metadata, 'director.director_profile_url') }}</a>
                                            @else
                                                <span class="ms-2">{{ __('app.dashboard.not_available') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if (filled(data_get($international, 'international_producer_name')) || filled(data_get($international, 'international_producer_company')))
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="header-title">
                                                <h2 class="episode-playlist-title wp-heading-inline">
                                                    <span class="position-relative">{{ __('app.applications.international_project_information') }}</span>
                                                </h2>
                                            </div>
                                        </div>
                                        <div class="card-body application-detail-list">
                                            <div class="mb-1"><span class="fw-600">{{ __('app.applications.international_producer_name') }}:</span><span class="ms-2">{{ data_get($international, 'international_producer_name', __('app.dashboard.not_available')) }}</span></div>
                                            <div class="mb-0"><span class="fw-600">{{ __('app.applications.international_producer_company') }}:</span><span class="ms-2">{{ data_get($international, 'international_producer_company', __('app.dashboard.not_available')) }}</span></div>
                                        </div>
                                    </div>
                                @endif

                                <div class="card">
                                    <div class="card-header">
                                        <h2 class="episode-playlist-title wp-heading-inline">
                                            <span class="position-relative">{{ __('app.admin.applications.schedule_title') }}</span>
                                        </h2>
                                    </div>
                                    <div class="card-body application-detail-list">
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.planned_start_date') }}:</span><span class="ms-2">{{ optional($application->planned_start_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.planned_end_date') }}:</span><span class="ms-2">{{ optional($application->planned_end_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.estimated_crew_count') }}:</span><span class="ms-2">{{ $application->estimated_crew_count ?: __('app.dashboard.not_available') }}</span></div>
                                        <div class="mb-0"><span class="fw-600">{{ __('app.applications.estimated_budget') }}:</span><span class="ms-2">{{ $formattedBudget }}</span></div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header">
                                        <h2 class="episode-playlist-title wp-heading-inline">
                                            <span class="position-relative">{{ __('app.applications.summary_title') }}</span>
                                        </h2>
                                    </div>
                                    <div class="card-body application-detail-list">
                                        <p class="mb-0" style="line-height: 1.8;">{{ $application->project_summary ?: __('app.dashboard.not_available') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div id="profile-activity" class="tab-pane fade">
                                @include('admin.applications.partials.documents', ['documents' => $documents])
                            </div>

                            <div id="profile-Annex" class="tab-pane fade">
                                <div class="card annex-card">
                                    <div class="card-body">
                                        <div class="form-card text-start pb-4">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.admin.applications.annex_title') }}</span>
                                            </h2>

                                            <div class="row">
                                                <div class="table-responsive mt-4">
                                                    <table id="basic-table" class="table table-striped mb-0 request-narrow-table" role="grid">
                                                        <tbody>
                                                            @forelse ($documentGroups as $group)
                                                                <tr>
                                                                    <td>
                                                                        <div class="d-flex align-items-center">
                                                                            <img class="rounded img-fluid avatar-40 me-3 bg-primary-subtle" src="{{ asset('images/clapboard.png') }}" alt="profile" loading="lazy">
                                                                            <h6>{{ $group['title'] }}</h6>
                                                                        </div>
                                                                        <div class="list-group px-5">
                                                                            @foreach ($group['items'] as $document)
                                                                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                                                                    <a href="{{ route('admin.applications.documents.download', [$application, $document]) }}">{{ $document->title }}</a>
                                                                                    <span class="badge bg-{{ $statusClass($document->status) }}">{{ $document->localizedStatus() }}</span>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td>{{ __('app.documents.empty_state') }}</td>
                                                                </tr>
                                                            @endforelse
                                                            <tr>
                                                                <td>
                                                                    <div class="fw-600 mb-2">{{ __('app.applications.required_approvals') }}</div>
                                                                    <div>{{ $requiredApprovals }}</div>
                                                                    <div class="fw-600 mt-4 mb-2">{{ __('app.applications.supporting_notes') }}</div>
                                                                    <div>{{ data_get($requirements, 'supporting_notes', __('app.applications.annex_empty_state')) }}</div>
                                                                    <div class="border-top mt-4 pt-4">
                                                                        @include('applications.partials.annex-summary', ['application' => $application, 'tableClass' => 'table table-striped mb-0'])
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="profile-decision" class="tab-pane fade">
                                @if ($canApproveApplication && ! $application->canBeFinallyDecided())
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="alert alert-info mb-0">{{ __('app.final_decision.approver_waiting_hint') }}</div>
                                        </div>
                                    </div>
                                @endif

                                @if ($canReviewApplication)
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                                <div class="iq-header-title">
                                                    <h2 class="episode-playlist-title wp-heading-inline">
                                                        <span class="position-relative">{{ __('app.admin.applications.review_title') }}</span>
                                                    </h2>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" action="{{ route('admin.applications.review', $application) }}" class="row g-3">
                                                @csrf
                                                <div class="col-12">
                                                    <label for="decision" class="form-label flex-grow-1">{{ __('app.admin.applications.review_decision') }}</label>
                                                    <select id="decision" name="decision" class="form-control bg-white" required>
                                                        @foreach (['under_review', 'needs_clarification'] as $decision)
                                                            <option value="{{ $decision }}" @selected(old('decision', $application->status) === $decision)>{{ __('app.statuses.'.$decision) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label flex-grow-1" for="note">{{ __('app.admin.applications.review_note') }}</label>
                                                    <textarea id="note" name="note" rows="6" class="form-control mt-2 bg-white">{{ old('note', $application->review_note) }}</textarea>
                                                </div>
                                                <div class="col-12">
                                                    <button class="btn btn-danger" type="submit">{{ __('app.admin.applications.review_submit') }}</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @endif

                                @if ($canApproveApplication)
                                    @include('admin.applications.partials.final-decision')
                                @endif
                            </div>

                            <div id="profile-activity2" class="tab-pane fade">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="iq-header-title">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.admin.applications.approvals_title') }}</span>
                                            </h2>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3 authority-sla-summary mb-4">
                                            @foreach ([
                                                ['label' => __('app.admin.authority_escalations.metrics.live_approvals'), 'value' => $authorityApprovalSignalStats['live'], 'class' => 'info'],
                                                ['label' => __('app.admin.authority_escalations.metrics.overdue_approvals'), 'value' => $authorityApprovalSignalStats['overdue'], 'class' => 'danger'],
                                                ['label' => __('app.admin.authority_escalations.metrics.escalated_approvals'), 'value' => $authorityApprovalSignalStats['escalated'], 'class' => 'dark'],
                                            ] as $metric)
                                                <div class="col-md-4">
                                                    <div class="card mb-0">
                                                        <div class="card-body">
                                                            <div class="text-muted">{{ $metric['label'] }}</div>
                                                            <h3 class="mb-0 text-{{ $metric['class'] }}">{{ $metric['value'] }}</h3>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="table-responsive mt-4">
                                            <table id="basic-table-approvals" class="table table-striped mb-0 request-narrow-table admin-approval-management-table" role="grid">
                                                <colgroup>
                                                    <col style="width: 13%;">
                                                    <col style="width: 18%;">
                                                    <col style="width: 14%;">
                                                    <col style="width: 15%;">
                                                    <col style="width: 8%;">
                                                    <col style="width: 10%;">
                                                    <col style="width: 7%;">
                                                    <col style="width: 15%;">
                                                </colgroup>
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('app.documents.title_label') }}</th>
                                                        <th>{{ __('app.admin.applications.authority') }}</th>
                                                        <th>{{ __('app.admin.applications.current_delegate') }}</th>
                                                        <th>{{ __('app.admin.authority_escalations.response_window') }}</th>
                                                        <th>{{ __('app.final_decision.issued_at') }}</th>
                                                        <th>{{ __('app.applications.updated_at') }}</th>
                                                        <th>{{ __('app.applications.status') }}</th>
                                                        <th>{{ __('app.admin.applications.actions') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($authorityApprovals as $approval)
                                                        @php($approvalSignal = $authorityApprovalSignals[$approval->getKey()] ?? ['enabled' => false, 'label' => null, 'is_overdue' => false, 'is_escalated' => false, 'due_at' => null])
                                                        <tr>
                                                            <td>{{ $approval->note ?: __('app.dashboard.not_available') }}</td>
                                                            <td>{{ $approval->localizedAuthority() }}</td>
                                                            <td>{{ $approval->assignedTo?->displayName() ?? $sharedAuthorityInboxLabel }}</td>
                                                            <td>
                                                                <div class="authority-sla-badges">
                                                                    @if ($approvalSignal['label'])
                                                                        <span class="badge bg-{{ $approvalSignal['is_overdue'] ? 'danger' : 'secondary' }}">{{ $approvalSignal['label'] }}</span>
                                                                    @else
                                                                        <span class="badge bg-light text-dark">{{ __('app.admin.authority_escalations.unconfigured_badge') }}</span>
                                                                    @endif
                                                                    @if ($approvalSignal['is_escalated'])
                                                                        <span class="badge bg-dark">{{ __('app.admin.authority_escalations.escalated_badge') }}</span>
                                                                    @endif
                                                                </div>
                                                                @if ($approvalSignal['due_at'])
                                                                    <div class="small text-muted mt-1">{{ __('app.admin.authority_escalations.due_at_label', ['date' => $approvalSignal['due_at']->format('Y-m-d h:i A')]) }}</div>
                                                                @endif
                                                            </td>
                                                            <td>{{ optional($approval->decided_at)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                                            <td>{{ optional($approval->updated_at)->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</td>
                                                            <td><span class="badge bg-{{ $statusClass($approval->status) }}">{{ $approval->localizedStatus() }}</span></td>
                                                            <td>
                                                                @if ($canManageAuthorityApprovals && ($canApproveApplication || ! in_array($approval->status, ['approved', 'rejected'], true)))
                                                                    <div class="d-grid gap-3">
                                                                        <form method="POST" action="{{ route('admin.applications.approvals.update', [$application, $approval]) }}" class="d-grid gap-2">
                                                                            @csrf
                                                                            <select name="status" class="form-select form-select-sm">
                                                                                @foreach ($authorityApprovalStatuses as $approvalStatus)
                                                                                    <option value="{{ $approvalStatus }}" @selected($approval->status === $approvalStatus)>{{ __('app.approvals.statuses.'.$approvalStatus) }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                            <input name="note" type="text" class="form-control form-control-sm" value="{{ $approval->note }}" placeholder="{{ __('app.admin.applications.review_note') }}">
                                                                            <button class="btn btn-sm btn-outline-primary" type="submit">{{ __('app.approvals.update_action') }}</button>
                                                                        </form>

                                                                        @if ($canAssignReviewer && ($authorityApprovalDelegates->get($approval->getKey())?->isNotEmpty() ?? false))
                                                                            <form method="POST" action="{{ route('admin.applications.approvals.assign', [$application, $approval]) }}" class="d-grid gap-2 border-top pt-3">
                                                                                @csrf
                                                                                <select name="assigned_user_id" class="form-select form-select-sm">
                                                                                    <option value="">{{ $sharedAuthorityInboxLabel }}</option>
                                                                                    @foreach ($authorityApprovalDelegates->get($approval->getKey(), collect()) as $delegate)
                                                                                        <option value="{{ $delegate->getKey() }}" @selected($approval->assigned_user_id === $delegate->getKey())>{{ $delegate->displayName() }}</option>
                                                                                    @endforeach
                                                                                </select>
                                                                                <input name="assignment_note" type="text" class="form-control form-control-sm" value="{{ old('assignment_note') }}" placeholder="{{ __('app.admin.applications.authority_assignment_note') }}">
                                                                                <button class="btn btn-sm btn-outline-secondary" type="submit">{{ __('app.admin.applications.reassign_approval_action') }}</button>
                                                                            </form>
                                                                        @endif
                                                                    </div>
                                                                @else
                                                                    <span class="text-muted">{{ __('app.dashboard.not_available') }}</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="8">{{ __('app.applications.no_required_approvals') }}</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="table-responsive mt-4">
                                            <table id="basic-table-approval-audit" class="table table-striped mb-0 request-narrow-table" role="grid">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('app.admin.applications.authority') }}</th>
                                                        <th>{{ __('app.admin.applications.audit_action') }}</th>
                                                        <th>{{ __('app.admin.applications.audit_from') }}</th>
                                                        <th>{{ __('app.admin.applications.audit_to') }}</th>
                                                        <th>{{ __('app.admin.applications.review_note') }}</th>
                                                        <th>{{ __('app.admin.entities.reviewed_by') }}</th>
                                                        <th>{{ __('app.final_decision.issued_at') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($authorityAuditTrail as $auditEvent)
                                                        @php($auditType = data_get($auditEvent->metadata, 'type'))
                                                        <tr>
                                                            <td>{{ data_get($auditEvent->metadata, 'authority_label', __('app.dashboard.not_available')) }}</td>
                                                            <td>
                                                                @if ($auditType === 'authority_reassigned')
                                                                    {{ __('app.admin.applications.audit_actions.reassigned') }}
                                                                @elseif ($auditType === 'authority_escalated')
                                                                    {{ __('app.admin.applications.audit_actions.escalated') }}
                                                                @else
                                                                    {{ __('app.admin.applications.audit_actions.status_updated') }}
                                                                @endif
                                                            </td>
                                                            <td>{{ data_get($auditEvent->metadata, 'from_user_name', data_get($auditEvent->metadata, 'assigned_user_name', __('app.dashboard.not_available'))) }}</td>
                                                            <td>
                                                                @if ($auditType === 'authority_reassigned')
                                                                    {{ data_get($auditEvent->metadata, 'to_user_name', $sharedAuthorityInboxLabel) ?: $sharedAuthorityInboxLabel }}
                                                                @elseif ($auditType === 'authority_escalated')
                                                                    {{ __('app.admin.authority_escalations.escalated_badge') }}
                                                                @else
                                                                    {{ data_get($auditEvent->metadata, 'approval_status_label', __('app.dashboard.not_available')) }}
                                                                @endif
                                                            </td>
                                                            <td>{{ data_get($auditEvent->metadata, 'reason', $auditEvent->note ?: __('app.dashboard.not_available')) }}</td>
                                                            <td>{{ $auditEvent->user?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                                            <td>{{ $auditEvent->happened_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="7">{{ __('app.admin.applications.authority_audit_empty') }}</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="profile-correspondence" class="tab-pane fade">
                                @include('admin.applications.partials.official-letters', ['officialLetters' => $officialLetters, 'officialLetterApprovals' => $officialLetterApprovals])
                                @include('admin.applications.partials.correspondence', ['correspondences' => $correspondences])
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
