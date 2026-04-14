@php($title = __('app.applications.create_title'))

@extends('layouts.portal-dashboard', ['title' => $title])

@section('page_layout_class', 'request-form-layout')

@push('styles')
    <style>
        .request-form-layout .streamit-wraper-table > .card-header {
            padding: 0 0 1.5rem;
        }

        .request-form-layout .streamit-wraper-table > .card {
            margin-bottom: 0;
        }

        .request-form-layout .streamit-wraper-table > .card > .card-body,
        .request-form-layout .streamit-wraper-table .streamit-tabs-card > .card-body {
            padding: 1.5rem;
        }

        .request-form-layout .form-actions {
            margin-top: 1.5rem;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="streamit-wraper-table">
                <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
                    <h2 class="episode-playlist-title wp-heading-inline">
                        <span class="position-relative">{{ __('app.applications.create_title') }}</span>
                    </h2>
                </div>
                <div class="card">
                    <div class="card-body">
                        @include('applications.partials.form')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
