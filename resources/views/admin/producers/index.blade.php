@php
    $title = __('app.admin.producers.title');
    $breadcrumb = __('app.admin.navigation.producers');
    $statusClass = static fn (string $status): string => match ($status) {
        'active' => 'success',
        'pending_review' => 'warning',
        'needs_completion' => 'info',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $primaryOwner = static fn ($entity) => $entity->users->sortByDesc(fn ($user) => (int) ($user->pivot?->is_primary ?? false))->first();
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('page_layout_class', 'admin-producers-index-layout')

@push('styles')
    <style>
        .admin-producers-index-layout {
            padding-top: 0;
        }

        .admin-producers-index-layout .card {
            margin-bottom: 0;
        }

        .admin-producers-index-layout > .row > [class*="col-"] {
            margin-bottom: 1.5rem;
        }

        .admin-producers-index-layout .card-header {
            padding-bottom: 0;
        }

        .admin-producers-index-layout .nav-pills .nav-link {
            white-space: nowrap;
        }

        .admin-producers-index-layout table thead th,
        .admin-producers-index-layout table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .admin-producers-index-layout .producer-review-note {
            min-height: 132px;
        }

        .admin-producers-index-layout .offcanvas-body .form-control.bg-light {
            display: flex;
            align-items: center;
            min-height: 48px;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center">
                <h2 class="episode-playlist-title wp-heading-inline mb-0">
                    <span class="position-relative">{{ __('app.admin.producers.directory_title') }}</span>
                </h2>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.producers.index') }}" class="row g-3 align-items-end">
                        <div class="col-lg-8">
                            <label class="form-label" for="q">{{ __('app.admin.filters.search_label') }}</label>
                            <input id="q" name="q" type="text" class="form-control bg-white" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.producers.search_placeholder') }}">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="status">{{ __('app.admin.entities.status') }}</label>
                            <select id="status" name="status" class="form-control bg-white">
                                @foreach (['all', 'active', 'pending_review', 'needs_completion', 'rejected'] as $status)
                                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status === 'all' ? __('app.admin.filters.all_option') : __('app.statuses.'.$status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button class="btn btn-danger" type="submit">{{ __('app.admin.filters.apply_action') }}</button>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.producers.index') }}">{{ __('app.admin.filters.clear_action') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <ul class="nav nav-pills mb-0 nav-fill" id="pills-tab-producers" role="tablist">
                @foreach (['student', 'company', 'ngo', 'school'] as $type)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link p-4 fontSize20 {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" href="#producer-{{ $type }}" role="tab">
                            {{ __('app.registration_types.'.$type) }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content" id="pills-tabContent-producers">
                @foreach (['student', 'company', 'ngo', 'school'] as $type)
                    @php($rows = $groupedEntities[$type] ?? collect())
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }} border p-5" id="producer-{{ $type }}" role="tabpanel">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="streamit-wraper-table">
                                    <div class="table-view table-space">
                                        <table class="data-tables table custom-table data-table-one custom-table-height" role="grid" data-toggle="data-table">
                                            <thead>
                                                <tr class="ligth">
                                                    <th>#</th>
                                                    <th>{{ __('app.auth.registration_number') }}</th>
                                                    <th>{{ __('app.admin.entities.name') }}</th>
                                                    <th>{{ __('app.admin.entities.owner') }}</th>
                                                    <th>{{ __('app.admin.producers.submitted_at') }}</th>
                                                    <th>{{ __('app.admin.entities.status') }}</th>
                                                    <th>{{ __('app.admin.entities.actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($rows as $entity)
                                                    @php($owner = $primaryOwner($entity))
                                                    @php($offcanvasId = 'producer-review-'.$entity->getKey())
                                                    <tr>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td>{{ $entity->registration_no ?: ($entity->national_id ?: __('app.dashboard.not_available')) }}</td>
                                                        <td>
                                                            {{ $entity->displayName() }}<br>
                                                            <span class="text-muted">{{ $entity->localizedRegistrationType() }}</span>
                                                        </td>
                                                        <td>
                                                            {{ $owner?->displayName() ?? __('app.dashboard.not_available') }}<br>
                                                            <span class="text-muted">{{ $owner?->email ?: __('app.dashboard.not_available') }}</span>
                                                        </td>
                                                        <td>{{ optional($entity->created_at)->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                                        <td><span class="badge bg-{{ $statusClass($entity->status) }}">{{ $entity->localizedStatus() }}</span></td>
                                                        <td>
                                                            <div class="flex align-items-center list-user-action">
                                                                <a class="btn btn-sm btn-icon btn-info-subtle rounded" data-bs-toggle="offcanvas" href="#{{ $offcanvasId }}" role="button" aria-controls="{{ $offcanvasId }}" title="{{ __('app.admin.entities.review_title') }}">
                                                                    <i class="ph ph-pencil-simple fs-6"></i>
                                                                </a>
                                                                <a class="btn btn-sm btn-icon btn-success-subtle rounded" href="{{ route('admin.entities.show', $entity->getKey()) }}" title="{{ __('app.admin.producers.open_profile') }}">
                                                                    <i class="ph ph-eye fs-6"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="7">{{ __('app.admin.entities.review_queue_empty') }}</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @foreach ($rows as $entity)
                            @php($owner = $primaryOwner($entity))
                            @php($offcanvasId = 'producer-review-'.$entity->getKey())
                            <div class="offcanvas offcanvas-end offcanvas-width-80" tabindex="-1" id="{{ $offcanvasId }}" aria-labelledby="{{ $offcanvasId }}-label">
                                <div class="offcanvas-header">
                                    <h2 class="episode-playlist-title wp-heading-inline" id="{{ $offcanvasId }}-label">
                                        <span class="position-relative">{{ __('app.admin.entities.registration_details_title') }} - {{ __('app.registration_types.'.$type) }}</span>
                                    </h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                                </div>

                                <div class="offcanvas-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">{{ __('app.admin.entities.name') }}</label>
                                            <div class="form-control bg-light">{{ $entity->displayName() }}</div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">{{ __('app.admin.entities.status') }}</label>
                                            <div class="form-control bg-light">{{ $entity->localizedStatus() }}</div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">{{ __('app.auth.registration_number') }}</label>
                                            <div class="form-control bg-light">{{ $entity->registration_no ?: ($entity->national_id ?: __('app.dashboard.not_available')) }}</div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">{{ __('app.admin.entities.owner') }}</label>
                                            <div class="form-control bg-light">{{ $owner?->displayName() ?? __('app.dashboard.not_available') }}</div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">{{ __('app.admin.users.email') }}</label>
                                            <div class="form-control bg-light">{{ $entity->email ?: $owner?->email ?: __('app.dashboard.not_available') }}</div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">{{ __('app.admin.users.phone') }}</label>
                                            <div class="form-control bg-light">{{ $entity->phone ?: $owner?->phone ?: __('app.dashboard.not_available') }}</div>
                                        </div>

                                        @if ($type === 'student')
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">{{ __('app.admin.users.national_id') }}</label>
                                                <div class="form-control bg-light">{{ $entity->national_id ?: $owner?->national_id ?: __('app.dashboard.not_available') }}</div>
                                            </div>
                                        @else
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">{{ __('app.dashboard.address') }}</label>
                                                <div class="form-control bg-light">{{ data_get($entity->metadata, 'address', __('app.dashboard.not_available')) }}</div>
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">{{ __('app.dashboard.organization_description') }}</label>
                                                <div class="form-control bg-light">{{ data_get($entity->metadata, 'description', __('app.dashboard.not_available')) }}</div>
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">{{ __('app.admin.entities.download_registration_document') }}</label>
                                                @if (data_get($entity->metadata, 'registration_document_path'))
                                                    <a href="{{ route('admin.entities.registration-document', $entity->getKey()) }}" class="d-flex align-items-center gap-2 text-decoration-none">
                                                        <i class="ph ph-file fs-5"></i>
                                                        <span>{{ data_get($entity->metadata, 'registration_document_name', __('app.admin.entities.download_registration_document')) }}</span>
                                                    </a>
                                                @else
                                                    <div class="form-control bg-light">{{ __('app.dashboard.not_available') }}</div>
                                                @endif
                                            </div>
                                        @endif

                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">{{ __('app.admin.entities.latest_review_note') }}</label>
                                            <div class="form-control bg-light">{{ data_get($entity->metadata, 'review.note', __('app.dashboard.not_available')) }}</div>
                                        </div>

                                        @if (in_array($entity->registration_type, ['ngo', 'school'], true) && in_array($entity->status, ['needs_completion', 'rejected'], true))
                                            @php($signedCompletionUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('registration.completion.link.edit', now()->addDays(7), ['entity' => $entity->getKey()]))
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">{{ __('app.admin.entities.completion_link_label') }}</label>
                                                <input type="text" class="form-control bg-light" value="{{ $signedCompletionUrl }}" readonly>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="offcanvas-footer border-top">
                                    <div class="p-3">
                                        @if ($entity->isRegistrationReviewable())
                                            <form method="POST" action="{{ route('admin.entities.review', $entity->getKey()) }}">
                                                @csrf
                                                <div class="mb-3">
                                                    <label class="form-label" for="producer-note-{{ $entity->getKey() }}">{{ __('app.admin.entities.review_note') }}</label>
                                                    <textarea id="producer-note-{{ $entity->getKey() }}" name="note" class="form-control producer-review-note">{{ data_get($entity->metadata, 'review.note') }}</textarea>
                                                </div>
                                                <div class="d-flex gap-3 justify-content-end flex-wrap">
                                                    <button class="btn btn-success" type="submit" name="decision" value="approve">{{ __('app.admin.entities.quick_approve_action') }}</button>
                                                    <button class="btn btn-warning" type="submit" name="decision" value="needs_completion">{{ __('app.admin.entities.quick_needs_completion_action') }}</button>
                                                    <button class="btn btn-danger" type="submit" name="decision" value="reject">{{ __('app.admin.entities.quick_reject_action') }}</button>
                                                </div>
                                            </form>
                                        @else
                                            <div class="d-flex gap-3 justify-content-end flex-wrap">
                                                <a class="btn btn-outline-primary" href="{{ route('admin.entities.show', $entity->getKey()) }}">{{ __('app.admin.producers.open_profile') }}</a>
                                            </div>
                                        @endif
                                        @if (in_array($entity->registration_type, ['ngo', 'school'], true) && in_array($entity->status, ['needs_completion', 'rejected'], true))
                                            @php($signedCompletionUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('registration.completion.link.edit', now()->addDays(7), ['entity' => $entity->getKey()]))
                                            <div class="mt-3 text-end">
                                                <a class="btn btn-outline-warning" href="{{ $signedCompletionUrl }}" target="_blank" rel="noopener">{{ __('app.admin.entities.open_completion_link') }}</a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
