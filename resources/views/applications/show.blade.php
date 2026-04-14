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
    $latestCorrespondence = $correspondences->first();
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

@section('page_layout_class', 'applicant-request-show-layout')

@push('styles')
    <style>
        .applicant-request-show-layout .request-actions-card {
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .applicant-request-show-layout .profile-tab {
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .applicant-request-show-layout .profile-tab .nav-link {
            white-space: nowrap;
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
    </style>
@endpush

@section('content')
    <div class="card view-request-bg">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">
                <div class="d-flex align-items-center">
                    <div class="profile-img position-relative me-3 mb-3 mb-lg-0 profile-logo profile-logo1">
                        <img src="{{ asset('images/OIP.jpeg') }}" alt="User-Profile" class="theme-color-default-img img-fluid rounded-pill avatar-100" loading="lazy">
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
                </ul>
            </div>
        </div>
    </div>

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
                                <li>
                                    <div class="timeline-dots timeline-dot1 border-{{ $statusBadgeClass }} text-{{ $statusBadgeClass }}"></div>
                                    <h6 class="float-left mb-1 fw-semibold">{{ __('app.workflow.current_stage') }}</h6>
                                    <small class="float-right mt-1">{{ optional($application->updated_at)->format('Y-m-d') }}</small>
                                    <div class="d-inline-block w-100">
                                        <p class="mb-0">{{ $application->localizedStage() }}</p>
                                        <p class="mb-0">{{ $application->localizedStatus() }}</p>
                                    </div>
                                </li>

                                @foreach ($authorityApprovals as $approval)
                                    @php($approvalColor = $timelineColor($approval->status))
                                    <li>
                                        <div class="timeline-dots timeline-dot1 border-{{ $approvalColor }} text-{{ $approvalColor }}"></div>
                                        <h6 class="float-left mb-1 fw-semibold">{{ $approval->localizedAuthority() }}</h6>
                                        @if ($approval->decided_at)
                                            <small class="float-right mt-1">{{ $approval->decided_at->format('Y-m-d') }}</small>
                                        @endif
                                        <div class="d-inline-block w-100">
                                            <p class="mb-0 text-{{ $approvalColor }}">{{ $approval->localizedStatus() }}</p>
                                            @if ($approval->note)
                                                <p class="mb-0">{{ $approval->note }}</p>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach

                                @foreach ($statusHistory as $event)
                                    @php($historyColor = $timelineColor($event->status))
                                    <li>
                                        <div class="timeline-dots timeline-dot1 border-{{ $historyColor }} text-{{ $historyColor }}"></div>
                                        <h6 class="float-left mb-1 fw-semibold">{{ $event->localizedStatus() }}</h6>
                                        @if ($event->happened_at)
                                            <small class="float-right mt-1">{{ $event->happened_at->format('Y-m-d') }}</small>
                                        @endif
                                        <div class="d-inline-block w-100">
                                            @if ($event->note)
                                                <p class="mb-0">{{ $event->note }}</p>
                                            @endif
                                            @if ($event->user)
                                                <p class="mb-0 text-muted">{{ $event->user->displayName() }}</p>
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
                        </div>
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mt-4">
                            <div class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-outline-secondary" data-bs-toggle="tab" href="#profile-approvals" role="tab" aria-selected="false">{{ __('app.request_state.open_correspondence') }}</a>
                                @if ($application->canBeEditedByApplicant())
                                    <a class="btn btn-light" href="{{ route('applications.edit', $application) }}">{{ __('app.applications.edit_action') }}</a>
                                @endif
                                @if ($application->canBeSubmittedByApplicant())
                                    <form method="POST" action="{{ route('applications.submit', $application) }}">
                                        @csrf
                                        <button class="btn btn-danger" type="submit">{{ __('app.applications.submit_action') }}</button>
                                    </form>
                                @endif
                            </div>
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
                                <div class="mb-1"><span class="fw-600">{{ __('app.applications.project_nationality') }}:</span><span class="ms-2">{{ __('app.applications.project_nationalities.'.$application->project_nationality) }}</span></div>
                                <div class="mb-1"><span class="fw-600">{{ __('app.applications.work_category') }}:</span><span class="ms-2">{{ __('app.applications.work_categories.'.$application->work_category) }}</span></div>
                                <div class="mb-0"><span class="fw-600">{{ __('app.applications.release_method') }}:</span><span class="ms-2">{{ __('app.applications.release_methods.'.$application->release_method) }}</span></div>
                            </div>
                        </div>

                        <div class="card request-section-card">
                            <div class="card-header">
                                <div class="header-title">
                                    <h2 class="episode-playlist-title wp-heading-inline">
                                        <span class="position-relative">{{ __('app.applications.producer_information') }}</span>
                                    </h2>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-1"><span class="fw-600">{{ __('app.applications.producer_name') }}:</span><span class="ms-2">{{ data_get($producer, 'producer_name', __('app.dashboard.not_available')) }}</span></div>
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
                                <div class="mb-1"><span class="fw-600">{{ __('app.applications.planned_start_date') }}:</span><span class="ms-2">{{ optional($application->planned_start_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                                <div class="mb-0"><span class="fw-600">{{ __('app.applications.planned_end_date') }}:</span><span class="ms-2">{{ optional($application->planned_end_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
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
                                    <span class="position-relative">{{ __('app.applications.summary_title') }}</span>
                                </h2>
                            </div>
                            <div class="card-body">
                                <p class="mb-0" style="line-height: 1.8;">{{ $application->project_summary }}</p>
                            </div>
                        </div>

                        <div class="card request-section-card">
                            <div class="card-header">
                                <h2 class="episode-playlist-title wp-heading-inline">
                                    <span class="position-relative">{{ __('app.applications.budget_title') }}</span>
                                </h2>
                            </div>
                            <div class="card-body">
                                <div class="mb-0"><span class="fw-600">{{ __('app.applications.estimated_budget') }}:</span><span class="ms-2">{{ $formattedBudget }}</span></div>
                            </div>
                        </div>
                    </div>

                    <div id="profile-documents" class="tab-pane fade">
                        @include('applications.partials.documents-applicant', ['documents' => $documents])
                    </div>

                    <div id="profile-annex" class="tab-pane fade">
                        <div class="card">
                            <div class="card-header">
                                <div class="header-title">
                                    <h2 class="episode-playlist-title wp-heading-inline">
                                        <span class="position-relative">{{ __('app.applications.annex_title') }}</span>
                                    </h2>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <span class="fw-600 d-block mb-2">{{ __('app.applications.required_approvals') }}</span>
                                    <div>{{ $requiredApprovals }}</div>
                                </div>
                                <div class="mb-4">
                                    <span class="fw-600 d-block mb-2">{{ __('app.applications.supporting_notes') }}</span>
                                    <div>{{ data_get($requirements, 'supporting_notes', __('app.applications.annex_empty_state')) }}</div>
                                </div>
                                @if ($application->review_note)
                                    <div class="alert alert-warning mb-0">{{ $application->review_note }}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div id="profile-approvals" class="tab-pane fade">
                        <div class="row g-3 mb-4">
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

                        <div class="card">
                            <div class="card-header">
                                <div class="header-title">
                                    <h2 class="episode-playlist-title wp-heading-inline">
                                        <span class="position-relative">{{ __('app.applications.approvals_title') }}</span>
                                    </h2>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table mb-0">
                                        <thead>
                                            <tr>
                                                <th>{{ __('app.applications.authority') }}</th>
                                                <th>{{ __('app.applications.status') }}</th>
                                                <th>{{ __('app.applications.decision_note') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($authorityApprovals as $approval)
                                                <tr>
                                                    <td>{{ $approval->localizedAuthority() }}</td>
                                                    <td>{{ $approval->localizedStatus() }}</td>
                                                    <td>{{ $approval->note ?: __('app.dashboard.not_available') }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3">{{ __('app.applications.no_required_approvals') }}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        @include('applications.partials.correspondence-applicant', ['correspondences' => $correspondences])
                        @include('applications.partials.final-decision-applicant')
                    </div>
                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
