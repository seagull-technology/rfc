@php
    $title = __('app.authority.dashboard.title');
    $isArabic = app()->getLocale() === 'ar';
    $approvedCount = $approvals->where('status', 'approved')->count();
    $reviewCount = $approvals->whereIn('status', ['pending', 'in_review'])->count();
    $metricPercent = static fn (int $value, int $total): int => $total > 0 ? (int) round(($value / $total) * 100) : 0;
    $statusClass = static fn (?string $status): string => match ($status) {
        'pending' => 'warning',
        'in_review' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
    };

    $metrics = [
        [
            'label' => $isArabic ? 'عدد الطلبات' : 'Requests count',
            'value' => $approvalStats['total'],
            'percent' => $metricPercent($approvalStats['total'], max($approvalStats['total'], 1)),
            'card_color' => 'danger',
            'progress_color' => 'danger',
        ],
        [
            'label' => $isArabic ? 'عدد الطلبات قيد الدراسة' : 'Requests in review',
            'value' => $reviewCount,
            'percent' => $metricPercent($reviewCount, max($approvalStats['total'], 1)),
            'card_color' => 'warning',
            'progress_color' => 'warning',
        ],
        [
            'label' => $isArabic ? 'عدد الطلبات الموافق عليها' : 'Approved requests',
            'value' => $approvedCount,
            'percent' => $metricPercent($approvedCount, max($approvalStats['total'], 1)),
            'card_color' => 'success',
            'progress_color' => 'success',
        ],
    ];
@endphp

@extends('layouts.authority-dashboard', ['title' => $title])

@section('page_layout_class', 'authority-dashboard-layout')

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

        .authority-dashboard-layout {
            padding-top: 0 !important;
        }

        .authority-dashboard-layout > .row {
            margin-bottom: 0;
        }

        .authority-dashboard-layout .card {
            margin-bottom: 1.5rem;
        }

        .authority-dashboard-layout .row .col-lg-4.col-md-6 > .card,
        .authority-dashboard-layout .row .col-lg-12.col-md-12 > .card {
            height: calc(100% - 1.5rem);
        }

        .authority-dashboard-layout .card-header {
            padding-bottom: 0;
        }

        .authority-dashboard-layout .card-dashboard .card-header {
            padding-top: 1.5rem;
        }

        .authority-dashboard-layout .card-dashboard .card-body > .table-responsive:first-child {
            margin-top: 0 !important;
        }

        .authority-dashboard-layout .table-responsive.rounded.py-4 {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        .authority-dashboard-layout table.table thead th,
        .authority-dashboard-layout table.table tbody td {
            white-space: nowrap;
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
            <div class="streamit-wraper-table">
                <div class="card card-dashboard">
                    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
                        <h2 class="episode-playlist-title wp-heading-inline">
                            <span class="position-relative">{{ $isArabic ? 'الطلبات' : 'Requests' }}</span>
                        </h2>
                    </div>
                    <div class="card-body pt-0">
                        <div class="mt-4 table-responsive">
                            <div class="table-responsive rounded py-4">
                                <table id="datatable" class="table" data-toggle="data-table">
                                    <thead>
                                        <tr class="ligth">
                                            <th>#</th>
                                            <th>{{ $isArabic ? 'رقم الطلب' : 'Request number' }}</th>
                                            <th>{{ $isArabic ? 'اسم المشروع' : 'Project name' }}</th>
                                            <th>{{ $isArabic ? 'اسم مقدم الطلب' : 'Applicant name' }}</th>
                                            <th>{{ $isArabic ? 'تاريخ تقديم الطلب' : 'Submission date' }}</th>
                                            <th>{{ __('app.applications.status') }}</th>
                                            <th>{{ __('app.authority.applications.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($approvals as $approval)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $approval->application?->code ?? __('app.dashboard.not_available') }}</td>
                                                <td>{{ $approval->application?->project_name ?? __('app.dashboard.not_available') }}</td>
                                                <td>{{ $approval->application?->submittedBy?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                                <td>{{ $approval->application?->submitted_at?->format('Y-m-d') ?: __('app.dashboard.not_available') }}</td>
                                                <td><span class="badge bg-{{ $statusClass($approval->status) }}">{{ $approval->localizedStatus() }}</span></td>
                                                <td>
                                                    <div class="flex align-items-center list-user-action">
                                                        <a class="btn btn-sm btn-icon btn-info-subtle rounded" href="{{ route('authority.applications.show', $approval->application) }}">
                                                            <span class="btn-inner"><i class="ph ph-eye fs-6"></i></span>
                                                        </a>
                                                    </div>
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
