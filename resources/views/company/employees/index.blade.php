@php
    $title = __('app.company.employees.title');
    $statusClass = static fn (?string $status): string => ($status ?? 'active') === 'active' ? 'success' : 'secondary';
@endphp

@extends('layouts.portal-dashboard', ['title' => $title])

@section('page_layout_class', 'company-employees-layout')

@push('styles')
    <style>
        .company-employees-layout {
            padding-top: 0 !important;
        }

        .company-employees-layout .company-employees-surface {
            background: #e9ecef;
            border-radius: 6px;
            padding: 2rem;
        }

        .company-employees-layout .company-employees-table-scroll {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
        }

        .company-employees-layout .company-employees-table {
            min-width: 1120px;
            table-layout: fixed;
            width: 100%;
        }

        .company-employees-layout .company-employees-table th,
        .company-employees-layout .company-employees-table td {
            white-space: normal;
            vertical-align: top;
            word-break: break-word;
        }

        .company-employees-layout .company-role-option {
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 6px;
            background: #fff;
            padding: 1rem;
            min-height: 100%;
        }

        .company-employees-layout .company-role-option .form-check-input {
            margin-top: .35rem;
        }

        .company-employees-layout .company-password-control {
            position: relative;
        }

        .company-employees-layout .company-password-control .form-control {
            padding-inline-end: 3rem;
        }

        .company-employees-layout .company-password-toggle {
            align-items: center;
            background: transparent;
            border: 0;
            color: var(--bs-secondary-color, #6c757d);
            display: inline-flex;
            font-size: 1.1rem;
            inset-block: 0;
            inset-inline-end: .25rem;
            justify-content: center;
            margin: auto 0;
            padding: 0;
            position: absolute;
            width: 2.5rem;
        }

        .company-employees-layout .company-password-toggle:hover,
        .company-employees-layout .company-password-toggle:focus {
            color: var(--bs-danger, #721d18);
            outline: none;
        }

        @media (max-width: 767.98px) {
            .company-employees-layout .company-employees-surface {
                padding: 1.25rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
        <div>
            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                <span class="position-relative">{{ __('app.company.employees.title') }}</span>
            </h2>
            <div class="text-muted">{{ __('app.company.employees.intro') }}</div>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-primary">{{ __('app.dashboard.back_to_dashboard') }}</a>
    </div>

    <div class="company-employees-surface">
        @if ($canManageEmployees)
            <div class="card mb-4">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.company.employees.add_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('company.employees.store') }}" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label for="name" class="form-label">{{ __('app.admin.users.name') }}</label>
                            <input id="name" name="name" type="text" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">{{ __('app.admin.users.email') }}</label>
                            <input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label for="phone" class="form-label">{{ __('app.admin.users.phone') }}</label>
                            <input id="phone" name="phone" type="text" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="national_id" class="form-label">{{ __('app.admin.users.national_id') }}</label>
                            <input id="national_id" name="national_id" type="text" class="form-control" value="{{ old('national_id') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="job_title" class="form-label">{{ __('app.company.employees.job_title') }}</label>
                            <input id="job_title" name="job_title" type="text" class="form-control" value="{{ old('job_title') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">{{ __('app.auth.password') }}</label>
                            <div class="company-password-control">
                                <input id="password" name="password" type="password" class="form-control" required autocomplete="new-password">
                                <button type="button" class="company-password-toggle" data-company-password-toggle aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                    <i class="ph ph-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">{{ __('app.auth.password_confirmation_label') }}</label>
                            <div class="company-password-control">
                                <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required autocomplete="new-password">
                                <button type="button" class="company-password-toggle" data-company-password-toggle aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                    <i class="ph ph-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('app.company.employees.role_label') }}</label>
                            <div class="row g-3">
                                @foreach ($roles as $role)
                                    <div class="col-lg-3 col-md-6">
                                        <label class="company-role-option d-flex gap-3">
                                            <input class="form-check-input" type="radio" name="role" value="{{ $role['name'] }}" @checked(old('role', 'company_creator') === $role['name']) required>
                                            <span>
                                                <span class="fw-semibold d-block">{{ $role['label'] }}</span>
                                                <span class="text-muted small">{{ $role['description'] }}</span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-danger">{{ __('app.company.employees.create_action') }}</button>
                            <span class="text-muted align-self-center">{{ __('app.company.employees.no_delete_hint') }}</span>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center">
                <div class="iq-header-title">
                    <h3 class="card-title">{{ __('app.company.employees.members_title') }}</h3>
                </div>
                <span class="badge bg-primary-subtle text-dark">{{ __('app.company.employees.members_count', ['count' => $members->count()]) }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive company-employees-table-scroll">
                    <table class="table mb-0 company-employees-table">
                        <colgroup>
                            <col style="width: 260px">
                            <col style="width: 180px">
                            <col style="width: 190px">
                            <col style="width: 150px">
                            <col style="width: 340px">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>{{ __('app.admin.users.name') }}</th>
                                <th>{{ __('app.company.employees.job_title') }}</th>
                                <th>{{ __('app.company.employees.role_label') }}</th>
                                <th>{{ __('app.applications.status') }}</th>
                                <th>{{ __('app.applications.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($members as $member)
                                @php
                                    $memberUser = $member['user'];
                                    $memberPivot = $memberUser->pivot;
                                    $isPrimary = (bool) ($memberPivot?->is_primary ?? false);
                                    $isSelf = (int) $memberUser->getKey() === (int) $user->getKey();
                                    $currentCompanyRole = $member['roles']->first(fn (string $roleName): bool => str_starts_with($roleName, 'company_'));
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $memberUser->displayName() }}</div>
                                        <div class="text-muted">{{ $memberUser->email }}</div>
                                        @if ($isPrimary)
                                            <span class="badge bg-danger mt-2">{{ __('app.company.employees.primary_owner_badge') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $memberPivot?->job_title ?: __('app.dashboard.not_available') }}</td>
                                    <td>
                                        @forelse ($member['roles'] as $roleName)
                                            <span class="badge bg-primary-subtle text-dark me-1 mb-1">{{ __('app.roles.'.$roleName) }}</span>
                                        @empty
                                            {{ __('app.dashboard.no_roles') }}
                                        @endforelse
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $statusClass($memberPivot?->status) }}">
                                            {{ __('app.statuses.'.($memberPivot?->status ?? 'active')) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($canManageEmployees && ! $isPrimary && ! $isSelf)
                                            <form method="POST" action="{{ route('company.employees.update', $memberUser->getKey()) }}" class="row g-2 align-items-end">
                                                @csrf
                                                <div class="col-md-6">
                                                    <label class="form-label small">{{ __('app.admin.users.name') }}</label>
                                                    <input name="name" type="text" class="form-control" value="{{ old('member.'.$memberUser->getKey().'.name', $memberUser->name) }}" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small">{{ __('app.company.employees.role_label') }}</label>
                                                    <select name="role" class="form-select" required>
                                                        @foreach ($roles as $role)
                                                            <option value="{{ $role['name'] }}" @selected(($currentCompanyRole ?: 'company_creator') === $role['name'])>{{ $role['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small">{{ __('app.admin.users.phone') }}</label>
                                                    <input name="phone" type="text" class="form-control" value="{{ $memberUser->phone }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small">{{ __('app.admin.users.national_id') }}</label>
                                                    <input name="national_id" type="text" class="form-control" value="{{ $memberUser->national_id }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small">{{ __('app.company.employees.job_title') }}</label>
                                                    <input name="job_title" type="text" class="form-control" value="{{ $memberPivot?->job_title }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small">{{ __('app.company.employees.new_password_optional') }}</label>
                                                    <div class="company-password-control">
                                                        <input name="password" type="password" class="form-control" autocomplete="new-password">
                                                        <button type="button" class="company-password-toggle" data-company-password-toggle aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                                            <i class="ph ph-eye-slash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small">{{ __('app.auth.password_confirmation_label') }}</label>
                                                    <div class="company-password-control">
                                                        <input name="password_confirmation" type="password" class="form-control" autocomplete="new-password">
                                                        <button type="button" class="company-password-toggle" data-company-password-toggle aria-label="{{ __('app.auth.show_password') }}" title="{{ __('app.auth.show_password') }}">
                                                            <i class="ph ph-eye-slash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-12 d-flex gap-2 flex-wrap">
                                                    <button type="submit" class="btn btn-sm btn-danger">{{ __('app.company.employees.save_action') }}</button>
                                                    <button type="submit"
                                                        formaction="{{ route('company.employees.status', $memberUser->getKey()) }}"
                                                        class="btn btn-sm btn-outline-warning"
                                                        name="status"
                                                        value="{{ ($memberPivot?->status ?? 'active') === 'active' ? 'inactive' : 'active' }}">
                                                        {{ ($memberPivot?->status ?? 'active') === 'active'
                                                            ? __('app.company.employees.deactivate_action')
                                                            : __('app.company.employees.activate_action') }}
                                                    </button>
                                                </div>
                                            </form>
                                        @else
                                            <span class="text-muted">{{ $isSelf ? __('app.company.employees.self_managed_in_profile') : __('app.company.employees.primary_managed_by_admin') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">{{ __('app.company.employees.empty_state') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-company-password-toggle]').forEach(function (toggle) {
            const passwordInput = toggle.closest('.company-password-control')?.querySelector('input');
            const icon = toggle.querySelector('i');

            if (!passwordInput || !icon) {
                return;
            }

            toggle.addEventListener('click', function () {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                const label = isPassword ? @js(__('app.auth.hide_password')) : @js(__('app.auth.show_password'));

                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                icon.classList.toggle('ph-eye', isPassword);
                icon.classList.toggle('ph-eye-slash', !isPassword);
                toggle.setAttribute('aria-label', label);
                toggle.setAttribute('title', label);
            });
        });
    </script>
@endpush
