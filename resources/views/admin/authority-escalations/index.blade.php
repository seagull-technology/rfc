@php
    $title = __('app.admin.authority_escalations.title');
    $breadcrumb = __('app.admin.navigation.authority_escalations');
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@push('styles')
    <style>
        .authority-escalations-layout {
            padding-top: 0;
        }

        .authority-escalations-layout .card {
            margin-bottom: 1.5rem;
        }

        .authority-escalations-layout .select2-container {
            width: 100% !important;
        }

        .authority-escalations-layout .hero-card .card-body {
            padding: 1.5rem;
        }

        .authority-escalations-layout .authority-card .card-body {
            padding: 1.25rem;
        }

        .authority-escalations-layout .authority-badge-stack {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }

        .authority-escalations-layout .authority-meta {
            color: #6c757d;
            font-size: .875rem;
        }
    </style>
@endpush

@section('page_layout_class', 'authority-escalations-layout')

@section('content')
    <div class="row g-3">
        <div class="col-12">
            <div class="card hero-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                                <span class="position-relative">{{ __('app.admin.authority_escalations.directory_title') }}</span>
                            </h2>
                            <div class="text-muted">{{ __('app.admin.authority_escalations.intro') }}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <a class="btn btn-outline-dark" href="{{ route('admin.authority-escalations.report') }}">{{ __('app.admin.authority_escalations.report_action') }}</a>
                            <div class="authority-badge-stack">
                            <span class="badge bg-dark">{{ __('app.admin.authority_escalations.metrics.authorities') }}: {{ $stats['authorities'] }}</span>
                            <span class="badge bg-secondary">{{ __('app.admin.authority_escalations.metrics.configured') }}: {{ $stats['configured'] }}</span>
                            <span class="badge bg-warning text-dark">{{ __('app.admin.authority_escalations.metrics.due_soon_approvals') }}: {{ $stats['due_soon_approvals'] }}</span>
                            <span class="badge bg-danger">{{ __('app.admin.authority_escalations.metrics.overdue_approvals') }}: {{ $stats['overdue_approvals'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @foreach ([
            ['label' => __('app.admin.authority_escalations.metrics.authorities'), 'value' => $stats['authorities']],
            ['label' => __('app.admin.authority_escalations.metrics.configured'), 'value' => $stats['configured']],
            ['label' => __('app.admin.authority_escalations.metrics.live_approvals'), 'value' => $stats['live_approvals']],
            ['label' => __('app.admin.authority_escalations.metrics.due_soon_approvals'), 'value' => $stats['due_soon_approvals']],
            ['label' => __('app.admin.authority_escalations.metrics.overdue_approvals'), 'value' => $stats['overdue_approvals']],
        ] as $metric)
            <div class="col-xl col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6>{{ $metric['label'] }}</h6>
                        <h3>{{ $metric['value'] }}</h3>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-12">
            <div class="row g-3">
                @forelse ($authorities as $row)
                    @php($entity = $row['entity'])
                    <div class="col-12">
                        <div class="card authority-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                    <div>
                                        <h4 class="mb-1">{{ $entity->displayName() }}</h4>
                                        <div class="authority-meta">{{ $entity->code ?: __('app.dashboard.not_available') }}</div>
                                    </div>
                                    <div class="authority-badge-stack">
                                        <span class="badge bg-info-subtle text-dark">{{ __('app.admin.authority_escalations.live_badges.live', ['count' => $row['live_approvals']]) }}</span>
                                        <span class="badge bg-warning-subtle text-dark">{{ __('app.admin.authority_escalations.live_badges.due_soon', ['count' => $row['due_soon_approvals']]) }}</span>
                                        <span class="badge bg-danger-subtle text-dark">{{ __('app.admin.authority_escalations.live_badges.overdue', ['count' => $row['overdue_approvals']]) }}</span>
                                        <span class="badge bg-dark-subtle text-dark">{{ __('app.admin.authority_escalations.live_badges.escalated', ['count' => $row['escalated_approvals']]) }}</span>
                                    </div>
                                </div>

                                <div class="authority-badge-stack mb-3">
                                    @forelse ($row['approval_codes'] as $approvalCode)
                                        <span class="badge bg-secondary-subtle text-dark">
                                            {{ __('app.applications.required_approval_options.'.$approvalCode) }}
                                        </span>
                                    @empty
                                        <span class="text-muted">{{ __('app.admin.authority_escalations.no_approval_codes') }}</span>
                                    @endforelse
                                </div>

                                <form method="POST" action="{{ route('admin.authority-escalations.update', $entity) }}" class="row g-3 align-items-start">
                                    @csrf
                                    <div class="col-xl-2 col-lg-3">
                                        <label class="form-label">{{ __('app.admin.authority_escalations.response_time_days') }}</label>
                                        <input
                                            type="text"
                                            inputmode="numeric"
                                            pattern="[0-9٠-٩۰-۹]*"
                                            name="response_time_days"
                                            class="form-control bg-white"
                                            value="{{ old('response_time_days', $row['settings']['response_time_days']) }}"
                                            placeholder="{{ __('app.admin.authority_escalations.response_time_placeholder') }}"
                                        >
                                    </div>
                                    <div class="col-xl-4 col-lg-4">
                                        <label class="form-label">{{ __('app.admin.authority_escalations.escalation_users') }}</label>
                                        <select name="escalation_user_ids[]" class="form-control bg-white select2" multiple data-placeholder="{{ __('app.admin.authority_escalations.escalation_users_placeholder') }}">
                                            @foreach ($escalationUsers as $user)
                                                <option value="{{ $user->getKey() }}" @selected(in_array($user->getKey(), $row['settings']['escalation_user_ids'], true))>
                                                    {{ $user->displayName() }}
                                                    @if ($user->primaryEntity())
                                                        - {{ $user->primaryEntity()->displayName() }}
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-xl-4 col-lg-4">
                                        <label class="form-label">{{ __('app.admin.authority_escalations.escalation_roles') }}</label>
                                        <select name="escalation_role_names[]" class="form-control bg-white select2" multiple data-placeholder="{{ __('app.admin.authority_escalations.escalation_roles_placeholder') }}">
                                            @foreach ($escalationRoles as $role)
                                                <option value="{{ $role->name }}" @selected(in_array($role->name, $row['settings']['escalation_role_names'], true))>
                                                    {{ __('app.roles.'.$role->name) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-lg-1 d-flex align-items-end">
                                        <button class="btn btn-danger w-100" type="submit">{{ __('app.admin.authority_escalations.save_action') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center text-muted py-4">{{ __('app.admin.authority_escalations.empty_state') }}</div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && typeof window.jQuery.fn.select2 === 'function') {
                window.jQuery('.select2').select2({
                    width: '100%'
                });
            }
        });
    </script>
@endpush
