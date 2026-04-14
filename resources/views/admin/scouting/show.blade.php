@php
    $title = $requestRecord->project_name;
    $breadcrumb = __('app.admin.navigation.scouting_requests');
    $metadata = $requestRecord->metadata ?? [];
    $producer = data_get($metadata, 'producer', []);
    $responsiblePerson = data_get($metadata, 'responsible_person', []);
    $production = data_get($metadata, 'production', []);
    $locations = data_get($metadata, 'locations', []);
    $crew = data_get($metadata, 'crew', []);
    $statusClass = static fn (?string $status): string => match ($status) {
        'draft' => 'secondary',
        'submitted' => 'warning',
        'under_review' => 'info',
        'needs_clarification' => 'danger',
        'approved' => 'success',
        'rejected' => 'dark',
        default => 'secondary',
    };
    $latestCorrespondence = $correspondences->first();
    $stateTitle = match ($requestRecord->status) {
        'needs_clarification' => __('app.admin_request_state.await_applicant_title'),
        'approved' => __('app.admin_request_state.approved_title'),
        'rejected' => __('app.admin_request_state.closed_title'),
        default => __('app.admin_request_state.review_in_progress_title'),
    };
    $stateBody = match ($requestRecord->status) {
        'needs_clarification' => __('app.admin_request_state.scouting_await_applicant_body'),
        'approved' => __('app.admin_request_state.scouting_approved_body'),
        'rejected' => __('app.admin_request_state.scouting_closed_body'),
        default => __('app.admin_request_state.scouting_review_in_progress_body'),
    };
    $nextCheckpoint = match (true) {
        $requestRecord->status === 'needs_clarification' => __('app.admin_request_state.await_applicant_checkpoint'),
        in_array($requestRecord->status, ['submitted', 'under_review'], true) => __('app.admin_request_state.review_submission_checkpoint'),
        default => __('app.admin_request_state.monitor_checkpoint'),
    };
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

        .admin-application-show-layout .request-section-card .card-body > div:last-child,
        .admin-application-show-layout .request-section-card .card-body > p:last-child {
            margin-bottom: 0;
        }

        .admin-application-show-layout .profile-tab {
            gap: .25rem;
        }

        .admin-application-show-layout .profile-tab .nav-link {
            white-space: nowrap;
        }

        .admin-application-show-layout .table thead th,
        .admin-application-show-layout .table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .admin-application-show-layout .application-hero-card {
            margin: 0 1rem 1.5rem;
        }

        .admin-application-show-layout .application-hero-card .card-body {
            padding: 1.5rem;
        }

        .admin-application-show-layout .timeline-note {
            overflow-wrap: anywhere;
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

        @media (max-width: 991.98px) {
            .admin-application-show-layout .application-hero-card {
                margin: 0 .75rem 1rem;
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
                        <h4 class="me-2 h4 text-white">{{ $requestRecord->entity?->displayName() ?? __('app.dashboard.not_available') }}</h4>
                        <h6 class="me-2 text-white">{{ $requestRecord->project_name }}</h6>
                        <div class="text-white">{{ $requestRecord->code }}</div>
                    </div>
                </div>
                <ul class="d-flex nav nav-pills mb-0 text-center profile-tab" data-toggle="slider-tab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active show" data-bs-toggle="tab" href="#profile-request" role="tab">{{ __('app.admin.scouting.request_tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile-decision" role="tab">{{ __('app.admin.scouting.review_title') }}</a>
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
                <div class="card-header">
                    <div class="header-title">
                        <h2 class="episode-playlist-title wp-heading-inline">
                            <span class="position-relative">{{ __('app.applications.status_timeline_title') }}</span>
                        </h2>
                    </div>
                </div>
                <div class="card-body">
                    <div class="iq-timeline0 m-0 d-flex align-items-center justify-content-between position-relative">
                        <ul class="list-inline p-0 m-0">
                            @forelse ($statusHistory as $event)
                                @php($eventColor = $statusClass($event->status))
                                <li>
                                    <div class="timeline-dots timeline-dot1 border-{{ $eventColor }} text-{{ $eventColor }}"></div>
                                    <h6 class="float-left mb-1 fw-semibold">{{ $event->user?->displayName() ?? __('app.roles.rfc_admin') }}</h6>
                                    @if ($event->happened_at)
                                        <small class="float-right mt-1">{{ $event->happened_at->format('Y-m-d') }}</small>
                                    @endif
                                    <div class="d-inline-block w-100">
                                        <p class="mb-0 text-{{ $eventColor }}">{{ $event->localizedStatus() }}</p>
                                        @if (filled($event->note))
                                            <p class="mb-0 timeline-note">{{ $event->note }}</p>
                                        @endif
                                    </div>
                                </li>
                            @empty
                                <li class="text-muted">{{ __('app.admin.scouting.empty_history') }}</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

                        <div class="card">
                <div class="card-header">
                    <div class="header-title">
                        <h2 class="episode-playlist-title wp-heading-inline">
                            <span class="position-relative">{{ __('app.admin.scouting.summary_title') }}</span>
                        </h2>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-2"><small class="text-muted d-block">{{ __('app.applications.status') }}</small>{{ $requestRecord->localizedStatus() }}</div>
                    <div class="mb-2"><small class="text-muted d-block">{{ __('app.workflow.current_stage') }}</small>{{ $requestRecord->localizedStage() }}</div>
                    <div class="mb-2"><small class="text-muted d-block">{{ __('app.admin.scouting.applicant_entity') }}</small>{{ $requestRecord->entity?->displayName() ?? __('app.dashboard.not_available') }}</div>
                    <div class="mb-2"><small class="text-muted d-block">{{ __('app.admin.scouting.submitted_by') }}</small>{{ $requestRecord->submittedBy?->displayName() ?? __('app.dashboard.not_available') }}</div>
                    <div class="mb-0"><small class="text-muted d-block">{{ __('app.admin.scouting.submitted_at') }}</small>{{ optional($requestRecord->submitted_at ?? $requestRecord->created_at)->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div>
                </div>
            </div>
        </div>

                    <div class="col-lg-9">
                        <div class="card admin-state-card">
                <div class="card-body">
                    <div>
                        <span class="badge bg-{{ $statusClass($requestRecord->status) }}">{{ $requestRecord->localizedStatus() }}</span>
                        <span class="ms-2 text-muted">{{ __('app.applications.request_number') }}: {{ $requestRecord->code }}</span>
                        <h3 class="admin-state-title">{{ $stateTitle }}</h3>
                        <p class="admin-state-text">{{ $stateBody }}</p>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mt-4">
                        <a class="btn btn-outline-secondary" data-bs-toggle="tab" href="#profile-decision" role="tab">{{ __('app.admin_request_state.open_review') }}</a>
                        <a class="btn btn-outline-secondary" data-bs-toggle="tab" href="#profile-correspondence" role="tab">{{ __('app.admin_request_state.open_correspondence') }}</a>
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
                            <div class="mb-1"><span class="fw-600">{{ __('app.applications.project_name') }}:</span><span class="ms-2">{{ $requestRecord->project_name }}</span></div>
                            <div class="mb-1"><span class="fw-600">{{ __('app.applications.project_nationality') }}:</span><span class="ms-2">{{ __('app.applications.project_nationalities.'.$requestRecord->project_nationality) }}</span></div>
                            <div class="mb-1"><span class="fw-600">{{ __('app.scouting.production_type') }}:</span><span class="ms-2">{{ collect(data_get($production, 'types', []))->map(fn ($type) => __('app.scouting.production_type_options.'.$type))->join('، ') ?: __('app.dashboard.not_available') }}</span></div>
                            <div class="mb-0"><span class="fw-600">{{ __('app.scouting.project_summary') }}:</span><span class="ms-2">{{ $requestRecord->project_summary ?: __('app.dashboard.not_available') }}</span></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card request-section-card">
                                <div class="card-header"><h2 class="episode-playlist-title wp-heading-inline"><span class="position-relative">{{ __('app.scouting.producer_tab') }}</span></h2></div>
                                <div class="card-body">
                                    <div class="mb-1"><span class="fw-600">{{ __('app.scouting.producer_name') }}:</span><span class="ms-2">{{ data_get($producer, 'producer_name', __('app.dashboard.not_available')) }}</span></div>
                                    <div class="mb-1"><span class="fw-600">{{ __('app.scouting.producer_nationality') }}:</span><span class="ms-2">{{ __('app.applications.project_nationalities.'.data_get($producer, 'producer_nationality', 'jordanian')) }}</span></div>
                                    <div class="mb-1"><span class="fw-600">{{ __('app.applications.production_company_name') }}:</span><span class="ms-2">{{ data_get($producer, 'production_company_name', __('app.dashboard.not_available')) }}</span></div>
                                    <div class="mb-1"><span class="fw-600">{{ __('app.applications.contact_address') }}:</span><span class="ms-2">{{ data_get($producer, 'contact_address', __('app.dashboard.not_available')) }}</span></div>
                                    <div class="mb-1"><span class="fw-600">{{ __('app.scouting.producer_phone') }}:</span><span class="ms-2">{{ data_get($producer, 'producer_phone', __('app.dashboard.not_available')) }}</span></div>
                                    <div class="mb-1"><span class="fw-600">{{ __('app.scouting.producer_mobile') }}:</span><span class="ms-2">{{ data_get($producer, 'producer_mobile', __('app.dashboard.not_available')) }}</span></div>
                                    <div class="mb-1"><span class="fw-600">{{ __('app.scouting.producer_fax') }}:</span><span class="ms-2">{{ data_get($producer, 'producer_fax', __('app.dashboard.not_available')) }}</span></div>
                                    <div class="mb-1"><span class="fw-600">{{ __('app.scouting.producer_email') }}:</span><span class="ms-2">{{ data_get($producer, 'producer_email', __('app.dashboard.not_available')) }}</span></div>
                                    <div class="mb-1"><span class="fw-600">{{ __('app.scouting.liaison_name') }}:</span><span class="ms-2">{{ data_get($producer, 'liaison_name', __('app.dashboard.not_available')) }}</span></div>
                                    <div class="mb-1"><span class="fw-600">{{ __('app.scouting.liaison_job_title') }}:</span><span class="ms-2">{{ data_get($producer, 'liaison_job_title', __('app.dashboard.not_available')) }}</span></div>
                                    <div class="mb-0"><span class="fw-600">{{ __('app.scouting.liaison_email') }}:</span><span class="ms-2">{{ data_get($producer, 'liaison_email', __('app.dashboard.not_available')) }}</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card request-section-card">
                                <div class="card-header"><h2 class="episode-playlist-title wp-heading-inline"><span class="position-relative">{{ __('app.scouting.responsible_person_tab') }}</span></h2></div>
                                <div class="card-body">
                                    <div class="mb-1"><span class="fw-600">{{ __('app.scouting.responsible_person_name') }}:</span><span class="ms-2">{{ data_get($responsiblePerson, 'name', __('app.dashboard.not_available')) }}</span></div>
                                    <div class="mb-1"><span class="fw-600">{{ __('app.scouting.responsible_person_nationality') }}:</span><span class="ms-2">{{ __('app.applications.project_nationalities.'.data_get($responsiblePerson, 'nationality', 'jordanian')) }}</span></div>
                                    <div class="mb-1"><span class="fw-600">{{ __('app.scouting.scout_start_date') }}:</span><span class="ms-2">{{ optional($requestRecord->scout_start_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                                    <div class="mb-0"><span class="fw-600">{{ __('app.scouting.scout_end_date') }}:</span><span class="ms-2">{{ optional($requestRecord->scout_end_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card request-section-card">
                        <div class="card-header"><h2 class="episode-playlist-title wp-heading-inline"><span class="position-relative">{{ __('app.scouting.story_tab') }}</span></h2></div>
                        <div class="card-body">
                            <div class="mb-3">{{ $requestRecord->story_text ?: __('app.dashboard.not_available') }}</div>
                            @if ($requestRecord->story_file_path)
                                <a class="btn btn-outline-primary" href="{{ route('admin.scouting-requests.story-file.download', $requestRecord) }}">{{ __('app.scouting.download_story_file') }}</a>
                            @endif
                        </div>
                    </div>

                    <div class="card request-section-card">
                        <div class="card-header"><h2 class="episode-playlist-title wp-heading-inline"><span class="position-relative">{{ __('app.scouting.locations_tab') }}</span></h2></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{{ __('app.scouting.governorate') }}</th>
                                            <th>{{ __('app.scouting.location_name') }}</th>
                                            <th>{{ __('app.scouting.location_nature') }}</th>
                                            <th>{{ __('app.scouting.start_date') }}</th>
                                            <th>{{ __('app.scouting.end_date') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($locations as $location)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ __('app.scouting.governorate_options.'.($location['governorate'] ?? 'amman')) }}</td>
                                                <td>{{ $location['location_name'] ?? __('app.dashboard.not_available') }}</td>
                                                <td>{{ __('app.scouting.location_nature_options.'.($location['location_nature'] ?? 'public_site')) }}</td>
                                                <td>{{ $location['start_date'] ?? __('app.dashboard.not_available') }}</td>
                                                <td>{{ $location['end_date'] ?? __('app.dashboard.not_available') }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6">{{ __('app.scouting.empty_state') }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card request-section-card">
                        <div class="card-header"><h2 class="episode-playlist-title wp-heading-inline"><span class="position-relative">{{ __('app.scouting.crew_tab') }}</span></h2></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{{ __('app.scouting.crew_name') }}</th>
                                            <th>{{ __('app.scouting.crew_job_title') }}</th>
                                            <th>{{ __('app.scouting.crew_nationality') }}</th>
                                            <th>{{ __('app.scouting.crew_identity') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($crew as $member)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $member['name'] ?? __('app.dashboard.not_available') }}</td>
                                                <td>{{ $member['job_title'] ?? __('app.dashboard.not_available') }}</td>
                                                <td>{{ __('app.applications.project_nationalities.'.($member['nationality'] ?? 'jordanian')) }}</td>
                                                <td>{{ $member['national_id_passport'] ?? __('app.dashboard.not_available') }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5">{{ __('app.scouting.empty_state') }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                            <div id="profile-decision" class="tab-pane fade">
                    <div class="card request-section-card">
                        <div class="card-header">
                            <div class="header-title">
                                <h2 class="episode-playlist-title wp-heading-inline">
                                    <span class="position-relative">{{ __('app.admin.scouting.review_title') }}</span>
                                </h2>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <span class="badge bg-{{ $statusClass($requestRecord->status) }}">{{ $requestRecord->localizedStatus() }}</span>
                                @if ($requestRecord->reviewed_at)
                                    <div class="text-muted mt-2">{{ __('app.scouting.latest_review_summary', ['name' => $requestRecord->reviewedBy?->displayName() ?? __('app.roles.rfc_admin'), 'date' => $requestRecord->reviewed_at->format('Y-m-d H:i')]) }}</div>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.scouting-requests.review', $requestRecord) }}" class="row g-3">
                                @csrf
                                <div class="col-md-4">
                                    <label class="form-label" for="decision">{{ __('app.admin.scouting.review_decision') }}</label>
                                    <select id="decision" name="decision" class="form-control bg-white" required>
                                        @foreach (['under_review', 'needs_clarification', 'approved', 'rejected'] as $decision)
                                            <option value="{{ $decision }}" @selected($requestRecord->status === $decision)>{{ __('app.statuses.'.$decision) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="note">{{ __('app.admin.scouting.review_note') }}</label>
                                    <textarea id="note" name="note" rows="5" class="form-control">{{ old('note', $requestRecord->review_note) }}</textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-danger" type="submit">{{ __('app.admin.scouting.review_submit') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                            <div id="profile-correspondence" class="tab-pane fade">
                                @include('admin.scouting.partials.correspondence', ['correspondences' => $correspondences])
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
