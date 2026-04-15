@php
    $ruleConditions = old('conditions', $rule->conditions ?? []);
@endphp

<input type="hidden" name="current_rule_id" value="{{ $rule->exists ? $rule->getKey() : '' }}">

<div class="row g-3">
    <div class="col-xl-6">
        <label for="name" class="form-label">{{ __('app.admin.approval_routing.name') }}</label>
        <input id="name" name="name" type="text" class="form-control" value="{{ old('name', $rule->name) }}" required>
    </div>
    <div class="col-xl-3">
        <label for="request_type" class="form-label">{{ __('app.admin.approval_routing.request_type') }}</label>
        <select id="request_type" name="request_type" class="form-select" required>
            <option value="application" @selected(old('request_type', $rule->request_type) === 'application')>{{ __('app.admin.approval_routing.request_types.application') }}</option>
        </select>
    </div>
    <div class="col-xl-3">
        <label for="priority" class="form-label">{{ __('app.admin.approval_routing.priority') }}</label>
        <input id="priority" name="priority" type="number" min="1" max="9999" class="form-control" value="{{ old('priority', $rule->priority ?? 100) }}" required>
    </div>
    <div class="col-xl-6">
        <label for="approval_code" class="form-label">{{ __('app.admin.approval_routing.approval_code') }}</label>
        <select id="approval_code" name="approval_code" class="form-select" required>
            <option value="">{{ __('app.admin.select_placeholder') }}</option>
            @foreach ($approvalCodes as $approvalCode)
                <option value="{{ $approvalCode }}" @selected(old('approval_code', $rule->approval_code) === $approvalCode)>{{ __('app.applications.required_approval_options.'.$approvalCode) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-xl-6">
        <label for="target_entity_id" class="form-label">{{ __('app.admin.approval_routing.target_entity') }}</label>
        <select id="target_entity_id" name="target_entity_id" class="form-select" required>
            <option value="">{{ __('app.admin.select_placeholder') }}</option>
            @foreach ($authorityEntities as $entity)
                <option value="{{ $entity->getKey() }}" @selected((string) old('target_entity_id', $rule->target_entity_id) === (string) $entity->getKey())>{{ $entity->displayName() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <div class="card bg-light-subtle border-0 mb-0">
            <div class="card-header">
                <div class="iq-header-title">
                    <h3 class="card-title">{{ __('app.admin.approval_routing.conditions_title') }}</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-xl-4">
                        <label for="conditions-project-nationalities" class="form-label">{{ __('app.applications.project_nationality') }}</label>
                        <select id="conditions-project-nationalities" name="conditions[project_nationalities][]" class="form-select" multiple size="3">
                            @foreach ($conditionOptions['project_nationalities'] as $option)
                                <option value="{{ $option }}" @selected(in_array($option, (array) data_get($ruleConditions, 'project_nationalities', []), true))>{{ __('app.applications.project_nationalities.'.$option) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-4">
                        <label for="conditions-work-categories" class="form-label">{{ __('app.applications.work_category') }}</label>
                        <select id="conditions-work-categories" name="conditions[work_categories][]" class="form-select" multiple size="6">
                            @foreach ($conditionOptions['work_categories'] as $option)
                                <option value="{{ $option }}" @selected(in_array($option, (array) data_get($ruleConditions, 'work_categories', []), true))>{{ __('app.applications.work_categories.'.$option) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-4">
                        <label for="conditions-release-methods" class="form-label">{{ __('app.applications.release_method') }}</label>
                        <select id="conditions-release-methods" name="conditions[release_methods][]" class="form-select" multiple size="5">
                            @foreach ($conditionOptions['release_methods'] as $option)
                                <option value="{{ $option }}" @selected(in_array($option, (array) data_get($ruleConditions, 'release_methods', []), true))>{{ __('app.applications.release_methods.'.$option) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">{{ __('app.admin.approval_routing.conditions_help') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input id="is_active" name="is_active" type="checkbox" class="form-check-input" value="1" @checked((bool) old('is_active', $rule->is_active))>
            <label class="form-check-label" for="is_active">{{ __('app.admin.approval_routing.active_label') }}</label>
        </div>
    </div>
</div>
