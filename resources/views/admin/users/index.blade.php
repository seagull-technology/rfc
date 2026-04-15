@php
    $title = __('app.admin.users.title');
    $breadcrumb = __('app.admin.navigation.users');
    $statusClass = static fn (string $status): string => match ($status) {
        'active' => 'success',
        'pending_review' => 'warning',
        'needs_completion' => 'info',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $primaryEntityForUser = static fn ($user) => $user->entities->sortByDesc(fn ($entity) => (int) ($entity->pivot?->is_primary ?? false))->first();
    $directoryTypeForUser = static function ($user) use ($primaryEntityForUser): ?string {
        if (filled($user->registration_type)) {
            return $user->registration_type;
        }

        $primaryEntity = $primaryEntityForUser($user);

        if (in_array($primaryEntity?->group?->code, ['rfc', 'admins', 'authorities'], true)) {
            return 'staff';
        }

        return null;
    };
    $tabTypes = collect(['student', 'company', 'ngo', 'school'])
        ->when(
            $users->contains(fn ($user) => $directoryTypeForUser($user) === 'staff') || $filters['registration_type'] === 'staff',
            fn ($types) => $types->push('staff')
        )
        ->values();
    $typedUsers = $tabTypes->mapWithKeys(fn (string $type): array => [
        $type => $users->filter(fn ($user) => $directoryTypeForUser($user) === $type)->values(),
    ]);
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('page_layout_class', 'admin-users-index-layout')

@push('styles')
    <style>
        .admin-users-index-layout {
            padding-top: 0;
        }

        .admin-users-index-layout .card {
            margin-bottom: 0;
        }

        .admin-users-index-layout > .row > [class*="col-"] {
            margin-bottom: 1.5rem;
        }

        .admin-users-index-layout .card-header {
            padding-bottom: 0;
        }

        .admin-users-index-layout .nav-pills .nav-link {
            white-space: nowrap;
        }

        .admin-users-index-layout table thead th,
        .admin-users-index-layout table tbody td {
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
                    <span class="position-relative">{{ __('app.admin.users.directory_title') }}</span>
                </h2>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">{{ __('app.admin.navigation.dashboard') }}</a>
                    <a class="btn btn-danger" href="{{ route('admin.users.create') }}">{{ __('app.admin.users.create_action') }}</a>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
                        <div class="col-xl-5">
                            <label for="filter-q" class="form-label">{{ __('app.admin.filters.search_label') }}</label>
                            <input id="filter-q" name="q" type="text" class="form-control bg-white" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.filters.users_search_placeholder') }}">
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
                            <label for="filter-registration-type" class="form-label">{{ __('app.admin.filters.registration_type_label') }}</label>
                            <select id="filter-registration-type" name="registration_type" class="form-select">
                                @foreach (['all', 'student', 'company', 'ngo', 'school', 'staff'] as $type)
                                    <option value="{{ $type }}" @selected($filters['registration_type'] === $type)>{{ $type === 'all' ? __('app.admin.filters.all_option') : __('app.registration_types.'.$type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-4">
                            <label for="filter-deleted" class="form-label">{{ __('app.admin.filters.deleted_label') }}</label>
                            <select id="filter-deleted" name="deleted" class="form-select">
                                @foreach (['all', 'without', 'only'] as $deleted)
                                    <option value="{{ $deleted }}" @selected($filters['deleted'] === $deleted)>{{ __('app.admin.filters.deleted_options.'.$deleted) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button class="btn btn-danger" type="submit">{{ __('app.admin.filters.apply_action') }}</button>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">{{ __('app.admin.filters.clear_action') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <ul class="nav nav-pills mb-0 nav-fill" id="pills-tab-users" role="tablist">
                @foreach ($tabTypes as $type)
                    <li class="nav-item">
                        <a class="nav-link p-3 fontSize20 {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" href="#user-{{ $type }}">
                            {{ __('app.registration_types.'.$type) }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="tab-content" id="pills-tab-users-content">
                @foreach ($typedUsers as $type => $rows)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }} border p-5" id="user-{{ $type }}" role="tabpanel">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="streamit-wraper-table">
                                    <div class="table-view table-space">
                                        <table id="users-table-{{ $type }}" class="data-tables table custom-table data-table-one custom-table-height" role="grid" data-toggle="data-table">
                                            <thead>
                                                <tr class="ligth">
                                                    <th>#</th>
                                                    <th>{{ __('app.admin.users.national_id') }}</th>
                                                    <th>{{ __('app.admin.users.name') }}</th>
                                                    <th>{{ __('app.admin.users.primary_entity') }}</th>
                                                    <th>{{ __('app.admin.users.status') }}</th>
                                                    <th>{{ __('app.admin.users.actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($rows as $user)
                                                    @php($primaryEntity = $primaryEntityForUser($user))
                                                    <tr>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td>{{ $user->national_id ?: __('app.dashboard.not_available') }}</td>
                                                        <td>
                                                            {{ $user->displayName() }}<br>
                                                            <span class="text-muted">{{ $user->email }}</span><br>
                                                            <span class="text-muted">{{ $user->username ?: __('app.dashboard.not_available') }}</span>
                                                        </td>
                                                        <td>
                                                            {{ $primaryEntity?->displayName() ?? __('app.dashboard.no_entity') }}<br>
                                                            <span class="text-muted">{{ $primaryEntity?->group?->displayName() ?? __('app.dashboard.not_available') }}</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-{{ $statusClass($user->status) }}">{{ $user->localizedStatus() }}</span>
                                                            @if ($user->trashed())
                                                                <br><span class="badge bg-danger mt-2">{{ __('app.admin.users.deleted_label') }}</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <div class="flex align-items-center list-user-action">
                                                                <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('admin.users.show', $user->getKey()) }}">
                                                                    <i class="ph ph-eye fs-6"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6">{{ __('app.admin.users.directory_title') }}: {{ __('app.admin.filters.all_option') }}</td>
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
