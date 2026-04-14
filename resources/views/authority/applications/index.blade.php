@php
    $title = __('app.authority.applications.title');
@endphp

@extends('layouts.authority-dashboard', ['title' => $title])

@section('hero')
    <div class="card bg-image-12 portal-authority-hero">
        <div class="card-body">
            <div class="text-center">
                <div>
                    <img src="{{ asset('images/111.jpeg') }}" alt="profile-img" class="rounded-pill avatar-130 img-fluid" loading="lazy">
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

        .authority-inbox-layout table.table thead th,
        .authority-inbox-layout table.table tbody td {
            white-space: nowrap;
        }

        .authority-inbox-layout .authority-directory-table .btn-icon {
            min-width: 38px;
            min-height: 38px;
        }

        .authority-inbox-layout .authority-signal {
            margin-top: 0.5rem;
        }

        .authority-inbox-layout .authority-signal .small {
            display: block;
            line-height: 1.5;
            white-space: normal;
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
        <div class="col-lg-4 col-md-6">
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

        <div class="col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">{{ __('app.authority.dashboard.metrics.in_review') }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div>
                            <h2 class="counter">{{ $stats['pending'] + $stats['in_review'] }}</h2>
                        </div>
                        <div class="border bg-warning-subtle rounded p-3">
                            <img src="{{ asset('images/clapboard.png') }}" alt="in review">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress bg-warning-subtle shadow-none w-100" style="height: 6px">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $stats['total'] > 0 ? (($stats['pending'] + $stats['in_review']) / $stats['total']) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">{{ __('app.authority.dashboard.metrics.updates') }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <div>
                            <h2 class="counter">{{ $stats['updates'] }}</h2>
                        </div>
                        <div class="border bg-primary-subtle rounded p-3">
                            <img src="{{ asset('images/clapboard.png') }}" alt="updates">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="progress bg-primary-subtle shadow-none w-100" style="height: 6px">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $stats['total'] > 0 ? ($stats['updates'] / $stats['total']) * 100 : 0 }}%"></div>
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
                                <div class="col-lg-2 d-flex gap-2 flex-wrap">
                                    <button class="btn btn-primary flex-fill" type="submit">{{ __('app.applications.apply_filters_action') }}</button>
                                    <a class="btn btn-outline-primary flex-fill" href="{{ route('authority.applications.index') }}">{{ __('app.applications.clear_filters_action') }}</a>
                                </div>
                            </div>
                        </form>

                        <div class="mt-4 table-responsive authority-directory-table">
                            <div class="table-responsive rounded py-4">
                                <table class="table mb-0">
                                    <thead>
                                        <tr class="ligth">
                                            <th>#</th>
                                            <th>{{ __('app.applications.request_number') }}</th>
                                            <th>{{ __('app.applications.project_name') }}</th>
                                            <th>{{ __('app.authority.applications.applicant') }}</th>
                                            <th>{{ __('app.applications.submitted_at_label') }}</th>
                                            <th>{{ __('app.applications.status') }}</th>
                                            <th>{{ __('app.authority.applications.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($approvals as $approval)
                                            @php
                                                $approvalSignal = $approvalSignals->get($approval->getKey(), ['active' => false]);
                                            @endphp
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $approval->application?->code }}</td>
                                                <td>
                                                    <div class="fw-semibold">{{ $approval->application?->project_name }}</div>
                                                    <div class="text-muted">{{ __('app.applications.work_categories.'.($approval->application?->work_category ?? 'feature_film')) }}</div>
                                                    @if ($approvalSignal['active'])
                                                        <div class="authority-signal">
                                                            <span class="badge bg-{{ $approvalSignal['class'] }}">{{ $approvalSignal['label'] }}</span>
                                                            @if ($approvalSignal['summary'])
                                                                <span class="small text-muted">{{ $approvalSignal['summary'] }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div>{{ $approval->application?->submittedBy?->displayName() ?? __('app.dashboard.not_available') }}</div>
                                                    <div class="text-muted">{{ $approval->localizedAuthority() }}</div>
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
                                                <td>
                                                    <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('authority.applications.show', $approval->application) }}">
                                                        <span class="btn-inner">
                                                            <i class="ph ph-eye fs-6"></i>
                                                        </span>
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7">{{ __('app.authority.applications.empty_state') }}</td>
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
