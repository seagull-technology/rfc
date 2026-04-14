@php
    $title = __('app.permits.registry_title');
    $breadcrumb = __('app.admin.navigation.permits');
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('content')
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
        <div>
            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                <span class="position-relative">{{ __('app.permits.registry_title') }}</span>
            </h2>
            <div class="text-muted">{{ __('app.permits.registry_intro') }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="{{ route('admin.permits.export', request()->query()) }}">{{ __('app.reports.export_current') }}</a>
            <a class="btn btn-outline-primary" href="{{ route('admin.applications.index') }}">{{ __('app.admin.navigation.applications') }}</a>
        </div>
    </div>

    <div class="row mb-4">
        @foreach ([
            ['value' => $stats['total'], 'label' => __('app.permits.metrics.total'), 'color' => 'primary'],
            ['value' => $stats['active'], 'label' => __('app.permits.metrics.active'), 'color' => 'success'],
            ['value' => $stats['issued_this_month'], 'label' => __('app.permits.metrics.issued_this_month'), 'color' => 'info'],
        ] as $metric)
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted mb-2">{{ $metric['label'] }}</div>
                        <h3 class="mb-0 text-{{ $metric['color'] }}">{{ $metric['value'] }}</h3>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header"><div class="iq-header-title"><h3 class="card-title">{{ __('app.admin.filters.title') }}</h3></div></div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.permits.index') }}" class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="q">{{ __('app.admin.filters.search_label') }}</label>
                    <input id="q" name="q" type="text" class="form-control" value="{{ $filters['q'] }}" placeholder="{{ __('app.permits.search_placeholder') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="status">{{ __('app.admin.filters.status_label') }}</label>
                    <select id="status" name="status" class="form-select">
                        @foreach (['all', 'active'] as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status === 'all' ? __('app.admin.filters.all_option') : __('app.permits.statuses.'.$status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary" type="submit">{{ __('app.admin.filters.apply_action') }}</button>
                    <a class="btn btn-outline-primary" href="{{ route('admin.permits.index') }}">{{ __('app.admin.filters.clear_action') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><div class="iq-header-title"><h3 class="card-title">{{ __('app.permits.directory_title') }}</h3></div></div>
        <div class="card-body">
            <div class="table-responsive border rounded py-3">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('app.permits.permit_number') }}</th>
                            <th>{{ __('app.admin.applications.application') }}</th>
                            <th>{{ __('app.admin.applications.entity') }}</th>
                            <th>{{ __('app.permits.issued_at') }}</th>
                            <th>{{ __('app.admin.applications.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($permits as $permit)
                            <tr>
                                <td>
                                    {{ $permit->permit_number }}<br>
                                    <span class="text-muted">{{ $permit->localizedStatus() }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.applications.show', $permit->application) }}">{{ $permit->application?->project_name }}</a><br>
                                    <span class="text-muted">{{ $permit->application?->code }}</span>
                                </td>
                                <td>{{ $permit->entity?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                <td>{{ $permit->issued_at?->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.permits.show', $permit) }}">{{ __('app.permits.open_action') }}</a>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.applications.show', $permit->application) }}">{{ __('app.admin.applications.request_tab') }}</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.applications.final-letter.print', $permit->application) }}" target="_blank">{{ __('app.final_decision.print_letter') }}</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">{{ __('app.permits.empty_state') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
