@php
    $title = __('app.contact_center.title');
@endphp

@extends('layouts.portal-dashboard', ['title' => $title])

@section('page_layout_class', 'contact-center-layout')

@push('styles')
    <style>
        .contact-center-layout .card-header {
            padding-bottom: 0;
        }

        .contact-center-layout table thead th,
        .contact-center-layout table tbody td {
            vertical-align: middle;
            white-space: nowrap;
        }
    </style>
@endpush

@section('content')
    <div class="content-inner container-fluid pb-0" id="page_layout">
        <div class="row">
            <div class="col-sm-12">
                <div class="streamit-wraper-table">
                    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
                        <h2 class="episode-playlist-title wp-heading-inline">
                            <span class="position-relative">{{ __('app.contact_center.title') }}</span>
                        </h2>
                    </div>

                    <div class="table-view table-space">
                        <table class="data-tables table custom-table data-table-one custom-table-height" role="grid" data-toggle="data-table1">
                            <thead>
                                <tr class="ligth">
                                    <th>#</th>
                                    <th>{{ __('app.contact_center.title_label') }}</th>
                                    <th>{{ __('app.contact_center.type_label') }}</th>
                                    <th>{{ __('app.contact_center.checkpoint_label') }}</th>
                                    <th>{{ __('app.contact_center.sender_label') }}</th>
                                    <th>{{ __('app.contact_center.sent_at') }}</th>
                                    <th>{{ __('app.contact_center.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($messages as $message)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $message['title'] }}</td>
                                        <td>{{ $message['type_label'] }}</td>
                                        <td>
                                            @if ($message['workflow_checkpoint_label'])
                                                <span class="badge bg-{{ $message['workflow_checkpoint_class'] }}">{{ $message['workflow_checkpoint_label'] }}</span>
                                            @else
                                                {{ __('app.dashboard.not_available') }}
                                            @endif
                                        </td>
                                        <td>{{ $message['sender_label'] }}</td>
                                        <td>{{ $message['created_at']?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                        <td>
                                            <div class="flex align-items-center list-user-action">
                                                <button class="btn btn-sm btn-icon btn-info-subtle rounded" type="button" data-bs-toggle="offcanvas" data-bs-target="#viewMsg-{{ $loop->iteration }}">
                                                    <i class="ph ph-eye fs-6"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">{{ __('app.contact_center.empty_state') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($messages as $message)
        <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="viewMsg-{{ $loop->iteration }}">
            <div class="offcanvas-header">
                <h2 class="episode-playlist-title wp-heading-inline">
                    <span class="position-relative">{{ __('app.contact_center.view_title') }}</span>
                </h2>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <div class="section-form">
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.contact_center.title_label') }}</label>
                        <div class="form-control bg-light">{{ $message['title'] }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.contact_center.body_label') }}</label>
                        <div class="form-control bg-light" style="min-height:120px; white-space:pre-line;">{{ $message['body'] }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.contact_center.type_label') }}</label>
                        <div class="form-control bg-light">{{ $message['type_label'] }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.contact_center.checkpoint_label') }}</label>
                        <div class="form-control bg-light">{{ $message['workflow_checkpoint_label'] ?? __('app.dashboard.not_available') }}</div>
                    </div>

                    @if ($message['attachment_url'])
                        <div class="mb-3">
                            <label class="form-label">{{ __('app.contact_center.attachment_label') }}</label>
                            <a href="{{ $message['attachment_url'] }}" class="d-flex align-items-center gap-2 text-decoration-none">
                                <i class="ph ph-file fs-5"></i>
                                <span>{{ $message['attachment_name'] ?? __('app.documents.download_action') }}</span>
                            </a>
                        </div>
                    @endif

                    @if ($message['source_url'])
                        <div class="mb-3">
                            <label class="form-label">{{ __('app.contact_center.station_label') }}</label>
                            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                <div class="form-control bg-light">{{ $message['station_label'] }}</div>
                                <a class="btn btn-outline-primary" href="{{ $message['source_url'] }}">{{ __('app.contact_center.open_source_action') }}</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="offcanvas-footer border-top">
                <div class="d-flex gap-3 p-3 justify-content-end">
                    <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-dismiss="offcanvas">
                        <i class="ph ph-caret-double-left"></i>{{ __('app.contact_center.close_action') }}
                    </button>
                </div>
            </div>
        </div>
    @endforeach
@endsection
