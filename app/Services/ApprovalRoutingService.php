<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApprovalRoutingRule;
use App\Models\Entity;
use App\Support\ApplicationWorkflowRegistry;
use Illuminate\Support\Collection;

class ApprovalRoutingService
{
    /**
     * @return Collection<int, array{approval_code:string,target_entity_id:int|null,approval_routing_rule_id:int|null}>
     */
    public function routesForApplication(Application $application): Collection
    {
        return $this->explainRoutesForApplication($application)
            ->map(fn (array $route): array => [
                'approval_code' => $route['approval_code'],
                'target_entity_id' => $route['target_entity_id'],
                'approval_routing_rule_id' => $route['approval_routing_rule_id'],
            ]);
    }

    /**
     * @return Collection<int, array{approval_code:string,approval_label:string,target_entity_id:int|null,target_entity_name:?string,conditions:array<string, mixed>}>
     */
    public function applicationRoutingPreviewRules(): Collection
    {
        return ApprovalRoutingRule::query()
            ->with('targetEntity.group')
            ->where('request_type', 'application')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(fn (ApprovalRoutingRule $rule): array => [
                'approval_code' => (string) $rule->approval_code,
                'approval_label' => __('app.applications.required_approval_options.'.((string) $rule->approval_code)),
                'target_entity_id' => $rule->target_entity_id,
                'target_entity_name' => $rule->targetEntity?->displayName(),
                'conditions' => (array) ($rule->conditions ?? []),
            ])
            ->values();
    }

    /**
     * @param  array{name:?string,approval_code:string,target_entity_id:int|null,target_entity_name:?string,priority:int,conditions:array<string, array<int, string>>}|null  $draftRule
     * @return Collection<int, array{approval_code:string,target_entity_id:int|null,approval_routing_rule_id:int|null,rule_name:?string,priority:?int,source:string,target_entity_name:?string}>
     */
    public function explainRoutesForApplication(Application $application, ?array $draftRule = null): Collection
    {
        $activeRules = ApprovalRoutingRule::query()
            ->with('targetEntity.group')
            ->where('request_type', 'application')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $matchingActiveRules = $activeRules
            ->filter(fn (ApprovalRoutingRule $rule): bool => $this->matchesRule($rule, $application))
            ->values();

        $requiredApprovals = $matchingActiveRules
            ->pluck('approval_code')
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => (string) $value)
            ->unique()
            ->values();

        if ($draftRule && $this->matchesConditions($draftRule['conditions'], $application)) {
            $requiredApprovals = $requiredApprovals
                ->push((string) $draftRule['approval_code'])
                ->unique()
                ->values();
        }

        if ($requiredApprovals->isEmpty()) {
            $requiredApprovals = collect((array) data_get($application->metadata, 'requirements.required_approvals', []))
                ->filter(fn ($value): bool => filled($value))
                ->map(fn ($value): string => (string) $value)
                ->unique()
                ->values();
        }

        if ($requiredApprovals->isEmpty()) {
            return collect();
        }

        $rules = $matchingActiveRules
            ->groupBy('approval_code');

