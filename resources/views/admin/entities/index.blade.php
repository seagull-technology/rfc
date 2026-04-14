@php
    $title = __('app.admin.entities.title');
    $breadcrumb = __('app.admin.navigation.entities');
    $entityStatusClass = static fn (string $status): string => match ($status) {
        'active' => 'success',
        'pending_review' => 'warning',
        'needs_completion' => 'info',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $primaryOwner = static fn ($entity) => $entity->users->sortByDesc(fn ($user) => (int) ($user->pivot?->is_primary ?? false))->first();
    $typedEntities = collect(['student', 'company', 'ngo', 'school'])
        ->mapWithKeys(fn (string $type): array => [
            $type => $entities->filter(fn ($entity) => $entity->registration_type === $type)->values(),
        ]);
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('page_layout_class', 'admin-entities-index-layout')

@push('styles')
    <style>
        .admin-entities-index-layout {
            padding-top: 0;
        }

        .admin-entities-index-layout .card {
            margin-bottom: 0;
        }

        .admin-entities-index-layout > .row > [class*="col-"] {
            margin-bottom: 1.5rem;
        }

        .admin-entities-index-layout .card-header {
            padding-bottom: 0;
        }

        .admin-entities-index-layout .nav-pills .nav-link {
            white-space: nowrap;
        }

        .admin-entities-index-layout table thead th,
        .admin-entities-index-layout table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center">
                <h2 class="episode-playlist-title wp-heading-inline">
                    <span class="position-relative">{{ __('app.admin.entities.directory_title') }}</span>
                </h2>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">{{ __('app.admin.navigation.dashboard') }}</a>
                    <a class="btn btn-danger" href="{{ route('admin.entities.create') }}">{{ __('app.admin.entities.create_action') }}</a>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.entities.index') }}" class="row g-3 align-items-end">
                        <div class="col-xl-4">
                            <label for="filter-q" class="form-label">{{ __('app.admin.filters.search_label') }}</label>
                            <input id="filter-q" name="q" type="text" class="form-control bg-white" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.filters.entities_search_placeholder') }}">
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label for="filter-status" class="form-label">{{ __('app.admin.filters.status_label') }}</label>
                            <select id="filter-status" name="status" class="form-select">
                                @foreach (['all', 'active', 'inactive', 'pending_review', 'needs_completion', 'rejected'] as $status)
                                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status === 'all' ? __('app.admin.filters.all_option') : __('app.statuses.'.$status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label for="filter-group" class="form-label">{{ __('app.admin.filters.group_label') }}</label>
                            <select id="filter-group" name="group_id" class="form-select">
                                <option value="">{{ __('app.admin.filters.all_option') }}</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}" @selected($filters['group_id'] === (string) $group->id)>{{ $group->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label for="filter-deleted" class="form-label">{{ __('app.admin.filters.deleted_label') }}</label>
                            <select id="filter-deleted" name="deleted" class="form-select">
                                @foreach (['all', 'without', 'only'] as $deleted)
                                    <option value="{{ $deleted }}" @selected($filters['deleted'] === $deleted)>{{ __('app.admin.filters.deleted_options.'.$deleted) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label for="filter-registration-type" class="form-label">{{ __('app.admin.filters.registration_type_label') }}</label>
                            <select id="filter-registration-type" name="registration_type" class="form-select">
                                @foreach (['all', 'student', 'company', 'ngo', 'school', 'staff'] as $type)
                                    <option value="{{ $type }}" @selected($filters['registration_type'] === $type)>{{ $type === 'all' ? __('app.admin.filters.all_option') : __('app.registration_types.'.$type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button class="btn btn-danger" type="submit">{{ __('app.admin.filters.apply_action') }}</button>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.entities.index') }}">{{ __('app.admin.filters.clear_action') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($reviewQueue->isNotEmpty())
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.entities.review_queue_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive border rounded py-3">
                            <table class="table mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('app.admin.entities.name') }}</th>
                                        <th>{{ __('app.admin.entities.owner') }}</th>
                                        <th>{{ __('app.admin.entities.status') }}</th>
                                        <th>{{ __('app.admin.entities.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reviewQueue as $reviewEntity)
                                        @php($owner = $primaryOwner($reviewEntity))
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.entities.show', $reviewEntity->getKey()) }}">{{ $reviewEntity->displayName() }}</a><br>
                                                <span class="text-muted">{{ $reviewEntity->localizedRegistrationType() }}</span><br>
                                                <span class="text-muted">{{ $reviewEntity->registration_no ?: __('app.dashboard.not_available') }}</span>
                                            </td>
                                            <td>
                                                {{ $owner?->displayName() ?? __('app.dashboard.not_available') }}<br>
                                                <span class="text-muted">{{ $owner?->email ?: __('app.dashboard.not_available') }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $entityStatusClass($reviewEntity->status) }}">{{ $reviewEntity->localizedStatus() }}</span><br>
                                                <span class="text-muted d-inline-block mt-2">{{ data_get($reviewEntity->metadata, 'review.note', __('app.dashboard.not_available')) }}</span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.entities.show', $reviewEntity->getKey()) }}">{{ __('app.admin.dashboard.table_action') }}</a>
                                                    <form method="POST" action="{{ route('admin.entities.review', $reviewEntity->getKey()) }}">
                                                        @csrf
                                                        <input type="hidden" name="decision" value="approve">
                                                        <button class="btn btn-sm btn-outline-success" type="submit">{{ __('app.admin.entities.quick_approve_action') }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.entities.review', $reviewEntity->getKey()) }}">
                                                        @csrf
                                                        <input type="hidden" name="decision" value="needs_completion">
                                                        <input type="hidden" name="note" value="{{ __('app.admin.entities.default_completion_note') }}">
                                                        <button class="btn btn-sm btn-outline-warning" type="submit">{{ __('app.admin.entities.quick_needs_completion_action') }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.entities.review', $reviewEntity->getKey()) }}">
                                                        @csrf
                                                        <input type="hidden" name="decision" value="reject">
                                                        <input type="hidden" name="note" value="{{ __('app.admin.entities.default_rejection_note') }}">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('app.admin.entities.quick_reject_action') }}</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12">
            <ul class="nav nav-pills mb-0 nav-fill" id="pills-tab-producers" role="tablist">
                @foreach (['student', 'company', 'ngo', 'school'] as $type)
                    <li class="nav-item">
                        <a class="nav-link p-3 fontSize20 {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" href="#{{ $type }}">
                            {{ __('app.registration_types.'.$type) }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="tab-content" id="pills-tabContent-1">
                @foreach ($typedEntities as $type => $rows)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }} border p-5" id="{{ $type }}" role="tabpanel">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="streamit-wraper-table">
                                    <div class="table-view table-space">
                                        <table id="entities-table-{{ $type }}" class="data-tables table custom-table data-table-one custom-table-height" role="grid" data-toggle="data-table">
                                            <thead>
                                                <tr class="ligth">
                                                    <th>#</th>
                                                    <th>{{ __('app.auth.registration_number') }}</th>
                                                    <th>{{ __('app.admin.entities.name') }}</th>
                                                    <th>{{ __('app.admin.entities.owner') }}</th>
                                                    <th>{{ __('app.admin.entities.status') }}</th>
                                                    <th>{{ __('app.admin.entities.actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($rows as $entity)
                                                    @php($owner = $primaryOwner($entity))
                                                    <tr>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td>{{ $entity->registration_no ?: ($entity->national_id ?: __('app.dashboard.not_available')) }}</td>
                                                        <td>
                                                            {{ $entity->displayName() }}<br>
                                                            <span class="text-muted">{{ $entity->group?->displayName() ?? __('app.dashboard.not_available') }}</span>
                                                        </td>
                                                        <td>
                                                            {{ $owner?->displayName() ?? __('app.dashboard.not_available') }}<br>
                                                            <span class="text-muted">{{ $owner?->email ?: __('app.dashboard.not_available') }}</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-{{ $entityStatusClass($entity->status) }}">{{ $entity->localizedStatus() }}</span>
                                                            @if ($entity->trashed())
                                                                <br><span class="badge bg-danger mt-2">{{ __('app.admin.entities.deleted_label') }}</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <div class="flex align-items-center list-user-action">
                                                                <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('admin.entities.show', $entity->getKey()) }}">
                                                                    <i class="ph ph-eye fs-6"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6">{{ __('app.admin.entities.review_queue_empty') }}</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
