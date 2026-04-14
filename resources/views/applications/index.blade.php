@php
    $title = __('app.applications.index_title');
    $activeApplications = $applications->whereIn('status', ['draft', 'submitted', 'under_review', 'needs_clarification'])->values();
    $previousApplications = $applications->whereIn('status', ['approved', 'rejected'])->values();
@endphp

@extends('layouts.portal-dashboard', ['title' => $title])

@section('page_layout_class', 'applicant-applications-layout')

@push('styles')
    <style>
        .applicant-applications-layout .request-toolbar {
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 6px;
            background: #fff;
            padding: 1.5rem;
        }

        .applicant-applications-layout .request-tabs .nav-link {
            font-size: 20px;
            font-weight: 500;
            color: #4b4f58;
            border-radius: 0;
        }

        .applicant-applications-layout .request-tabs .nav-link.active {
            color: #b72d1f;
        }

        .applicant-applications-layout .request-pane {
            border: 1px solid rgba(0, 0, 0, 0.08);
            padding: 2rem;
            background: #fff;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="streamit-wraper-table">
                <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
                    <div>
                        <h2 class="episode-playlist-title wp-heading-inline mb-1">
                            <span class="position-relative">{{ __('app.applications.index_title') }}</span>
                        </h2>
                        <div class="text-muted">{{ __('app.applications.index_intro') }}</div>
                    </div>
                    <a class="btn btn-danger" href="{{ route('applications.create') }}">
                        <i class="fa fa-plus me-2"></i>{{ __('app.applications.create_action') }}
                    </a>
                </div>

                <form method="GET" action="{{ route('applications.index') }}" class="request-toolbar mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-7">
                            <label class="form-label" for="q">{{ __('app.applications.search_label') }}</label>
                            <input id="q" name="q" type="text" class="form-control" value="{{ $filters['q'] }}" placeholder="{{ __('app.applications.search_placeholder') }}">
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label" for="status">{{ __('app.applications.status') }}</label>
                            <select id="status" name="status" class="form-control select2-basic-single">
                                @foreach (['all', 'draft', 'submitted', 'under_review', 'needs_clarification', 'approved', 'rejected'] as $status)
                                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status === 'all' ? __('app.applications.all_statuses') : __('app.statuses.'.$status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 d-flex gap-2 flex-wrap">
                            <button class="btn btn-primary flex-fill" type="submit">{{ __('app.applications.apply_filters_action') }}</button>
                            <a class="btn btn-outline-primary flex-fill" href="{{ route('applications.index') }}">{{ __('app.applications.clear_filters_action') }}</a>
                        </div>
                    </div>
                </form>

                <ul class="nav nav-pills mb-0 request-tabs" id="applicant-request-directory-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active p-4 fontSize20" id="applicant-active-tab" data-bs-toggle="pill" href="#applicant-active-pane" role="tab" aria-selected="true">
                            {{ __('app.applications.current_requests') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link p-4 fontSize20" id="applicant-previous-tab" data-bs-toggle="pill" href="#applicant-previous-pane" role="tab" aria-selected="false">
                            {{ __('app.applications.previous_requests') }}
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active request-pane" id="applicant-active-pane" role="tabpanel" aria-labelledby="applicant-active-tab">
                        @include('applications.partials.table', ['applications' => $activeApplications])
                    </div>
                    <div class="tab-pane fade request-pane" id="applicant-previous-pane" role="tabpanel" aria-labelledby="applicant-previous-tab">
                        @include('applications.partials.table', ['applications' => $previousApplications])
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
