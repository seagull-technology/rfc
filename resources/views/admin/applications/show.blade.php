@php
    $title = $application->project_name;
    $breadcrumb = __('app.admin.navigation.applications');
    $statusClass = static fn (?string $status): string => match ($status) {
        'draft' => 'secondary',
        'submitted', 'pending_review', 'pending' => 'warning',
        'under_review', 'in_review' => 'info',
        'needs_clarification' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $metadata = $application->metadata ?? [];
    $requirements = data_get($metadata, 'requirements', []);
    $international = data_get($metadata, 'international', []);
    $rfcDecision = (array) data_get($metadata, 'rfc_decision', []);
    $rfcDecisionStatus = data_get($rfcDecision, 'status');
    $rfcFacilitationIssued = filled(data_get($rfcDecision, 'facilitation_issued_at'));
    $formattedBudget = $application->estimated_budget ? number_format((float) $application->estimated_budget, 2) : __('app.dashboard.not_available');
    $requiredApprovals = collect(data_get($requirements, 'required_approvals', []))
        ->map(fn ($approval) => __('app.applications.required_approval_options.'.$approval))
        ->join('، ') ?: __('app.applications.no_required_approvals');
    $translateOrFallback = static function (string $translationKey, string $fallback): string {
        $translated = __($translationKey);

        return $translated === $translationKey ? $fallback : $translated;
    };
    $formatFallback = static fn (?string $value): string => filled($value) ? str((string) $value)->replace('_', ' ')->title()->toString() : __('app.dashboard.not_available');
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
    $issuedOfficialLetters = $officialLetters
        ->where('status', 'issued')
        ->values();
    $officialLetterForApproval = static function ($approval) use ($issuedOfficialLetters) {
        return $issuedOfficialLetters
            ->filter(function ($letter) use ($approval): bool {
                if ((int) $letter->application_authority_approval_id === (int) $approval->getKey()) {
                    return true;
                }

                return $approval->entity_id !== null && (int) $letter->target_entity_id === (int) $approval->entity_id;
            })
            ->sortByDesc(fn ($letter): int => ($letter->issued_at ?? $letter->updated_at ?? $letter->created_at)?->timestamp ?? 0)
            ->first();
    };
    $rfcDecisionNote = data_get($rfcDecision, 'note') ?: $application->review_note;
    $rfcFacilitationIssuedAt = $asDate(data_get($rfcDecision, 'facilitation_issued_at'));
    $rfcDate = $rfcFacilitationIssuedAt
        ?? $asDate(data_get($rfcDecision, 'decided_at'))
        ?? $asDate($application->reviewed_at)
        ?? $asDate($application->submitted_at)
        ?? $asDate($application->created_at);
    $rfcTimelineStatus = match (true) {
        $rfcDecisionStatus === 'rejected', $application->status === 'rejected' => 'rejected',
        $rfcDecisionStatus === 'returned', $application->status === 'needs_clarification' => 'needs_clarification',
        $rfcDecisionStatus === 'accepted' || $rfcFacilitationIssuedAt !== null => 'approved',
        in_array($application->status, ['submitted', 'under_review'], true) => 'under_review',
        default => $application->status,
    };
    $rfcTimelineStatusLabel = match (true) {
        $rfcDecisionStatus === 'accepted' || $rfcFacilitationIssuedAt !== null => __('app.rfc_decision.statuses.accepted'),
        $rfcDecisionStatus === 'returned' => __('app.rfc_decision.statuses.returned'),
        $rfcDecisionStatus === 'rejected' => __('app.rfc_decision.statuses.rejected'),
        default => $application->localizedStatus(),
    };
    $rfcTimelineNote = match (true) {
        $rfcFacilitationIssuedAt !== null => __('app.rfc_decision.history.facilitation_issued'),
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
    $pendingApprovalsCount = $authorityApprovals->whereIn('status', ['pending', 'in_review'])->count();
    $officialBooksPrepared = $rfcFacilitationIssued || $officialLetters->isNotEmpty();
    $nextCheckpoint = match (true) {
        $application->status === 'needs_clarification' => __('app.admin_request_state.await_applicant_checkpoint'),
        $application->current_stage === 'rfc_facilitation' && ! $officialBooksPrepared => __('app.admin_request_state.facilitation_checkpoint'),
        $application->current_stage === 'rfc_facilitation' && $officialBooksPrepared && ! $application->authorityRoutingStarted() => __('app.admin_request_state.official_books_checkpoint'),
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
        $application->current_stage === 'rfc_facilitation' && $officialBooksPrepared && ! $application->authorityRoutingStarted() => __('app.admin_request_state.official_books_title'),
        $application->current_stage === 'rfc_facilitation' && ! $officialBooksPrepared => __('app.admin_request_state.facilitation_title'),
        default => __('app.admin_request_state.review_in_progress_title'),
    };
    $stateBody = match (true) {
        $application->status === 'needs_clarification' => __('app.admin_request_state.application_await_applicant_body'),
        $application->status === 'approved' => __('app.admin_request_state.application_approved_body'),
        $application->status === 'rejected' => __('app.admin_request_state.application_closed_body'),
        $application->canBeFinallyDecided() => __('app.admin_request_state.application_final_decision_body'),
        $pendingApprovalsCount > 0 => __('app.admin_request_state.application_waiting_authorities_body'),
        $application->current_stage === 'rfc_facilitation' && $officialBooksPrepared && ! $application->authorityRoutingStarted() => __('app.admin_request_state.application_official_books_body'),
        $application->current_stage === 'rfc_facilitation' && ! $officialBooksPrepared => __('app.admin_request_state.application_facilitation_body'),
        default => __('app.admin_request_state.application_review_in_progress_body'),
    };
    $rfcDecisionMaker = $rfcDecisionUsers->get((int) data_get($rfcDecision, 'decided_by_user_id'));
    $rfcDecisionIssuedBy = $rfcDecisionUsers->get((int) data_get($rfcDecision, 'facilitation_issued_by_user_id'));
    $rfcDecisionDate = filled(data_get($rfcDecision, 'decided_at')) ? \Illuminate\Support\Carbon::parse(data_get($rfcDecision, 'decided_at'))->format('Y-m-d H:i') : null;
    $rfcFacilitationIssuedDate = filled(data_get($rfcDecision, 'facilitation_issued_at')) ? \Illuminate\Support\Carbon::parse(data_get($rfcDecision, 'facilitation_issued_at'))->format('Y-m-d H:i') : null;
    $applicantAnnexSubmission = (array) data_get($metadata, 'applicant_annex_submission', []);
    $applicantAnnexHistory = $statusHistory->first(fn ($event): bool => $event->note === __('app.applications.history.annex_updated'));
    $hasApplicantAnnexSubmission = filled(data_get($applicantAnnexSubmission, 'submitted_at')) || $applicantAnnexHistory !== null;

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
    $canIssueFacilitationBook = $canReviewApplication
        && $rfcDecisionStatus === 'accepted'
        && ! $rfcFacilitationIssued
        && ! $application->authorityRoutingStarted();
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

        .admin-application-show-layout .admin-application-table-scroll {
            overflow-x: auto;
        }

        .admin-application-show-layout .admin-detail-table {
            table-layout: fixed;
            width: 100%;
        }

        .admin-application-show-layout .admin-documents-table {
            min-width: 960px;
        }

        .admin-application-show-layout .admin-annex-documents-table {
            min-width: 760px;
        }

        .admin-application-show-layout .admin-final-decision-table {
            min-width: 760px;
        }

        .admin-application-show-layout .admin-approval-audit-table {
            min-width: 920px;
        }

        .admin-application-show-layout .admin-official-letters-table {
            table-layout: fixed;
            width: 100%;
        }

        .admin-application-show-layout .admin-approval-demo-table {
            border-collapse: collapse;
            min-width: 1120px;
            table-layout: fixed;
            width: 100%;
        }

        [dir="rtl"] .admin-application-show-layout .admin-approval-table-scroll {
            direction: rtl;
        }

        [dir="rtl"] .admin-application-show-layout .admin-approval-table-scroll > .admin-approval-demo-table {
            direction: rtl;
        }

        .admin-application-show-layout .admin-approval-demo-table thead th {
            background: #e6e8ec;
            border-bottom: 3px solid #6f1d17;
            color: #111827;
            font-weight: 800;
            padding: 1rem;
        }

        .admin-application-show-layout .admin-approval-demo-table tbody tr:not(.approval-management-row) td {
            border: 0;
            padding: 1rem;
            vertical-align: middle;
            white-space: normal !important;
            word-break: normal;
        }

        .admin-application-show-layout .admin-approval-demo-table th,
        .admin-application-show-layout .admin-approval-demo-table td {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .admin-application-show-layout .admin-approval-demo-table.table-striped > tbody > tr:nth-of-type(odd):not(.approval-management-row) > * {
            --bs-table-accent-bg: #f2f3f6;
            background-color: #f2f3f6;
        }

        .admin-application-show-layout .admin-approval-demo-table.table-striped > tbody > tr:nth-of-type(even):not(.approval-management-row) > * {
            --bs-table-accent-bg: #fff;
            background-color: #fff;
        }

        .admin-application-show-layout .admin-approval-card > .card-header {
            padding-bottom: .25rem;
        }

        .admin-application-show-layout .admin-approval-card > .card-body {
            padding-top: .5rem;
        }

        .admin-application-show-layout .approval-model-title {
            color: #111827;
            font-weight: 800;
            line-height: 1.45;
            max-width: 100%;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .admin-application-show-layout .approval-model-cell .d-flex {
            min-width: 0;
            width: 100%;
        }

        .admin-application-show-layout .approval-model-cell .d-flex > div {
            min-width: 0;
            max-width: 100%;
        }

        .admin-application-show-layout .approval-model-icon {
            background: rgba(111, 29, 23, .08);
            flex: 0 0 2.5rem;
            height: 2.5rem;
            object-fit: contain;
            padding: .25rem;
            width: 2.5rem;
        }

        .admin-application-show-layout .approval-model-note {
            color: #8b96a8;
            font-size: .875rem;
            font-weight: 700;
            margin-top: .35rem;
        }

        .admin-application-show-layout .approval-status-line {
            align-items: center;
            display: inline-flex;
            font-weight: 800;
            gap: .4rem;
            justify-content: center;
            line-height: 1.45;
            white-space: normal;
        }

        .admin-application-show-layout .approval-status-line i {
            font-size: 1.25rem;
        }

        .admin-application-show-layout .approval-status-line.is-approved,
        .admin-application-show-layout .approval-status-line.is-approved_with_book {
            color: #198754;
        }

        .admin-application-show-layout .approval-status-line.is-in_review,
        .admin-application-show-layout .approval-status-line.is-pending {
            color: #b7791f;
        }

        .admin-application-show-layout .approval-status-line.is-rejected {
            color: #dc3545;
        }

        .admin-application-show-layout .approval-management-row > td {
            background: transparent !important;
            border: 0;
            padding: 0 !important;
        }

        .admin-application-show-layout .approval-management-panel {
            background: #fff;
            border: 1px solid rgba(17, 24, 39, .08);
            box-shadow: 0 12px 28px rgba(17, 24, 39, .08);
            margin: -.25rem auto .75rem;
            padding: 1rem;
            width: 100%;
        }

        .admin-application-show-layout .approval-management-meta {
            color: #6c757d;
            font-size: .875rem;
            font-weight: 700;
        }

        .admin-application-show-layout .approval-authority-cell {
            font-weight: 700;
            line-height: 1.45;
            overflow-wrap: anywhere;
            white-space: normal !important;
        }

        .admin-application-show-layout .approval-date-cell {
            font-weight: 700;
            white-space: nowrap !important;
        }

        .admin-application-show-layout .approval-status-cell {
            text-align: center;
        }

        .admin-application-show-layout .approval-status-trigger {
            background: transparent;
            border: 0;
            padding: 0;
            text-align: inherit;
        }

        .admin-application-show-layout .approval-audit-shell {
            margin-inline: auto;
            width: 88%;
        }

        .admin-application-show-layout .admin-official-letters-table {
            min-width: 1000px;
        }

        .admin-application-show-layout .admin-approval-demo-table th,
        .admin-application-show-layout .admin-approval-demo-table td,
        .admin-application-show-layout .admin-official-letters-table th,
        .admin-application-show-layout .admin-official-letters-table td,
        .admin-application-show-layout .admin-detail-table th,
        .admin-application-show-layout .admin-detail-table td {
            vertical-align: top;
            white-space: normal;
            word-break: break-word;
        }

        .admin-application-show-layout .admin-official-letter-action-cell {
            text-align: center;
        }

        .admin-application-show-layout .official-letter-directory {
            display: grid;
            gap: 1rem;
        }

        .admin-application-show-layout .official-letter-entity-card {
            border: 0;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
            padding: 1.25rem;
        }

        .admin-application-show-layout .official-letter-entity-avatar {
            background: rgba(13, 110, 253, .08);
            border-radius: 999px;
            height: 50px;
            object-fit: contain;
            padding: .25rem;
            width: 50px;
        }

        .admin-application-show-layout .official-letter-list {
            display: grid;
            gap: .5rem;
            padding: .5rem;
        }

        .admin-application-show-layout .official-letter-list-item {
            align-items: center;
            border: 0;
            display: grid;
            gap: .75rem;
            grid-template-areas:
                "main actions"
                "meta actions";
            grid-template-columns: minmax(0, 1fr) auto;
            padding: .9rem 1rem;
        }

        .admin-application-show-layout .official-letter-open-button {
            align-items: center;
            background: transparent;
            border: 0;
            color: inherit;
            display: inline-flex;
            gap: .75rem;
            grid-area: main;
            min-width: 0;
            padding: 0;
            text-align: start;
        }

        .admin-application-show-layout .official-letter-envelope {
            flex: 0 0 28px;
            height: 28px;
            object-fit: contain;
            width: 28px;
        }

        .admin-application-show-layout .official-letter-list-title {
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .admin-application-show-layout .official-letter-list-meta,
        .admin-application-show-layout .official-letter-list-actions {
            align-items: center;
            display: inline-flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .admin-application-show-layout .official-letter-list-meta {
            grid-area: meta;
            justify-content: flex-start;
        }

        .admin-application-show-layout .official-letter-list-actions {
            grid-area: actions;
            justify-content: flex-end;
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

            .admin-application-show-layout .official-letter-list-item {
                grid-template-areas:
                    "main"
                    "meta"
                    "actions";
                grid-template-columns: 1fr;
            }

            .admin-application-show-layout .official-letter-list-meta,
            .admin-application-show-layout .official-letter-list-actions {
                justify-content: flex-start;
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
                        <a class="nav-link" data-bs-toggle="tab" href="#profile-correspondence" role="tab">{{ __('app.official_letters.tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile-wrap-report" role="tab">{{ __('app.wrap_report.tab') }}</a>
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
                                            @php
                                                $eventColor = $statusClass($event['status']);
                                            @endphp
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
                                                    @if (filled($event['meta']))
                                                        <p class="mb-0 text-muted timeline-note">{{ $event['meta'] }}</p>
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
                                        <a class="btn btn-outline-secondary" data-bs-toggle="tab" href="#profile-correspondence" role="tab">{{ __('app.admin_request_state.open_official_letters') }}</a>
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
                                <div class="card admin-approval-card">
                                    <div class="card-header">
                                        <div class="header-title">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.applications.project_information') }}</span>
                                            </h2>
                                        </div>
                                    </div>
                                    <div class="card-body application-detail-list">
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.project_name') }}:</span><span class="ms-2">{{ $application->project_name }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.project_nationality') }}:</span><span class="ms-2">{{ \App\Models\Nationality::labelFor($application->project_nationality) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.work_category') }}:</span><span class="ms-2">{{ \App\Models\WorkCategory::labelFor($application->work_category) }}</span></div>
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.release_method') }}:</span><span class="ms-2">{{ \App\Models\ReleaseMethod::labelFor($application->release_method) }}</span></div>
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
                                        <div class="mb-1"><span class="fw-600">{{ __('app.applications.director_nationality') }}:</span><span class="ms-2">{{ \App\Models\Nationality::labelFor(data_get($metadata, 'director.director_nationality')) }}</span></div>
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
                                @include('applications.partials.documents-applicant', ['documents' => $documents])
                            </div>

                            <div id="profile-Annex" class="tab-pane fade">
                                <div class="card annex-card">
                                    <div class="card-body">
                                        <div class="form-card text-start pb-4">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.admin.applications.annex_title') }}</span>
                                            </h2>

                                            @if ($hasApplicantAnnexSubmission)
                                                <div class="mt-4">
                                                    <div class="fw-600 mb-2">{{ __('app.applications.required_approvals') }}</div>
                                                    <div>{{ $requiredApprovals }}</div>
                                                    <div class="fw-600 mt-4 mb-2">{{ __('app.applications.supporting_notes') }}</div>
                                                    <div>{{ data_get($requirements, 'supporting_notes', __('app.applications.annex_empty_state')) }}</div>
                                                    <div class="border-top mt-4 pt-4">
                                                        @include('applications.partials.annex-summary', ['application' => $application, 'tableClass' => 'table table-striped mb-0'])
                                                    </div>
                                                </div>
                                            @else
                                                <div class="alert alert-light border mt-4 mb-0">{{ __('app.admin.applications.annex_empty_state') }}</div>
                                            @endif
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

                                @if (! $canReviewApplication && ($rfcDecisionStatus || $rfcFacilitationIssued))
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row g-3">
                                                @if ($rfcDecisionStatus)
                                                    <div class="col-md-6">
                                                        <div class="admin-state-meta">
                                                            <span class="admin-state-meta-label">{{ __('app.rfc_decision.recorded_by') }}</span>
                                                            <div class="fw-semibold">{{ $rfcDecisionMaker?->displayName() ?? $application->reviewedBy?->displayName() ?? __('app.dashboard.not_available') }}</div>
                                                            @if ($rfcDecisionDate)
                                                                <div class="text-muted small mt-1">{{ $rfcDecisionDate }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif
                                                @if ($rfcFacilitationIssued)
                                                    <div class="col-md-6">
                                                        <div class="admin-state-meta">
                                                            <span class="admin-state-meta-label">{{ __('app.rfc_decision.facilitation_issued_by') }}</span>
                                                            <div class="fw-semibold">{{ $rfcDecisionIssuedBy?->displayName() ?? __('app.dashboard.not_available') }}</div>
                                                            @if ($rfcFacilitationIssuedDate)
                                                                <div class="text-muted small mt-1">{{ $rfcFacilitationIssuedDate }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
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
                                            @if ($rfcDecisionStatus || $rfcFacilitationIssued)
                                                <div class="row g-3 mb-4">
                                                    @if ($rfcDecisionStatus)
                                                        <div class="col-md-6">
                                                            <div class="admin-state-meta">
                                                                <span class="admin-state-meta-label">{{ __('app.rfc_decision.recorded_by') }}</span>
                                                                <div class="fw-semibold">{{ $rfcDecisionMaker?->displayName() ?? $application->reviewedBy?->displayName() ?? __('app.dashboard.not_available') }}</div>
                                                                @if ($rfcDecisionDate)
                                                                    <div class="text-muted small mt-1">{{ $rfcDecisionDate }}</div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endif
                                                    @if ($rfcFacilitationIssued)
                                                        <div class="col-md-6">
                                                            <div class="admin-state-meta">
                                                                <span class="admin-state-meta-label">{{ __('app.rfc_decision.facilitation_issued_by') }}</span>
                                                                <div class="fw-semibold">{{ $rfcDecisionIssuedBy?->displayName() ?? __('app.dashboard.not_available') }}</div>
                                                                @if ($rfcFacilitationIssuedDate)
                                                                    <div class="text-muted small mt-1">{{ $rfcFacilitationIssuedDate }}</div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                            <form method="POST" action="{{ route('admin.applications.review', $application) }}" class="row g-3">
                                                @csrf
                                                <div class="col-12">
                                                    <label for="decision" class="form-label flex-grow-1">{{ __('app.admin.applications.review_decision') }}</label>
                                                    <select id="decision" name="decision" class="form-control bg-white" data-rfc-decision-select required>
                                                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                                        @foreach (['accepted', 'returned', 'rejected'] as $decision)
                                                            <option value="{{ $decision }}" @selected(old('decision', $rfcDecisionStatus) === $decision)>{{ __('app.rfc_decision.statuses.'.$decision) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-12" data-rfc-decision-note-wrap>
                                                    <label class="form-label flex-grow-1" for="note">{{ __('app.admin.applications.review_note') }}</label>
                                                    <textarea id="note" name="note" rows="6" class="form-control mt-2 bg-white" data-rfc-decision-note>{{ old('note', data_get($rfcDecision, 'note', $application->review_note)) }}</textarea>
                                                </div>
                                                <div class="col-12">
                                                    <button class="btn btn-danger" type="submit">{{ __('app.admin.applications.review_submit') }}</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    @if ($canIssueFacilitationBook)
                                        <div class="card">
                                            <div class="card-body">
                                                <form method="POST" action="{{ route('admin.applications.issue-facilitation-letter', $application) }}" class="d-grid gap-2">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success btn-lg">{{ __('app.rfc_decision.issue_facilitation_action') }}</button>
                                                </form>
                                            </div>
                                        </div>
                                    @elseif ($rfcFacilitationIssued)
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="alert alert-success mb-0">{{ __('app.rfc_decision.facilitation_issued') }}</div>
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                @if ($officialBooksPrepared)
                                    @include('admin.applications.partials.official-letters', [
                                        'officialLetters' => $officialLetters,
                                        'officialLetterApprovals' => $officialLetterApprovals,
                                        'mode' => 'issue',
                                    ])
                                @endif

                                @if ($canApproveApplication)
                                    @include('admin.applications.partials.final-decision')
                                @endif
                            </div>

                            <div id="profile-activity2" class="tab-pane fade">
                                <div class="card admin-approval-card">
                                    <div class="card-header">
                                        <div class="iq-header-title">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.admin.applications.approvals_title') }}</span>
                                            </h2>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive rounded py-3 admin-application-table-scroll admin-approval-table-scroll">
	                                            <table id="basic-table-approvals" class="table table-striped mb-0 request-narrow-table admin-approval-demo-table" role="grid">
		                                                <colgroup>
		                                                    <col style="width: 42%;">
		                                                    <col style="width: 24%;">
		                                                    <col style="width: 13%;">
		                                                    <col style="width: 13%;">
		                                                    <col style="width: 8%;">
		                                                </colgroup>
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('app.admin.applications.approval_model_column') }}</th>
                                                        <th>{{ __('app.admin.applications.authority') }}</th>
                                                        <th>{{ __('app.admin.applications.approval_issue_date') }}</th>
                                                        <th>{{ __('app.admin.applications.approval_last_movement') }}</th>
                                                        <th>{{ __('app.applications.status') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($authorityApprovals as $approval)
                                                        @php
                                                            $approvalSignal = $authorityApprovalSignals[$approval->getKey()] ?? ['enabled' => false, 'label' => null, 'is_overdue' => false, 'is_escalated' => false, 'due_at' => null];
                                                            $approvalLetter = $officialLetterForApproval($approval);
                                                            $approvalModelTitle = $approvalLetter?->subject
                                                                ?: ($approval->authority_code
                                                                    ? $translateOrFallback('app.applications.required_approval_options.'.$approval->authority_code, $approval->localizedAuthority())
                                                                    : $approval->localizedAuthority());
                                                            $approvalIssueDate = $asDate($approvalLetter?->issued_at ?? $approvalLetter?->letter_date);
                                                            $approvalLastMovement = collect([
                                                                $approvalLetter?->updated_at,
                                                                $approvalLetter?->issued_at,
                                                                $approval->response_attachment_uploaded_at,
                                                                $approval->decided_at,
                                                                $approval->assigned_at,
                                                                $approval->updated_at,
                                                                $approval->created_at,
                                                            ])
                                                                ->map(fn ($value) => $asDate($value))
                                                                ->filter()
                                                                ->sortByDesc(fn ($date) => $date->timestamp)
                                                                ->first();
                                                            $approvalStatusKey = match ($approval->status) {
                                                                'approved' => $approval->response_attachment_path ? 'approved_with_book' : 'approved',
                                                                'rejected' => 'rejected',
                                                                'in_review' => 'in_review',
                                                                default => 'pending',
                                                            };
                                                            $approvalStatusIcon = match ($approvalStatusKey) {
                                                                'approved', 'approved_with_book' => 'ph-fill ph-check-circle',
                                                                'rejected' => 'ph-fill ph-x-circle',
                                                                'in_review' => 'ph-fill ph-clock',
                                                                default => 'ph-fill ph-hourglass',
                                                            };
	                                                            $canManageThisApproval = $canManageAuthorityApprovals && ($canApproveApplication || ! in_array($approval->status, ['approved', 'rejected'], true));
	                                                            $hasApprovalDetails = $canManageThisApproval || $approval->response_attachment_path || $approvalSignal['label'] || $approvalSignal['is_escalated'];
	                                                            $approvalManagementId = 'approval-management-'.$approval->getKey();
	                                                        @endphp
	                                                        <tr>
	                                                            <td class="approval-model-cell">
	                                                                <div class="d-flex align-items-center">
	                                                                    <img class="rounded img-fluid avatar-40 me-3 bg-primary-subtle approval-model-icon" src="{{ asset('images/clapboard.png') }}" alt="" aria-hidden="true">
	                                                                    <div>
	                                                                        <h6 class="approval-model-title mb-0">{{ $approvalModelTitle }}</h6>
	                                                                        @if ($approval->note)
	                                                                            <div class="approval-model-note">{{ $approval->note }}</div>
	                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="approval-authority-cell">{{ $approval->localizedAuthority() }}</td>
                                                            <td class="approval-date-cell">
                                                                {{ $approvalIssueDate?->format('Y-m-d') ?: __('app.dashboard.not_available') }}
                                                            </td>
	                                                            <td class="approval-date-cell">{{ $approvalLastMovement?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
	                                                            <td class="approval-status-cell">
	                                                                @if ($hasApprovalDetails)
	                                                                    <button class="approval-status-trigger" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $approvalManagementId }}" aria-expanded="false" aria-controls="{{ $approvalManagementId }}" title="{{ __('app.admin.applications.approval_manage_action') }}">
	                                                                        <span class="approval-status-line is-{{ $approvalStatusKey }}">
	                                                                            <i class="{{ $approvalStatusIcon }}" aria-hidden="true"></i>
	                                                                            {{ __('app.admin.applications.approval_status_display.'.$approvalStatusKey) }}
	                                                                        </span>
	                                                                    </button>
	                                                                @else
	                                                                    <span class="approval-status-line is-{{ $approvalStatusKey }}">
	                                                                        <i class="{{ $approvalStatusIcon }}" aria-hidden="true"></i>
	                                                                        {{ __('app.admin.applications.approval_status_display.'.$approvalStatusKey) }}
	                                                                    </span>
	                                                                @endif

	                                                                <span class="visually-hidden">
	                                                                    @if ($approvalSignal['label'])
	                                                                        {{ __('app.admin.authority_escalations.response_window') }} {{ $approvalSignal['label'] }}
	                                                                    @endif
	                                                                    @if ($approvalSignal['is_escalated'])
	                                                                        {{ __('app.admin.authority_escalations.escalated_badge') }}
	                                                                    @endif
	                                                                    @if ($approval->response_attachment_path)
	                                                                        {{ __('app.approvals.response_book') }} {{ __('app.approvals.response_book_download') }} {{ $approval->response_attachment_name ?: __('app.approvals.response_book') }}
	                                                                    @endif
	                                                                </span>
	                                                            </td>
	                                                        </tr>

	                                                        @if ($hasApprovalDetails)
	                                                            <tr class="approval-management-row">
	                                                                <td colspan="5">
	                                                                    <div class="collapse" id="{{ $approvalManagementId }}">
                                                                        <div class="approval-management-panel">
                                                                            <div class="row g-4 align-items-start">
                                                                                <div class="col-lg-4">
                                                                                    <h6 class="mb-3">{{ __('app.admin.applications.approval_management_title') }}</h6>
                                                                                    <div class="approval-management-meta mb-2">{{ __('app.admin.applications.current_delegate') }}: {{ $approval->assignedTo?->displayName() ?? $sharedAuthorityInboxLabel }}</div>
                                                                                    <div class="authority-sla-badges mb-2">
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
                                                                                        <div class="small text-muted">{{ __('app.admin.authority_escalations.due_at_label', ['date' => $approvalSignal['due_at']->format('Y-m-d h:i A')]) }}</div>
                                                                                    @endif
	                                                                                    @if ($approval->response_attachment_path)
	                                                                                        <div class="small text-muted mt-2 text-break">{{ $approval->response_attachment_name ?: __('app.approvals.response_book') }}</div>
	                                                                                        <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('admin.applications.approvals.attachment.download', [$application, $approval]) }}">
	                                                                                            <i class="ph ph-download-simple me-1"></i>{{ __('app.approvals.response_book_download') }}
	                                                                                        </a>
	                                                                                    @else
	                                                                                        <div class="small text-muted mt-2">{{ __('app.approvals.response_book_none') }}</div>
	                                                                                    @endif
                                                                                </div>

	                                                                                <div class="col-lg-4">
	                                                                                    @if ($canManageThisApproval)
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
	                                                                                    @endif
	                                                                                </div>

	                                                                                <div class="col-lg-4">
	                                                                                    @if ($canManageThisApproval && $canAssignReviewer && ($authorityApprovalDelegates->get($approval->getKey())?->isNotEmpty() ?? false))
	                                                                                        <form method="POST" action="{{ route('admin.applications.approvals.assign', [$application, $approval]) }}" class="d-grid gap-2">
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
	                                                                                    @elseif ($canManageThisApproval)
	                                                                                        <div class="alert alert-light mb-0">{{ __('app.admin.applications.authority_assignment_unavailable') }}</div>
	                                                                                    @endif
	                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endif
                                                    @empty
                                                        <tr>
                                                            <td colspan="5">{{ __('app.applications.no_required_approvals') }}</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="approval-audit-shell mt-4">
                                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#authority-approval-audit" aria-expanded="false" aria-controls="authority-approval-audit">
                                                {{ __('app.admin.applications.approval_audit_toggle') }}
                                            </button>

                                            <div class="collapse mt-3" id="authority-approval-audit">
                                                <div class="table-responsive rounded py-3 admin-application-table-scroll">
                                                    <table id="basic-table-approval-audit" class="table table-striped mb-0 admin-detail-table admin-approval-audit-table" role="grid">
                                                        <colgroup>
                                                            <col style="width: 17%;">
                                                            <col style="width: 14%;">
                                                            <col style="width: 16%;">
                                                            <col style="width: 16%;">
                                                            <col style="width: 17%;">
                                                            <col style="width: 10%;">
                                                            <col style="width: 10%;">
                                                        </colgroup>
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
                                                                @php
                                                                    $auditType = data_get($auditEvent->metadata, 'type');
                                                                @endphp
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
                                </div>
                            </div>

                            <div id="profile-correspondence" class="tab-pane fade">
                                @include('admin.applications.partials.official-letters', [
                                    'officialLetters' => $officialLetters,
                                    'officialLetterApprovals' => $officialLetterApprovals,
                                    'mode' => 'directory',
                                ])
                            </div>

                            <div id="profile-wrap-report" class="tab-pane fade">
                                @include('admin.applications.partials.wrap-report', [
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
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-rfc-decision-select]').forEach(function (select) {
                const form = select.closest('form');
                const noteWrap = form ? form.querySelector('[data-rfc-decision-note-wrap]') : null;
                const note = form ? form.querySelector('[data-rfc-decision-note]') : null;
                const syncNoteVisibility = function () {
                    const needsNote = ['returned', 'rejected'].includes(select.value);

                    if (noteWrap) {
                        noteWrap.classList.toggle('d-none', !needsNote);
                    }

                    if (note) {
                        note.required = needsNote;
                    }
                };

                select.addEventListener('change', syncNoteVisibility);
                syncNoteVisibility();
            });
        });
    </script>
@endpush
