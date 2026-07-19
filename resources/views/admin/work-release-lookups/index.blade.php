@extends('layouts.admin-dashboard', [
    'title' => __('app.admin.work_release_lookups.title'),
    'breadcrumb' => __('app.admin.navigation.work_release_lookups'),
])

@push('styles')
    <style>
        .lookup-table {
            min-width: 960px;
        }

        .lookup-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: .75rem;
        }
    </style>
@endpush

@section('content')
    <div class="content-inner container-fluid pb-0" id="page_layout">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="mb-2">{{ __('app.admin.work_release_lookups.title') }}</h1>
                <p class="text-muted mb-0">{{ __('app.admin.work_release_lookups.intro') }}</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                <i class="ph ph-arrow-left me-1"></i>{{ __('app.admin.work_release_lookups.back_to_dashboard') }}
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
                'work_categories' => __('app.admin.work_release_lookups.stats.work_categories'),
                'active_work_categories' => __('app.admin.work_release_lookups.stats.active_work_categories'),
                'release_methods' => __('app.admin.work_release_lookups.stats.release_methods'),
                'active_release_methods' => __('app.admin.work_release_lookups.stats.active_release_methods'),
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
                <form method="GET" action="{{ route('admin.work-release-lookups.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-6">
                        <label for="lookup-q" class="form-label">{{ __('app.admin.filters.search_label') }}</label>
                        <input id="lookup-q" type="search" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.work_release_lookups.search_placeholder') }}">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="lookup-status" class="form-label">{{ __('app.admin.filters.status_label') }}</label>
                        <select id="lookup-status" name="status" class="form-select">
                            @foreach ([
                                'all' => __('app.admin.filters.all_option'),
                                'active' => __('app.statuses.active'),
                                'inactive' => __('app.statuses.inactive'),
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 d-grid">
                        <button type="submit" class="btn btn-primary">{{ __('app.admin.filters.apply_action') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title mb-0">{{ __('app.admin.work_release_lookups.create_work_category_title') }}</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.work-release-lookups.work-categories.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-lg-2">
                        <label for="new-work-category-code" class="form-label">{{ __('app.admin.work_release_lookups.code') }}</label>
                        <input id="new-work-category-code" name="code" type="text" class="form-control" value="{{ old('code') }}" placeholder="feature_film">
                    </div>
                    <div class="col-lg-2">
                        <label for="new-work-category-name-en" class="form-label">{{ __('app.admin.work_release_lookups.name_en') }}</label>
                        <input id="new-work-category-name-en" name="name_en" type="text" class="form-control" value="{{ old('name_en') }}" required>
                    </div>
                    <div class="col-lg-2">
                        <label for="new-work-category-name-ar" class="form-label">{{ __('app.admin.work_release_lookups.name_ar') }}</label>
                        <input id="new-work-category-name-ar" name="name_ar" type="text" class="form-control" value="{{ old('name_ar') }}" required>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="new-work-category-summary-words" class="form-label">{{ __('app.admin.work_release_lookups.work_summary_min_words') }}</label>
                        <input id="new-work-category-summary-words" name="work_summary_min_words" type="number" min="1" max="5000" class="form-control" value="{{ old('work_summary_min_words', \App\Models\WorkCategory::DEFAULT_WORK_SUMMARY_MIN_WORDS) }}" required>
                    </div>
                    <div class="col-lg-1 col-md-6">
                        <label for="new-work-category-sort-order" class="form-label">{{ __('app.admin.work_release_lookups.sort_order') }}</label>
                        <input id="new-work-category-sort-order" name="sort_order" type="number" min="0" class="form-control" value="{{ old('sort_order', 500) }}">
                    </div>
                    <div class="col-lg-1 col-md-3">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check">
                            <input id="new-work-category-active" name="is_active" type="checkbox" class="form-check-input" value="1" checked>
                            <label for="new-work-category-active" class="form-check-label">{{ __('app.admin.work_release_lookups.active') }}</label>
                        </div>
                    </div>
                    <div class="col-lg-2 d-grid">
                        <button type="submit" class="btn btn-primary">{{ __('app.admin.work_release_lookups.create_action') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                <h2 class="card-title mb-0">{{ __('app.admin.work_release_lookups.work_categories_table_title') }}</h2>
                <span class="badge bg-light text-dark">{{ $workCategories->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 lookup-table">
                        <thead>
                            <tr>
                                <th>{{ __('app.admin.work_release_lookups.code') }}</th>
                                <th>{{ __('app.admin.work_release_lookups.name_en') }}</th>
                                <th>{{ __('app.admin.work_release_lookups.name_ar') }}</th>
                                <th>{{ __('app.admin.work_release_lookups.work_summary_min_words') }}</th>
                                <th>{{ __('app.admin.work_release_lookups.sort_order') }}</th>
                                <th>{{ __('app.admin.work_release_lookups.availability') }}</th>
                                <th>{{ __('app.admin.work_release_lookups.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($workCategories as $workCategory)
                                <tr>
                                    <td><span class="badge bg-light text-dark lookup-code">{{ $workCategory->code }}</span></td>
                                    <td><input name="name_en" form="work-category-update-{{ $workCategory->getKey() }}" type="text" class="form-control" value="{{ $workCategory->name_en }}" required></td>
                                    <td><input name="name_ar" form="work-category-update-{{ $workCategory->getKey() }}" type="text" class="form-control" value="{{ $workCategory->name_ar }}" required></td>
                                    <td><input name="work_summary_min_words" form="work-category-update-{{ $workCategory->getKey() }}" type="number" min="1" max="5000" class="form-control" value="{{ $workCategory->workSummaryMinWords() }}" required></td>
                                    <td><input name="sort_order" form="work-category-update-{{ $workCategory->getKey() }}" type="number" min="0" class="form-control" value="{{ $workCategory->sort_order }}" required></td>
                                    <td>
                                        <input type="hidden" form="work-category-update-{{ $workCategory->getKey() }}" name="is_active" value="0">
                                        <div class="form-check">
                                            <input id="work-category-active-{{ $workCategory->getKey() }}" form="work-category-update-{{ $workCategory->getKey() }}" name="is_active" type="checkbox" class="form-check-input" value="1" @checked($workCategory->is_active)>
                                            <label for="work-category-active-{{ $workCategory->getKey() }}" class="form-check-label">{{ __('app.admin.work_release_lookups.active') }}</label>
                                        </div>
                                    </td>
                                    <td>
                                        <form id="work-category-update-{{ $workCategory->getKey() }}" method="POST" action="{{ route('admin.work-release-lookups.work-categories.update', $workCategory) }}">
                                            @csrf
                                            <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                        </form>
                                        <form method="POST" action="{{ route('admin.work-release-lookups.work-categories.status', $workCategory) }}" class="d-flex gap-2">
                                            @csrf
                                            <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                            <input type="hidden" name="is_active" value="{{ $workCategory->is_active ? '0' : '1' }}">
                                            <button type="submit" form="work-category-update-{{ $workCategory->getKey() }}" class="btn btn-sm btn-primary">{{ __('app.admin.work_release_lookups.save_action') }}</button>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                {{ $workCategory->is_active ? __('app.admin.work_release_lookups.deactivate_action') : __('app.admin.work_release_lookups.activate_action') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">{{ __('app.admin.work_release_lookups.work_categories_empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title mb-0">{{ __('app.admin.work_release_lookups.create_release_method_title') }}</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.work-release-lookups.release-methods.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-lg-2">
                        <label for="new-release-method-code" class="form-label">{{ __('app.admin.work_release_lookups.code') }}</label>
                        <input id="new-release-method-code" name="code" type="text" class="form-control" value="{{ old('code') }}" placeholder="cinema">
                    </div>
                    <div class="col-lg-3">
                        <label for="new-release-method-name-en" class="form-label">{{ __('app.admin.work_release_lookups.name_en') }}</label>
                        <input id="new-release-method-name-en" name="name_en" type="text" class="form-control" value="{{ old('name_en') }}" required>
                    </div>
                    <div class="col-lg-3">
                        <label for="new-release-method-name-ar" class="form-label">{{ __('app.admin.work_release_lookups.name_ar') }}</label>
                        <input id="new-release-method-name-ar" name="name_ar" type="text" class="form-control" value="{{ old('name_ar') }}" required>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="new-release-method-sort-order" class="form-label">{{ __('app.admin.work_release_lookups.sort_order') }}</label>
                        <input id="new-release-method-sort-order" name="sort_order" type="number" min="0" class="form-control" value="{{ old('sort_order', 500) }}">
                    </div>
                    <div class="col-lg-1 col-md-3">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check">
                            <input id="new-release-method-active" name="is_active" type="checkbox" class="form-check-input" value="1" checked>
                            <label for="new-release-method-active" class="form-check-label">{{ __('app.admin.work_release_lookups.active') }}</label>
                        </div>
                    </div>
                    <div class="col-lg-1 d-grid">
                        <button type="submit" class="btn btn-primary">{{ __('app.admin.work_release_lookups.create_action') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                <h2 class="card-title mb-0">{{ __('app.admin.work_release_lookups.release_methods_table_title') }}</h2>
                <span class="badge bg-light text-dark">{{ $releaseMethods->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 lookup-table">
                        <thead>
                            <tr>
                                <th>{{ __('app.admin.work_release_lookups.code') }}</th>
                                <th>{{ __('app.admin.work_release_lookups.name_en') }}</th>
                                <th>{{ __('app.admin.work_release_lookups.name_ar') }}</th>
                                <th>{{ __('app.admin.work_release_lookups.sort_order') }}</th>
                                <th>{{ __('app.admin.work_release_lookups.availability') }}</th>
                                <th>{{ __('app.admin.work_release_lookups.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($releaseMethods as $releaseMethod)
                                <tr>
                                    <td><span class="badge bg-light text-dark lookup-code">{{ $releaseMethod->code }}</span></td>
                                    <td><input name="name_en" form="release-method-update-{{ $releaseMethod->getKey() }}" type="text" class="form-control" value="{{ $releaseMethod->name_en }}" required></td>
                                    <td><input name="name_ar" form="release-method-update-{{ $releaseMethod->getKey() }}" type="text" class="form-control" value="{{ $releaseMethod->name_ar }}" required></td>
                                    <td><input name="sort_order" form="release-method-update-{{ $releaseMethod->getKey() }}" type="number" min="0" class="form-control" value="{{ $releaseMethod->sort_order }}" required></td>
                                    <td>
                                        <input type="hidden" form="release-method-update-{{ $releaseMethod->getKey() }}" name="is_active" value="0">
                                        <div class="form-check">
                                            <input id="release-method-active-{{ $releaseMethod->getKey() }}" form="release-method-update-{{ $releaseMethod->getKey() }}" name="is_active" type="checkbox" class="form-check-input" value="1" @checked($releaseMethod->is_active)>
                                            <label for="release-method-active-{{ $releaseMethod->getKey() }}" class="form-check-label">{{ __('app.admin.work_release_lookups.active') }}</label>
                                        </div>
                                    </td>
                                    <td>
                                        <form id="release-method-update-{{ $releaseMethod->getKey() }}" method="POST" action="{{ route('admin.work-release-lookups.release-methods.update', $releaseMethod) }}">
                                            @csrf
                                            <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                        </form>
                                        <form method="POST" action="{{ route('admin.work-release-lookups.release-methods.status', $releaseMethod) }}" class="d-flex gap-2">
                                            @csrf
                                            <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                            <input type="hidden" name="is_active" value="{{ $releaseMethod->is_active ? '0' : '1' }}">
                                            <button type="submit" form="release-method-update-{{ $releaseMethod->getKey() }}" class="btn btn-sm btn-primary">{{ __('app.admin.work_release_lookups.save_action') }}</button>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                {{ $releaseMethod->is_active ? __('app.admin.work_release_lookups.deactivate_action') : __('app.admin.work_release_lookups.activate_action') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">{{ __('app.admin.work_release_lookups.release_methods_empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
