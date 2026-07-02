@extends('layouts.admin-dashboard', [
    'title' => __('app.admin.nationalities.title'),
    'breadcrumb' => __('app.admin.navigation.nationalities'),
])

@push('styles')
    <style>
        .nationality-table {
            min-width: 1080px;
        }

        .nationality-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: .75rem;
        }

        .nationality-checks {
            display: grid;
            gap: .35rem;
        }
    </style>
@endpush

@section('content')
    <div class="content-inner container-fluid pb-0" id="page_layout">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="mb-2">{{ __('app.admin.nationalities.title') }}</h1>
                <p class="text-muted mb-0">{{ __('app.admin.nationalities.intro') }}</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                <i class="ph ph-arrow-left me-1"></i>{{ __('app.admin.nationalities.back_to_dashboard') }}
            </a>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-3 mb-4">
            @foreach ([
                'total' => __('app.admin.nationalities.stats.total'),
                'active' => __('app.admin.nationalities.stats.active'),
                'project' => __('app.admin.nationalities.stats.project'),
                'director' => __('app.admin.nationalities.stats.director'),
                'international_producer' => __('app.admin.nationalities.stats.international_producer'),
            ] as $statKey => $statLabel)
                <div class="col-md col-6">
                    <div class="border rounded bg-white p-3 h-100">
                        <div class="text-muted small">{{ $statLabel }}</div>
                        <div class="h4 mb-0">{{ $stats[$statKey] ?? 0 }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.nationalities.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label for="nationality-q" class="form-label">{{ __('app.admin.filters.search_label') }}</label>
                        <input id="nationality-q" type="search" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.nationalities.search_placeholder') }}">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="nationality-status" class="form-label">{{ __('app.admin.filters.status_label') }}</label>
                        <select id="nationality-status" name="status" class="form-select">
                            @foreach ([
                                'all' => __('app.admin.filters.all_option'),
                                'active' => __('app.statuses.active'),
                                'inactive' => __('app.statuses.inactive'),
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="nationality-usage" class="form-label">{{ __('app.admin.nationalities.usage_filter') }}</label>
                        <select id="nationality-usage" name="usage" class="form-select">
                            @foreach ([
                                'all' => __('app.admin.filters.all_option'),
                                'project' => __('app.admin.nationalities.usages.project'),
                                'director' => __('app.admin.nationalities.usages.director'),
                                'international_producer' => __('app.admin.nationalities.usages.international_producer'),
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['usage'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-1 d-grid">
                        <button type="submit" class="btn btn-primary">{{ __('app.admin.filters.apply_action') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title mb-0">{{ __('app.admin.nationalities.create_title') }}</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.nationalities.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-lg-2">
                        <label for="new-code" class="form-label">{{ __('app.admin.nationalities.code') }}</label>
                        <input id="new-code" name="code" type="text" class="form-control" value="{{ old('code') }}" placeholder="canadian">
                    </div>
                    <div class="col-lg-3">
                        <label for="new-name-en" class="form-label">{{ __('app.admin.nationalities.name_en') }}</label>
                        <input id="new-name-en" name="name_en" type="text" class="form-control" value="{{ old('name_en') }}" required>
                    </div>
                    <div class="col-lg-3">
                        <label for="new-name-ar" class="form-label">{{ __('app.admin.nationalities.name_ar') }}</label>
                        <input id="new-name-ar" name="name_ar" type="text" class="form-control" value="{{ old('name_ar') }}" required>
                    </div>
                    <div class="col-lg-1 col-md-3">
                        <label for="new-sort-order" class="form-label">{{ __('app.admin.nationalities.sort_order') }}</label>
                        <input id="new-sort-order" name="sort_order" type="number" min="0" class="form-control" value="{{ old('sort_order', 500) }}">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <div class="nationality-checks">
                            <input type="hidden" name="is_active" value="0">
                            <input type="hidden" name="available_for_project" value="0">
                            <input type="hidden" name="available_for_director" value="0">
                            <input type="hidden" name="available_for_international_producer" value="0">
                            @foreach ([
                                'is_active' => __('app.admin.nationalities.active'),
                                'available_for_project' => __('app.admin.nationalities.usages.project'),
                                'available_for_director' => __('app.admin.nationalities.usages.director'),
                                'available_for_international_producer' => __('app.admin.nationalities.usages.international_producer'),
                            ] as $field => $label)
                                <div class="form-check">
                                    <input id="new-{{ $field }}" name="{{ $field }}" type="checkbox" class="form-check-input" value="1" checked>
                                    <label for="new-{{ $field }}" class="form-check-label">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-lg-1 d-grid">
                        <button type="submit" class="btn btn-primary">{{ __('app.admin.nationalities.create_action') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                <h2 class="card-title mb-0">{{ __('app.admin.nationalities.table_title') }}</h2>
                <span class="badge bg-light text-dark">{{ $nationalities->total() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 nationality-table">
                        <thead>
                            <tr>
                                <th>{{ __('app.admin.nationalities.code') }}</th>
                                <th>{{ __('app.admin.nationalities.name_en') }}</th>
                                <th>{{ __('app.admin.nationalities.name_ar') }}</th>
                                <th>{{ __('app.admin.nationalities.sort_order') }}</th>
                                <th>{{ __('app.admin.nationalities.availability') }}</th>
                                <th>{{ __('app.admin.nationalities.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($nationalities as $nationality)
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark nationality-code">{{ $nationality->code }}</span>
                                    </td>
                                    <td>
                                        <input name="name_en" form="nationality-update-{{ $nationality->getKey() }}" type="text" class="form-control" value="{{ $nationality->name_en }}" required>
                                    </td>
                                    <td>
                                        <input name="name_ar" form="nationality-update-{{ $nationality->getKey() }}" type="text" class="form-control" value="{{ $nationality->name_ar }}" required>
                                    </td>
                                    <td>
                                        <input name="sort_order" form="nationality-update-{{ $nationality->getKey() }}" type="number" min="0" class="form-control" value="{{ $nationality->sort_order }}" required>
                                    </td>
                                    <td>
                                        <div class="nationality-checks">
                                            @foreach ([
                                                'is_active' => __('app.admin.nationalities.active'),
                                                'available_for_project' => __('app.admin.nationalities.usages.project'),
                                                'available_for_director' => __('app.admin.nationalities.usages.director'),
                                                'available_for_international_producer' => __('app.admin.nationalities.usages.international_producer'),
                                            ] as $field => $label)
                                                <input type="hidden" form="nationality-update-{{ $nationality->getKey() }}" name="{{ $field }}" value="0">
                                                <div class="form-check">
                                                    <input id="{{ $field }}-{{ $nationality->getKey() }}" form="nationality-update-{{ $nationality->getKey() }}" name="{{ $field }}" type="checkbox" class="form-check-input" value="1" @checked((bool) $nationality->{$field})>
                                                    <label for="{{ $field }}-{{ $nationality->getKey() }}" class="form-check-label">{{ $label }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <form id="nationality-update-{{ $nationality->getKey() }}" method="POST" action="{{ route('admin.nationalities.update', $nationality) }}">
                                            @csrf
                                            <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                            <input type="hidden" name="usage" value="{{ $filters['usage'] }}">
                                        </form>
                                        <form method="POST" action="{{ route('admin.nationalities.status', $nationality) }}" class="d-flex gap-2">
                                            @csrf
                                            <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                            <input type="hidden" name="usage" value="{{ $filters['usage'] }}">
                                            <input type="hidden" name="is_active" value="{{ $nationality->is_active ? '0' : '1' }}">
                                            <button type="submit" form="nationality-update-{{ $nationality->getKey() }}" class="btn btn-sm btn-primary">
                                                {{ __('app.admin.nationalities.save_action') }}
                                            </button>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                {{ $nationality->is_active ? __('app.admin.nationalities.deactivate_action') : __('app.admin.nationalities.activate_action') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">{{ __('app.admin.nationalities.empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($nationalities->hasPages())
                <div class="card-footer">
                    {{ $nationalities->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
