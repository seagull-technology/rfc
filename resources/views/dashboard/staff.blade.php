@php
    $title = __('app.dashboard.title');
    $isArabic = app()->getLocale() === 'ar';
    $metricPercent = static fn (int $value, int $total): int => $total > 0 ? (int) round(($value / $total) * 100) : 0;
    $statusClass = static fn (?string $status): string => match ($status) {
        'draft' => 'secondary',
        'submitted' => 'warning',
        'under_review' => 'warning',
        'needs_clarification' => 'danger',
        'approved' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
    };

    $metrics = [
        [
            'label' => $isArabic ? 'عدد طلبات الانتاج' : 'Production requests',
            'value' => $applicationStats['total'],
            'percent' => $metricPercent($applicationStats['total'], max($applicationStats['total'], 1)),
            'card_color' => 'danger',
            'progress_color' => 'danger',
            'icon' => asset('images/clapboard.png'),
        ],
        [
            'label' => $isArabic ? 'عدد الطلبات قيد الدراسة' : 'Requests in review',
            'value' => $applicationStats['active_reviews'],
            'percent' => $metricPercent($applicationStats['active_reviews'], max($applicationStats['total'], 1)),
            'card_color' => 'warning',
            'progress_color' => 'warning',
            'icon' => asset('images/clapboard.png'),
        ],
        [
            'label' => $isArabic ? 'عدد طلبات الاستقصاء' : 'Scouting requests',
            'value' => $scoutingStats['total'],
            'percent' => $metricPercent($scoutingStats['total'], max($scoutingStats['total'], 1)),
            'card_color' => 'success',
            'progress_color' => 'success',
            'icon' => asset('images/Scout.png'),
        ],
    ];

    $recentApplications = $applications->take(8);
@endphp

@extends('layouts.portal-dashboard', ['title' => $title])

@section('page_layout_class', 'staff-dashboard-layout')

@push('styles')
    <style>
        .staff-dashboard-layout {
            padding-top: 0 !important;
        }

        .staff-dashboard-layout .portal-staff-hero {
            border: 0;
            border-radius: .5rem;
            margin-bottom: 0;
            overflow: hidden;
        }

        .staff-dashboard-layout .portal-staff-hero .card-body {
            padding: 2.5rem 1rem 2rem;
        }

        .staff-dashboard-layout .portal-staff-hero .avatar-130 {
            height: 130px;
            width: 130px;
        }

        .staff-dashboard-layout .portal-staff-hero h3 {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: .35rem;
        }

        .staff-dashboard-layout .card {
            margin-bottom: 1.5rem;
        }

        .staff-dashboard-layout .card-header {
            padding-bottom: 0;
        }

        .staff-dashboard-layout .table-responsive.rounded.py-4 {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        .staff-dashboard-layout table.table thead th,
        .staff-dashboard-layout table.table tbody td {
            vertical-align: middle;
            white-space: nowrap;
        }
    </style>
@endpush

@section('hero')
    <div class="card bg-image-12 portal-staff-hero">
        <div class="card-body">
            <div class="text-center">
                <div>
                    <img src="{{ asset('images/logo.svg') }}" alt="profile-img" class="rounded-pill avatar-130 img-fluid bg-white p-2" loading="lazy">
                </div>
                <div class="mt-3">
                    <h3 class="d-inline-block text-white">{{ $entity->displayName() }}</h3>
                    <div class="text-white-50">{{ $roles->isNotEmpty() ? $roles->join(' | ') : __('app.dashboard.no_roles') }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        @foreach ($metrics as $metric)
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="text-center">{{ $metric['label'] }}</div>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div>
                                <h2 class="counter">{{ $metric['value'] }}</h2>
                                {{ $metric['percent'] }}%
                            </div>
                            <div class="border bg-{{ $metric['card_color'] }}-subtle rounded p-3">
                                <img src="{{ $metric['icon'] }}" alt="metric icon">
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="progress bg-{{ $metric['card_color'] }}-subtle shadow-none w-100" style="height: 6px">
                                <div class="progress-bar bg-{{ $metric['progress_color'] }}" role="progressbar" style="width: {{ $metric['percent'] }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <div class="header-title">
                        <h2 class="episode-playlist-title wp-heading-inline">
                            <span class="position-relative">{{ __('app.dashboard.title') }}</span>
                        </h2>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-2"><span class="fw-600">{{ __('app.dashboard.signed_in_user') }}:</span> <span class="ms-2">{{ $user->displayName() }}</span></div>
                    <div class="mb-2"><span class="fw-600">{{ __('app.dashboard.current_entity') }}:</span> <span class="ms-2">{{ $entity->displayName() }}</span></div>
                    <div class="mb-2"><span class="fw-600">{{ __('app.dashboard.account_status') }}:</span> <span class="ms-2">{{ $user->localizedStatus() }}</span></div>
                    <div class="mb-0"><span class="fw-600">{{ __('app.dashboard.assigned_roles') }}:</span> <span class="ms-2">{{ $roles->isNotEmpty() ? $roles->join(', ') : __('app.dashboard.no_roles') }}</span></div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
                    <h2 class="episode-playlist-title wp-heading-inline">
                        <span class="position-relative">{{ $isArabic ? 'أحدث طلبات الانتاج' : 'Latest production requests' }}</span>
                    </h2>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive rounded py-4">
                        <table class="table">
                            <thead>
                                <tr class="ligth">
                                    <th>#</th>
                                    <th>{{ $isArabic ? 'رقم الطلب' : 'Request number' }}</th>
                                    <th>{{ $isArabic ? 'اسم المشروع' : 'Project name' }}</th>
                                    <th>{{ $isArabic ? 'الجهة' : 'Entity' }}</th>
                                    <th>{{ __('app.applications.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentApplications as $application)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $application->code }}</td>
                                        <td>{{ $application->project_name }}</td>
                                        <td>{{ $application->entity?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                        <td><span class="badge bg-{{ $statusClass($application->status) }}">{{ $application->localizedStatus() }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">{{ __('app.applications.empty_state') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
