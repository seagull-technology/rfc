@php
    $title = __('app.admin.groups.title');
    $breadcrumb = __('app.admin.navigation.groups');
    $canManagePermissions = auth()->user()?->can('permissions.manage') ?? false;
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('content')
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
        <div>
            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                <span class="position-relative">{{ __('app.admin.groups.title') }}</span>
            </h2>
            <div class="text-muted">{{ __('app.admin.groups.intro') }}</div>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">{{ __('app.admin.navigation.dashboard') }}</a>
    </div>

    <div class="row">
        <div class="col-xl-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">{{ __('app.admin.groups.title') }}</span>
                            <h2 class="counter mt-2">{{ $groups->count() }}</h2>
                        </div>
                        <div class="rounded p-3 bg-primary-subtle">
                            <i class="ph ph-stack fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">{{ __('app.admin.entities.title') }}</span>
                            <h2 class="counter mt-2">{{ $groups->sum('entities_count') }}</h2>
                        </div>
                        <div class="rounded p-3 bg-info-subtle">
                            <i class="ph ph-buildings fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">{{ __('app.admin.groups.allowed_roles') }}</span>
                            <h2 class="counter mt-2">{{ $groups->flatMap->roles->unique('id')->count() }}</h2>
                        </div>
                        <div class="rounded p-3 bg-success-subtle">
                            <i class="ph ph-shield-check fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card mt-4">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.filters.title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.groups.index') }}" class="row g-3">
                        <div class="col-xl-8">
                            <label for="filter-q" class="form-label">{{ __('app.admin.filters.search_label') }}</label>
                            <input id="filter-q" name="q" type="text" class="form-control" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.filters.groups_search_placeholder') }}">
                        </div>
                        <div class="col-xl-4">
                            <label for="filter-role" class="form-label">{{ __('app.admin.filters.role_label') }}</label>
                            <select id="filter-role" name="role" class="form-select">
                                <option value="all">{{ __('app.admin.filters.all_option') }}</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role }}" @selected($filters['role'] === $role)>{{ __('app.roles.'.$role) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button class="btn btn-primary" type="submit">{{ __('app.admin.filters.apply_action') }}</button>
                            <a class="btn btn-outline-primary" href="{{ route('admin.groups.index') }}">{{ __('app.admin.filters.clear_action') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @foreach ($groups as $group)
            <div class="col-12">
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center">
                        <div class="iq-header-title">
                            <h3 class="card-title mb-1">{{ $group->displayName() }}</h3>
                            <div class="text-muted">{{ $group->description }}</div>
                        </div>
                        <span class="badge bg-primary-subtle text-dark">{{ __('app.admin.groups.entities_count') }}: {{ $group->entities_count }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-xl-4">
                                <h6 class="mb-3">{{ __('app.admin.groups.allowed_roles') }}</h6>
                                <div class="d-flex gap-2 flex-wrap">
                                    @foreach ($group->roles as $role)
                                        <span class="badge bg-dark-subtle text-dark">{{ __('app.roles.'.$role->name) }}</span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-xl-8">
                                <div class="d-flex justify-content-between gap-3 flex-wrap align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-1">{{ __('app.admin.groups.role_permissions') }}</h6>
                                        <div class="text-muted small">{{ __('app.admin.groups.role_permissions_hint') }}</div>
                                    </div>
                                    @if (! $canManagePermissions)
                                        <span class="badge bg-secondary-subtle text-dark">{{ __('app.admin.groups.read_only') }}</span>
                                    @endif
                                </div>
                                <div class="row g-3">
                                    @foreach ($group->roles as $role)
                                        @php
                                            $rolePermissionNames = $role->permissions->pluck('name')->all();
                                            $roleIsLocked = $role->name === 'super_admin';
                                            $roleDomId = 'group-'.$group->getKey().'-role-'.$role->getKey();
                                        @endphp
                                        <div class="col-lg-6">
                                            <form method="POST" action="{{ route('admin.groups.roles.permissions.update', $role->name) }}" class="border rounded p-3 h-100 bg-white">
                                                @csrf
                                                <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                                <input type="hidden" name="role" value="{{ $filters['role'] }}">

                                                <div class="d-flex justify-content-between gap-3 align-items-start mb-3">
                                                    <div>
                                                        <div class="fw-semibold">{{ __('app.roles.'.$role->name) }}</div>
                                                        <div class="text-muted small">{{ trans_choice('app.admin.groups.permissions_count', count($rolePermissionNames), ['count' => count($rolePermissionNames)]) }}</div>
                                                    </div>
                                                    @if ($roleIsLocked)
                                                        <span class="badge bg-danger-subtle text-dark">{{ __('app.admin.groups.locked_role') }}</span>
                                                    @endif
                                                </div>

                                                <div class="accordion" id="role-permissions-{{ $roleDomId }}">
                                                    @foreach ($permissionGroups as $permissionGroup => $permissions)
                                                        @php
                                                            $panelId = $roleDomId.'-permission-group-'.$loop->index;
                                                            $checkedInGroup = $permissions->filter(fn ($permission) => in_array($permission->name, $rolePermissionNames, true))->count();
                                                        @endphp
                                                        <div class="border-top py-2">
                                                            <button class="btn btn-link w-100 px-0 text-start text-decoration-none d-flex justify-content-between gap-3 align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $panelId }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $panelId }}">
                                                                <span class="fw-semibold text-dark">{{ __('app.admin.groups.permission_groups.'.$permissionGroup) }}</span>
                                                                <span class="badge bg-primary-subtle text-dark">{{ $checkedInGroup }}/{{ $permissions->count() }}</span>
                                                            </button>
                                                            <div id="{{ $panelId }}" class="collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#role-permissions-{{ $roleDomId }}">
                                                                <div class="row g-2 pb-2">
                                                                    @foreach ($permissions as $permission)
                                                                        <div class="col-12">
                                                                            <div class="form-check">
                                                                                <input id="{{ $roleDomId }}-permission-{{ $permission->getKey() }}" class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, $rolePermissionNames, true)) @disabled(! $canManagePermissions || $roleIsLocked)>
                                                                                <label class="form-check-label" for="{{ $roleDomId }}-permission-{{ $permission->getKey() }}">
                                                                                    {{ __('app.permissions.'.$permission->name) }}
                                                                                </label>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>

                                                @if ($canManagePermissions && ! $roleIsLocked)
                                                    <div class="mt-3">
                                                        <button class="btn btn-primary w-100" type="submit">{{ __('app.admin.groups.save_permissions') }}</button>
                                                    </div>
                                                @endif
                                            </form>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
