<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApprovalRoutingRule;
use App\Models\Entity;
use App\Models\FormLookupOption;
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
        if (! $this->conditionsHaveValues($conditions)) {
            return false;
        }

        return $this->matchesConditionList(
            $conditions,
            'project_nationalities',
            $this->projectNationalityConditionValues((string) $application->project_nationality),
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
    private function matchesConditionList(array $conditions, string $key, string|array|null $value): bool
    {
        return $this->matchesConditionValues(
            $conditions,
            $key,
            collect((array) $value)
                ->filter(fn ($item): bool => filled($item))
                ->map(fn ($item): string => (string) $item)
                ->all(),
        );
    }

    /**
     * @return array<int, string>
     */
    private function projectNationalityConditionValues(?string $value): array
    {
        if (! filled($value)) {
            return [];
        }

        $values = [(string) $value];

        if (! in_array((string) $value, ['jordanian', 'international'], true)) {
            $values[] = 'international';
        }

        return array_values(array_unique($values));
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

            $flags = array_merge($flags, collect((array) data_get($annex, 'filming_locations', []))
                ->pluck('location_type')
                ->filter(fn ($value): bool => filled($value))
                ->map(fn ($value): string => 'location_type_'.((string) $value))
                ->all());
        }

        if ($this->hasFilledRows(data_get($annex, 'cast_crew', []))) {
            $flags[] = 'cast_crew';
        }

        $specialLocationRequirements = (array) data_get($annex, 'special_location_requirements', []);

        if ($this->hasFilledRows($specialLocationRequirements)) {
            $flags[] = 'special_location_requirements';

            $flags = array_merge($flags, collect($specialLocationRequirements)
                ->filter(fn ($row): bool => is_array($row) && $this->hasFilledValues($row))
                ->keys()
                ->filter(fn ($value): bool => filled($value))
                ->map(fn ($value): string => 'special_requirement_'.((string) $value))
                ->all());
        }

        if (
            (bool) data_get($annex, 'safety_guidelines.acknowledged')
            || filled(data_get($annex, 'safety_guidelines.notes'))
        ) {
            $flags[] = 'safety_guidelines';
        }

        if ($this->hasFilledRows(data_get($annex, 'imported_equipment', []))) {
            $flags[] = 'imported_equipment';

            $importedEquipmentRows = data_get($annex, 'imported_equipment', []);
            $flags = array_merge($flags, $this->formLookupFlagsForRows(
                $importedEquipmentRows,
                'classification',
                'imported_equipment_category_',
                FormLookupOption::TYPE_EQUIPMENT_CATEGORY,
            ));
            $flags = array_merge($flags, $this->formLookupFlagsForRows(
                $importedEquipmentRows,
                'shipping_method',
                'imported_equipment_shipping_method_',
                FormLookupOption::TYPE_EQUIPMENT_SHIPPING_METHOD,
            ));
            $flags = array_merge($flags, $this->formLookupFlagsForRows(
                $importedEquipmentRows,
                'entry_point',
                'imported_equipment_entry_point_',
                FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT,
            ));
        }

        if (
            $this->hasFilledRows(data_get($annex, 'military_border_locations', []))
            || $this->hasFilledRows(data_get($annex, 'military_border_equipment', []))
        ) {
            $flags[] = 'military_border_equipment';

            $flags = array_merge($flags, $this->formLookupFlagsForRows(
                data_get($annex, 'military_border_locations', []),
                'location_type',
                'military_location_type_',
                FormLookupOption::TYPE_MILITARY_BORDER_LOCATION_TYPE,
            ));
            $flags = array_merge($flags, $this->formLookupFlagsForRows(
                data_get($annex, 'military_border_equipment', []),
                'classification',
                'military_equipment_category_',
                FormLookupOption::TYPE_EQUIPMENT_CATEGORY,
            ));
            $flags = array_merge($flags, $this->formLookupFlagsForRows(
                data_get($annex, 'military_border_equipment', []),
                'entry_point',
                'military_equipment_entry_point_',
                FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT,
            ));
        }

        if ($this->hasFilledValues((array) data_get($annex, 'airport_filming', []))) {
            $flags[] = 'airport_filming';
            $flags = array_merge($flags, $this->formLookupFlagsForValue(
                data_get($annex, 'airport_filming.airport_name'),
                'airport_filming_airport_',
                FormLookupOption::TYPE_AIRPORT,
            ));
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
     * @return array<int, string>
     */
    private function formLookupFlagsForRows(mixed $rows, string $field, string $flagPrefix, string $lookupType): array
    {
        return collect((array) $rows)
            ->filter(fn ($row): bool => is_array($row))
            ->pluck($field)
            ->flatMap(fn ($value): array => $this->formLookupFlagsForValue($value, $flagPrefix, $lookupType))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function formLookupFlagsForValue(mixed $value, string $flagPrefix, string $lookupType): array
    {
        return collect(FormLookupOption::codesForValue($lookupType, $value))
            ->map(fn (string $code): string => $flagPrefix.$code)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    private function conditionsHaveValues(array $conditions): bool
    {
        return collect(\Illuminate\Support\Arr::dot($conditions))
            ->contains(fn ($value): bool => filled($value));
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
