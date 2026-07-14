@php
    $title = $entity->displayName();
    $statusClass = static fn (?string $status): string => match ($status) {
        'submitted' => 'warning',
        'under_review' => 'warning',
        'needs_clarification' => 'danger',
        'approved' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $isInternationalProducerUser = $user->registration_type === 'international_producer';
@endphp

@extends('layouts.portal-dashboard', ['title' => $title])

@section('page_layout_class', 'foreign-producer-profile-layout')

@push('styles')
    <style>
        .foreign-producer-profile-layout {
            padding-top: 0 !important;
        }

        .foreign-producer-profile-layout .foreign-producer-hero {
            border: 0;
            border-radius: .5rem;
            margin-bottom: 0;
            overflow: hidden;
        }

        .foreign-producer-profile-layout .foreign-producer-hero .card-body {
            padding: 2.5rem 1rem 2rem;
        }

        .foreign-producer-profile-layout .foreign-producer-hero .avatar-130 {
            height: 130px;
            width: 130px;
        }

        .foreign-producer-profile-layout .foreign-producer-hero h3 {
            font-size: 2.1rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .foreign-producer-profile-layout .card {
            margin-bottom: 1.5rem;
        }

        .foreign-producer-profile-layout .card-header {
            padding-bottom: 0;
        }

        .foreign-producer-profile-layout .card-dashboard .card-header {
            padding-top: 1.5rem;
        }

        .foreign-producer-profile-layout .table-responsive.rounded.py-4 {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        .foreign-producer-profile-layout table.table thead th,
        .foreign-producer-profile-layout table.table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .foreign-producer-profile-layout .foreign-producer-table-scroll {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
        }

        .foreign-producer-profile-layout .foreign-producer-table {
            min-width: 1120px;
            table-layout: fixed;
            width: 100%;
        }

        .foreign-producer-profile-layout .foreign-producer-table thead th,
        .foreign-producer-profile-layout .foreign-producer-table tbody td {
            white-space: normal;
            vertical-align: top;
            word-break: break-word;
        }

        .foreign-producer-profile-layout .foreign-producer-declaration {
            font-size: 1rem;
            line-height: 1.85;
        }
    </style>
@endpush

@section('hero')
    <div class="card bg-image-12 foreign-producer-hero">
        <div class="card-body">
            <div class="text-center">
                <div>
                    <img src="{{ $profileLogoUrl }}" alt="profile-img" class="rounded-pill avatar-130 img-fluid" loading="lazy">
                </div>
                <div class="mt-3">
                    <h3 class="d-inline-block text-white">{{ $entity->displayName() }} - {{ __('app.profile.foreign_producer_suffix') }}</h3>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="card card-dashboard">
                <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
                    <h2 class="episode-playlist-title wp-heading-inline">
                        <span class="position-relative">{{ __('app.applications.index_title') }}</span>
                    </h2>
                </div>
                <div class="card-body pt-0">
                    <div class="mt-4">
                        <div class="table-responsive rounded py-4 foreign-producer-table-scroll">
                            <table class="table foreign-producer-table foreign-producer-applications-table" data-toggle="data-table">
                                <colgroup>
                                    <col style="width: 70px">
                                    <col style="width: 170px">
                                    <col style="width: 260px">
                                    <col style="width: 210px">
                                    <col style="width: 170px">
                                    <col style="width: 130px">
                                    <col style="width: 110px">
                                </colgroup>
                                <thead>
                                    <tr class="ligth">
                                        <th>#</th>
                                        <th>{{ __('app.applications.request_number') }}</th>
                                        <th>{{ __('app.applications.project_name') }}</th>
                                        <th>{{ __('app.authority.applications.applicant') }}</th>
                                        <th>{{ __('app.applications.submitted_at_label') }}</th>
                                        <th>{{ __('app.applications.status') }}</th>
                                        <th>{{ __('app.applications.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($entityApplications as $application)
                                        @php
                                            $declaration = data_get($application->metadata, 'international.account.declaration', []);
                                            $declarationSigned = (bool) data_get($declaration, 'accepted') && filled(data_get($declaration, 'signed_at'));
                                        @endphp
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $application->code }}</td>
                                            <td>{{ $application->project_name }}</td>
                                            <td>{{ $application->submittedBy?->displayName() ?? $user->displayName() }}</td>
                                            <td>{{ $application->submitted_at?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $statusClass($application->status) }}">{{ $application->localizedStatus() }}</span>
                                                @if ($isInternationalProducerUser)
                                                    <div class="small mt-2 text-{{ $declarationSigned ? 'success' : 'warning' }}">
                                                        {{ $declarationSigned ? __('app.profile.foreign_producer_declaration_signed') : __('app.profile.foreign_producer_declaration_pending') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <a
                                                    class="btn btn-sm btn-icon btn-info-subtle rounded"
                                                    href="#"
                                                    data-bs-toggle="offcanvas"
                                                    data-bs-target="#foreign-producer-application-{{ $application->getKey() }}"
                                                    aria-controls="foreign-producer-application-{{ $application->getKey() }}"
                                                >
                                                    <span class="btn-inner"><i class="ph ph-eye fs-6"></i></span>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">{{ __('app.applications.empty_state') }}</td>
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

    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="card card-dashboard">
                <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
                    <h2 class="episode-playlist-title wp-heading-inline">
                        <span class="position-relative">{{ __('app.dashboard.scouting_request_type_plural') }}</span>
                    </h2>
                </div>
                <div class="card-body pt-0">
                    <div class="mt-4">
                        <div class="table-responsive rounded py-4 foreign-producer-table-scroll">
                            <table class="table foreign-producer-table foreign-producer-scouting-table" data-toggle="data-table">
                                <colgroup>
                                    <col style="width: 70px">
                                    <col style="width: 170px">
                                    <col style="width: 260px">
                                    <col style="width: 210px">
                                    <col style="width: 170px">
                                    <col style="width: 130px">
                                    <col style="width: 110px">
                                </colgroup>
                                <thead>
                                    <tr class="ligth">
                                        <th>#</th>
                                        <th>{{ __('app.applications.request_number') }}</th>
                                        <th>{{ __('app.applications.project_name') }}</th>
                                        <th>{{ __('app.authority.applications.applicant') }}</th>
                                        <th>{{ __('app.applications.submitted_at_label') }}</th>
                                        <th>{{ __('app.applications.status') }}</th>
                                        <th>{{ __('app.applications.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($scoutingRequests as $scoutingRequest)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $scoutingRequest->code }}</td>
                                            <td>{{ $scoutingRequest->project_name }}</td>
                                            <td>{{ $scoutingRequest->submittedBy?->displayName() ?? $user->displayName() }}</td>
                                            <td>{{ $scoutingRequest->submitted_at?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                            <td><span class="badge bg-{{ $statusClass($scoutingRequest->status) }}">{{ $scoutingRequest->localizedStatus() }}</span></td>
                                            <td>
                                                <a
                                                    class="btn btn-sm btn-icon btn-info-subtle rounded"
                                                    href="#"
                                                    data-bs-toggle="offcanvas"
                                                    data-bs-target="#foreign-producer-scouting-{{ $scoutingRequest->getKey() }}"
                                                    aria-controls="foreign-producer-scouting-{{ $scoutingRequest->getKey() }}"
                                                >
                                                    <span class="btn-inner"><i class="ph ph-eye fs-6"></i></span>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">{{ __('app.scouting.empty_state') }}</td>
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

    @foreach ($entityApplications as $application)
        @php
            $declaration = data_get($application->metadata, 'international.account.declaration', []);
            $declarationSigned = (bool) data_get($declaration, 'accepted') && filled(data_get($declaration, 'signed_at'));
            $declarationSignedAt = filled(data_get($declaration, 'signed_at'))
                ? \Illuminate\Support\Carbon::parse((string) data_get($declaration, 'signed_at'))->format('Y-m-d H:i')
                : null;
            $declarationFormId = 'foreign-producer-declaration-form-'.$application->getKey();
        @endphp
        <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="foreign-producer-application-{{ $application->getKey() }}">
            <div class="offcanvas-header">
                <h2 class="episode-playlist-title wp-heading-inline">
                    <span class="position-relative">{{ __('app.profile.foreign_producer_declaration_title') }}</span>
                </h2>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                @if ($declarationSigned)
                    <div class="alert alert-success">
                        <div class="fw-semibold">{{ __('app.profile.foreign_producer_declaration_signed') }}</div>
                        @if ($declarationSignedAt)
                            <div class="small mt-1">{{ __('app.profile.foreign_producer_declaration_signed_at', ['date' => $declarationSignedAt]) }}</div>
                        @endif
                        @if (filled(data_get($declaration, 'signed_by_name')))
                            <div class="small">{{ __('app.profile.foreign_producer_declaration_signed_by', ['name' => data_get($declaration, 'signed_by_name')]) }}</div>
                        @endif
                    </div>
                @endif
                @if ($isInternationalProducerUser && ! $declarationSigned)
                    <form id="{{ $declarationFormId }}" method="POST" action="{{ route('profile.foreign-producer.applications.declaration.store', $application) }}">
                        @csrf
                        <div class="section-form">
                            <div class="form-check form-group">
                                <input type="checkbox" class="form-check-input @error('declaration_accepted') is-invalid @enderror" id="declaration-accepted-{{ $application->getKey() }}" name="declaration_accepted" value="1" required>
                                <label class="form-label" for="declaration-accepted-{{ $application->getKey() }}">
                                    {{ __('app.profile.foreign_producer_declaration_body') }}
                                </label>
                                @error('declaration_accepted')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </form>
                @else
                    <div class="foreign-producer-declaration">
                        {{ __('app.profile.foreign_producer_declaration_body') }}
                    </div>
                @endif
                <div class="mt-4">
                    <div class="fw-semibold">{{ __('app.applications.request_number') }}: {{ $application->code }}</div>
                    <div class="text-muted">{{ $application->project_name }}</div>
                </div>
            </div>
            <div class="offcanvas-footer border-top">
                <div class="d-flex gap-3 p-3 justify-content-end">
                    @if ($isInternationalProducerUser && ! $declarationSigned)
                        <button type="submit" form="{{ $declarationFormId }}" class="btn btn-danger d-flex align-items-center gap-2">
                            <i class="ph-fill ph-floppy-disk-back"></i>{{ __('app.profile.foreign_producer_declaration_save') }}
                        </button>
                    @endif
                    <a class="btn btn-danger d-flex align-items-center gap-2" href="{{ route('applications.show', $application) }}">
                        <i class="ph ph-eye"></i>{{ __('app.profile.foreign_producer_open_request') }}
                    </a>
                    <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas" aria-label="Close">
                        <i class="ph ph-caret-double-left"></i>{{ __('app.profile.foreign_producer_close') }}
                    </button>
                </div>
            </div>
        </div>
    @endforeach

    @foreach ($scoutingRequests as $scoutingRequest)
        <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="foreign-producer-scouting-{{ $scoutingRequest->getKey() }}">
            <div class="offcanvas-header">
                <h2 class="episode-playlist-title wp-heading-inline">
                    <span class="position-relative">{{ __('app.profile.foreign_producer_declaration_title') }}</span>
                </h2>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <div class="foreign-producer-declaration">
                    {{ __('app.profile.foreign_producer_declaration_body') }}
                </div>
                <div class="mt-4">
                    <div class="fw-semibold">{{ __('app.applications.request_number') }}: {{ $scoutingRequest->code }}</div>
                    <div class="text-muted">{{ $scoutingRequest->project_name }}</div>
                </div>
            </div>
            <div class="offcanvas-footer border-top">
                <div class="d-flex gap-3 p-3 justify-content-end">
                    <a class="btn btn-danger d-flex align-items-center gap-2" href="{{ route('scouting-requests.show', $scoutingRequest) }}">
                        <i class="ph ph-eye"></i>{{ __('app.profile.foreign_producer_open_request') }}
                    </a>
                    <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas" aria-label="Close">
                        <i class="ph ph-caret-double-left"></i>{{ __('app.profile.foreign_producer_close') }}
                    </button>
                </div>
            </div>
        </div>
    @endforeach
@endsection
