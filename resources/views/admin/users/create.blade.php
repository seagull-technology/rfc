@php
    $title = __('app.admin.users.create_title');
    $breadcrumb = __('app.admin.navigation.users');
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('content')
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
        <div>
            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                <span class="position-relative">{{ __('app.admin.users.create_title') }}</span>
            </h2>
            <div class="text-muted">{{ __('app.admin.users.intro') }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">{{ __('app.admin.navigation.dashboard') }}</a>
            <a class="btn btn-primary" href="{{ route('admin.users.index') }}">{{ __('app.admin.navigation.users') }}</a>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.users.create_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.store') }}" class="row g-3">
                        @csrf

                        <div class="col-md-6">
                            <label for="name" class="form-label">{{ __('app.admin.users.name') }}</label>
                            <input id="name" name="name" type="text" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label">{{ __('app.auth.username') }}</label>
                            <input id="username" name="username" type="text" class="form-control" value="{{ old('username') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">{{ __('app.auth.email') }}</label>
                            <input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="national_id" class="form-label">{{ __('app.auth.national_id') }}</label>
                            <input id="national_id" name="national_id" type="text" class="form-control" value="{{ old('national_id') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">{{ __('app.auth.mobile_number') }}</label>
                            <input id="phone" name="phone" type="text" class="form-control" value="{{ old('phone') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="job_title" class="form-label">{{ __('app.admin.entities.member_job_title') }}</label>
                            <input id="job_title" name="job_title" type="text" class="form-control" value="{{ old('job_title') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">{{ __('app.auth.password') }}</label>
                            <input id="password" name="password" type="password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">{{ __('app.auth.confirm_password') }}</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="entity_id" class="form-label">{{ __('app.admin.users.initial_entity') }}</label>
                            <select id="entity_id" name="entity_id" class="form-select" required>
                                <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                @foreach ($entities as $entity)
                                    <option value="{{ $entity->id }}" data-roles="{{ $entity->group->roles->pluck('name')->join(',') }}" @selected(old('entity_id') == $entity->id)>
                                        {{ $entity->displayName() }} ({{ $entity->group?->displayName() }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label">{{ __('app.admin.users.initial_role') }}</label>
                            <select id="role" name="role" class="form-select" required>
                                <option value="">{{ __('app.admin.select_entity_first') }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="is_primary" class="form-label">{{ __('app.admin.entities.member_primary') }}</label>
                            <select id="is_primary" name="is_primary" class="form-select">
                                <option value="1" @selected(old('is_primary', '1') === '1')>{{ __('app.admin.yes') }}</option>
                                <option value="0" @selected(old('is_primary') === '0')>{{ __('app.admin.no') }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">{{ __('app.admin.users.create_action') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const entitySelect = document.getElementById('entity_id');
            const roleSelect = document.getElementById('role');
            const selectedRole = @json(old('role'));
            const labels = @json($entities->flatMap(fn ($entity) => $entity->group->roles->pluck('name'))->unique()->mapWithKeys(fn ($roleName) => [$roleName => __('app.roles.'.$roleName)]));

            const populateRoles = () => {
                const selectedOption = entitySelect.options[entitySelect.selectedIndex];
                const roles = (selectedOption?.dataset.roles || '').split(',').filter(Boolean);

                roleSelect.innerHTML = '';

                if (!roles.length) {
                    roleSelect.innerHTML = `<option value="">{{ __('app.admin.select_entity_first') }}</option>`;
                    return;
                }

                roleSelect.innerHTML = `<option value="">{{ __('app.admin.select_placeholder') }}</option>`;

                roles.forEach((roleName) => {
                    const option = document.createElement('option');
                    option.value = roleName;
                    option.textContent = labels[roleName] || roleName;
                    option.selected = selectedRole === roleName;
                    roleSelect.appendChild(option);
                });
            };

            entitySelect.addEventListener('change', populateRoles);
            populateRoles();
        })();
    </script>
@endpush
