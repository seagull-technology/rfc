@php
    $title = $rule->name;
    $breadcrumb = __('app.admin.navigation.approval_routing');
    $conditions = collect((array) ($rule->conditions ?? []));
    $translateCondition = static function (string $type, string $value): string {
        return match ($type) {
            'project_nationalities' => __('app.applications.project_nationalities.'.$value),
            'work_categories' => __('app.applications.work_categories.'.$value),
            'release_methods' => __('app.applications.release_methods.'.$value),
            default => $value,
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
    $formatAuditValues = static function (?array $values) use ($translateCondition, $auditEntityNames): array {
        if (! $values) {
            return [];
        }

        $rows = [
            __('app.admin.approval_routing.name') => $values['name'] ?? null,
            __('app.admin.approval_routing.request_type') => filled($values['request_type'] ?? null) ? __('app.admin.approval_routing.request_types.'.($values['request_type'])) : null,
            __('app.admin.approval_routing.approval_code') => filled($values['approval_code'] ?? null) ? __('app.applications.required_approval_options.'.($values['approval_code'])) : null,
            __('app.admin.approval_routing.target_entity') => filled($values['target_entity_id'] ?? null)
                ? ($auditEntityNames[$values['target_entity_id']] ?? $values['target_entity_id'])
                : null,
            __('app.admin.approval_routing.priority') => $values['priority'] ?? null,
            __('app.admin.approval_routing.status') => array_key_exists('is_active', $values) ? ((bool) $values['is_active'] ? __('app.statuses.active') : __('app.statuses.inactive')) : null,
        ];

        $conditionLabels = collect((array) ($values['conditions'] ?? []))
            ->flatMap(function (array $items, string $type) use ($translateCondition) {
                return collect($items)->map(fn ($item) => $translateCondition($type, (string) $item));
            })
            ->filter()
            ->values()
            ->all();

        $rows[__('app.admin.approval_routing.conditions_title')] = $conditionLabels === []
            ? __('app.admin.approval_routing.any_condition')
            : implode(', ', $conditionLabels);

        return $rows;
    };
@endphp

@extends('layouts.admin-dashboard', ['title' => $title])

@section('content')
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap align-items-center mb-4">
        <div>
            <h2 class="episode-playlist-title wp-heading-inline mb-1">
                <span class="position-relative">{{ $rule->name }}</span>
            </h2>
            <div class="text-muted">{{ __('app.admin.approval_routing.show_intro') }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary" href="{{ route('admin.approval-routing.index') }}">{{ __('app.admin.navigation.approval_routing') }}</a>
            <a class="btn btn-danger" href="{{ route('admin.approval-routing.edit', $rule) }}">{{ __('app.admin.approval_routing.update_action') }}</a>
            <form method="POST" action="{{ route('admin.approval-routing.status', $rule) }}" class="d-inline">
                @csrf
                <input type="hidden" name="is_active" value="{{ $rule->is_active ? 0 : 1 }}">
                <button class="btn btn-outline-{{ $rule->is_active ? 'warning' : 'success' }}" type="submit">
                    {{ $rule->is_active ? __('app.admin.approval_routing.deactivate_action') : __('app.admin.approval_routing.activate_action') }}
                </button>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.approval_routing.summary_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('app.admin.approval_routing.approval_code') }}</small>
                        <div>{{ $rule->localizedApproval() }}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('app.admin.approval_routing.target_entity') }}</small>
                        <div>{{ $rule->targetEntity?->displayName() ?? __('app.dashboard.not_available') }}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('app.admin.approval_routing.priority') }}</small>
                        <div>{{ $rule->priority }}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('app.admin.approval_routing.status') }}</small>
                        <span class="badge bg-{{ $rule->is_active ? 'success' : 'secondary' }}">
                            {{ $rule->is_active ? __('app.statuses.active') : __('app.statuses.inactive') }}
                        </span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('app.admin.approval_routing.last_changed_by') }}</small>
                        <div>{{ $rule->latestAudit?->changedBy?->displayName() ?? __('app.dashboard.not_available') }}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('app.admin.approval_routing.last_changed_at') }}</small>
                        <div>{{ optional($rule->latestAudit?->created_at)->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('app.admin.approval_routing.last_change_action') }}</small>
                        <div>
                            @if ($rule->latestAudit)
                                <span class="badge bg-{{ $auditBadgeClass($rule->latestAudit->action) }}">{{ $rule->latestAudit->localizedAction() }}</span>
                            @else
                                {{ __('app.dashboard.not_available') }}
                            @endif
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('app.admin.approval_routing.analytics_usage_count') }}</small>
                        <div>{{ $ruleUsage['total'] }}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('app.admin.approval_routing.analytics_last_used') }}</small>
                        <div>
                            {{ $ruleUsage['last_used_at'] ? \Illuminate\Support\Carbon::parse($ruleUsage['last_used_at'])->format('Y-m-d H:i') : __('app.dashboard.not_available') }}
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">{{ __('app.admin.approval_routing.analytics_status_breakdown') }}</small>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge bg-light text-dark border">{{ __('app.approvals.statuses.pending') }}: {{ $ruleUsage['pending'] }}</span>
                            <span class="badge bg-light text-dark border">{{ __('app.approvals.statuses.in_review') }}: {{ $ruleUsage['in_review'] }}</span>
                            <span class="badge bg-light text-dark border">{{ __('app.approvals.statuses.approved') }}: {{ $ruleUsage['approved'] }}</span>
                            <span class="badge bg-light text-dark border">{{ __('app.approvals.statuses.rejected') }}: {{ $ruleUsage['rejected'] }}</span>
                        </div>
                    </div>
                    <div class="mb-0">
                        <small class="text-muted d-block">{{ __('app.admin.approval_routing.conditions_title') }}</small>
                        @php
                            $labels = $conditions
                                ->flatMap(fn (array $items, string $type) => collect($items)->map(fn ($item) => $translateCondition($type, (string) $item)))
                                ->filter()
                                ->values();
                        @endphp
                        @if ($labels->isEmpty())
                            <div>{{ __('app.admin.approval_routing.any_condition') }}</div>
                        @else
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($labels as $label)
                                    <span class="badge bg-light text-dark border">{{ $label }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.approval_routing.risk_detail_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    @if ($relatedConflicts->isEmpty())
                        <div class="text-muted">{{ __('app.admin.approval_routing.risk_detail_empty') }}</div>
                    @else
                        <div class="text-muted mb-3">{{ __('app.admin.approval_routing.risk_detail_intro') }}</div>
                        <div class="d-flex flex-column gap-3">
                            @foreach ($relatedConflicts as $conflict)
                                <div class="border rounded p-3">
                                    <div class="d-flex justify-content-between gap-2 flex-wrap align-items-center mb-2">
                                        <span class="badge bg-{{ $conflict['type'] === 'shadowed_rule' ? 'warning text-dark' : 'danger' }}">
                                            {{ __('app.admin.approval_routing.conflict_types.'.$conflict['type']) }}
                                        </span>
                                        <span class="small text-muted">{{ __('app.admin.approval_routing.risk_roles.'.$conflict['role']) }}</span>
                                    </div>
                                    <div class="small mb-2">
                                        <span class="fw-semibold">{{ __('app.admin.approval_routing.conflict_competing_rule') }}:</span>
                                        <a href="{{ route('admin.approval-routing.show', $conflict['other_rule']) }}">{{ $conflict['other_rule']->name }}</a>
                                    </div>
                                    <div class="small text-muted">{{ __('app.admin.approval_routing.conflict_recommendations.'.$conflict['type']) }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <div class="iq-header-title">
                        <h3 class="card-title">{{ __('app.admin.approval_routing.audit_title') }}</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive border rounded py-3">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('app.admin.approval_routing.audit_action_label') }}</th>
                                    <th>{{ __('app.admin.approval_routing.audit_by_label') }}</th>
                                    <th>{{ __('app.admin.approval_routing.audit_at_label') }}</th>
                                    <th>{{ __('app.admin.approval_routing.audit_changes_label') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($audits as $audit)
                                    <tr>
                                        <td>{{ $audit->localizedAction() }}</td>
                                        <td>{{ $audit->changedBy?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                        <td>{{ optional($audit->created_at)->format('Y-m-d H:i') ?: __('app.dashboard.not_available') }}</td>
                                        <td class="text-wrap">
                                            @php
                                                $oldValues = $formatAuditValues($audit->old_values);
                                                $newValues = $formatAuditValues($audit->new_values);
                                            @endphp
                                            <div class="small">
                                                @if ($audit->action === 'created')
                                                    @foreach ($newValues as $label => $value)
                                                        <div><span class="text-muted">{{ $label }}:</span> {{ $value }}</div>
                                                    @endforeach
                                                @elseif ($audit->action === 'deleted')
                                                    @foreach ($oldValues as $label => $value)
                                                        <div><span class="text-muted">{{ $label }}:</span> {{ $value }}</div>
                                                    @endforeach
                                                @else
                                                    @foreach ($newValues as $label => $newValue)
                                                        @php($oldValue = $oldValues[$label] ?? null)
                                                        @continue($oldValue === $newValue)
                                                        <div>
                                                            <span class="text-muted">{{ $label }}:</span>
                                                            <span class="text-decoration-line-through">{{ $oldValue ?: __('app.dashboard.not_available') }}</span>
                                                            <span class="mx-1">&rarr;</span>
                                                            <span>{{ $newValue ?: __('app.dashboard.not_available') }}</span>
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </td>
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
        </div>
    </div>
@endsection