        return $requiredApprovals
            ->flatMap(function (string $approvalCode) use ($application, $rules, $draftRule): Collection {
                $matchingRules = collect($rules->get($approvalCode, []))
                    ->filter(fn (ApprovalRoutingRule $rule): bool => $this->matchesRule($rule, $application))
                    ->map(fn (ApprovalRoutingRule $rule): array => [
                        'approval_code' => $approvalCode,
                        'target_entity_id' => $rule->target_entity_id,
                        'approval_routing_rule_id' => $rule->getKey(),
                        'rule_name' => $rule->name,
                        'priority' => (int) $rule->priority,
                        'source' => 'rule',
                        'target_entity_name' => $rule->targetEntity?->displayName(),
                    ])
                    ->values();

                if (
                    $draftRule
                    && $draftRule['approval_code'] === $approvalCode
                    && $this->matchesConditions($draftRule['conditions'], $application)
                ) {
                    $matchingRules->push([
                        'approval_code' => $approvalCode,
                        'target_entity_id' => $draftRule['target_entity_id'],
                        'approval_routing_rule_id' => null,
                        'rule_name' => $draftRule['name'],
                        'priority' => $draftRule['priority'],
                        'source' => 'draft',
                        'target_entity_name' => $draftRule['target_entity_name'],
                    ]);
                }

                if ($matchingRules->isNotEmpty()) {
                    return $matchingRules
                        ->sortBy(fn (array $route): int => (((int) ($route['priority'] ?? 9999)) * 10) + ($route['source'] === 'draft' ? 0 : 1))
                        ->values();
                }

                return $this->fallbackRoutesForApproval($approvalCode);
            })
            ->unique(fn (array $route): string => $route['approval_code'].'|'.($route['target_entity_id'] ?? 'none'))
            ->values();
    }

    public function matchesRule(ApprovalRoutingRule $rule, Application $application): bool
    {
        return $this->matchesConditions((array) ($rule->conditions ?? []), $application);
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    public function matchesConditions(array $conditions, Application $application): bool
    {
        return $this->matchesConditionList(
            $conditions,
            'project_nationalities',
            (string) $application->project_nationality,
        ) && $this->matchesConditionList(
            $conditions,
            'work_categories',
            (string) $application->work_category,
        ) && $this->matchesConditionList(
            $conditions,
            'release_methods',
            (string) $application->release_method,
        ) && $this->matchesConditionValues(
            $conditions,
            'annex_flags',
            $this->annexFlagsForApplication($application),
        ) && $this->matchesConditionValues(
            $conditions,
            'governorates',
            $this->governoratesForApplication($application),
        );
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    private function matchesConditionList(array $conditions, string $key, ?string $value): bool
    {
        return $this->matchesConditionValues(
            $conditions,
            $key,
            filled($value) ? [(string) $value] : [],
        );
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<int, string>  $values
     */
    private function matchesConditionValues(array $conditions, string $key, array $values): bool
    {
        $allowed = collect((array) data_get($conditions, $key, []))
            ->filter(fn ($item): bool => filled($item))
            ->map(fn ($item): string => (string) $item)
            ->values();

        if ($allowed->isEmpty()) {
            return true;
        }

        $actualValues = collect($values)
            ->filter(fn ($item): bool => filled($item))
            ->map(fn ($item): string => (string) $item)
            ->unique()
            ->values();

        return $actualValues->intersect($allowed)->isNotEmpty();
    }

    /**
     * @return array<int, string>
     */
    private function annexFlagsForApplication(Application $application): array
    {
        $annex = (array) data_get($application->metadata ?? [], 'annex', []);
        $flags = [];

        if ((bool) data_get($annex, 'work_content_summary.confirmed')) {
            $flags[] = 'work_content_confirmed';
        }

        if ($this->hasFilledRows(data_get($annex, 'filming_locations', []))) {
            $flags[] = 'filming_locations';
        }

        if (
            (bool) data_get($annex, 'safety_guidelines.acknowledged')
            || filled(data_get($annex, 'safety_guidelines.notes'))
        ) {
            $flags[] = 'safety_guidelines';
        }

        if ($this->hasFilledRows(data_get($annex, 'imported_equipment', []))) {
            $flags[] = 'imported_equipment';
        }

        if ($this->hasFilledRows(data_get($annex, 'military_border_equipment', []))) {
            $flags[] = 'military_border_equipment';
        }

        if ($this->hasFilledValues((array) data_get($annex, 'airport_filming', []))) {
            $flags[] = 'airport_filming';
        }

        if ($this->hasFilledRows(data_get($annex, 'governmental_scenes', []))) {
            $flags[] = 'governmental_scenes';
        }

        return array_values(array_unique($flags));
    }

    /**
     * @return array<int, string>
     */
    private function governoratesForApplication(Application $application): array
    {
        return collect((array) data_get($application->metadata ?? [], 'annex.filming_locations', []))
            ->pluck('governorate')
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => (string) $value)
            ->unique()
            ->values()
            ->all();
    }

    private function hasFilledRows(mixed $rows): bool
    {
        return collect((array) $rows)
            ->contains(fn ($row): bool => is_array($row) && $this->hasFilledValues($row));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function hasFilledValues(array $values): bool
    {
        return collect($values)
            ->contains(fn ($value): bool => filled($value));
    }

    /**
     * @return Collection<int, array{approval_code:string,target_entity_id:int|null,approval_routing_rule_id:int|null,rule_name:?string,priority:?int,source:string,target_entity_name:?string}>
     */
    private function fallbackRoutesForApproval(string $approvalCode): Collection
    {
        $entityCodes = ApplicationWorkflowRegistry::entityCodesForApproval($approvalCode);

        if ($entityCodes === []) {
            return collect([[
                'approval_code' => $approvalCode,
                'target_entity_id' => null,
                'approval_routing_rule_id' => null,
                'rule_name' => null,
                'priority' => null,
                'source' => 'fallback',
                'target_entity_name' => null,
            ]]);
        }

        $entities = Entity::query()
            ->whereIn('code', $entityCodes)
            ->get(['id', 'name_en', 'name_ar'])
            ->values();

        if ($entities->isEmpty()) {
            return collect([[
                'approval_code' => $approvalCode,
                'target_entity_id' => null,
                'approval_routing_rule_id' => null,
                'rule_name' => null,
                'priority' => null,
                'source' => 'fallback',
                'target_entity_name' => null,
            ]]);
        }

        return $entities->map(fn (Entity $entity): array => [
            'approval_code' => $approvalCode,
            'target_entity_id' => $entity->getKey(),
            'approval_routing_rule_id' => null,
            'rule_name' => null,
            'priority' => null,
            'source' => 'fallback',
            'target_entity_name' => $entity->displayName(),
        ]);
    }
}
