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

        .foreign-producer-profile-layout table.table thead th,
        .foreign-producer-profile-layout table.table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }
    </style>
@endpush

@section('hero')
    <div class="card bg-image-12 foreign-producer-hero">
        <div class="card-body">
            <div class="text-center">
                <div>
                    <img src="{{ asset('images/OIP.jpeg') }}" alt="profile-img" class="rounded-pill avatar-130 img-fluid" loading="lazy">
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
                    <div class="mt-4 table-responsive">
                        <div class="table-responsive rounded py-4">
                            <table class="table" data-toggle="data-table">
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
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $application->code }}</td>
                                            <td>{{ $application->project_name }}</td>
                                            <td>{{ $application->submittedBy?->displayName() ?? $user->displayName() }}</td>
                                            <td>{{ $application->submitted_at?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                            <td><span class="badge bg-{{ $statusClass($application->status) }}">{{ $application->localizedStatus() }}</span></td>
                                            <td>
                                                <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('applications.show', $application) }}">
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
                    <div class="mt-4 table-responsive">
                        <div class="table-responsive rounded py-4">
                            <table class="table" data-toggle="data-table">
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
                                                <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('scouting-requests.show', $scoutingRequest) }}">
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
@endsection
