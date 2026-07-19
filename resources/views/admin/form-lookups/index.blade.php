@extends('layouts.admin-dashboard', [
    'title' => __('app.admin.form_lookups.title'),
    'breadcrumb' => __('app.admin.navigation.form_lookups'),
])

@push('styles')
    <style>
        .form-lookup-table {
            min-width: 1560px;
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
                <h1 class="mb-2">{{ __('app.admin.form_lookups.title') }}</h1>
                <p class="text-muted mb-0">{{ __('app.admin.form_lookups.intro') }}</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                <i class="ph ph-arrow-left me-1"></i>{{ __('app.admin.form_lookups.back_to_dashboard') }}
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
                'total' => __('app.admin.form_lookups.stats.total'),
                'active' => __('app.admin.form_lookups.stats.active'),
                'types' => __('app.admin.form_lookups.stats.types'),
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
                <form method="GET" action="{{ route('admin.form-lookups.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label for="lookup-q" class="form-label">{{ __('app.admin.filters.search_label') }}</label>
                        <input id="lookup-q" type="search" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.form_lookups.search_placeholder') }}">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="lookup-type" class="form-label">{{ __('app.admin.form_lookups.type') }}</label>
                        <select id="lookup-type" name="type" class="form-select">
                            <option value="">{{ __('app.admin.filters.all_option') }}</option>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
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
                    <div class="col-lg-2 d-grid">
                        <button type="submit" class="btn btn-primary">{{ __('app.admin.filters.apply_action') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title mb-0">{{ __('app.admin.form_lookups.create_title') }}</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.form-lookups.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-lg-3">
                        <label for="new-type" class="form-label">{{ __('app.admin.form_lookups.type') }}</label>
                        <select id="new-type" name="type" class="form-select" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $filters['type']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label for="new-code" class="form-label">{{ __('app.admin.form_lookups.code') }}</label>
                        <input id="new-code" name="code" type="text" class="form-control" value="{{ old('code') }}" required>
                    </div>
                    <div class="col-lg-2">
                        <label for="new-name-en" class="form-label">{{ __('app.admin.form_lookups.name_en') }}</label>
                        <input id="new-name-en" name="name_en" type="text" class="form-control" value="{{ old('name_en') }}" required>
                    </div>
                    <div class="col-lg-2">
                        <label for="new-name-ar" class="form-label">{{ __('app.admin.form_lookups.name_ar') }}</label>
                        <input id="new-name-ar" name="name_ar" type="text" class="form-control" value="{{ old('name_ar') }}" required>
                    </div>
                    <div class="col-lg-1 col-md-4">
                        <label for="new-sort-order" class="form-label">{{ __('app.admin.form_lookups.sort_order') }}</label>
                        <input id="new-sort-order" name="sort_order" type="number" min="0" class="form-control" value="{{ old('sort_order', 500) }}">
                    </div>
                    <div class="col-lg-1 col-md-4">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check">
                            <input id="new-active" name="is_active" type="checkbox" class="form-check-input" value="1" checked>
                            <label for="new-active" class="form-check-label">{{ __('app.admin.form_lookups.active') }}</label>
                        </div>
                    </div>
                    <div class="col-lg-1 col-md-4 d-grid">
                        <button type="submit" class="btn btn-primary">{{ __('app.admin.form_lookups.create_action') }}</button>
                    </div>
                    <div class="col-12" data-support-requirement-fields @if (old('type', $filters['type']) !== \App\Models\FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT) hidden @endif>
                        <div class="border p-3">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <label for="new-entity-ids" class="form-label">{{ __('app.admin.form_lookups.authority_entities') }}</label>
                                    <select id="new-entity-ids" name="entity_ids[]" class="form-select" multiple size="6">
                                        @foreach ($authorityEntities as $entity)
                                            <option value="{{ $entity->getKey() }}" @selected(in_array($entity->getKey(), array_map('intval', (array) old('entity_ids', [])), true))>{{ $entity->displayName() }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">{{ __('app.admin.form_lookups.authority_entities_help') }}</div>
                                </div>
                                <div class="col-lg-3">
                                    <label for="new-notes-prompt-en" class="form-label">{{ __('app.admin.form_lookups.notes_prompt_en') }}</label>
                                    <textarea id="new-notes-prompt-en" name="notes_prompt_en" class="form-control" rows="5">{{ old('notes_prompt_en') }}</textarea>
                                </div>
                                <div class="col-lg-3">
                                    <label for="new-notes-prompt-ar" class="form-label">{{ __('app.admin.form_lookups.notes_prompt_ar') }}</label>
                                    <textarea id="new-notes-prompt-ar" name="notes_prompt_ar" class="form-control" rows="5">{{ old('notes_prompt_ar') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                <h2 class="card-title mb-0">{{ __('app.admin.form_lookups.table_title') }}</h2>
                <span class="badge bg-light text-dark">{{ $options->total() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle form-lookup-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('app.admin.form_lookups.type') }}</th>
                                <th>{{ __('app.admin.form_lookups.code') }}</th>
                                <th>{{ __('app.admin.form_lookups.name_en') }}</th>
                                <th>{{ __('app.admin.form_lookups.name_ar') }}</th>
                                <th>{{ __('app.admin.form_lookups.sort_order') }}</th>
                                <th>{{ __('app.admin.form_lookups.authority_entities') }}</th>
                                <th>{{ __('app.admin.form_lookups.notes_prompt') }}</th>
                                <th>{{ __('app.admin.form_lookups.availability') }}</th>
                                <th>{{ __('app.admin.form_lookups.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($options as $option)
                                <tr>
                                    <td>{{ $types[$option->type] ?? $option->type }}</td>
                                    <td><span class="badge bg-light text-dark lookup-code">{{ $option->code }}</span></td>
                                    <td><input form="option-update-{{ $option->getKey() }}" name="name_en" class="form-control" value="{{ $option->name_en }}" required></td>
                                    <td><input form="option-update-{{ $option->getKey() }}" name="name_ar" class="form-control" value="{{ $option->name_ar }}" required></td>
                                    <td><input form="option-update-{{ $option->getKey() }}" name="sort_order" type="number" min="0" class="form-control" value="{{ $option->sort_order }}"></td>
                                    <td>
                                        @if ($option->type === \App\Models\FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT)
                                            <select form="option-update-{{ $option->getKey() }}" name="entity_ids[]" class="form-select" multiple size="5">
                                                @foreach ($authorityEntities as $entity)
                                                    <option value="{{ $entity->getKey() }}" @selected($option->entities->contains('id', $entity->getKey()))>{{ $entity->displayName() }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <span class="text-muted">{{ __('app.dashboard.not_available') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($option->type === \App\Models\FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT)
                                            <input form="option-update-{{ $option->getKey() }}" name="notes_prompt_en" class="form-control mb-2" value="{{ data_get($option->metadata, 'notes_prompt_en') }}" placeholder="{{ __('app.admin.form_lookups.notes_prompt_en') }}">
                                            <input form="option-update-{{ $option->getKey() }}" name="notes_prompt_ar" class="form-control" value="{{ data_get($option->metadata, 'notes_prompt_ar') }}" placeholder="{{ __('app.admin.form_lookups.notes_prompt_ar') }}">
                                        @else
                                            <span class="text-muted">{{ __('app.dashboard.not_available') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <input form="option-update-{{ $option->getKey() }}" type="hidden" name="is_active" value="0">
                                        <input form="option-update-{{ $option->getKey() }}" id="option-active-{{ $option->getKey() }}" name="is_active" type="checkbox" class="form-check-input" value="1" @checked($option->is_active)>
                                        <label for="option-active-{{ $option->getKey() }}" class="form-check-label">{{ __('app.admin.form_lookups.active') }}</label>
                                    </td>
                                    <td>
                                        <form id="option-update-{{ $option->getKey() }}" method="POST" action="{{ route('admin.form-lookups.update', $option) }}">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $option->type }}">
                                            <input type="hidden" name="code" value="{{ $option->code }}">
                                        </form>
                                        <form method="POST" action="{{ route('admin.form-lookups.status', $option) }}" class="d-flex gap-2">
                                            @csrf
                                            <button form="option-update-{{ $option->getKey() }}" type="submit" class="btn btn-sm btn-danger">{{ __('app.admin.form_lookups.save_action') }}</button>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                {{ $option->is_active ? __('app.admin.form_lookups.deactivate_action') : __('app.admin.form_lookups.activate_action') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">{{ __('app.admin.form_lookups.empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($options->hasPages())
                <div class="card-footer">
                    {{ $options->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const typeSelect = document.getElementById('new-type');
            const supportFields = document.querySelector('[data-support-requirement-fields]');

            const syncSupportFields = () => {
                if (!typeSelect || !supportFields) return;
                supportFields.hidden = typeSelect.value !== @json(\App\Models\FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT);
            };

            typeSelect?.addEventListener('change', syncSupportFields);
            syncSupportFields();
        });
    </script>
@endpush
