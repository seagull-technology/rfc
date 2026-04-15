@php
    $translateCondition = static function (string $type, string $value): string {
        return match ($type) {
            'project_nationalities' => __('app.applications.project_nationalities.'.$value),
            'work_categories' => __('app.applications.work_categories.'.$value),
            'release_methods' => __('app.applications.release_methods.'.$value),
            default => $value,
        };
    };
    $conditionLabels = collect($conditions)
        ->flatMap(function (array $items, string $type) use ($translateCondition) {
            return collect($items)->map(fn ($item) => $translateCondition($type, (string) $item));
        })
        ->filter()
        ->values();
@endphp

<div class="card h-100 mb-0">
    <div class="card-header">
        <div class="iq-header-title">
            <h3 class="card-title">{{ __('app.admin.approval_routing.preview_title') }}</h3>
        </div>
    </div>
    <div class="card-body">
        @if (! $previewReady)
            <div class="text-muted">{{ __('app.admin.approval_routing.preview_empty') }}</div>
        @else
            <div class="mb-3">
                <small class="text-muted d-block">{{ __('app.admin.approval_routing.approval_code') }}</small>
                <div>{{ __('app.applications.required_approval_options.'.$approvalCode) }}</div>
            </div>

            <div class="mb-3">
                <small class="text-muted d-block">{{ __('app.admin.approval_routing.target_entity') }}</small>
                <div>{{ $targetEntity?->displayName() ?? __('app.admin.approval_routing.preview_no_target') }}</div>
            </div>

            <div class="mb-3">
                <small class="text-muted d-block">{{ __('app.admin.approval_routing.conditions_title') }}</small>
                @if ($conditionLabels->isEmpty())
                    <div>{{ __('app.admin.approval_routing.any_condition') }}</div>
                @else
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($conditionLabels as $label)
                            <span class="badge bg-light text-dark border">{{ $label }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($duplicateRule)
                <div class="alert alert-warning py-2">
                    <div class="fw-semibold">{{ __('app.admin.approval_routing.preview_duplicate_title') }}</div>
                    <div class="small">
                        {{ __('app.admin.approval_routing.preview_duplicate_text', ['name' => $duplicateRule->name]) }}
                    </div>
                </div>
            @endif

            @if ($overlapRules->isNotEmpty())
                <div class="alert alert-info py-2">
                    <div class="fw-semibold mb-1">{{ __('app.admin.approval_routing.preview_overlap_title') }}</div>
                    <div class="small text-muted mb-2">{{ __('app.admin.approval_routing.preview_overlap_text') }}</div>
                    <div class="d-flex flex-column gap-2">
                        @foreach ($overlapRules as $overlap)
                            <div class="border rounded p-2 bg-white">
                                <div class="fw-semibold">{{ $overlap['rule']->name }}</div>
                                <div class="small text-muted">
                                    {{ __('app.admin.approval_routing.preview_overlap_relations.'.$overlap['relation']) }}
                                    @if ($overlap['same_target'])
                                        • {{ __('app.admin.approval_routing.preview_overlap_same_target') }}
                                    @else
                                        • {{ __('app.admin.approval_routing.preview_overlap_other_target', ['name' => $overlap['rule']->targetEntity?->displayName() ?? __('app.dashboard.not_available')]) }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="row g-2 mb-3">
                <div class="col-4">
                    <div class="border rounded p-2 text-center h-100">
                        <div class="small text-muted">{{ __('app.admin.approval_routing.preview_stats.total') }}</div>
                        <div class="fs-4 fw-semibold">{{ $matchedApplicationsCount }}</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border rounded p-2 text-center h-100">
                        <div class="small text-muted">{{ __('app.admin.approval_routing.preview_stats.active') }}</div>
                        <div class="fs-4 fw-semibold">{{ $matchedStats['active'] }}</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border rounded p-2 text-center h-100">
                        <div class="small text-muted">{{ __('app.admin.approval_routing.preview_stats.resolved') }}</div>
                        <div class="fs-4 fw-semibold">{{ $matchedStats['resolved'] }}</div>
                    </div>
                </div>
            </div>

            <div class="mb-2 fw-semibold">{{ __('app.admin.approval_routing.preview_matches_title') }}</div>

            @if ($matchedApplications->isEmpty())
                <div class="text-muted">{{ __('app.admin.approval_routing.preview_no_matches') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('app.applications.request_number') }}</th>
                                <th>{{ __('app.applications.project_name') }}</th>
                                <th>{{ __('app.admin.applications.entity') }}</th>
                                <th>{{ __('app.applications.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($matchedApplications as $application)
                                <tr>
                                    <td>{{ $application->code }}</td>
                                    <td>{{ $application->project_name }}</td>
                                    <td>{{ $application->entity?->displayName() ?? __('app.dashboard.not_available') }}</td>
                                    <td>{{ $application->localizedStatus() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($matchedApplicationsCount > $matchedApplications->count())
                    <div class="small text-muted mt-2">
                        {{ __('app.admin.approval_routing.preview_more_matches', ['count' => $matchedApplicationsCount - $matchedApplications->count()]) }}
                    </div>
                @endif
            @endif
        @endif
    </div>
</div>
