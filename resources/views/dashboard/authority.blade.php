@php
    $title = __('app.authority.dashboard.title');
    $metricPercent = static fn (int $value, int $total): int => $total > 0 ? (int) round(($value / $total) * 100) : 0;
    $statusClass = static fn (?string $status): string => match ($status) {
        'pending' => 'warning',
        'in_review' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
    };
    $activeReviewCount = $approvalStats['pending'] + $approvalStats['in_review'];
    $approvedCount = $approvals->where('status', 'approved')->count();
    $primaryMetrics = [
        [
            'label' => __('app.authority.dashboard.metrics.total'),
            'value' => $approvalStats['total'],
            'percent' => $metricPercent($approvalStats['total'], max($approvalStats['total'], 1)),
            'card_color' => 'danger',
            'progress_color' => 'danger',
        ],
        [
            'label' => __('app.authority.dashboard.metrics.in_review'),
            'value' => $activeReviewCount,
            'percent' => $metricPercent($activeReviewCount, max($approvalStats['total'], 1)),
            'card_color' => 'warning',
            'progress_color' => 'warning',
        ],
        [
            'label' => __('app.statuses.approved'),
            'value' => $approvedCount,
            'percent' => $metricPercent($approvedCount, max($approvalStats['total'], 1)),
            'card_color' => 'success',
            'progress_color' => 'success',
        ],
    ];
    $secondaryMetrics = [
        [
            'label' => __('app.authority.dashboard.metrics.my_assigned'),
            'value' => $approvalStats['my_assigned'],
            'badge_class' => 'bg-warning-subtle text-dark',
        ],
        [
            'label' => __('app.authority.dashboard.metrics.shared_inbox'),
            'value' => $approvalStats['shared_inbox'],
            'badge_class' => 'bg-primary-subtle text-dark',
        ],
        [
            'label' => __('app.authority.dashboard.metrics.updates'),
            'value' => $approvalStats['updates'],
            'badge_class' => 'bg-success-subtle text-dark',
        ],
        [
            'label' => __('app.authority.dashboard.metrics.official_books'),
            'value' => $approvalStats['official_books'],
            'badge_class' => 'bg-info-subtle text-dark',
        ],
        [
            'label' => __('app.authority.dashboard.metrics.overdue'),
            'value' => $approvalStats['overdue'],
            'badge_class' => 'bg-danger-subtle text-dark',
        ],
        [
            'label' => __('app.authority.dashboard.metrics.escalated'),
            'value' => $approvalStats['escalated'],
            'badge_class' => 'bg-dark text-white',
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
            margin-bottom: 1.5rem;
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
        .authority-dashboard-layout .row .col-lg-12.col-md-12 > .card,
        .authority-dashboard-layout .row .col-lg-3.col-md-6 > .card {
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
            vertical-align: middle;
        }

        .authority-dashboard-layout table.table thead th:not(:nth-child(3)),
        .authority-dashboard-layout table.table tbody td:not(:nth-child(3)) {
            white-space: nowrap;
        }

        .authority-dashboard-layout .authority-sla-badges {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            margin-top: .5rem;
        }

        .authority-dashboard-layout .authority-operations-strip {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            justify-content: flex-end;
        }

        .authority-dashboard-layout .authority-operations-strip .badge {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            border-radius: 999px;
            font-size: .82rem;
            font-weight: 500;
            padding: .55rem .85rem;
        }

        .authority-dashboard-layout .authority-operations-strip .count {
            font-weight: 700;
        }

        @media (max-width: 767.98px) {
            .portal-authority-hero .card-body {
                padding-top: 2rem;
                padding-bottom: 1.75rem;
            }

            .portal-authority-hero h3 {
                font-size: 1.75rem;
            }

            .authority-dashboard-layout .authority-operations-strip {
                justify-content: flex-start;
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
        @foreach ($primaryMetrics as $metric)
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
                        <span class="position-relative">{{ __('app.authority.navigation.applications') }}</span>
                    </h2>
                    <div class="authority-operations-strip">
                        @foreach ($secondaryMetrics as $metric)
                            <span class="badge {{ $metric['badge_class'] }}">
                                <span>{{ $metric['label'] }}</span>
                                <span class="count">{{ $metric['value'] }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="mt-4 table-responsive">
                        <div class="table-responsive rounded py-4">
                            <table id="authority-requests-table" class="table" data-toggle="data-table">
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
                                        @php($approvalSignal = $approvalSignals->get($approval->getKey(), ['active' => false]))
                                        @php($approvalSlaSignal = $approvalSlaSignals->get($approval->getKey(), ['label' => null, 'is_overdue' => false, 'is_escalated' => false, 'due_at' => null]))
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $approval->application?->code ?? __('app.dashboard.not_available') }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $approval->application?->project_name ?? __('app.dashboard.not_available') }}</div>
                                                @if ($approvalSignal['active'])
                                                    <div class="mt-2">
                                                        <span class="badge bg-{{ $approvalSignal['class'] }}">{{ $approvalSignal['label'] }}</span>
                                                    </div>
                                                @endif
                                                <div class="authority-sla-badges">
                                                    @if ($approvalSlaSignal['label'])
                                                        <span class="badge bg-{{ $approvalSlaSignal['is_overdue'] ? 'danger' : 'secondary' }}">{{ $approvalSlaSignal['label'] }}</span>
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
                                            <td>{{ $approval->application?->submittedBy?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $approval->assigned_user_id ? 'warning' : 'primary' }}">
                                                    {{ $approval->assigned_user_id ? __('app.authority.applications.ownership_badges.mine') : __('app.authority.applications.ownership_badges.shared') }}
                                                </span>
                                            </td>
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
@endsection

@push('scripts')
    @include('partials.sla-countdown-script')
@endpush
