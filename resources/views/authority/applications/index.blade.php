@php
    $title = __('app.authority.applications.title');
    $entityLogoUrl = \App\Support\EntityLogo::url($entity, 'images/111.jpeg');
@endphp

@extends('layouts.authority-dashboard', ['title' => $title])

@section('hero')
    <div class="card bg-image-12 portal-authority-hero">
        <div class="card-body">
            <div class="text-center">
                <div>
                    <img src="{{ $entityLogoUrl }}" alt="profile-img" class="rounded-pill avatar-130 img-fluid" loading="lazy">
                </div>
                <div class="mt-3">
                    <h3 class="d-inline-block text-white">{{ $entity->displayName() }}</h3>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_layout_class', 'authority-inbox-layout')

@push('styles')
    <style>
        .portal-authority-hero {
            border: 0;
            border-radius: .5rem;
            margin-bottom: 0;
            overflow: hidden;
        }

        .portal-authority-hero .card-body {
            padding: 2.5rem 1rem 2rem;
        }

        .portal-authority-hero .avatar-130 {
            height: 130px;
            width: 130px;
        }

        .portal-authority-hero h3 {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .authority-inbox-layout {
            padding-top: 0 !important;
        }

        .authority-inbox-layout .card {
            margin-bottom: 1.5rem;
        }

        .authority-inbox-layout .card-header {
            padding-bottom: 0;
        }

        .authority-inbox-layout .card-dashboard .card-header {
            padding-top: 1.5rem;
        }

        .authority-inbox-layout .authority-directory-table {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
        }

        .authority-inbox-layout .authority-requests-table-scroll {
            overflow-x: auto;
        }

        .authority-inbox-layout .authority-requests-table {
            min-width: 1240px;
            table-layout: fixed;
            width: 100%;
        }

        .authority-inbox-layout .authority-requests-table thead th,
        .authority-inbox-layout .authority-requests-table tbody td {
            white-space: normal;
            vertical-align: top;
            word-break: break-word;
        }

        .authority-inbox-layout .authority-directory-table .btn-icon {
            min-width: 38px;
            min-height: 38px;
        }

        .authority-inbox-layout .authority-requests-table thead th:first-child,
        .authority-inbox-layout .authority-requests-table tbody td:first-child,
        .authority-inbox-layout .authority-requests-actions-cell {
            text-align: center;
        }

        .authority-inbox-layout .authority-signal {
            margin-top: 0.5rem;
        }

        .authority-inbox-layout .authority-signal .small {
            display: block;
            line-height: 1.5;
            white-space: normal;
        }

        .authority-inbox-layout .authority-sla-badges {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            margin-top: .5rem;
        }

        @media (max-width: 767.98px) {
            .portal-authority-hero .card-body {
                padding-top: 2rem;
                padding-bottom: 1.75rem;
            }

            .portal-authority-hero h3 {
                font-size: 1.75rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">{{ __('app.authority.dashboard.metrics.total') }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div>
                            <h2 class="counter">{{ $stats['total'] }}</h2>
                        </div>
                        <div class="border bg-danger-subtle rounded p-3">
                            <img src="{{ asset('images/clapboard.png') }}" alt="requests">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress bg-danger-subtle shadow-none w-100" style="height: 6px">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $stats['total'] > 0 ? 100 : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">{{ __('app.authority.dashboard.metrics.my_assigned') }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div>
                            <h2 class="counter">{{ $stats['my_assigned'] }}</h2>
                        </div>
                        <div class="border bg-warning-subtle rounded p-3">
                            <img src="{{ asset('images/clapboard.png') }}" alt="my assigned">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress bg-warning-subtle shadow-none w-100" style="height: 6px">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $stats['total'] > 0 ? ($stats['my_assigned'] / $stats['total']) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">{{ __('app.authority.dashboard.metrics.shared_inbox') }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div>
                            <h2 class="counter">{{ $stats['shared_inbox'] }}</h2>
                        </div>
                        <div class="border bg-primary-subtle rounded p-3">
                            <img src="{{ asset('images/clapboard.png') }}" alt="shared inbox">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress bg-primary-subtle shadow-none w-100" style="height: 6px">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $stats['total'] > 0 ? ($stats['shared_inbox'] / $stats['total']) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">{{ __('app.authority.dashboard.metrics.updates') }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div>
                            <h2 class="counter">{{ $stats['updates'] }}</h2>
                        </div>
                        <div class="border bg-success-subtle rounded p-3">
                            <img src="{{ asset('images/clapboard.png') }}" alt="updates">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress bg-success-subtle shadow-none w-100" style="height: 6px">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $stats['total'] > 0 ? ($stats['updates'] / $stats['total']) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">{{ __('app.authority.dashboard.metrics.official_books') }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div>
                            <h2 class="counter">{{ $stats['official_books'] }}</h2>
                        </div>
                        <div class="border bg-info-subtle rounded p-3">
                            <img src="{{ asset('images/clapboard.png') }}" alt="official books">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress bg-info-subtle shadow-none w-100" style="height: 6px">
                            <div class="progress-bar bg-info" role="progressbar" style="width: {{ $stats['total'] > 0 ? ($stats['official_books'] / $stats['total']) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">{{ __('app.authority.dashboard.metrics.overdue') }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div>
                            <h2 class="counter">{{ $stats['overdue'] }}</h2>
                        </div>
                        <div class="border bg-danger-subtle rounded p-3">
                            <img src="{{ asset('images/clapboard.png') }}" alt="overdue">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress bg-danger-subtle shadow-none w-100" style="height: 6px">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $stats['total'] > 0 ? ($stats['overdue'] / $stats['total']) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">{{ __('app.authority.dashboard.metrics.escalated') }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div>
                            <h2 class="counter">{{ $stats['escalated'] }}</h2>
                        </div>
                        <div class="border bg-dark-subtle rounded p-3">
                            <img src="{{ asset('images/clapboard.png') }}" alt="escalated">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress bg-dark-subtle shadow-none w-100" style="height: 6px">
                            <div class="progress-bar bg-dark" role="progressbar" style="width: {{ $stats['total'] > 0 ? ($stats['escalated'] / $stats['total']) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="streamit-wraper-table">
                <div class="card card-dashboard">
                    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
                        <h2 class="episode-playlist-title wp-heading-inline">
                            <span class="position-relative">{{ __('app.authority.applications.title') }}</span>
                        </h2>
                    </div>
                    <div class="card-body pt-0">
                        <form method="GET" action="{{ route('authority.applications.index') }}" class="mb-4">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-8">
                                    <label class="form-label" for="q">{{ __('app.admin.filters.search_label') }}</label>
                                    <input id="q" name="q" type="text" class="form-control" value="{{ $filters['q'] }}" placeholder="{{ __('app.authority.applications.search_placeholder') }}">
                                </div>
                                <div class="col-lg-2">
                                    <label class="form-label" for="status">{{ __('app.applications.status') }}</label>
                                    <select id="status" name="status" class="form-control select2-basic-single">
                                        @foreach (['all', 'pending', 'in_review', 'approved', 'rejected'] as $status)
                                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status === 'all' ? __('app.applications.all_statuses') : __('app.approvals.statuses.'.$status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-2">
                                    <label class="form-label" for="ownership">{{ __('app.authority.applications.ownership') }}</label>
                                    <select id="ownership" name="ownership" class="form-control select2-basic-single">
                                        @foreach (['all', 'mine', 'shared'] as $ownership)
                                            <option value="{{ $ownership }}" @selected($filters['ownership'] === $ownership)>{{ __('app.authority.applications.ownership_filters.'.$ownership) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-12 d-flex gap-2 flex-wrap">
                                    <button class="btn btn-primary flex-fill" type="submit">{{ __('app.applications.apply_filters_action') }}</button>
                                    <a class="btn btn-outline-primary flex-fill" href="{{ route('authority.applications.index') }}">{{ __('app.applications.clear_filters_action') }}</a>
                                </div>
                            </div>
                        </form>

                        <div class="mt-4 table-responsive authority-directory-table">
                            <div class="table-responsive rounded py-4 authority-requests-table-scroll">
                                <table class="table mb-0 authority-requests-table">
                                    <colgroup>
                                        <col style="width: 64px">
                                        <col style="width: 136px">
                                        <col style="width: 278px">
                                        <col style="width: 160px">
                                        <col style="width: 170px">
                                        <col style="width: 126px">
                                        <col style="width: 126px">
                                        <col style="width: 110px">
                                        <col style="width: 70px">
                                    </colgroup>
                                    <thead>
                                        <tr class="ligth">
                                            <th>#</th>
                                            <th>{{ __('app.applications.request_number') }}</th>
                                            <th>{{ __('app.applications.project_name') }}</th>
                                            <th>{{ __('app.authority.applications.approval_type') }}</th>
                                            <th>{{ __('app.authority.applications.applicant') }}</th>
                                            <th>{{ __('app.authority.applications.ownership') }}</th>
                                            <th>{{ __('app.applications.submitted_at_label') }}</th>
                                            <th>{{ __('app.applications.status') }}</th>
                                            <th>{{ __('app.authority.applications.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($approvals as $approval)
                                            @php
                                                $approvalSignal = $approvalSignals->get($approval->getKey(), ['active' => false]);
                                                $approvalSlaSignal = $approvalSlaSignals->get($approval->getKey(), ['label' => null, 'is_overdue' => false, 'is_escalated' => false, 'due_at' => null]);
                                            @endphp
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $approval->application?->code }}</td>
                                                <td>
                                                    <div class="fw-semibold">{{ $approval->application?->project_name }}</div>
                                                    <div class="text-muted">{{ \App\Models\WorkCategory::labelFor($approval->application?->work_category ?? 'feature_film') }}</div>
                                                    @if ($approvalSignal['active'])
                                                        <div class="authority-signal">
                                                            <span class="badge bg-{{ $approvalSignal['class'] }}">{{ $approvalSignal['label'] }}</span>
                                                            @if ($approvalSignal['summary'])
                                                                <span class="small text-muted">{{ $approvalSignal['summary'] }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    <div class="authority-sla-badges">
                                                        @if ($approvalSlaSignal['label'])
                                                            <span class="badge bg-{{ $approvalSlaSignal['is_overdue'] ? 'danger' : ($approvalSlaSignal['is_due_soon'] ? 'warning text-dark' : 'secondary') }}">{{ $approvalSlaSignal['label'] }}</span>
                                                        @endif
                                                        @if ($approvalSlaSignal['is_escalated'])
                                                            <span class="badge bg-dark">{{ __('app.admin.authority_escalations.escalated_badge') }}</span>
                                                        @endif
                                                    </div>
                                                    @if ($approvalSlaSignal['due_at'])
                                                        <div class="small text-muted mt-1">{{ __('app.admin.authority_escalations.due_at_label', ['date' => $approvalSlaSignal['due_at']->format('Y-m-d h:i A')]) }}</div>
                                                        <div
                                                            class="small text-muted mt-1"
                                                            data-sla-countdown
                                                            data-due-at="{{ $approvalSlaSignal['due_at']->toIso8601String() }}"
                                                            data-remaining-template="{{ __('app.admin.authority_escalations.countdown_remaining') }}"
                                                            data-overdue-template="{{ __('app.admin.authority_escalations.countdown_overdue') }}"
                                                        ></div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary-subtle text-dark">{{ __('app.applications.required_approval_options.'.$approval->authority_code) }}</span>
                                                </td>
                                                <td>
                                                    <div>{{ $approval->application?->submittedBy?->displayName() ?? __('app.dashboard.not_available') }}</div>
                                                    <div class="text-muted">{{ $approval->localizedAuthority() }}</div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{ $approval->assigned_user_id ? 'warning' : 'primary' }}">
                                                        {{ $approval->assigned_user_id ? __('app.authority.applications.ownership_badges.mine') : __('app.authority.applications.ownership_badges.shared') }}
                                                    </span>
                                                </td>
                                                <td>{{ optional($approval->application?->submitted_at ?? $approval->application?->created_at)->format('Y-m-d') }}</td>
                                                <td>
                                                    @php
                                                        $badgeClass = match ($approval->status) {
                                                            'approved' => 'success',
                                                            'rejected' => 'danger',
                                                            'in_review' => 'warning',
                                                            default => 'secondary',
                                                        };
                                                    @endphp
                                                    <span class="badge bg-{{ $badgeClass }}">{{ $approval->localizedStatus() }}</span>
                                                </td>
                                                <td class="authority-requests-actions-cell">
                                                    <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('authority.applications.show', $approval->application) }}">
                                                        <span class="btn-inner">
                                                            <i class="ph ph-eye fs-6"></i>
                                                        </span>
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9">{{ __('app.authority.applications.empty_state') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.sla-countdown-script')
@endpush
