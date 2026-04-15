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
     * @param  array{name:?string,approval_code:string,target_entity_id:int|null,target_entity_name:?string,priority:int,conditions:array<string, array<int, string>>}|null  $draftRule
     * @return Collection<int, array{approval_code:string,target_entity_id:int|null,approval_routing_rule_id:int|null,rule_name:?string,priority:?int,source:string,target_entity_name:?string}>
     */
    public function explainRoutesForApplication(Application $application, ?array $draftRule = null): Collection
    {
        $requiredApprovals = collect((array) data_get($application->metadata, 'requirements.required_approvals', []))
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => (string) $value)
            ->unique()
            ->values();

        if ($requiredApprovals->isEmpty()) {
            return collect();
        }

        $rules = ApprovalRoutingRule::query()
            ->with('targetEntity.group')
            ->where('request_type', 'application')
            ->where('is_active', true)
            ->whereIn('approval_code', $requiredApprovals)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
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
        );
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    private function matchesConditionList(array $conditions, string $key, ?string $value): bool
    {
        $allowed = collect((array) data_get($conditions, $key, []))
            ->filter(fn ($item): bool => filled($item))
            ->map(fn ($item): string => (string) $item)
            ->values();

        if ($allowed->isEmpty()) {
            return true;
        }

        return filled($value) && $allowed->contains($value);
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
