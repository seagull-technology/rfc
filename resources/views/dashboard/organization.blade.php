@php
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

    $productionMetrics = [
        [
            'label' => $isArabic ? 'عدد طلبات الانتاج' : 'Production requests',
            'value' => $applicationStats['total'],
            'percent' => $metricPercent($applicationStats['total'], max($applicationStats['total'], 1)),
            'card_color' => 'danger',
            'progress_color' => 'danger',
        ],
        [
            'label' => $isArabic ? 'عدد طلبات الانتاج قيد الدراسة' : 'Production requests in review',
            'value' => $applicationStats['active_reviews'],
            'percent' => $metricPercent($applicationStats['active_reviews'], max($applicationStats['total'], 1)),
            'card_color' => 'warning',
            'progress_color' => 'warning',
        ],
        [
            'label' => $isArabic ? 'عدد طلبات الانتاج الموافق عليها' : 'Approved production requests',
            'value' => $applicationStats['approved'],
            'percent' => $metricPercent($applicationStats['approved'], max($applicationStats['total'], 1)),
            'card_color' => 'success',
            'progress_color' => 'success',
        ],
    ];

    $inquiryMetrics = [
        [
            'label' => $isArabic ? 'عدد طلبات الاستقصاء' : 'Scouting requests',
            'value' => $scoutingStats['total'],
            'text_color' => 'danger',
            'card_color' => 'danger-subtle',
        ],
        [
            'label' => $isArabic ? 'عدد طلبات الاستقصاء قيد الدراسة' : 'Scouting requests in review',
            'value' => $scoutingStats['active_reviews'],
            'text_color' => 'warning',
            'card_color' => 'warning-subtle',
        ],
        [
            'label' => $isArabic ? 'عدد طلبات الاستقصاء الموافق عليها' : 'Approved scouting requests',
            'value' => $scoutingStats['approved'],
            'text_color' => 'success',
            'card_color' => 'success-subtle',
        ],
    ];
@endphp

@extends('layouts.portal-dashboard', ['title' => __('app.dashboard.organization_title')])

@section('page_layout_class', 'organization-dashboard-layout')

