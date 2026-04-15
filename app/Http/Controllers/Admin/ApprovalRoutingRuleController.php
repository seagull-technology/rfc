<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationAuthorityApproval;
use App\Models\ApprovalRoutingRule;
use App\Models\ApprovalRoutingRuleAudit;
use App\Models\Entity;
use App\Models\User;
use App\Services\ApprovalRoutingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApprovalRoutingRuleController extends Controller
{
    public function __construct(
        private readonly ApprovalRoutingService $approvalRoutingService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'approval_code' => ['nullable', Rule::in(['all', ...$this->approvalCodes()])],
            'target_entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'is_active' => ['nullable', Rule::in(['all', '1', '0'])],
            'risk' => ['nullable', Rule::in(['all', 'any', 'shadowed_rule', 'same_priority_overlap'])],
            'cleanup' => ['nullable', Rule::in(['all', 'unused', 'stale'])],
            'last_action' => ['nullable', Rule::in(['all', ...$this->auditActions()])],
            'last_changed_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $activeRules = ApprovalRoutingRule::query()
            ->with(['targetEntity.group', 'authorityApprovals', 'latestAudit.changedBy'])
            ->where('request_type', 'application')
            ->where('is_active', true)
            ->orderBy('approval_code')
            ->orderBy('target_entity_id')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
        $activeUsageStats = $this->usageStatsForRules($activeRules);

        $conflictReport = $this->buildConflictReport($activeRules);
        $riskFilter = $filters['risk'] ?? 'all';
        $cleanupFilter = $filters['cleanup'] ?? 'all';
        $lastActionFilter = $filters['last_action'] ?? 'all';
        $lastChangedByUserIdFilter = isset($filters['last_changed_by_user_id']) ? (int) $filters['last_changed_by_user_id'] : null;
        $filteredConflictReport = $riskFilter === 'all'
            ? $conflictReport
            : $conflictReport->filter(fn (array $finding): bool => $riskFilter === 'any' || $finding['type'] === $riskFilter)->values();
        $cleanupCandidates = $this->cleanupCandidatesForRules($activeRules, $activeUsageStats, $cleanupFilter);

        $query = ApprovalRoutingRule::query()
            ->with(['targetEntity.group', 'latestAudit.changedBy'])
            ->orderBy('priority')
            ->orderBy('id');

        if (filled($filters['q'] ?? null)) {
            $search = trim((string) $filters['q']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhereHas('targetEntity', fn (Builder $entityQuery): Builder => $entityQuery
                        ->where('name_en', 'like', '%'.$search.'%')
                        ->orWhere('name_ar', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%'));
            });
        }

        if (($filters['approval_code'] ?? 'all') !== 'all') {
            $query->where('approval_code', $filters['approval_code']);
        }

        if (filled($filters['target_entity_id'] ?? null)) {
            $query->where('target_entity_id', $filters['target_entity_id']);
        }

        if (($filters['is_active'] ?? 'all') !== 'all') {
            $query->where('is_active', $filters['is_active'] === '1');
        }

        if ($lastActionFilter !== 'all') {
            $query->whereHas('latestAudit', fn (Builder $auditQuery): Builder => $auditQuery->where('action', $lastActionFilter));
        }

        if ($lastChangedByUserIdFilter !== null) {
            $query->whereHas('latestAudit', fn (Builder $auditQuery): Builder => $auditQuery->where('changed_by_user_id', $lastChangedByUserIdFilter));
        }

        if ($riskFilter !== 'all') {
            $riskyRuleIds = $filteredConflictReport
                ->flatMap(fn (array $finding): array => [
                    $finding['primary_rule']->getKey(),
                    $finding['secondary_rule']->getKey(),
                ])
                ->unique()
                ->values();

            $query->whereIn('id', $riskyRuleIds->all());
        }

        if ($cleanupFilter !== 'all') {
            $query->whereIn('id', $cleanupCandidates->pluck('rule.id')->all());
        }

        $rules = $query->get();
        $usageStats = $this->usageStatsForRules($rules);

        return view('admin.approval-routing.index', [
            'rules' => $rules,
            'usageStats' => $usageStats,
            'usageSummary' => $this->usageSummaryForRules($rules, $usageStats),
            'cleanupCandidates' => $cleanupCandidates,
            'cleanupSummary' => [
                'total' => $cleanupCandidates->count(),
                'unused' => $this->cleanupCandidatesForRules($activeRules, $activeUsageStats, 'unused')->count(),
                'stale' => $this->cleanupCandidatesForRules($activeRules, $activeUsageStats, 'stale')->count(),
                'threshold_days' => $this->staleRuleThresholdDays(),
            ],
            'conflictReport' => $filteredConflictReport,
            'conflictSummary' => [
                'total_findings' => $conflictReport->count(),
                'filtered_findings' => $filteredConflictReport->count(),
                'affected_rules' => $filteredConflictReport
                    ->flatMap(fn (array $finding): array => [
                        $finding['primary_rule']->getKey(),
                        $finding['secondary_rule']->getKey(),
                    ])
                    ->unique()
                    ->count(),
                'shadowed_rule' => $conflictReport->where('type', 'shadowed_rule')->count(),
                'same_priority_overlap' => $conflictReport->where('type', 'same_priority_overlap')->count(),
            ],
            'recentAudits' => ApprovalRoutingRuleAudit::query()
                ->with(['changedBy', 'rule.targetEntity'])
                ->latest()
                ->take(12)
                ->get(),
            'authorityEntities' => $this->authorityEntities(),
            'approvalCodes' => $this->approvalCodes(),
            'auditActions' => $this->auditActions(),
            'auditUsers' => $this->auditUsers(),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'approval_code' => $filters['approval_code'] ?? 'all',
                'target_entity_id' => isset($filters['target_entity_id']) ? (string) $filters['target_entity_id'] : '',
                'is_active' => $filters['is_active'] ?? 'all',
                'risk' => $riskFilter,
                'cleanup' => $cleanupFilter,
                'last_action' => $lastActionFilter,
                'last_changed_by_user_id' => $lastChangedByUserIdFilter ? (string) $lastChangedByUserIdFilter : '',
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $duplicateRuleId = $request->integer('duplicate_rule_id');
        $rule = $duplicateRuleId > 0
            ? $this->duplicateDraftRule($duplicateRuleId)
            : new ApprovalRoutingRule([
                'request_type' => 'application',
                'priority' => 100,
                'is_active' => true,
                'conditions' => [],
            ]);

        return view('admin.approval-routing.create', [
            ...$this->formViewData($rule),
            'isDuplicateDraft' => $duplicateRuleId > 0,
            'sourceRule' => $duplicateRuleId > 0 ? ApprovalRoutingRule::query()->find($duplicateRuleId) : null,
        ]);
    }

    public function bulkUpdateStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rule_ids' => ['required', 'array', 'min:1'],
            'rule_ids.*' => ['integer', 'exists:approval_routing_rules,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $rules = ApprovalRoutingRule::query()
            ->whereIn('id', $validated['rule_ids'])
            ->get();

        $targetStatus = (bool) $validated['is_active'];
        $updatedCount = 0;

        foreach ($rules as $rule) {
            if ((bool) $rule->is_active === $targetStatus) {
                continue;
            }

            $oldValues = $this->auditValues($rule);
            $rule->forceFill([
                'is_active' => $targetStatus,
            ])->save();
            $rule->refresh();

            $this->logAudit(
                action: $rule->is_active ? 'activated' : 'deactivated',
                rule: $rule,
                userId: $request->user()?->getKey(),
                oldValues: $oldValues,
                newValues: $this->auditValues($rule),
            );

            $updatedCount++;
        }

        return redirect()
            ->route('admin.approval-routing.index', array_filter([
                'q' => (string) $request->input('redirect_q', ''),
                'approval_code' => (string) $request->input('redirect_approval_code', 'all'),
                'target_entity_id' => (string) $request->input('redirect_target_entity_id', ''),
                'is_active' => (string) $request->input('redirect_is_active', 'all'),
                'risk' => (string) $request->input('redirect_risk', 'all'),
                'cleanup' => (string) $request->input('redirect_cleanup', 'all'),
                'last_action' => (string) $request->input('redirect_last_action', 'all'),
                'last_changed_by_user_id' => (string) $request->input('redirect_last_changed_by_user_id', ''),
            ], fn (string $value): bool => $value !== '' && $value !== 'all'))
            ->with('status', $targetStatus
                ? __('app.admin.approval_routing.bulk_activated', ['count' => $updatedCount])
                : __('app.admin.approval_routing.bulk_deactivated', ['count' => $updatedCount]));
    }

    public function simulator(Request $request): View
    {
        $filters = $request->validate([
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
            'q' => ['nullable', 'string', 'max:255'],
            'draft.name' => ['nullable', 'string', 'max:255'],
            'draft.approval_code' => ['nullable', Rule::in(['', ...$this->approvalCodes()])],
            'draft.target_entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'draft.priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'draft.conditions.project_nationalities' => ['nullable', 'array'],
            'draft.conditions.project_nationalities.*' => [Rule::in(['jordanian', 'international'])],
            'draft.conditions.work_categories' => ['nullable', 'array'],
            'draft.conditions.work_categories.*' => [Rule::in(['feature_film', 'documentary', 'series', 'commercial', 'tv_program', 'student_project'])],
            'draft.conditions.release_methods' => ['nullable', 'array'],
            'draft.conditions.release_methods.*' => [Rule::in(['cinema', 'television', 'streaming', 'festival', 'digital'])],
        ]);

        $applicationOptionsQuery = Application::query()
            ->with('entity')
            ->latest();

        if (filled($filters['q'] ?? null)) {
            $search = trim((string) $filters['q']);
            $applicationOptionsQuery->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('code', 'like', '%'.$search.'%')
                    ->orWhere('project_name', 'like', '%'.$search.'%')
                    ->orWhereHas('entity', fn (Builder $entityQuery): Builder => $entityQuery
                        ->where('name_en', 'like', '%'.$search.'%')
                        ->orWhere('name_ar', 'like', '%'.$search.'%'));
            });
        }

        $applicationOptions = $applicationOptionsQuery
            ->take(30)
            ->get();

        $selectedApplication = null;
        $simulationRoutes = collect();
        $draftSimulationRoutes = collect();
        $draftSimulation = $this->simulatorDraftRule($request);
        $simulationChanges = [
            'added' => collect(),
            'removed' => collect(),
            'changed' => collect(),
            'unchanged' => collect(),
        ];

        if (filled($filters['application_id'] ?? null)) {
            $selectedApplication = Application::query()
                ->with(['entity', 'submittedBy'])
                ->findOrFail($filters['application_id']);
            $simulationRoutes = $this->approvalRoutingService->explainRoutesForApplication($selectedApplication);

            if ($draftSimulation !== null) {
                $draftSimulationRoutes = $this->approvalRoutingService->explainRoutesForApplication($selectedApplication, $draftSimulation);
                $simulationChanges = $this->compareSimulationRoutes($simulationRoutes, $draftSimulationRoutes);
            }
        }

        return view('admin.approval-routing.simulator', [
            'applicationOptions' => $applicationOptions,
            'selectedApplication' => $selectedApplication,
            'simulationRoutes' => $simulationRoutes,
            'draftSimulationRoutes' => $draftSimulationRoutes,
            'draftSimulation' => $draftSimulation,
            'simulationChanges' => $simulationChanges,
            'conditionOptions' => [
                'project_nationalities' => ['jordanian', 'international'],
                'work_categories' => ['feature_film', 'documentary', 'series', 'commercial', 'tv_program', 'student_project'],
                'release_methods' => ['cinema', 'television', 'streaming', 'festival', 'digital'],
            ],
            'authorityEntities' => $this->authorityEntities(),
            'approvalCodes' => $this->approvalCodes(),
            'filters' => [
                'application_id' => isset($filters['application_id']) ? (string) $filters['application_id'] : '',
                'q' => $filters['q'] ?? '',
            ],
        ]);
    }

    public function show(ApprovalRoutingRule $approvalRouting): View
    {
        $approvalRouting->load(['targetEntity.group', 'audits.changedBy']);
        $approvalRouting->loadMissing('latestAudit.changedBy');

        return view('admin.approval-routing.show', [
            ...$this->formViewData($approvalRouting),
            'rule' => $approvalRouting,
            'relatedConflicts' => $this->relatedConflictFindings($approvalRouting),
            'ruleUsage' => $this->usageStatsForRules(collect([$approvalRouting]))->get($approvalRouting->getKey(), $this->emptyUsageStat()),
            'audits' => $approvalRouting->audits,
            'auditEntityNames' => Entity::query()
                ->whereIn('id', collect($approvalRouting->audits)
                    ->flatMap(fn (ApprovalRoutingRuleAudit $audit): array => [
                        data_get($audit->old_values, 'target_entity_id'),
                        data_get($audit->new_values, 'target_entity_id'),
                    ])
                    ->filter()
                    ->unique()
                    ->values()
                    ->all())
                ->get()
                ->mapWithKeys(fn (Entity $entity): array => [$entity->getKey() => $entity->displayName()]),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        return response()->json([
            'html' => view('admin.approval-routing.partials.preview', $this->previewViewData($request))->render(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPayload($request);

        $rule = ApprovalRoutingRule::query()->create($validated);
        $this->logAudit(
            action: 'created',
            rule: $rule,
            userId: $request->user()?->getKey(),
            oldValues: null,
            newValues: $this->auditValues($rule),
        );

        return redirect()
            ->route('admin.approval-routing.index')
            ->with('status', __('app.admin.approval_routing.created'));
    }

    public function edit(ApprovalRoutingRule $approvalRouting): View
    {
        $approvalRouting->loadMissing(['targetEntity.group', 'latestAudit.changedBy']);

        return view('admin.approval-routing.edit', [
            ...$this->formViewData($approvalRouting),
            'relatedConflicts' => $this->relatedConflictFindings($approvalRouting),
        ]);
    }

    public function update(Request $request, ApprovalRoutingRule $approvalRouting): RedirectResponse
    {
        $oldValues = $this->auditValues($approvalRouting);
        $approvalRouting->update($this->validatedPayload($request, $approvalRouting));
        $approvalRouting->refresh();

        $this->logAudit(
            action: 'updated',
            rule: $approvalRouting,
            userId: $request->user()?->getKey(),
            oldValues: $oldValues,
            newValues: $this->auditValues($approvalRouting),
        );

        return redirect()
            ->route('admin.approval-routing.index')
            ->with('status', __('app.admin.approval_routing.updated'));
    }

    public function updateStatus(Request $request, ApprovalRoutingRule $approvalRouting): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $oldValues = $this->auditValues($approvalRouting);
        $approvalRouting->forceFill([
            'is_active' => (bool) $validated['is_active'],
        ])->save();
        $approvalRouting->refresh();

        $this->logAudit(
            action: $approvalRouting->is_active ? 'activated' : 'deactivated',
            rule: $approvalRouting,
            userId: $request->user()?->getKey(),
            oldValues: $oldValues,
            newValues: $this->auditValues($approvalRouting),
        );

        return redirect()
            ->back()
            ->with('status', $approvalRouting->is_active
                ? __('app.admin.approval_routing.activated')
                : __('app.admin.approval_routing.deactivated'));
    }

    public function destroy(Request $request, ApprovalRoutingRule $approvalRouting): RedirectResponse
    {
        $oldValues = $this->auditValues($approvalRouting);

        $this->logAudit(
            action: 'deleted',
            rule: $approvalRouting,
            userId: $request->user()?->getKey(),
            oldValues: $oldValues,
            newValues: null,
        );

        $approvalRouting->delete();

        return redirect()
            ->route('admin.approval-routing.index')
            ->with('status', __('app.admin.approval_routing.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formViewData(ApprovalRoutingRule $rule): array
    {
        return [
            'rule' => $rule,
            'authorityEntities' => $this->authorityEntities(),
            'approvalCodes' => $this->approvalCodes(),
            'conditionOptions' => [
                'project_nationalities' => ['jordanian', 'international'],
                'work_categories' => ['feature_film', 'documentary', 'series', 'commercial', 'tv_program', 'student_project'],
                'release_methods' => ['cinema', 'television', 'streaming', 'festival', 'digital'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?ApprovalRoutingRule $currentRule = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'request_type' => ['required', Rule::in(['application'])],
            'approval_code' => ['required', Rule::in($this->approvalCodes())],
            'target_entity_id' => [
                'required',
                'integer',
                Rule::exists('entities', 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $exists = Entity::query()
                        ->whereKey($value)
                        ->whereHas('group', fn (Builder $query): Builder => $query->where('code', 'authorities'))
                        ->exists();

                    if (! $exists) {
                        $fail(__('validation.exists', ['attribute' => $attribute]));
                    }
                },
            ],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'conditions.project_nationalities' => ['nullable', 'array'],
            'conditions.project_nationalities.*' => [Rule::in(['jordanian', 'international'])],
            'conditions.work_categories' => ['nullable', 'array'],
            'conditions.work_categories.*' => [Rule::in(['feature_film', 'documentary', 'series', 'commercial', 'tv_program', 'student_project'])],
            'conditions.release_methods' => ['nullable', 'array'],
            'conditions.release_methods.*' => [Rule::in(['cinema', 'television', 'streaming', 'festival', 'digital'])],
        ]);

        $conditions = [
            'project_nationalities' => $this->normalizeConditionValues($validated['conditions']['project_nationalities'] ?? []),
            'work_categories' => $this->normalizeConditionValues($validated['conditions']['work_categories'] ?? []),
            'release_methods' => $this->normalizeConditionValues($validated['conditions']['release_methods'] ?? []),
        ];

        $isActive = (bool) ($validated['is_active'] ?? false);
        $this->ensureNoDuplicateActiveRule(
            requestType: $validated['request_type'],
            approvalCode: $validated['approval_code'],
            targetEntityId: (int) $validated['target_entity_id'],
            conditions: $conditions,
            isActive: $isActive,
            currentRuleId: $currentRule?->getKey(),
        );

        return [
            'name' => $validated['name'],
            'request_type' => $validated['request_type'],
            'approval_code' => $validated['approval_code'],
            'target_entity_id' => (int) $validated['target_entity_id'],
            'priority' => (int) $validated['priority'],
            'is_active' => $isActive,
            'conditions' => $conditions,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Entity>
     */
    private function authorityEntities()
    {
        return Entity::query()
            ->whereHas('group', fn (Builder $query): Builder => $query->where('code', 'authorities'))
            ->orderBy('name_en')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function approvalCodes(): array
    {
        return ['public_security', 'digital_economy', 'environment', 'municipalities', 'airports', 'drones', 'heritage'];
    }

    /**
     * @return array<int, string>
     */
    private function auditActions(): array
    {
        return ['created', 'updated', 'activated', 'deactivated', 'deleted'];
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function auditUsers()
    {
        return User::query()
            ->whereIn('id', ApprovalRoutingRuleAudit::query()
                ->whereNotNull('changed_by_user_id')
                ->select('changed_by_user_id')
                ->distinct())
            ->orderBy('name')
            ->orderBy('email')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function previewViewData(Request $request): array
    {
        $previewInput = $this->previewInput($request);
        $approvalCode = $previewInput['approval_code'];
        $conditions = $previewInput['conditions'];
        $targetEntity = $previewInput['target_entity'];
        $currentRuleId = $previewInput['current_rule_id'];

        $matchedApplications = collect();

        if ($approvalCode) {
            $matchedApplications = Application::query()
                ->with(['entity', 'submittedBy'])
                ->latest()
                ->get()
                ->filter(function (Application $application) use ($approvalCode, $conditions): bool {
                    return in_array(
                        $approvalCode,
                        (array) data_get($application->metadata, 'requirements.required_approvals', []),
                        true
                    ) && $this->approvalRoutingService->matchesConditions($conditions, $application);
                })
                ->values();
        }

        $duplicateRule = null;
        $overlapRules = collect();

        if ($approvalCode) {
            $candidateRules = ApprovalRoutingRule::query()
                ->with('targetEntity')
                ->when($currentRuleId, fn (Builder $query): Builder => $query->whereKeyNot($currentRuleId))
                ->where('request_type', 'application')
                ->where('approval_code', $approvalCode)
                ->where('is_active', true)
                ->get();

            if ($targetEntity) {
                $duplicateRule = $candidateRules
                    ->where('target_entity_id', $targetEntity->getKey())
                    ->first(fn (ApprovalRoutingRule $rule): bool => $this->normalizedConditions($rule->conditions ?? []) === $conditions);
            }

            $overlapRules = $candidateRules
                ->map(function (ApprovalRoutingRule $rule) use ($conditions, $targetEntity): ?array {
                    $ruleConditions = $this->normalizedConditions($rule->conditions ?? []);
                    $relation = $this->conditionRelation($conditions, $ruleConditions);

                    if ($relation === null || $relation === 'exact') {
                        return null;
                    }

                    return [
                        'rule' => $rule,
                        'relation' => $relation,
                        'same_target' => $targetEntity?->getKey() === $rule->target_entity_id,
                    ];
                })
                ->filter()
                ->values()
                ->take(6);
        }

        return [
            'previewReady' => filled($approvalCode),
            'approvalCode' => $approvalCode,
            'targetEntity' => $targetEntity,
            'conditions' => $conditions,
            'matchedApplicationsCount' => $matchedApplications->count(),
            'matchedApplications' => $matchedApplications->take(5),
            'matchedStats' => [
                'drafts' => $matchedApplications->where('status', 'draft')->count(),
                'active' => $matchedApplications->whereIn('status', ['submitted', 'under_review', 'needs_clarification'])->count(),
                'resolved' => $matchedApplications->whereIn('status', ['approved', 'rejected'])->count(),
            ],
            'duplicateRule' => $duplicateRule,
            'overlapRules' => $overlapRules,
        ];
    }

    /**
     * @return array{approval_code:?string,target_entity:?Entity,conditions:array<string,array<int,string>>,current_rule_id:?int}
     */
    private function previewInput(Request $request): array
    {
        $approvalCode = (string) $request->input('approval_code', '');
        $approvalCode = in_array($approvalCode, $this->approvalCodes(), true) ? $approvalCode : null;

        $targetEntityId = $request->integer('target_entity_id');
        $targetEntity = $targetEntityId > 0
            ? Entity::query()
                ->whereKey($targetEntityId)
                ->whereHas('group', fn (Builder $query): Builder => $query->where('code', 'authorities'))
                ->first()
            : null;

        $currentRuleId = $request->integer('current_rule_id');
        if ($currentRuleId <= 0) {
            $currentRuleId = null;
        }

        return [
            'approval_code' => $approvalCode,
            'target_entity' => $targetEntity,
            'conditions' => [
                'project_nationalities' => $this->normalizeConditionValues((array) $request->input('conditions.project_nationalities', [])),
                'work_categories' => $this->normalizeConditionValues((array) $request->input('conditions.work_categories', [])),
                'release_methods' => $this->normalizeConditionValues((array) $request->input('conditions.release_methods', [])),
            ],
            'current_rule_id' => $currentRuleId,
        ];
    }

    /**
     * @return array{name:string,approval_code:string,target_entity_id:int|null,target_entity_name:?string,priority:int,conditions:array<string,array<int,string>>}|null
     */
    private function simulatorDraftRule(Request $request): ?array
    {
        $approvalCode = (string) $request->input('draft.approval_code', '');

        if (! in_array($approvalCode, $this->approvalCodes(), true)) {
            return null;
        }

        $targetEntityId = $request->integer('draft.target_entity_id');
        $targetEntity = $targetEntityId > 0
            ? Entity::query()
                ->whereKey($targetEntityId)
                ->whereHas('group', fn (Builder $query): Builder => $query->where('code', 'authorities'))
                ->first()
            : null;

        return [
            'name' => trim((string) $request->input('draft.name', __('app.admin.approval_routing.simulator_draft_rule_name'))) ?: __('app.admin.approval_routing.simulator_draft_rule_name'),
            'approval_code' => $approvalCode,
            'target_entity_id' => $targetEntity?->getKey(),
            'target_entity_name' => $targetEntity?->displayName(),
            'priority' => max(1, (int) $request->input('draft.priority', 100)),
            'conditions' => [
                'project_nationalities' => $this->normalizeConditionValues((array) $request->input('draft.conditions.project_nationalities', [])),
                'work_categories' => $this->normalizeConditionValues((array) $request->input('draft.conditions.work_categories', [])),
                'release_methods' => $this->normalizeConditionValues((array) $request->input('draft.conditions.release_methods', [])),
            ],
        ];
    }

    private function duplicateDraftRule(int $duplicateRuleId): ApprovalRoutingRule
    {
        $sourceRule = ApprovalRoutingRule::query()
            ->with('targetEntity')
            ->findOrFail($duplicateRuleId);

        return new ApprovalRoutingRule([
            'name' => $sourceRule->name.' '.__('app.admin.approval_routing.duplicate_suffix'),
            'request_type' => $sourceRule->request_type,
            'approval_code' => $sourceRule->approval_code,
            'target_entity_id' => $sourceRule->target_entity_id,
            'priority' => (int) $sourceRule->priority,
            'is_active' => false,
            'conditions' => $sourceRule->conditions ?? [],
        ]);
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function normalizeConditionValues(array $values): array
    {
        $normalized = collect($values)
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => (string) $value)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $normalized;
    }

    /**
     * @param  array<string, array<int, string>>  $conditions
     */
    private function ensureNoDuplicateActiveRule(
        string $requestType,
        string $approvalCode,
        int $targetEntityId,
        array $conditions,
        bool $isActive,
        ?int $currentRuleId = null,
    ): void {
        if (! $isActive) {
            return;
        }

        $duplicateExists = ApprovalRoutingRule::query()
            ->when($currentRuleId, fn (Builder $query): Builder => $query->whereKeyNot($currentRuleId))
            ->where('request_type', $requestType)
            ->where('approval_code', $approvalCode)
            ->where('target_entity_id', $targetEntityId)
            ->where('is_active', true)
            ->get()
            ->contains(function (ApprovalRoutingRule $rule) use ($conditions): bool {
                return $this->normalizedConditions($rule->conditions ?? []) === $conditions;
            });

        if ($duplicateExists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'approval_code' => __('app.admin.approval_routing.duplicate_active_rule'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @return array<string, array<int, string>>
     */
    private function normalizedConditions(array $conditions): array
    {
        return [
            'project_nationalities' => $this->normalizeConditionValues((array) data_get($conditions, 'project_nationalities', [])),
            'work_categories' => $this->normalizeConditionValues((array) data_get($conditions, 'work_categories', [])),
            'release_methods' => $this->normalizeConditionValues((array) data_get($conditions, 'release_methods', [])),
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $proposedConditions
     * @param  array<string, array<int, string>>  $existingConditions
     */
    private function conditionRelation(array $proposedConditions, array $existingConditions): ?string
    {
        if (! $this->conditionsOverlap($proposedConditions, $existingConditions)) {
            return null;
        }

        if ($proposedConditions === $existingConditions) {
            return 'exact';
        }

        $existingBroader = $this->conditionsContain($existingConditions, $proposedConditions);
        $proposedBroader = $this->conditionsContain($proposedConditions, $existingConditions);

        if ($existingBroader && ! $proposedBroader) {
            return 'existing_broader';
        }

        if ($proposedBroader && ! $existingBroader) {
            return 'existing_narrower';
        }

        return 'partial_overlap';
    }

    /**
     * @param  array<string, array<int, string>>  $leftConditions
     * @param  array<string, array<int, string>>  $rightConditions
     */
    private function conditionsOverlap(array $leftConditions, array $rightConditions): bool
    {
        foreach (array_keys($leftConditions) as $key) {
            if (! $this->dimensionOverlaps($leftConditions[$key] ?? [], $rightConditions[$key] ?? [])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, array<int, string>>  $containerConditions
     * @param  array<string, array<int, string>>  $containedConditions
     */
    private function conditionsContain(array $containerConditions, array $containedConditions): bool
    {
        foreach (array_keys($containerConditions) as $key) {
            if (! $this->dimensionContains($containerConditions[$key] ?? [], $containedConditions[$key] ?? [])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $leftValues
     * @param  array<int, string>  $rightValues
     */
    private function dimensionOverlaps(array $leftValues, array $rightValues): bool
    {
        if ($leftValues === [] || $rightValues === []) {
            return true;
        }

        return array_intersect($leftValues, $rightValues) !== [];
    }

    /**
     * @param  array<int, string>  $containerValues
     * @param  array<int, string>  $containedValues
     */
    private function dimensionContains(array $containerValues, array $containedValues): bool
    {
        if ($containerValues === []) {
            return true;
        }

        if ($containedValues === []) {
            return false;
        }

        return collect($containedValues)->every(fn (string $value): bool => in_array($value, $containerValues, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(ApprovalRoutingRule $rule): array
    {
        return [
            'name' => $rule->name,
            'request_type' => $rule->request_type,
            'approval_code' => $rule->approval_code,
            'target_entity_id' => $rule->target_entity_id,
            'priority' => (int) $rule->priority,
            'is_active' => (bool) $rule->is_active,
            'conditions' => $rule->conditions ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function logAudit(string $action, ApprovalRoutingRule $rule, ?int $userId, ?array $oldValues, ?array $newValues): void
    {
        ApprovalRoutingRuleAudit::query()->create([
            'approval_routing_rule_id' => $rule->getKey(),
            'changed_by_user_id' => $userId,
            'rule_name' => $rule->name,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{approval_code:string,target_entity_id:int|null,approval_routing_rule_id:int|null,rule_name:?string,priority:?int,source:string,target_entity_name:?string}>  $currentRoutes
     * @param  \Illuminate\Support\Collection<int, array{approval_code:string,target_entity_id:int|null,approval_routing_rule_id:int|null,rule_name:?string,priority:?int,source:string,target_entity_name:?string}>  $draftRoutes
     * @return array{added:\Illuminate\Support\Collection,removed:\Illuminate\Support\Collection,changed:\Illuminate\Support\Collection,unchanged:\Illuminate\Support\Collection}
     */
    private function compareSimulationRoutes($currentRoutes, $draftRoutes): array
    {
        $routeKey = static fn (array $route): string => $route['approval_code'].'|'.($route['target_entity_id'] ?? 'none');

        $current = $currentRoutes->keyBy($routeKey);
        $draft = $draftRoutes->keyBy($routeKey);

        $added = $draft
            ->reject(fn (array $route, string $key): bool => $current->has($key))
            ->values();

        $removed = $current
            ->reject(fn (array $route, string $key): bool => $draft->has($key))
            ->values();

        $changed = $draft
            ->filter(function (array $route, string $key) use ($current): bool {
                if (! $current->has($key)) {
                    return false;
                }

                return $this->routeSignature($route) !== $this->routeSignature($current->get($key));
            })
            ->map(fn (array $route, string $key): array => [
                'before' => $current->get($key),
                'after' => $route,
            ])
            ->values();

        $unchanged = $draft
            ->filter(function (array $route, string $key) use ($current): bool {
                if (! $current->has($key)) {
                    return false;
                }

                return $this->routeSignature($route) === $this->routeSignature($current->get($key));
            })
            ->values();

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'unchanged' => $unchanged,
        ];
    }

    /**
     * @param  array{approval_code:string,target_entity_id:int|null,approval_routing_rule_id:int|null,rule_name:?string,priority:?int,source:string,target_entity_name:?string}  $route
     * @return array<string, scalar|null>
     */
    private function routeSignature(array $route): array
    {
        return [
            'rule_name' => $route['rule_name'],
            'priority' => $route['priority'],
            'source' => $route['source'],
            'target_entity_name' => $route['target_entity_name'],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ApprovalRoutingRule>  $rules
     * @return \Illuminate\Support\Collection<int, array{type:string,approval_code:string,target_entity_name:string,primary_rule:ApprovalRoutingRule,secondary_rule:ApprovalRoutingRule}>
     */
    private function buildConflictReport($rules)
    {
        $findings = collect();

        foreach ($rules->groupBy(fn (ApprovalRoutingRule $rule): string => $rule->request_type.'|'.$rule->approval_code.'|'.$rule->target_entity_id) as $groupedRules) {
            $values = $groupedRules->values();
            $count = $values->count();

            for ($leftIndex = 0; $leftIndex < $count; $leftIndex++) {
                for ($rightIndex = $leftIndex + 1; $rightIndex < $count; $rightIndex++) {
                    /** @var ApprovalRoutingRule $left */
                    $left = $values[$leftIndex];
                    /** @var ApprovalRoutingRule $right */
                    $right = $values[$rightIndex];

                    $leftConditions = $this->normalizedConditions($left->conditions ?? []);
                    $rightConditions = $this->normalizedConditions($right->conditions ?? []);
                    $relation = $this->conditionRelation($leftConditions, $rightConditions);

                    if ($relation === null) {
                        continue;
                    }

                    if ($left->priority === $right->priority) {
                        $findings->push($this->conflictFinding(
                            type: 'same_priority_overlap',
                            rule: $left,
                            competingRule: $right,
                        ));

                        continue;
                    }

                    if ($relation === 'exact') {
                        [$higherPriorityRule, $lowerPriorityRule] = $left->priority < $right->priority
                            ? [$left, $right]
                            : [$right, $left];

                        $findings->push($this->conflictFinding(
                            type: 'shadowed_rule',
                            rule: $higherPriorityRule,
                            competingRule: $lowerPriorityRule,
                        ));

                        continue;
                    }

                    if ($relation === 'existing_broader' && $right->priority < $left->priority) {
                        $findings->push($this->conflictFinding(
                            type: 'shadowed_rule',
                            rule: $right,
                            competingRule: $left,
                        ));

                        continue;
                    }

                    if ($relation === 'existing_narrower' && $left->priority < $right->priority) {
                        $findings->push($this->conflictFinding(
                            type: 'shadowed_rule',
                            rule: $left,
                            competingRule: $right,
                        ));
                    }
                }
            }
        }

        return $findings
            ->unique(fn (array $finding): string => $finding['type'].'|'.$finding['primary_rule']->getKey().'|'.$finding['secondary_rule']->getKey())
            ->sortBy(fn (array $finding): string => $finding['type'].'|'.$finding['approval_code'].'|'.$finding['target_entity_name'].'|'.$finding['primary_rule']->priority.'|'.$finding['secondary_rule']->priority)
            ->values();
    }

    /**
     * @return array{type:string,approval_code:string,target_entity_name:string,primary_rule:ApprovalRoutingRule,secondary_rule:ApprovalRoutingRule}
     */
    private function conflictFinding(string $type, ApprovalRoutingRule $rule, ApprovalRoutingRule $competingRule): array
    {
        return [
            'type' => $type,
            'approval_code' => $rule->approval_code,
            'target_entity_name' => $rule->targetEntity?->displayName() ?? __('app.dashboard.not_available'),
            'primary_rule' => $rule,
            'secondary_rule' => $competingRule,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{type:string,approval_code:string,target_entity_name:string,primary_rule:ApprovalRoutingRule,secondary_rule:ApprovalRoutingRule,role:string,other_rule:ApprovalRoutingRule}>
     */
    private function relatedConflictFindings(ApprovalRoutingRule $rule)
    {
        $relatedRuleId = $rule->getKey();

        return $this->buildConflictReport($this->rulesForConflictAnalysis($rule))
            ->filter(fn (array $finding): bool => $finding['primary_rule']->getKey() === $relatedRuleId || $finding['secondary_rule']->getKey() === $relatedRuleId)
            ->map(function (array $finding) use ($relatedRuleId): array {
                $isPrimary = $finding['primary_rule']->getKey() === $relatedRuleId;

                return [
                    ...$finding,
                    'role' => $isPrimary ? 'primary' : 'secondary',
                    'other_rule' => $isPrimary ? $finding['secondary_rule'] : $finding['primary_rule'],
                ];
            })
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ApprovalRoutingRule>  $rules
     * @return \Illuminate\Support\Collection<int, array{total:int,pending:int,in_review:int,approved:int,rejected:int,last_used_at:?string}>
     */
    private function usageStatsForRules($rules)
    {
        $ruleIds = $rules->pluck('id')->filter()->values();

        if ($ruleIds->isEmpty()) {
            return collect();
        }

        $stats = ApplicationAuthorityApproval::query()
            ->selectRaw('approval_routing_rule_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected")
            ->selectRaw('MAX(created_at) as last_used_at')
            ->whereIn('approval_routing_rule_id', $ruleIds->all())
            ->groupBy('approval_routing_rule_id')
            ->get()
            ->keyBy('approval_routing_rule_id')
            ->map(function (ApplicationAuthorityApproval $approval): array {
                return [
                    'total' => (int) $approval->total,
                    'pending' => (int) $approval->pending,
                    'in_review' => (int) $approval->in_review,
                    'approved' => (int) $approval->approved,
                    'rejected' => (int) $approval->rejected,
                    'last_used_at' => filled($approval->last_used_at) ? (string) $approval->last_used_at : null,
                ];
            });

        return $ruleIds->mapWithKeys(fn (int $ruleId): array => [
            $ruleId => $stats->get($ruleId, $this->emptyUsageStat()),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ApprovalRoutingRule>  $rules
     * @param  \Illuminate\Support\Collection<int, array{total:int,pending:int,in_review:int,approved:int,rejected:int,last_used_at:?string}>  $usageStats
     * @return array{total_routed:int,rules_used:int,unused_active_rules:int,top_rule:?ApprovalRoutingRule,top_rule_total:int}
     */
    private function usageSummaryForRules($rules, $usageStats): array
    {
        $rulesUsed = $usageStats->filter(fn (array $stat): bool => $stat['total'] > 0);
        $activeRules = $rules->filter(fn (ApprovalRoutingRule $rule): bool => $rule->is_active);
        $unusedActiveRules = $activeRules
            ->filter(fn (ApprovalRoutingRule $rule): bool => ($usageStats->get($rule->getKey(), $this->emptyUsageStat())['total'] ?? 0) === 0)
            ->count();
        $topRuleId = $rulesUsed
            ->sortByDesc(fn (array $stat): int => $stat['total'])
            ->keys()
            ->first();

        return [
            'total_routed' => $usageStats->sum('total'),
            'rules_used' => $rulesUsed->count(),
            'unused_active_rules' => $unusedActiveRules,
            'top_rule' => $topRuleId ? $rules->firstWhere('id', (int) $topRuleId) : null,
            'top_rule_total' => $topRuleId ? (int) ($usageStats->get((int) $topRuleId)['total'] ?? 0) : 0,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ApprovalRoutingRule>  $rules
     * @param  \Illuminate\Support\Collection<int, array{total:int,pending:int,in_review:int,approved:int,rejected:int,last_used_at:?string}>  $usageStats
     * @return \Illuminate\Support\Collection<int, array{type:string,rule:ApprovalRoutingRule,usage:array{total:int,pending:int,in_review:int,approved:int,rejected:int,last_used_at:?string}}>
     */
    private function cleanupCandidatesForRules($rules, $usageStats, string $filter = 'all')
    {
        return $rules
            ->filter(fn (ApprovalRoutingRule $rule): bool => $rule->is_active)
            ->map(function (ApprovalRoutingRule $rule) use ($usageStats): ?array {
                $usage = $usageStats->get($rule->getKey(), $this->emptyUsageStat());
                $approvals = $rule->authorityApprovals()->get(['status', 'created_at', 'updated_at']);
                $approvalCount = $approvals->count();
                $lastUsedAt = $approvals
                    ->max(fn (ApplicationAuthorityApproval $approval): ?\Illuminate\Support\Carbon => $approval->created_at ?? $approval->updated_at);
                $lastMaintenanceAt = $rule->updated_at ?? $rule->created_at;
                $usage = [
                    'total' => $approvalCount,
                    'pending' => $approvals->where('status', 'pending')->count(),
                    'in_review' => $approvals->where('status', 'in_review')->count(),
                    'approved' => $approvals->where('status', 'approved')->count(),
                    'rejected' => $approvals->where('status', 'rejected')->count(),
                    'last_used_at' => ($lastUsedAt ?? $lastMaintenanceAt)?->toDateTimeString() ?? $usage['last_used_at'],
                ];

                $isStale = ($lastUsedAt !== null && now()->diffInDays($lastUsedAt) >= $this->staleRuleThresholdDays())
                    || ($lastMaintenanceAt !== null && now()->diffInDays($lastMaintenanceAt) >= $this->staleRuleThresholdDays());

                if ($isStale) {
                    return [
                        'type' => 'stale',
                        'rule' => $rule,
                        'usage' => $usage,
                    ];
                }

                if ($approvalCount === 0) {
                    return [
                        'type' => 'unused',
                        'rule' => $rule,
                        'usage' => $usage,
                    ];
                }

                return null;
            })
            ->filter(function (?array $candidate) use ($filter): bool {
                if ($candidate === null) {
                    return false;
                }

                return $filter === 'all' || $candidate['type'] === $filter;
            })
            ->values();
    }

    /**
     * @return array{total:int,pending:int,in_review:int,approved:int,rejected:int,last_used_at:?string}
     */
    private function emptyUsageStat(): array
    {
        return [
            'total' => 0,
            'pending' => 0,
            'in_review' => 0,
            'approved' => 0,
            'rejected' => 0,
            'last_used_at' => null,
        ];
    }

    private function staleRuleThresholdDays(): int
    {
        return 90;
    }

    /**
     * @return \Illuminate\Support\Collection<int, ApprovalRoutingRule>
     */
    private function rulesForConflictAnalysis(?ApprovalRoutingRule $focusRule = null)
    {
        $rules = ApprovalRoutingRule::query()
            ->with(['targetEntity.group'])
            ->where('request_type', 'application')
            ->where('is_active', true)
            ->orderBy('approval_code')
            ->orderBy('target_entity_id')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($focusRule !== null && ! $focusRule->is_active) {
            $focusRule->loadMissing(['targetEntity.group']);
            $rules = $rules->push($focusRule);
        }

        return $rules
            ->unique(fn (ApprovalRoutingRule $rule): int => $rule->getKey())
            ->values();
    }
}
