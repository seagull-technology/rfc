@php
    $title = $requestRecord->project_name;
    $metadata = $requestRecord->metadata ?? [];
    $producer = data_get($metadata, 'producer', []);
    $responsiblePerson = data_get($metadata, 'responsible_person', []);
    $production = data_get($metadata, 'production', []);
    $locations = data_get($metadata, 'locations', []);
    $crew = data_get($metadata, 'crew', []);
    $statusClass = match ($requestRecord->status) {
        'submitted' => 'warning',
        'under_review' => 'info',
        'needs_clarification' => 'danger',
        'approved' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $latestCorrespondence = $correspondences->first();
    $requestState = match ($requestRecord->status) {
        'draft' => [
            'title' => __('app.request_state.draft_title'),
            'body' => __('app.request_state.scouting_draft_body'),
        ],
        'submitted', 'under_review' => [
            'title' => __('app.request_state.review_title'),
            'body' => __('app.request_state.scouting_review_body'),
        ],
        'needs_clarification' => [
            'title' => __('app.request_state.clarification_title'),
            'body' => __('app.request_state.scouting_clarification_body'),
        ],
        'approved' => [
            'title' => __('app.request_state.approved_title'),
            'body' => __('app.request_state.scouting_approved_body'),
        ],
        'rejected' => [
            'title' => __('app.request_state.rejected_title'),
            'body' => __('app.request_state.scouting_rejected_body'),
        ],
        default => [
            'title' => __('app.request_state.review_title'),
            'body' => __('app.request_state.scouting_review_body'),
        ],
    };
@endphp

@extends('layouts.portal-dashboard', ['title' => $title])

@push('styles')
    <style>
        .scouting-show-layout .request-actions-card {
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .scouting-show-layout .profile-tab {
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .scouting-show-layout .profile-tab .nav-link {
            white-space: nowrap;
        }

        .scouting-show-layout .request-section-card .card-body > div:last-child,
        .scouting-show-layout .request-section-card .card-body > p:last-child {
            margin-bottom: 0;
        }

        .scouting-show-layout .timeline-note {
            overflow-wrap: anywhere;
        }

        .scouting-show-layout .table thead th,
        .scouting-show-layout .table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .scouting-show-layout .request-state-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: .75rem 0 .5rem;
        }

        .scouting-show-layout .request-state-text {
            color: #6c757d;
            margin-bottom: 0;
            max-width: 58rem;
        }

        .scouting-show-layout .request-state-meta {
            border: 1px solid rgba(17, 24, 39, .08);
            border-radius: .5rem;
            height: 100%;
            padding: 1rem;
        }

        .scouting-show-layout .request-state-meta-label {
            color: #6c757d;
            display: block;
            font-size: .8125rem;
            font-weight: 600;
            margin-bottom: .5rem;
        }

        .scouting-show-layout .request-state-meta-detail {
            color: #6c757d;
            display: block;
            font-size: .875rem;
            margin-top: .5rem;
        }
    </style>
@endpush

@section('page_layout_class', 'scouting-show-layout')

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
                        <h6 class="me-2 text-white">{{ $requestRecord->project_name }}</h6>
                        <div class="text-white">{{ $requestRecord->code }}</div>
                    </div>
                </div>

                <ul class="d-flex nav nav-pills mb-0 text-center profile-tab" data-toggle="slider-tab" id="profile-pills-tab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active show" data-bs-toggle="tab" href="#profile-request" role="tab" aria-selected="true">{{ __('app.admin.scouting.request_tab') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile-correspondence" role="tab" aria-selected="false">{{ __('app.correspondence.tab') }}</a>
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
                    <div class="card-header"><h2 class="episode-playlist-title wp-heading-inline"><span class="position-relative">{{ __('app.applications.status_timeline_title') }}</span></h2></div>
                    <div class="card-body">
                        <div class="iq-timeline0 m-0 d-flex align-items-center justify-content-between position-relative">
                            <ul class="list-inline p-0 m-0">
                                @forelse ($statusHistory as $event)
                                    @php
                                        $eventColor = match ($event->status) {
                                            'submitted' => 'warning',
                                            'under_review' => 'info',
                                            'needs_clarification' => 'danger',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <li>
                                        <div class="timeline-dots timeline-dot1 border-{{ $eventColor }} text-{{ $eventColor }}"></div>
                                        <h6 class="float-left mb-1 fw-semibold">{{ $event->user?->displayName() ?? __('app.dashboard.not_available') }}</h6>
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
                                    <li class="text-muted">{{ __('app.scouting.empty_history') }}</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2 class="episode-playlist-title wp-heading-inline"><span class="position-relative">{{ __('app.scouting.workflow_title') }}</span></h2></div>
                    <div class="card-body">
                        <div class="mb-3"><small class="text-muted d-block">{{ __('app.applications.status') }}</small>{{ $requestRecord->localizedStatus() }}</div>
                        <div class="mb-3"><small class="text-muted d-block">{{ __('app.workflow.current_stage') }}</small>{{ $requestRecord->localizedStage() }}</div>
                        <div class="mb-3"><small class="text-muted d-block">{{ __('app.scouting.reviewed_by') }}</small>{{ $requestRecord->reviewedBy?->displayName() ?? __('app.dashboard.not_available') }}</div>
                        <div class="mb-0"><small class="text-muted d-block">{{ __('app.scouting.reviewed_at') }}</small>{{ optional($requestRecord->reviewed_at)->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div>
                    </div>
                </div>

                @if ($requestRecord->review_note)
                    <div class="card">
                        <div class="card-header"><h2 class="episode-playlist-title wp-heading-inline"><span class="position-relative">{{ __('app.scouting.review_note_title') }}</span></h2></div>
                        <div class="card-body">{{ $requestRecord->review_note }}</div>
                    </div>
                @endif
            </div>

            <div class="col-lg-9">
                <div class="card request-actions-card mb-4">
                    <div class="card-body">
                        <div>
                            <span class="badge bg-{{ $statusClass }}">{{ $requestRecord->localizedStatus() }}</span>
                            <span class="ms-2 text-muted">{{ __('app.applications.request_number') }}: {{ $requestRecord->code }}</span>
                            <h3 class="request-state-title">{{ $requestState['title'] }}</h3>
                            <p class="request-state-text">{{ $requestState['body'] }}</p>
                        </div>
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mt-4">
                            <div class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-outline-secondary" data-bs-toggle="tab" href="#profile-correspondence" role="tab" aria-selected="false">{{ __('app.request_state.open_correspondence') }}</a>
                                @if ($requestRecord->canBeEditedByApplicant())
                                    <a class="btn btn-light" href="{{ route('scouting-requests.edit', $requestRecord) }}">{{ __('app.applications.edit_action') }}</a>
                                @endif
                                @if ($requestRecord->canBeSubmittedByApplicant())
                                    <form method="POST" action="{{ route('scouting-requests.submit', $requestRecord) }}">
                                        @csrf
                                        <button class="btn btn-danger" type="submit">{{ __('app.scouting.submit_action') }}</button>
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
                            <div class="card-header"><h2 class="episode-playlist-title wp-heading-inline"><span class="position-relative">{{ __('app.applications.project_information') }}</span></h2></div>
                            <div class="card-body">
                                <div class="mb-1"><span class="fw-600">{{ __('app.applications.project_name') }}:</span><span class="ms-2">{{ $requestRecord->project_name }}</span></div>
                                <div class="mb-1"><span class="fw-600">{{ __('app.applications.project_nationality') }}:</span><span class="ms-2">{{ __('app.applications.project_nationalities.'.$requestRecord->project_nationality) }}</span></div>
                                <div class="mb-1"><span class="fw-600">{{ __('app.scouting.production_type') }}:</span><span class="ms-2">{{ collect(data_get($production, 'types', []))->map(fn ($type) => __('app.scouting.production_type_options.'.$type))->join('، ') ?: __('app.dashboard.not_available') }}</span></div>
                                <div class="mb-0"><span class="fw-600">{{ __('app.scouting.project_summary') }}:</span><span class="ms-2">{{ $requestRecord->project_summary }}</span></div>
                            </div>
                        </div>

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

                        <div class="card request-section-card">
                            <div class="card-header"><h2 class="episode-playlist-title wp-heading-inline"><span class="position-relative">{{ __('app.scouting.responsible_person_tab') }}</span></h2></div>
                            <div class="card-body">
                                <div class="mb-1"><span class="fw-600">{{ __('app.scouting.responsible_person_name') }}:</span><span class="ms-2">{{ data_get($responsiblePerson, 'name', __('app.dashboard.not_available')) }}</span></div>
                                <div class="mb-0"><span class="fw-600">{{ __('app.scouting.responsible_person_nationality') }}:</span><span class="ms-2">{{ __('app.applications.project_nationalities.'.data_get($responsiblePerson, 'nationality', 'jordanian')) }}</span></div>
                            </div>
                        </div>

                        <div class="card request-section-card">
                            <div class="card-header"><h2 class="episode-playlist-title wp-heading-inline"><span class="position-relative">{{ __('app.scouting.scout_dates_tab') }}</span></h2></div>
                            <div class="card-body">
                                <div class="mb-1"><span class="fw-600">{{ __('app.scouting.scout_start_date') }}:</span><span class="ms-2">{{ optional($requestRecord->scout_start_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                                <div class="mb-1"><span class="fw-600">{{ __('app.scouting.scout_end_date') }}:</span><span class="ms-2">{{ optional($requestRecord->scout_end_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                                <div class="mb-1"><span class="fw-600">{{ __('app.scouting.production_start_date') }}:</span><span class="ms-2">{{ optional($requestRecord->production_start_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                                <div class="mb-0"><span class="fw-600">{{ __('app.scouting.production_end_date') }}:</span><span class="ms-2">{{ optional($requestRecord->production_end_date)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</span></div>
                            </div>
                        </div>

                        <div class="card request-section-card">
                            <div class="card-header"><h2 class="episode-playlist-title wp-heading-inline"><span class="position-relative">{{ __('app.scouting.story_tab') }}</span></h2></div>
                            <div class="card-body">
                                <div class="mb-3">{{ $requestRecord->story_text ?: __('app.dashboard.not_available') }}</div>
                                @if ($requestRecord->story_file_path)
                                    <a class="btn btn-outline-primary" href="{{ route('scouting-requests.story-file.download', $requestRecord) }}">{{ __('app.scouting.download_story_file') }}</a>
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
                                                <th>{{ __('app.scouting.google_map_url') }}</th>
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
                                                    <td>{{ $location['google_map_url'] ?? __('app.dashboard.not_available') }}</td>
                                                    <td>{{ __('app.scouting.location_nature_options.'.($location['location_nature'] ?? 'public_site')) }}</td>
                                                    <td>{{ $location['start_date'] ?? __('app.dashboard.not_available') }}</td>
                                                    <td>{{ $location['end_date'] ?? __('app.dashboard.not_available') }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="7">{{ __('app.scouting.empty_state') }}</td></tr>
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

                    <div id="profile-correspondence" class="tab-pane fade">
                        <div class="row g-3 mb-4">
                            @foreach (['review_progress', 'latest_official_step', 'next_required_step'] as $overviewKey)
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

                        @if ($requestRecord->review_note || $latestCorrespondence)
                            <div class="row g-3 mb-4">
                                @if ($requestRecord->review_note)
                                    <div class="col-lg-6">
                                        <div class="request-state-meta">
                                            <span class="request-state-meta-label">{{ __('app.request_state.review_note') }}</span>
                                            <div>{{ $requestRecord->review_note }}</div>
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

                        @include('scouting.partials.correspondence', ['correspondences' => $correspondences])
                    </div>
                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
