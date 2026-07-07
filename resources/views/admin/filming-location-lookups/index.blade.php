@extends('layouts.admin-dashboard', [
    'title' => __('app.admin.filming_location_lookups.title'),
    'breadcrumb' => __('app.admin.navigation.filming_location_lookups'),
])

@push('styles')
    <style>
        .lookup-table {
            min-width: 1120px;
        }

        .lookup-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: .75rem;
        }

        .lookup-checks {
            display: grid;
            gap: .35rem;
        }

        .governorate-check-grid {
            display: grid;
            gap: .35rem .75rem;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        }
    </style>
@endpush

@section('content')
    <div class="content-inner container-fluid pb-0" id="page_layout">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="mb-2">{{ __('app.admin.filming_location_lookups.title') }}</h1>
                <p class="text-muted mb-0">{{ __('app.admin.filming_location_lookups.intro') }}</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                <i class="ph ph-arrow-left me-1"></i>{{ __('app.admin.filming_location_lookups.back_to_dashboard') }}
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
                'governorates' => __('app.admin.filming_location_lookups.stats.governorates'),
                'active_governorates' => __('app.admin.filming_location_lookups.stats.active_governorates'),
                'location_types' => __('app.admin.filming_location_lookups.stats.location_types'),
                'active_location_types' => __('app.admin.filming_location_lookups.stats.active_location_types'),
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
                <form method="GET" action="{{ route('admin.filming-location-lookups.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-6">
                        <label for="lookup-q" class="form-label">{{ __('app.admin.filters.search_label') }}</label>
                        <input id="lookup-q" type="search" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.filming_location_lookups.search_placeholder') }}">
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
                <h2 class="card-title mb-0">{{ __('app.admin.filming_location_lookups.create_governorate_title') }}</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.filming-location-lookups.governorates.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-lg-2">
                        <label for="new-governorate-code" class="form-label">{{ __('app.admin.filming_location_lookups.code') }}</label>
                        <input id="new-governorate-code" name="code" type="text" class="form-control" value="{{ old('code') }}" placeholder="amman">
                    </div>
                    <div class="col-lg-3">
                        <label for="new-governorate-name-en" class="form-label">{{ __('app.admin.filming_location_lookups.name_en') }}</label>
                        <input id="new-governorate-name-en" name="name_en" type="text" class="form-control" value="{{ old('name_en') }}" required>
                    </div>
                    <div class="col-lg-3">
                        <label for="new-governorate-name-ar" class="form-label">{{ __('app.admin.filming_location_lookups.name_ar') }}</label>
                        <input id="new-governorate-name-ar" name="name_ar" type="text" class="form-control" value="{{ old('name_ar') }}" required>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="new-governorate-sort-order" class="form-label">{{ __('app.admin.filming_location_lookups.sort_order') }}</label>
                        <input id="new-governorate-sort-order" name="sort_order" type="number" min="0" class="form-control" value="{{ old('sort_order', 500) }}">
                    </div>
                    <div class="col-lg-1 col-md-3">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check">
                            <input id="new-governorate-active" name="is_active" type="checkbox" class="form-check-input" value="1" checked>
                            <label for="new-governorate-active" class="form-check-label">{{ __('app.admin.filming_location_lookups.active') }}</label>
                        </div>
                    </div>
                    <div class="col-lg-1 d-grid">
                        <button type="submit" class="btn btn-primary">{{ __('app.admin.filming_location_lookups.create_action') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                <h2 class="card-title mb-0">{{ __('app.admin.filming_location_lookups.governorates_table_title') }}</h2>
                <span class="badge bg-light text-dark">{{ $governorates->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 lookup-table">
                        <thead>
                            <tr>
                                <th>{{ __('app.admin.filming_location_lookups.code') }}</th>
                                <th>{{ __('app.admin.filming_location_lookups.name_en') }}</th>
                                <th>{{ __('app.admin.filming_location_lookups.name_ar') }}</th>
                                <th>{{ __('app.admin.filming_location_lookups.sort_order') }}</th>
                                <th>{{ __('app.admin.filming_location_lookups.availability') }}</th>
                                <th>{{ __('app.admin.filming_location_lookups.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($governorates as $governorate)
                                <tr>
                                    <td><span class="badge bg-light text-dark lookup-code">{{ $governorate->code }}</span></td>
                                    <td><input name="name_en" form="governorate-update-{{ $governorate->getKey() }}" type="text" class="form-control" value="{{ $governorate->name_en }}" required></td>
                                    <td><input name="name_ar" form="governorate-update-{{ $governorate->getKey() }}" type="text" class="form-control" value="{{ $governorate->name_ar }}" required></td>
                                    <td><input name="sort_order" form="governorate-update-{{ $governorate->getKey() }}" type="number" min="0" class="form-control" value="{{ $governorate->sort_order }}" required></td>
                                    <td>
                                        <input type="hidden" form="governorate-update-{{ $governorate->getKey() }}" name="is_active" value="0">
                                        <div class="form-check">
                                            <input id="governorate-active-{{ $governorate->getKey() }}" form="governorate-update-{{ $governorate->getKey() }}" name="is_active" type="checkbox" class="form-check-input" value="1" @checked($governorate->is_active)>
                                            <label for="governorate-active-{{ $governorate->getKey() }}" class="form-check-label">{{ __('app.admin.filming_location_lookups.active') }}</label>
                                        </div>
                                    </td>
                                    <td>
                                        <form id="governorate-update-{{ $governorate->getKey() }}" method="POST" action="{{ route('admin.filming-location-lookups.governorates.update', $governorate) }}">
                                            @csrf
                                            <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                        </form>
                                        <form method="POST" action="{{ route('admin.filming-location-lookups.governorates.status', $governorate) }}" class="d-flex gap-2">
                                            @csrf
                                            <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                            <input type="hidden" name="is_active" value="{{ $governorate->is_active ? '0' : '1' }}">
                                            <button type="submit" form="governorate-update-{{ $governorate->getKey() }}" class="btn btn-sm btn-primary">
                                                {{ __('app.admin.filming_location_lookups.save_action') }}
                                            </button>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                {{ $governorate->is_active ? __('app.admin.filming_location_lookups.deactivate_action') : __('app.admin.filming_location_lookups.activate_action') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">{{ __('app.admin.filming_location_lookups.governorates_empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title mb-0">{{ __('app.admin.filming_location_lookups.create_location_type_title') }}</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.filming-location-lookups.location-types.store') }}" class="row g-3 align-items-start">
                    @csrf
                    <div class="col-lg-2">
                        <label for="new-location-type-code" class="form-label">{{ __('app.admin.filming_location_lookups.code') }}</label>
                        <input id="new-location-type-code" name="code" type="text" class="form-control" value="{{ old('code') }}" placeholder="public_locations">
                    </div>
                    <div class="col-lg-2">
                        <label for="new-location-type-name-en" class="form-label">{{ __('app.admin.filming_location_lookups.name_en') }}</label>
                        <input id="new-location-type-name-en" name="name_en" type="text" class="form-control" value="{{ old('name_en') }}" required>
                    </div>
                    <div class="col-lg-2">
                        <label for="new-location-type-name-ar" class="form-label">{{ __('app.admin.filming_location_lookups.name_ar') }}</label>
                        <input id="new-location-type-name-ar" name="name_ar" type="text" class="form-control" value="{{ old('name_ar') }}" required>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="new-location-type-sort-order" class="form-label">{{ __('app.admin.filming_location_lookups.sort_order') }}</label>
                        <input id="new-location-type-sort-order" name="sort_order" type="number" min="0" class="form-control" value="{{ old('sort_order', 500) }}">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="new-location-type-approval-days" class="form-label">{{ __('app.admin.filming_location_lookups.approval_days') }}</label>
                        <input id="new-location-type-approval-days" name="approval_days" type="number" min="0" max="365" class="form-control" value="{{ old('approval_days') }}" placeholder="14">
                        <div class="form-text">{{ __('app.admin.filming_location_lookups.approval_days_help') }}</div>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check mt-lg-4">
                            <input id="new-location-type-active" name="is_active" type="checkbox" class="form-check-input" value="1" checked>
                            <label for="new-location-type-active" class="form-check-label">{{ __('app.admin.filming_location_lookups.active') }}</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-label">{{ __('app.admin.filming_location_lookups.governorate_associations') }}</div>
                        <div class="governorate-check-grid">
                            @foreach ($allGovernorates as $governorate)
                                <div class="form-check">
                                    <input id="new-location-type-governorate-{{ $governorate->getKey() }}" name="governorates[]" type="checkbox" class="form-check-input" value="{{ $governorate->code }}" checked>
                                    <label for="new-location-type-governorate-{{ $governorate->getKey() }}" class="form-check-label">{{ $governorate->displayName() }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">{{ __('app.admin.filming_location_lookups.create_action') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                <h2 class="card-title mb-0">{{ __('app.admin.filming_location_lookups.location_types_table_title') }}</h2>
                <span class="badge bg-light text-dark">{{ $locationTypes->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 lookup-table">
                        <thead>
                            <tr>
                                <th>{{ __('app.admin.filming_location_lookups.code') }}</th>
                                <th>{{ __('app.admin.filming_location_lookups.name_en') }}</th>
                                <th>{{ __('app.admin.filming_location_lookups.name_ar') }}</th>
                                <th>{{ __('app.admin.filming_location_lookups.sort_order') }}</th>
                                <th>{{ __('app.admin.filming_location_lookups.approval_days') }}</th>
                                <th>{{ __('app.admin.filming_location_lookups.availability') }}</th>
                                <th>{{ __('app.admin.filming_location_lookups.governorate_associations') }}</th>
                                <th>{{ __('app.admin.filming_location_lookups.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($locationTypes as $locationType)
                                <tr>
                                    <td><span class="badge bg-light text-dark lookup-code">{{ $locationType->code }}</span></td>
                                    <td><input name="name_en" form="location-type-update-{{ $locationType->getKey() }}" type="text" class="form-control" value="{{ $locationType->name_en }}" required></td>
                                    <td><input name="name_ar" form="location-type-update-{{ $locationType->getKey() }}" type="text" class="form-control" value="{{ $locationType->name_ar }}" required></td>
                                    <td><input name="sort_order" form="location-type-update-{{ $locationType->getKey() }}" type="number" min="0" class="form-control" value="{{ $locationType->sort_order }}" required></td>
                                    <td><input name="approval_days" form="location-type-update-{{ $locationType->getKey() }}" type="number" min="0" max="365" class="form-control" value="{{ $locationType->approval_days }}"></td>
                                    <td>
                                        <input type="hidden" form="location-type-update-{{ $locationType->getKey() }}" name="is_active" value="0">
                                        <div class="form-check">
                                            <input id="location-type-active-{{ $locationType->getKey() }}" form="location-type-update-{{ $locationType->getKey() }}" name="is_active" type="checkbox" class="form-check-input" value="1" @checked($locationType->is_active)>
                                            <label for="location-type-active-{{ $locationType->getKey() }}" class="form-check-label">{{ __('app.admin.filming_location_lookups.active') }}</label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="governorate-check-grid">
                                            @foreach ($allGovernorates as $governorate)
                                                <div class="form-check">
                                                    <input id="location-type-{{ $locationType->getKey() }}-governorate-{{ $governorate->getKey() }}" form="location-type-update-{{ $locationType->getKey() }}" name="governorates[]" type="checkbox" class="form-check-input" value="{{ $governorate->code }}" @checked($locationType->governorates->contains('code', $governorate->code))>
                                                    <label for="location-type-{{ $locationType->getKey() }}-governorate-{{ $governorate->getKey() }}" class="form-check-label">{{ $governorate->displayName() }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <form id="location-type-update-{{ $locationType->getKey() }}" method="POST" action="{{ route('admin.filming-location-lookups.location-types.update', $locationType) }}">
                                            @csrf
                                            <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                        </form>
                                        <form method="POST" action="{{ route('admin.filming-location-lookups.location-types.status', $locationType) }}" class="d-flex gap-2">
                                            @csrf
                                            <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                            <input type="hidden" name="is_active" value="{{ $locationType->is_active ? '0' : '1' }}">
                                            <button type="submit" form="location-type-update-{{ $locationType->getKey() }}" class="btn btn-sm btn-primary">
                                                {{ __('app.admin.filming_location_lookups.save_action') }}
                                            </button>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                {{ $locationType->is_active ? __('app.admin.filming_location_lookups.deactivate_action') : __('app.admin.filming_location_lookups.activate_action') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">{{ __('app.admin.filming_location_lookups.location_types_empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