@push('styles')
    <style>
        .organization-dashboard-layout {
            padding-top: 0 !important;
        }

        .organization-dashboard-layout .portal-organization-hero {
            border: 0;
            border-radius: .5rem;
            margin-bottom: 0;
            overflow: hidden;
        }

        .organization-dashboard-layout .portal-organization-hero .card-body {
            padding: 2.5rem 1rem 2rem;
        }

        .organization-dashboard-layout .portal-organization-hero .avatar-130 {
            height: 130px;
            width: 130px;
        }

        .organization-dashboard-layout .portal-organization-hero h3 {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .organization-dashboard-layout .card {
            margin-bottom: 1.5rem;
        }

        .organization-dashboard-layout .card-header {
            padding-bottom: 0;
        }

        .organization-dashboard-layout .card-dashboard .card-header {
            padding-top: 1.5rem;
        }

        .organization-dashboard-layout .card-dashboard .card-header .btn-danger {
            min-height: 44px;
            padding: .75rem 1.25rem;
        }

        .organization-dashboard-layout .table-responsive.rounded.py-4 {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        .organization-dashboard-layout table.table thead th,
        .organization-dashboard-layout table.table tbody td {
            white-space: nowrap;
            vertical-align: middle;
        }

        @media (max-width: 767.98px) {
            .organization-dashboard-layout .portal-organization-hero .card-body {
                padding-top: 2rem;
                padding-bottom: 1.75rem;
            }

            .organization-dashboard-layout .portal-organization-hero h3 {
                font-size: 1.75rem;
            }
        }
    </style>
@endpush

@section('hero')
    <div class="card bg-image-12 portal-organization-hero">
        <div class="card-body">
            <div class="text-center">
                <div>
                    <img src="{{ asset('images/OIP.jpeg') }}" alt="profile-img" class="rounded-pill avatar-130 img-fluid" loading="lazy">
                </div>
                <div class="mt-3">
                    <h3 class="d-inline-block text-white">{{ $entity->displayName() }}</h3>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        @foreach ($productionMetrics as $metric)
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
                                <img src="{{ asset('images/clapboard.png') }}" alt="clapboard">
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
        <div class="col-lg-12 col-md-12">
            <div class="card card-dashboard">
                <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
                    <h2 class="episode-playlist-title wp-heading-inline">
                        <span class="position-relative">{{ __('app.applications.index_title') }}</span>
                    </h2>
                    <a class="btn btn-danger" href="{{ route('applications.create') }}"><i class="fa-solid fa-plus me-2"></i>{{ __('app.applications.create_action') }}</a>
                </div>
                <div class="card-body pt-0">
                    <div class="mt-4 table-responsive">
                        <div class="table-responsive rounded py-4">
                            <table id="organization-production-table" class="table" data-toggle="data-table">
                                <thead>
                                    <tr class="ligth">
                                        <th>#</th>
                                        <th>{{ $isArabic ? 'رقم الطلب' : 'Request number' }}</th>
                                        <th>{{ $isArabic ? 'اسم المشروع' : 'Project name' }}</th>
                                        <th>{{ $isArabic ? 'اسم مقدم الطلب' : 'Applicant name' }}</th>
                                        <th>{{ $isArabic ? 'تاريخ تقديم الطلب' : 'Submission date' }}</th>
                                        <th>{{ __('app.applications.status') }}</th>
                                        <th>{{ __('app.applications.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($applications as $application)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $application->code }}</td>
                                            <td>{{ $application->project_name }}</td>
                                            <td>{{ $application->submittedBy?->displayName() ?? $user->displayName() }}</td>
                                            <td>{{ $application->submitted_at?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                            <td><span class="badge bg-{{ $statusClass($application->status) }}">{{ $application->localizedStatus() }}</span></td>
                                            <td>
                                                <div class="flex align-items-center list-user-action">
                                                    <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('applications.show', $application) }}">
                                                        <span class="btn-inner"><i class="ph ph-eye fs-6"></i></span>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">{{ __('app.applications.empty_state') }}</td>
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

    <div class="row">
        @foreach ($inquiryMetrics as $metric)
            <div class="col-lg-4 col-md-6">
                <div class="card bg-{{ $metric['card_color'] }} rounded p-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h2 class="counter">{{ $metric['value'] }}</h2>
                                <div class="text-{{ $metric['text_color'] }} fw-600">{{ $metric['label'] }}</div>
                            </div>
                            <div>
                                <img src="{{ asset('images/Scout.png') }}" alt="scout">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="card card-dashboard">
                <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
                    <h2 class="episode-playlist-title wp-heading-inline">
                        <span class="position-relative">{{ $isArabic ? 'طلبات استقصاء' : 'Scouting requests' }}</span>
                    </h2>
                    <a class="btn btn-danger" href="{{ route('scouting-requests.create') }}"><i class="fa-solid fa-plus me-2"></i>{{ $isArabic ? 'تقديم طلب استقصاء' : 'Submit scouting request' }}</a>
                </div>
                <div class="card-body pt-0">
                    <div class="mt-4 table-responsive">
                        <div class="table-responsive rounded py-4">
                            <table id="organization-scouting-table" class="table" data-toggle="data-table">
                                <thead>
                                    <tr class="ligth">
                                        <th>#</th>
                                        <th>{{ $isArabic ? 'رقم الطلب' : 'Request number' }}</th>
                                        <th>{{ $isArabic ? 'اسم المشروع' : 'Project name' }}</th>
                                        <th>{{ $isArabic ? 'اسم مقدم الطلب' : 'Applicant name' }}</th>
                                        <th>{{ $isArabic ? 'تاريخ تقديم الطلب' : 'Submission date' }}</th>
                                        <th>{{ __('app.applications.status') }}</th>
                                        <th>{{ __('app.applications.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($scoutingRequests as $scoutingRequest)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $scoutingRequest->code }}</td>
                                            <td>{{ $scoutingRequest->project_name }}</td>
                                            <td>{{ $scoutingRequest->submittedBy?->displayName() ?? $user->displayName() }}</td>
                                            <td>{{ $scoutingRequest->submitted_at?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                            <td><span class="badge bg-{{ $statusClass($scoutingRequest->status) }}">{{ $scoutingRequest->localizedStatus() }}</span></td>
                                            <td>
                                                <div class="flex align-items-center list-user-action">
                                                    <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('scouting-requests.show', $scoutingRequest) }}">
                                                        <span class="btn-inner"><i class="ph ph-eye fs-6"></i></span>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">{{ $isArabic ? 'لا توجد طلبات استقصاء متاحة حالياً.' : 'There are no scouting requests available yet.' }}</td>
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
@endsection
