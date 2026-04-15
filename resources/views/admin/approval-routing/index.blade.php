@php
    $title = __('app.admin.approval_routing.title');
    $breadcrumb = __('app.admin.navigation.approval_routing');
    $translateCondition = static function (string $type, string $value): string {
        return match ($type) {
            'project_nationalities' => __('app.applications.project_nationalities.'.$value),
            'work_categories' => __('app.applications.work_categories.'.$value),
            'release_methods' => __('app.applications.release_methods.'.$value),
            default => $value,
        };
    };
    $conflictBadgeClass = static function (string $type): string {
        return match ($type) {
            'shadowed_rule' => 'warning',
            'same_priority_overlap' => 'danger',
            default => 'secondary',
        };
    };
    $auditBadgeClass = static function (?string $action): string {
        return match ($action) {
            'created', 'activated' => 'success',
            'updated' => 'primary',
            'deactivated' => 'warning text-dark',
            'deleted' => 'danger',
            default => 'secondary',
        };
    };
    $riskFilterUrl = static function (string $risk) use ($filters): string {
        return route('admin.approval-routing.index', array_filter([
            'q' => $filters['q'],
            'approval_code' => $filters['approval_code'] !== 'all' ? $filters['approval_code'] : null,
            'target_entity_id' => $filters['target_entity_id'] !== '' ? $filters['target_entity_id'] : null,
            'is_active' => $filters['is_active'] !== 'all' ? $filters['is_active'] : null,
            'risk' => $risk !== 'all' ? $risk : null,
            'cleanup' => $filters['cleanup'] !== 'all' ? $filters['cleanup'] : null,
        ], fn ($value) => $value !== null && $value !== ''));
    };
    $cleanupFilterUrl = static function (string $cleanup) use ($filters): string {
        return route('admin.approval-routing.index', array_filter([
            'q' => $filters['q'],
            'approval_code' => $filters['approval_code'] !== 'all' ? $filters['approval_code'] : null,
            'target_entity_id' => $filters['target_entity_id'] !== '' ? $filters['target_entity_id'] : null,
            'is_active' => $filters['is_active'] !== 'all' ? $filters['is_active'] : null,
            'risk' => $filters['risk'] !== 'all' ? $filters['risk'] : null,
            'cleanup' => $cleanup !== 'all' ? $cleanup : null,
        ], fn ($value) => $value !== null && $value !== ''));
    };
    $filteredRiskRuleIds = $conflictReport
        ->flatMap(fn (array $finding) => [$finding['primary_rule']->getKey(), $finding['secondary_rule']->getKey()])
        ->unique()
        ->values();
    $cleanupRuleIds = $cleanupCandidates
        ->pluck('rule.id')
        ->unique()
        ->values();
    $topUsedRules = collect($rules)
        ->sortByDesc(fn ($rule) => data_get($usageStats, $rule->getKey().'.total', 0))
        ->filter(fn ($rule) => data_get($usageStats, $rule->getKey().'.total', 0) > 0)
        ->take(5)
        ->values();
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('content')
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
        <div>
            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                <span class="position-relative">{{ __('app.admin.approval_routing.directory_title') }}</span>
            </h2>
            <div class="text-muted">{{ __('app.admin.approval_routing.intro') }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">{{ __('app.admin.navigation.dashboard') }}</a>
            <a class="btn btn-outline-secondary" href="{{ route('admin.approval-routing.simulator') }}">{{ __('app.admin.approval_routing.simulator_action') }}</a>
            <a class="btn btn-danger" href="{{ route('admin.approval-routing.create') }}">{{ __('app.admin.approval_routing.create_action') }}</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="iq-header-title">
                <h3 class="card-title">{{ __('app.admin.approval_routing.cleanup_title') }}</h3>
            </div>
            <div class="text-muted mt-2">{{ __('app.admin.approval_routing.cleanup_intro', ['days' => $cleanupSummary['threshold_days']]) }}</div>
            <div class="d-flex gap-2 flex-wrap mt-3">
                <span class="badge bg-dark">{{ __('app.admin.approval_routing.cleanup_summary.total', ['count' => $cleanupSummary['total']]) }}</span>
                <span class="badge bg-secondary">{{ __('app.admin.approval_routing.cleanup_summary.unused', ['count' => $cleanupSummary['unused']]) }}</span>
                <span class="badge bg-warning text-dark">{{ __('app.admin.approval_routing.cleanup_summary.stale', ['count' => $cleanupSummary['stale']]) }}</span>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-3">
                <a class="btn btn-sm {{ $filters['cleanup'] === 'unused' ? 'btn-secondary' : 'btn-outline-secondary' }}" href="{{ $cleanupFilterUrl('unused') }}">{{ __('app.admin.approval_routing.cleanup_filters.unused') }}</a>
                <a class="btn btn-sm {{ $filters['cleanup'] === 'stale' ? 'btn-warning' : 'btn-outline-warning' }}" href="{{ $cleanupFilterUrl('stale') }}">{{ __('app.admin.approval_routing.cleanup_filters.stale') }}</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ $cleanupFilterUrl('all') }}">{{ __('app.admin.approval_routing.cleanup_filters.clear') }}</a>
                @if ($cleanupRuleIds->isNotEmpty())
                    <form method="POST" action="{{ route('admin.approval-routing.bulk-status') }}" class="d-inline-flex">
                        @csrf
                        @foreach ($cleanupRuleIds as $ruleId)
                            <input type="hidden" name="rule_ids[]" value="{{ $ruleId }}">
                        @endforeach
                        <input type="hidden" name="is_active" value="0">
                        <input type="hidden" name="redirect_q" value="{{ $filters['q'] }}">
                        <input type="hidden" name="redirect_approval_code" value="{{ $filters['approval_code'] }}">
                        <input type="hidden" name="redirect_target_entity_id" value="{{ $filters['target_entity_id'] }}">
                        <input type="hidden" name="redirect_is_active" value="{{ $filters['is_active'] }}">
                        <input type="hidden" name="redirect_risk" value="{{ $filters['risk'] }}">
                        <input type="hidden" name="redirect_cleanup" value="{{ $filters['cleanup'] }}">
                        <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('app.admin.approval_routing.cleanup_bulk_deactivate_action') }}</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if ($cleanupCandidates->isEmpty())
                <div class="text-muted">{{ __('app.admin.approval_routing.cleanup_empty') }}</div>
            @else
                <div class="table-responsive border rounded py-3">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('app.admin.approval_routing.name') }}</th>
                                <th>{{ __('app.admin.approval_routing.cleanup_type') }}</th>
                                <th>{{ __('app.admin.approval_routing.analytics_usage_count') }}</th>
                                <th>{{ __('app.admin.approval_routing.analytics_last_used') }}</th>
                                <th>{{ __('app.admin.approval_routing.last_changed_by') }}</th>
                                <th>{{ __('app.admin.approval_routing.last_changed_at') }}</th>
                                <th>{{ __('app.admin.approval_routing.last_change_action') }}</th>
                                <th>{{ __('app.admin.approval_routing.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cleanupCandidates as $candidate)
                                @php
                                    $latestAudit = $candidate['rule']->latestAudit;
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.approval-routing.show', $candidate['rule']) }}">{{ $candidate['rule']->name }}</a>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $candidate['type'] === 'unused' ? 'secondary' : 'warning text-dark' }}">
                                            {{ __('app.admin.approval_routing.cleanup_types.'.$candidate['type']) }}
                                        </span>
                                    </td>
                                    <td>{{ $candidate['usage']['total'] }}</td>
                                    <td>{{ $candidate['usage']['last_used_at'] ? \Illuminate\Support\Carbon::parse($candidate['usage']['last_used_at'])->format('Y-m-d H:i') : __('app.dashboard.not_available') }}</td>
                                    <td>{{ $latestAudit?->changedBy?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                    <td>{{ optional($latestAudit?->created_at)->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</td>
                                    <td>
                                        @if ($latestAudit)
                                            <span class="badge bg-{{ $auditBadgeClass($latestAudit->action) }}">{{ $latestAudit->localizedAction() }}</span>
                                        @else
                                            {{ __('app.dashboard.not_available') }}
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.approval-routing.edit', $candidate['rule']) }}">{{ __('app.admin.approval_routing.update_action') }}</a>
                                            <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.approval-routing.create', ['duplicate_rule_id' => $candidate['rule']->getKey()]) }}">{{ __('app.admin.approval_routing.duplicate_action') }}</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="iq-header-title">
                <h3 class="card-title">{{ __('app.admin.approval_routing.analytics_title') }}</h3>
            </div>
            <div class="text-muted mt-2">{{ __('app.admin.approval_routing.analytics_intro') }}</div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ __('app.admin.approval_routing.analytics_cards.total_routed') }}</div>
                        <div class="fs-3 fw-semibold">{{ $usageSummary['total_routed'] }}</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ __('app.admin.approval_routing.analytics_cards.rules_used') }}</div>
                        <div class="fs-3 fw-semibold">{{ $usageSummary['rules_used'] }}</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ __('app.admin.approval_routing.analytics_cards.unused_active_rules') }}</div>
                        <div class="fs-3 fw-semibold">{{ $usageSummary['unused_active_rules'] }}</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ __('app.admin.approval_routing.analytics_cards.top_rule') }}</div>
                        @if ($usageSummary['top_rule'])
                            <div class="fw-semibold">{{ $usageSummary['top_rule']->name }}</div>
                            <div class="small text-muted">{{ __('app.admin.approval_routing.analytics_cards.top_rule_count', ['count' => $usageSummary['top_rule_total']]) }}</div>
                        @else
                            <div class="text-muted">{{ __('app.admin.approval_routing.analytics_empty') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            @if ($topUsedRules->isEmpty())
                <div class="text-muted">{{ __('app.admin.approval_routing.analytics_empty') }}</div>
            @else
                <div class="table-responsive border rounded py-3">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('app.admin.approval_routing.name') }}</th>
                                <th>{{ __('app.admin.approval_routing.analytics_usage_count') }}</th>
                                <th>{{ __('app.admin.approval_routing.analytics_last_used') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topUsedRules as $rule)
                                @php
                                    $stat = $usageStats->get($rule->getKey(), ['total' => 0, 'last_used_at' => null]);
                                @endphp
                                <tr>
                                    <td><a href="{{ route('admin.approval-routing.show', $rule) }}">{{ $rule->name }}</a></td>
                                    <td>{{ $stat['total'] }}</td>
                                    <td>{{ $stat['last_used_at'] ? \Illuminate\Support\Carbon::parse($stat['last_used_at'])->format('Y-m-d H:i') : __('app.dashboard.not_available') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.approval-routing.index') }}" class="row g-3 align-items-end">
                <div class="col-xl-3">
                    <label for="filter-q" class="form-label">{{ __('app.admin.filters.search_label') }}</label>
                    <input id="filter-q" name="q" type="text" class="form-control bg-white" value="{{ $filters['q'] }}" placeholder="{{ __('app.admin.approval_routing.search_placeholder') }}">
                </div>
                <div class="col-xl-2">
                    <label for="filter-approval-code" class="form-label">{{ __('app.admin.approval_routing.approval_code') }}</label>
                    <select id="filter-approval-code" name="approval_code" class="form-select">
                        <option value="all">{{ __('app.admin.filters.all_option') }}</option>
                        @foreach ($approvalCodes as $approvalCode)
                            <option value="{{ $approvalCode }}" @selected($filters['approval_code'] === $approvalCode)>{{ __('app.applications.required_approval_options.'.$approvalCode) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3">
                    <label for="filter-target-entity" class="form-label">{{ __('app.admin.approval_routing.target_entity') }}</label>
                    <select id="filter-target-entity" name="target_entity_id" class="form-select">
                        <option value="">{{ __('app.admin.filters.all_option') }}</option>
                        @foreach ($authorityEntities as $entity)
                            <option value="{{ $entity->getKey() }}" @selected($filters['target_entity_id'] === (string) $entity->getKey())>{{ $entity->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2">
                    <label for="filter-active" class="form-label">{{ __('app.admin.approval_routing.status') }}</label>
                    <select id="filter-active" name="is_active" class="form-select">
                        <option value="all" @selected($filters['is_active'] === 'all')>{{ __('app.admin.filters.all_option') }}</option>
                        <option value="1" @selected($filters['is_active'] === '1')>{{ __('app.statuses.active') }}</option>
                        <option value="0" @selected($filters['is_active'] === '0')>{{ __('app.statuses.inactive') }}</option>
                    </select>
                </div>
                <div class="col-xl-2">
                    <label for="filter-risk" class="form-label">{{ __('app.admin.approval_routing.conflict_risk') }}</label>
                    <select id="filter-risk" name="risk" class="form-select">
                        <option value="all" @selected($filters['risk'] === 'all')>{{ __('app.admin.filters.all_option') }}</option>
                        <option value="any" @selected($filters['risk'] === 'any')>{{ __('app.admin.approval_routing.risk_filters.any') }}</option>
                        <option value="shadowed_rule" @selected($filters['risk'] === 'shadowed_rule')>{{ __('app.admin.approval_routing.conflict_types.shadowed_rule') }}</option>
                        <option value="same_priority_overlap" @selected($filters['risk'] === 'same_priority_overlap')>{{ __('app.admin.approval_routing.conflict_types.same_priority_overlap') }}</option>
                    </select>
                </div>
                <div class="col-xl-2">
                    <label for="filter-cleanup" class="form-label">{{ __('app.admin.approval_routing.cleanup_type') }}</label>
                    <select id="filter-cleanup" name="cleanup" class="form-select">
                        <option value="all" @selected($filters['cleanup'] === 'all')>{{ __('app.admin.filters.all_option') }}</option>
                        <option value="unused" @selected($filters['cleanup'] === 'unused')>{{ __('app.admin.approval_routing.cleanup_types.unused') }}</option>
                        <option value="stale" @selected($filters['cleanup'] === 'stale')>{{ __('app.admin.approval_routing.cleanup_types.stale') }}</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button class="btn btn-danger" type="submit">{{ __('app.admin.filters.apply_action') }}</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.approval-routing.index') }}">{{ __('app.admin.filters.clear_action') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="iq-header-title">
                <h3 class="card-title">{{ __('app.admin.approval_routing.conflict_report_title') }}</h3>
            </div>
            <div class="text-muted mt-2">{{ __('app.admin.approval_routing.conflict_report_intro') }}</div>
            <div class="d-flex gap-2 flex-wrap mt-3">
                <span class="badge bg-dark">{{ __('app.admin.approval_routing.conflict_summary.findings', ['count' => $conflictSummary['filtered_findings']]) }}</span>
                <span class="badge bg-secondary">{{ __('app.admin.approval_routing.conflict_summary.affected_rules', ['count' => $conflictSummary['affected_rules']]) }}</span>
                <span class="badge bg-warning text-dark">{{ __('app.admin.approval_routing.conflict_summary.shadowed_rule', ['count' => $conflictSummary['shadowed_rule']]) }}</span>
                <span class="badge bg-danger">{{ __('app.admin.approval_routing.conflict_summary.same_priority_overlap', ['count' => $conflictSummary['same_priority_overlap']]) }}</span>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-3">
                <a class="btn btn-sm {{ $filters['risk'] === 'any' ? 'btn-danger' : 'btn-outline-danger' }}" href="{{ $riskFilterUrl('any') }}">{{ __('app.admin.approval_routing.risk_filters.any') }}</a>
                <a class="btn btn-sm {{ $filters['risk'] === 'shadowed_rule' ? 'btn-warning' : 'btn-outline-warning' }}" href="{{ $riskFilterUrl('shadowed_rule') }}">{{ __('app.admin.approval_routing.conflict_types.shadowed_rule') }}</a>
                <a class="btn btn-sm {{ $filters['risk'] === 'same_priority_overlap' ? 'btn-danger' : 'btn-outline-danger' }}" href="{{ $riskFilterUrl('same_priority_overlap') }}">{{ __('app.admin.approval_routing.conflict_types.same_priority_overlap') }}</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ $riskFilterUrl('all') }}">{{ __('app.admin.approval_routing.risk_filters.clear') }}</a>
                @if ($filteredRiskRuleIds->isNotEmpty())
                    <form method="POST" action="{{ route('admin.approval-routing.bulk-status') }}" class="d-inline-flex">
                        @csrf
                        @foreach ($filteredRiskRuleIds as $ruleId)
                            <input type="hidden" name="rule_ids[]" value="{{ $ruleId }}">
                        @endforeach
                        <input type="hidden" name="is_active" value="0">
                        <input type="hidden" name="redirect_q" value="{{ $filters['q'] }}">
                        <input type="hidden" name="redirect_approval_code" value="{{ $filters['approval_code'] }}">
                        <input type="hidden" name="redirect_target_entity_id" value="{{ $filters['target_entity_id'] }}">
                        <input type="hidden" name="redirect_is_active" value="{{ $filters['is_active'] }}">
                        <input type="hidden" name="redirect_risk" value="{{ $filters['risk'] }}">
                        <input type="hidden" name="redirect_cleanup" value="{{ $filters['cleanup'] }}">
                        <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('app.admin.approval_routing.bulk_deactivate_action') }}</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if ($conflictReport->isEmpty())
                <div class="text-center text-muted py-4">{{ __('app.admin.approval_routing.conflict_report_empty') }}</div>
            @else
                <div class="table-responsive border rounded py-3">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('app.admin.approval_routing.conflict_risk') }}</th>
                                <th>{{ __('app.admin.approval_routing.approval_code') }}</th>
                                <th>{{ __('app.admin.approval_routing.target_entity') }}</th>
                                <th>{{ __('app.admin.approval_routing.conflict_rule_a') }}</th>
                                <th>{{ __('app.admin.approval_routing.conflict_rule_b') }}</th>
                                <th>{{ __('app.admin.approval_routing.conflict_recommendation') }}</th>
                                <th>{{ __('app.admin.approval_routing.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($conflictReport as $finding)
                                <tr>
                                    <td>
                                        <span class="badge bg-{{ $conflictBadgeClass($finding['type']) }}">
                                            {{ __('app.admin.approval_routing.conflict_types.'.$finding['type']) }}
                                        </span>
                                    </td>
                                    <td>{{ __('app.applications.required_approval_options.'.$finding['approval_code']) }}</td>
                                    <td>{{ $finding['target_entity_name'] }}</td>
                                    <td>
                                        <div class="fw-semibold">
                                            <a href="{{ route('admin.approval-routing.show', $finding['primary_rule']) }}">{{ $finding['primary_rule']->name }}</a>
                                        </div>
                                        <div class="text-muted small">
                                            {{ __('app.admin.approval_routing.priority') }}: {{ $finding['primary_rule']->priority }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">
                                            <a href="{{ route('admin.approval-routing.show', $finding['secondary_rule']) }}">{{ $finding['secondary_rule']->name }}</a>
                                        </div>
                                        <div class="text-muted small">
                                            {{ __('app.admin.approval_routing.priority') }}: {{ $finding['secondary_rule']->priority }}
                                        </div>
                                    </td>
                                    <td class="text-muted">
                                        {{ __('app.admin.approval_routing.conflict_recommendations.'.$finding['type']) }}
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.approval-routing.edit', $finding['primary_rule']) }}">{{ __('app.admin.approval_routing.conflict_actions.edit_primary') }}</a>
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.approval-routing.edit', $finding['secondary_rule']) }}">{{ __('app.admin.approval_routing.conflict_actions.edit_secondary') }}</a>
                                            <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.approval-routing.create', ['duplicate_rule_id' => $finding['secondary_rule']->getKey()]) }}">{{ __('app.admin.approval_routing.conflict_actions.duplicate_secondary') }}</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="iq-header-title">
                <h3 class="card-title">{{ __('app.admin.approval_routing.audit_recent_title') }}</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive border rounded py-3">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('app.admin.approval_routing.name') }}</th>
                            <th>{{ __('app.admin.approval_routing.audit_action_label') }}</th>
                            <th>{{ __('app.admin.approval_routing.audit_by_label') }}</th>
                            <th>{{ __('app.admin.approval_routing.audit_at_label') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentAudits as $audit)
                            <tr>
                                <td>
                                    @if ($audit->rule)
                                        <a href="{{ route('admin.approval-routing.show', $audit->rule) }}">{{ $audit->rule_name }}</a>
                                    @else
                                        {{ $audit->rule_name }}
                                    @endif
                                </td>
                                <td>{{ $audit->localizedAction() }}</td>
                                <td>{{ $audit->changedBy?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                <td>{{ optional($audit->created_at)->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">{{ __('app.admin.approval_routing.audit_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive border rounded py-3">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('app.admin.approval_routing.name') }}</th>
                            <th>{{ __('app.admin.approval_routing.approval_code') }}</th>
                            <th>{{ __('app.admin.approval_routing.target_entity') }}</th>
                            <th>{{ __('app.admin.approval_routing.conditions_title') }}</th>
                            <th>{{ __('app.admin.approval_routing.priority') }}</th>
                            <th>{{ __('app.admin.approval_routing.analytics_usage_count') }}</th>
                            <th>{{ __('app.admin.approval_routing.analytics_last_used') }}</th>
                            <th>{{ __('app.admin.approval_routing.last_changed_by') }}</th>
                            <th>{{ __('app.admin.approval_routing.last_changed_at') }}</th>
                            <th>{{ __('app.admin.approval_routing.last_change_action') }}</th>
                            <th>{{ __('app.admin.approval_routing.status') }}</th>
                            <th>{{ __('app.admin.approval_routing.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rules as $rule)
                            @php
                                $latestAudit = $rule->latestAudit;
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $rule->name }}</div>
                                    <div class="text-muted small">{{ __('app.admin.approval_routing.request_types.'.$rule->request_type) }}</div>
                                </td>
                                <td>{{ $rule->localizedApproval() }}</td>
                                <td>{{ $rule->targetEntity?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                <td>
                                    @php
                                        $conditionLabels = collect((array) ($rule->conditions ?? []))
                                            ->flatMap(function (array $values, string $type) use ($translateCondition) {
                                                return collect($values)
                                                    ->filter(fn ($value) => filled($value))
                                                    ->map(fn ($value) => $translateCondition($type, (string) $value));
                                            })
                                            ->values();
                                    @endphp
                                    @if ($conditionLabels->isEmpty())
                                        <span class="text-muted">{{ __('app.admin.approval_routing.any_condition') }}</span>
                                    @else
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach ($conditionLabels as $label)
                                                <span class="badge bg-light text-dark border">{{ $label }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $rule->priority }}</td>
                                @php
                                    $usage = $usageStats->get($rule->getKey(), ['total' => 0, 'last_used_at' => null]);
                                @endphp
                                <td>{{ $usage['total'] }}</td>
                                <td>{{ $usage['last_used_at'] ? \Illuminate\Support\Carbon::parse($usage['last_used_at'])->format('Y-m-d H:i') : __('app.dashboard.not_available') }}</td>
                                <td>{{ $latestAudit?->changedBy?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                <td>{{ optional($latestAudit?->created_at)->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</td>
                                <td>
                                    @if ($latestAudit)
                                        <span class="badge bg-{{ $auditBadgeClass($latestAudit->action) }}">{{ $latestAudit->localizedAction() }}</span>
                                    @else
                                        {{ __('app.dashboard.not_available') }}
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $rule->is_active ? 'success' : 'secondary' }}">
                                        {{ $rule->is_active ? __('app.statuses.active') : __('app.statuses.inactive') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.approval-routing.show', $rule) }}">{{ __('app.admin.dashboard.table_action') }}</a>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.approval-routing.edit', $rule) }}">{{ __('app.admin.approval_routing.update_action') }}</a>
                                        <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.approval-routing.create', ['duplicate_rule_id' => $rule->getKey()]) }}">{{ __('app.admin.approval_routing.duplicate_action') }}</a>
                                        <form method="POST" action="{{ route('admin.approval-routing.status', $rule) }}">
                                            @csrf
                                            <input type="hidden" name="is_active" value="{{ $rule->is_active ? 0 : 1 }}">
                                            <button class="btn btn-sm btn-outline-{{ $rule->is_active ? 'warning' : 'success' }}" type="submit">
                                                {{ $rule->is_active ? __('app.admin.approval_routing.deactivate_action') : __('app.admin.approval_routing.activate_action') }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.approval-routing.destroy', $rule) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('app.admin.approval_routing.delete_action') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">{{ __('app.admin.approval_routing.empty_state') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
