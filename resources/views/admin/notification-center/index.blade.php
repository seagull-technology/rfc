@extends('layouts.admin-dashboard', [
    'title' => __('app.admin.notification_center.title'),
    'breadcrumb' => __('app.admin.navigation.notification_center'),
])

@push('styles')
    <style>
        .notification-center-table {
            min-width: 1280px;
        }

        .notification-center-message {
            max-width: 28rem;
            white-space: normal;
        }

        .notification-center-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: .78rem;
        }

        .notification-center-response {
            max-width: 22rem;
            max-height: 8rem;
            overflow: auto;
            white-space: pre-wrap;
        }
    </style>
@endpush

@section('content')
    <div class="content-inner container-fluid pb-0" id="page_layout">
        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="mb-2">{{ __('app.admin.notification_center.title') }}</h1>
                <p class="text-muted mb-0">{{ __('app.admin.notification_center.intro') }}</p>
            </div>
            <a class="btn btn-primary" href="{{ route('admin.notification-center.export', request()->query()) }}">
                <i class="ph ph-download-simple me-1"></i>{{ __('app.admin.notification_center.export_action') }}
            </a>
        </div>

        <div class="row g-3 mb-4">
            @foreach ([
                'total' => 'ph-bell-ringing',
                'sent' => 'ph-check-circle',
                'failed' => 'ph-warning-circle',
                'skipped' => 'ph-prohibit',
                'pending' => 'ph-hourglass',
            ] as $statKey => $icon)
                <div class="col-xl col-md-4 col-6">
                    <div class="border rounded bg-white p-3 h-100">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <div class="text-muted small">{{ __('app.admin.notification_center.stats.'.$statKey) }}</div>
                                <div class="h3 mb-0">{{ number_format($stats[$statKey] ?? 0) }}</div>
                            </div>
                            <span class="avatar avatar-40 rounded bg-primary-subtle text-primary">
                                <i class="ph {{ $icon }} fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.notification-center.index') }}" class="row g-3 align-items-end">
                    <div class="col-xl-3 col-lg-6">
                        <label for="notification-q" class="form-label">{{ __('app.admin.filters.search_label') }}</label>
                        <input id="notification-q" type="search" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.notification_center.search_placeholder') }}">
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label for="notification-channel" class="form-label">{{ __('app.admin.notification_center.channel') }}</label>
                        <select id="notification-channel" name="channel" class="form-select">
                            <option value="">{{ __('app.admin.filters.all_option') }}</option>
                            @foreach ($channels as $channel)
                                <option value="{{ $channel }}" @selected($filters['channel'] === $channel)>{{ __('app.admin.notification_center.channels.'.$channel) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label for="notification-status" class="form-label">{{ __('app.admin.filters.status_label') }}</label>
                        <select id="notification-status" name="status" class="form-select">
                            <option value="">{{ __('app.admin.filters.all_option') }}</option>
                            @foreach (['pending', 'sent', 'failed', 'skipped'] as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __('app.admin.notification_center.statuses.'.$status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label for="notification-type" class="form-label">{{ __('app.admin.notification_center.type') }}</label>
                        <select id="notification-type" name="type" class="form-select">
                            <option value="">{{ __('app.admin.filters.all_option') }}</option>
                            @foreach ($types as $type)
                                @php
                                    $typeLabel = __('app.admin.notification_center.types.'.$type);
                                @endphp
                                <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ $typeLabel === 'app.admin.notification_center.types.'.$type ? \Illuminate\Support\Str::headline($type) : $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-1 col-md-6">
                        <label for="notification-date-from" class="form-label">{{ __('app.admin.notification_center.date_from') }}</label>
                        <input id="notification-date-from" type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
                    </div>
                    <div class="col-xl-1 col-md-6">
                        <label for="notification-date-to" class="form-label">{{ __('app.admin.notification_center.date_to') }}</label>
                        <input id="notification-date-to" type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
                    </div>
                    <div class="col-xl-1 col-md-6 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">{{ __('app.admin.filters.apply_action') }}</button>
                        <a href="{{ route('admin.notification-center.index') }}" class="btn btn-outline-secondary">{{ __('app.admin.filters.clear_action') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <h2 class="card-title mb-0">{{ __('app.admin.notification_center.table_title') }}</h2>
                    <span class="badge bg-light text-dark">{{ $logs->total() }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle notification-center-table">
                        <thead>
                            <tr>
                                <th>{{ __('app.admin.notification_center.created_at') }}</th>
                                <th>{{ __('app.admin.notification_center.channel') }}</th>
                                <th>{{ __('app.admin.filters.status_label') }}</th>
                                <th>{{ __('app.admin.notification_center.recipient') }}</th>
                                <th>{{ __('app.admin.notification_center.message') }}</th>
                                <th>{{ __('app.admin.notification_center.context') }}</th>
                                <th>{{ __('app.admin.notification_center.response') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($logs as $log)
                                @php
                                    $statusClass = [
                                        'sent' => 'success',
                                        'failed' => 'danger',
                                        'skipped' => 'secondary',
                                        'pending' => 'warning',
                                    ][$log->status] ?? 'light';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $log->created_at?->format('Y-m-d') }}</div>
                                        <div class="text-muted small">{{ $log->created_at?->format('H:i') }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary">{{ $log->localizedChannel() }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $statusClass }}">{{ $log->localizedStatus() }}</span>
                                        @if ($log->failed_at)
                                            <div class="text-muted small mt-1">{{ $log->failed_at->diffForHumans() }}</div>
                                        @elseif ($log->sent_at)
                                            <div class="text-muted small mt-1">{{ $log->sent_at->diffForHumans() }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $log->recipient_name ?: __('app.dashboard.not_available') }}</div>
                                        <div class="text-muted small">{{ $log->recipient_email ?: __('app.dashboard.not_available') }}</div>
                                        <div class="text-muted small">{{ $log->recipient_phone ?: __('app.dashboard.not_available') }}</div>
                                    </td>
                                    <td class="notification-center-message">
                                        <div class="fw-semibold">{{ $log->title ?: __('app.dashboard.not_available') }}</div>
                                        @if ($log->body)
                                            <div class="text-muted small mt-1">{{ $log->body }}</div>
                                        @endif
                                        @php
                                            $typeKey = $log->type_key ?: 'unknown';
                                            $typeLabel = __('app.admin.notification_center.types.'.$typeKey);
                                        @endphp
                                        <div class="notification-center-code text-muted mt-2">{{ $typeLabel === 'app.admin.notification_center.types.'.$typeKey ? \Illuminate\Support\Str::headline($typeKey) : $typeLabel }}</div>
                                    </td>
                                    <td>
                                        @if ($log->url)
                                            <a href="{{ $log->url }}" class="btn btn-sm btn-outline-primary">{{ __('app.admin.notification_center.open_context') }}</a>
                                        @else
                                            <span class="text-muted">{{ __('app.dashboard.not_available') }}</span>
                                        @endif
                                        <div class="notification-center-code text-muted mt-2">
                                            {{ $log->context_type ?: '-' }} #{{ $log->context_id ?: '-' }}
                                        </div>
                                    </td>
                                    <td>
                                        @if ($log->error)
                                            <div class="text-danger small">{{ $log->error }}</div>
                                        @endif
                                        @if ($log->response)
                                            <pre class="notification-center-response bg-light border rounded p-2 small mb-0">{{ json_encode($log->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @else
                                            <span class="text-muted">{{ __('app.dashboard.not_available') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">{{ __('app.admin.notification_center.empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($logs->hasPages())
                    <div class="mt-3">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
