@php
    $title = __('app.contact_center.admin_title');
    $breadcrumb = __('app.admin.navigation.contact_center');
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('page_layout_class', 'admin-contact-center-layout')

@push('styles')
    <style>
        .admin-contact-center-layout .card-header {
            padding-bottom: 0;
        }

        .admin-contact-center-layout table thead th,
        .admin-contact-center-layout table tbody td {
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
                            <span class="position-relative">{{ __('app.contact_center.admin_title') }}</span>
                        </h2>
                        <button class="btn btn-danger" type="button" data-bs-toggle="offcanvas" data-bs-target="#newMsg">
                            <i class="fa-solid fa-plus me-2"></i>{{ __('app.contact_center.new_message_action') }}
                        </button>
                    </div>

                    <div class="table-view table-space">
                        <table class="data-tables table custom-table data-table-one custom-table-height" role="grid" data-toggle="data-table1">
                            <thead>
                                <tr class="ligth">
                                    <th>#</th>
                                    <th>{{ __('app.contact_center.title_label') }}</th>
                                    <th>{{ __('app.contact_center.type_label') }}</th>
                                    <th>{{ __('app.contact_center.checkpoint_label') }}</th>
                                    <th>{{ __('app.contact_center.recipient_label') }}</th>
                                    <th>{{ __('app.contact_center.station_label') }}</th>
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
                                        <td>{{ $message['recipient_label'] }}</td>
                                        <td>{{ $message['station_label'] }}</td>
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
                                        <td colspan="8">{{ __('app.contact_center.empty_state') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="newMsg">
        <div class="offcanvas-header">
            <h2 class="episode-playlist-title wp-heading-inline">
                <span class="position-relative">{{ __('app.contact_center.compose_title') }}</span>
            </h2>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="{{ route('admin.contact-center.messages.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="section-form">
                    <div class="mb-3">
                        <label class="form-label" for="contact_center_title">{{ __('app.contact_center.title_label') }}</label>
                        <input id="contact_center_title" name="title" type="text" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="contact_center_message">{{ __('app.contact_center.body_label') }}</label>
                        <textarea id="contact_center_message" name="message" rows="6" class="form-control" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="contact_center_type">{{ __('app.contact_center.type_label') }}</label>
                        <select id="contact_center_type" name="message_type" class="form-select" required>
                            @foreach (['general_notice', 'official_reply', 'decision', 'follow_up'] as $type)
                                <option value="{{ $type }}">{{ __('app.contact_center.message_types.'.$type) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.contact_center.recipient_scope_label') }}</label>
                        <div class="d-flex gap-4 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recipient_scope" id="allProducers" value="all" checked>
                                <label class="form-check-label" for="allProducers">{{ __('app.contact_center.recipient_scopes.all') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recipient_scope" id="specificProducer" value="specific">
                                <label class="form-check-label" for="specificProducer">{{ __('app.contact_center.recipient_scopes.specific') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="producerSelect" style="display:none;">
                        <label class="form-label" for="entity_id">{{ __('app.contact_center.recipient_entity_label') }}</label>
                        <select id="entity_id" name="entity_id" class="form-select">
                            <option value="">{{ __('app.admin.select_placeholder') }}</option>
                            @foreach ($recipientEntities as $recipientEntity)
                                <option value="{{ $recipientEntity->getKey() }}">{{ $recipientEntity->displayName() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="contact_center_attachment">{{ __('app.correspondence.attachment') }}</label>
                        <input id="contact_center_attachment" name="attachment" type="file" class="form-control">
                    </div>
                </div>

                <div class="d-flex gap-3 justify-content-end">
                    <button class="btn btn-danger" type="submit">{{ __('app.correspondence.send_action') }}</button>
                </div>
            </form>
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

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.contact_center.recipient_label') }}</label>
                        <div class="form-control bg-light">{{ $message['recipient_label'] }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.contact_center.station_label') }}</label>
                        <div class="form-control bg-light">{{ $message['station_label'] }}</div>
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
                            <a class="btn btn-outline-primary" href="{{ $message['source_url'] }}">{{ __('app.contact_center.open_source_action') }}</a>
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const allProducers = document.getElementById('allProducers');
            const specificProducer = document.getElementById('specificProducer');
            const producerSelect = document.getElementById('producerSelect');

            if (!allProducers || !specificProducer || !producerSelect) {
                return;
            }

            const toggleProducerSelect = function () {
                producerSelect.style.display = specificProducer.checked ? 'block' : 'none';
            };

            allProducers.addEventListener('change', toggleProducerSelect);
            specificProducer.addEventListener('change', toggleProducerSelect);

            toggleProducerSelect();
        });
    </script>
@endpush
