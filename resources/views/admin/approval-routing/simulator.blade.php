@php
    $title = __('app.admin.approval_routing.simulator_title');
    $breadcrumb = __('app.admin.navigation.approval_routing');
    $requiredApprovals = collect((array) data_get($selectedApplication?->metadata, 'requirements.required_approvals', []))
        ->map(fn ($approval) => __('app.applications.required_approval_options.'.$approval))
        ->join(', ');
    $draftConditions = data_get(request()->query(), 'draft.conditions', [
        'project_nationalities' => [],
        'work_categories' => [],
        'release_methods' => [],
    ]);
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('content')
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
        <div>
            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                <span class="position-relative">{{ __('app.admin.approval_routing.simulator_title') }}</span>
            </h2>
            <div class="text-muted">{{ __('app.admin.approval_routing.simulator_intro') }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary" href="{{ route('admin.approval-routing.index') }}">{{ __('app.admin.navigation.approval_routing') }}</a>
            <a class="btn btn-danger" href="{{ route('admin.approval-routing.create') }}">{{ __('app.admin.approval_routing.create_action') }}</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.approval-routing.simulator') }}" class="row g-3 align-items-end">
                <div class="col-xl-4">
                    <label for="simulator-q" class="form-label">{{ __('app.admin.filters.search_label') }}</label>
                    <input id="simulator-q" name="q" type="text" class="form-control bg-white" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.approval_routing.simulator_search_placeholder') }}">
                </div>
                <div class="col-xl-6">
                    <label for="application_id" class="form-label">{{ __('app.admin.approval_routing.simulator_application') }}</label>
                    <select id="application_id" name="application_id" class="form-select">
                        <option value="">{{ __('app.admin.select_placeholder') }}</option>
                        @foreach ($applicationOptions as $application)
                            <option value="{{ $application->getKey() }}" @selected($filters['application_id'] === (string) $application->getKey())>
                                {{ $application->code }} - {{ $application->project_name }} - {{ $application->entity?->displayName() ?? __('app.dashboard.not_available') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 d-flex gap-2">
                    <button class="btn btn-danger flex-grow-1" type="submit">{{ __('app.admin.approval_routing.simulator_run_action') }}</button>
                </div>
            </form>
        </div>
    </div>

    @if ($selectedApplication)
        <div class="row g-4">
            <div class="col-xl-4">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.approval_routing.simulator_summary_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">{{ __('app.applications.request_number') }}</small>
                            <div>{{ $selectedApplication->code }}</div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">{{ __('app.applications.project_name') }}</small>
                            <div>{{ $selectedApplication->project_name }}</div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">{{ __('app.admin.applications.entity') }}</small>
                            <div>{{ $selectedApplication->entity?->displayName() ?? __('app.dashboard.not_available') }}</div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">{{ __('app.applications.project_nationality') }}</small>
                            <div>{{ __('app.applications.project_nationalities.'.$selectedApplication->project_nationality) }}</div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">{{ __('app.applications.work_category') }}</small>
                            <div>{{ __('app.applications.work_categories.'.$selectedApplication->work_category) }}</div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">{{ __('app.applications.release_method') }}</small>
                            <div>{{ __('app.applications.release_methods.'.$selectedApplication->release_method) }}</div>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted d-block">{{ __('app.applications.required_approvals') }}</small>
                            <div>{{ $requiredApprovals ?: __('app.applications.no_required_approvals') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.approval_routing.simulator_compare_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('admin.approval-routing.simulator') }}" class="row g-3">
                            <input type="hidden" name="application_id" value="{{ $selectedApplication->getKey() }}">
                            <input type="hidden" name="q" value="{{ $filters['q'] }}">

                            <div class="col-xl-6">
                                <label for="draft-name" class="form-label">{{ __('app.admin.approval_routing.name') }}</label>
                                <input id="draft-name" name="draft[name]" type="text" class="form-control" value="{{ request()->query('draft.name', __('app.admin.approval_routing.simulator_draft_rule_name')) }}">
                            </div>
                            <div class="col-xl-3">
                                <label for="draft-priority" class="form-label">{{ __('app.admin.approval_routing.priority') }}</label>
                                <input id="draft-priority" name="draft[priority]" type="number" min="1" max="9999" class="form-control" value="{{ request()->query('draft.priority', 100) }}">
                            </div>
                            <div class="col-xl-3">
                                <label for="draft-approval-code" class="form-label">{{ __('app.admin.approval_routing.approval_code') }}</label>
                                <select id="draft-approval-code" name="draft[approval_code]" class="form-select">
                                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                    @foreach ($approvalCodes as $approvalCode)
                                        <option value="{{ $approvalCode }}" @selected(request()->query('draft.approval_code') === $approvalCode)>{{ __('app.applications.required_approval_options.'.$approvalCode) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="draft-target-entity" class="form-label">{{ __('app.admin.approval_routing.target_entity') }}</label>
                                <select id="draft-target-entity" name="draft[target_entity_id]" class="form-select">
                                    <option value="">{{ __('app.admin.select_placeholder') }}</option>
                                    @foreach ($authorityEntities as $entity)
                                        <option value="{{ $entity->getKey() }}" @selected((string) request()->query('draft.target_entity_id') === (string) $entity->getKey())>{{ $entity->displayName() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-4">
                                <label for="draft-project-nationalities" class="form-label">{{ __('app.applications.project_nationality') }}</label>
                                <select id="draft-project-nationalities" name="draft[conditions][project_nationalities][]" class="form-select" multiple size="3">
                                    @foreach ($conditionOptions['project_nationalities'] as $option)
                                        <option value="{{ $option }}" @selected(in_array($option, (array) data_get($draftConditions, 'project_nationalities', []), true))>{{ __('app.applications.project_nationalities.'.$option) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-4">
                                <label for="draft-work-categories" class="form-label">{{ __('app.applications.work_category') }}</label>
                                <select id="draft-work-categories" name="draft[conditions][work_categories][]" class="form-select" multiple size="6">
                                    @foreach ($conditionOptions['work_categories'] as $option)
                                        <option value="{{ $option }}" @selected(in_array($option, (array) data_get($draftConditions, 'work_categories', []), true))>{{ __('app.applications.work_categories.'.$option) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-4">
                                <label for="draft-release-methods" class="form-label">{{ __('app.applications.release_method') }}</label>
                                <select id="draft-release-methods" name="draft[conditions][release_methods][]" class="form-select" multiple size="5">
                                    @foreach ($conditionOptions['release_methods'] as $option)
                                        <option value="{{ $option }}" @selected(in_array($option, (array) data_get($draftConditions, 'release_methods', []), true))>{{ __('app.applications.release_methods.'.$option) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 d-flex gap-2 flex-wrap">
                                <button class="btn btn-danger" type="submit">{{ __('app.admin.approval_routing.simulator_compare_action') }}</button>
                                <a class="btn btn-outline-secondary" href="{{ route('admin.approval-routing.simulator', ['application_id' => $selectedApplication->getKey()]) }}">{{ __('app.admin.approval_routing.simulator_clear_draft_action') }}</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.approval_routing.simulator_current_results_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($simulationRoutes->isEmpty())
                            <div class="text-muted">{{ __('app.admin.approval_routing.simulator_empty_routes') }}</div>
                        @else
                            <div class="table-responsive border rounded py-3">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('app.admin.approval_routing.approval_code') }}</th>
                                            <th>{{ __('app.admin.approval_routing.target_entity') }}</th>
                                            <th>{{ __('app.admin.approval_routing.simulator_source') }}</th>
                                            <th>{{ __('app.admin.approval_routing.priority') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($simulationRoutes as $route)
                                            <tr>
                                                <td>{{ __('app.applications.required_approval_options.'.$route['approval_code']) }}</td>
                                                <td>{{ $route['target_entity_name'] ?? __('app.admin.approval_routing.simulator_no_target') }}</td>
                                                <td>
                                                    @if ($route['source'] === 'rule')
                                                        <div class="fw-semibold">{{ __('app.admin.approval_routing.simulator_sources.rule') }}</div>
                                                        <div class="small text-muted">{{ $route['rule_name'] }}</div>
                                                    @elseif ($route['source'] === 'draft')
                                                        <div class="fw-semibold">{{ __('app.admin.approval_routing.simulator_sources.draft') }}</div>
                                                        <div class="small text-muted">{{ $route['rule_name'] }}</div>
                                                    @else
                                                        <div class="fw-semibold">{{ __('app.admin.approval_routing.simulator_sources.fallback') }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ $route['priority'] ?? __('app.dashboard.not_available') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="iq-header-title">
                            <h3 class="card-title">{{ __('app.admin.approval_routing.simulator_after_results_title') }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        @if (! $draftSimulation)
                            <div class="text-muted">{{ __('app.admin.approval_routing.simulator_after_empty_state') }}</div>
                        @elseif ($draftSimulationRoutes->isEmpty())
                            <div class="text-muted">{{ __('app.admin.approval_routing.simulator_empty_routes') }}</div>
                        @else
                            <div class="table-responsive border rounded py-3">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('app.admin.approval_routing.approval_code') }}</th>
                                            <th>{{ __('app.admin.approval_routing.target_entity') }}</th>
                                            <th>{{ __('app.admin.approval_routing.simulator_source') }}</th>
                                            <th>{{ __('app.admin.approval_routing.priority') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($draftSimulationRoutes as $route)
                                            <tr @class(['table-warning' => $route['source'] === 'draft'])>
                                                <td>{{ __('app.applications.required_approval_options.'.$route['approval_code']) }}</td>
                                                <td>{{ $route['target_entity_name'] ?? __('app.admin.approval_routing.simulator_no_target') }}</td>
                                                <td>
                                                    @if ($route['source'] === 'rule')
                                                        <div class="fw-semibold">{{ __('app.admin.approval_routing.simulator_sources.rule') }}</div>
                                                        <div class="small text-muted">{{ $route['rule_name'] }}</div>
                                                    @elseif ($route['source'] === 'draft')
                                                        <div class="fw-semibold">{{ __('app.admin.approval_routing.simulator_sources.draft') }}</div>
                                                        <div class="small text-muted">{{ $route['rule_name'] }}</div>
                                                    @else
                                                        <div class="fw-semibold">{{ __('app.admin.approval_routing.simulator_sources.fallback') }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ $route['priority'] ?? __('app.dashboard.not_available') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($draftSimulation)
            <div class="card mt-4">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.approval_routing.simulator_change_summary_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-xl-3 col-md-6">
                            <div class="border rounded p-3 h-100 text-center">
                                <div class="small text-muted">{{ __('app.admin.approval_routing.simulator_change_stats.added') }}</div>
                                <div class="fs-4 fw-semibold">{{ $simulationChanges['added']->count() }}</div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="border rounded p-3 h-100 text-center">
                                <div class="small text-muted">{{ __('app.admin.approval_routing.simulator_change_stats.removed') }}</div>
                                <div class="fs-4 fw-semibold">{{ $simulationChanges['removed']->count() }}</div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="border rounded p-3 h-100 text-center">
                                <div class="small text-muted">{{ __('app.admin.approval_routing.simulator_change_stats.changed') }}</div>
                                <div class="fs-4 fw-semibold">{{ $simulationChanges['changed']->count() }}</div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="border rounded p-3 h-100 text-center">
                                <div class="small text-muted">{{ __('app.admin.approval_routing.simulator_change_stats.unchanged') }}</div>
                                <div class="fs-4 fw-semibold">{{ $simulationChanges['unchanged']->count() }}</div>
                            </div>
                        </div>
                    </div>

                    @if ($simulationChanges['added']->isEmpty() && $simulationChanges['removed']->isEmpty() && $simulationChanges['changed']->isEmpty())
                        <div class="text-muted">{{ __('app.admin.approval_routing.simulator_change_empty') }}</div>
                    @else
                        <div class="table-responsive border rounded py-3">
                            <table class="table mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('app.admin.approval_routing.simulator_change_type') }}</th>
                                        <th>{{ __('app.admin.approval_routing.approval_code') }}</th>
                                        <th>{{ __('app.admin.approval_routing.simulator_current_results_title') }}</th>
                                        <th>{{ __('app.admin.approval_routing.simulator_after_results_title') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($simulationChanges['added'] as $route)
                                        <tr>
                                            <td>{{ __('app.admin.approval_routing.simulator_change_labels.added') }}</td>
                                            <td>{{ __('app.applications.required_approval_options.'.$route['approval_code']) }}</td>
                                            <td>{{ __('app.dashboard.not_available') }}</td>
                                            <td>{{ $route['target_entity_name'] ?? __('app.admin.approval_routing.simulator_no_target') }}<br><span class="text-muted small">{{ __('app.admin.approval_routing.simulator_sources.'.$route['source']) }}</span></td>
                                        </tr>
                                    @endforeach
                                    @foreach ($simulationChanges['removed'] as $route)
                                        <tr>
                                            <td>{{ __('app.admin.approval_routing.simulator_change_labels.removed') }}</td>
                                            <td>{{ __('app.applications.required_approval_options.'.$route['approval_code']) }}</td>
                                            <td>{{ $route['target_entity_name'] ?? __('app.admin.approval_routing.simulator_no_target') }}<br><span class="text-muted small">{{ __('app.admin.approval_routing.simulator_sources.'.$route['source']) }}</span></td>
                                            <td>{{ __('app.dashboard.not_available') }}</td>
                                        </tr>
                                    @endforeach
                                    @foreach ($simulationChanges['changed'] as $change)
                                        <tr>
                                            <td>{{ __('app.admin.approval_routing.simulator_change_labels.changed') }}</td>
                                            <td>{{ __('app.applications.required_approval_options.'.$change['after']['approval_code']) }}</td>
                                            <td>{{ $change['before']['target_entity_name'] ?? __('app.admin.approval_routing.simulator_no_target') }}<br><span class="text-muted small">{{ __('app.admin.approval_routing.simulator_sources.'.$change['before']['source']) }}</span></td>
                                            <td>{{ $change['after']['target_entity_name'] ?? __('app.admin.approval_routing.simulator_no_target') }}<br><span class="text-muted small">{{ __('app.admin.approval_routing.simulator_sources.'.$change['after']['source']) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    @else
        <div class="card">
            <div class="card-body text-muted">
                {{ __('app.admin.approval_routing.simulator_empty_state') }}
            </div>
        </div>
    @endif
@endsection
