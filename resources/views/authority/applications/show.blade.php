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

        .authority-request-show-layout .profile-tab {
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .authority-request-show-layout .profile-tab .nav-link {
            white-space: nowrap;
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

        @media (max-width: 991.98px) {
            .authority-request-show-layout .authority-hero-card {
                margin: 0 .75rem 1rem;
            }

            .authority-request-show-layout .documents-table {
                width: 100%;
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
                                    </div>
                                </div>
                            </div>

                            <div id="authority-documents" class="tab-pane fade">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="form-card text-start pb-4">
                                            <h2 class="episode-playlist-title wp-heading-inline">
                                                <span class="position-relative">{{ __('app.documents.title') }}</span>
                                            </h2>

                                            <div class="row">
                                                <div class="table-responsive mt-4">
                                                    <table class="table table-striped mb-0 documents-table">
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
                                                                    <td colspan="4">{{ __('app.documents.empty_state') }}</td>
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
                                <div class="mb-3">
                                    <span class="badge bg-{{ $decisionBadgeClass }}">{{ $currentApproval->localizedStatus() }}</span>
                                    @if ($currentApproval->decided_at)
                                        <div class="text-muted mt-2">{{ $currentApproval->decided_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                </div>
                                @if ($currentApproval->note)
                                    <div class="mb-3">{{ $currentApproval->note }}</div>
                                @endif
                                <form method="POST" action="{{ route('authority.applications.approval.update', $application) }}" class="row g-3">
                                    @csrf
                                    <div class="col-12">
                                        <label class="form-label" for="status">{{ __('app.applications.status') }}</label>
                                        <select id="status" name="status" class="form-control select2-basic-single" required>
                                            @foreach (['pending', 'in_review', 'approved', 'rejected'] as $status)
                                                <option value="{{ $status }}" @selected($currentApproval->status === $status)>{{ __('app.approvals.statuses.'.$status) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="note">{{ __('app.authority.applications.approval_note') }}</label>
                                        <textarea id="note" name="note" rows="6" class="form-control">{{ old('note', $currentApproval->note) }}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <button class="btn btn-danger" type="submit">{{ __('app.authority.applications.save_decision') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card request-pane-card">
                            <div class="card-header">
                                <div class="header-title">
                                    <h2 class="episode-playlist-title wp-heading-inline">
                                        <span class="position-relative">{{ __('app.correspondence.title') }}</span>
                                    </h2>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="border rounded p-3 bg-light mb-4">
                                    <form method="POST" action="{{ route('authority.applications.correspondence.store', $application) }}" enctype="multipart/form-data" class="row g-3">
                                        @csrf
                                        <div class="col-12">
                                            <label class="form-label" for="subject">{{ __('app.correspondence.subject') }}</label>
                                            <input id="subject" name="subject" type="text" class="form-control">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="attachment">{{ __('app.correspondence.attachment') }}</label>
                                            <input id="attachment" name="attachment" type="file" class="form-control">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="message">{{ __('app.correspondence.message') }}</label>
                                            <textarea id="message" name="message" rows="4" class="form-control" required></textarea>
                                        </div>
                                        <div class="col-12">
                                            <button class="btn btn-danger" type="submit">{{ __('app.correspondence.send_action') }}</button>
                                        </div>
                                    </form>
                                </div>

                                <ul class="list-inline p-0 m-0">
                                    @forelse ($correspondences as $message)
                                        <li class="mb-3">
                                            <div class="border rounded p-3">
                                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                                    <div>
                                                        <h6 class="mb-1">{{ $message->sender_name }}</h6>
                                                        <div class="text-muted small">{{ $message->localizedSenderType() }} | {{ $message->created_at?->format('Y-m-d H:i') }}</div>
                                                        @if ($message->subject)
                                                            <div class="mt-2 fw-semibold">{{ $message->subject }}</div>
                                                        @endif
                                                    </div>
                                                    @if ($message->attachment_path)
                                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('authority.applications.correspondence.download', [$application, $message]) }}">{{ __('app.correspondence.download_attachment') }}</a>
                                                    @endif
                                                </div>
                                                <div class="mt-3 text-break">{{ $message->message }}</div>
                                            </div>
                                        </li>
                                    @empty
                                        <li class="text-muted border rounded p-3">{{ __('app.correspondence.empty_state') }}</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
