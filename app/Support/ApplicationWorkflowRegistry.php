<?php

namespace App\Support;

use App\Models\ApprovalRoutingRule;
use App\Models\Entity;

class ApplicationWorkflowRegistry
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function approvalAuthorityMap(): array
    {
        return (array) config('application_workflow.approval_authority_map', []);
    }

    /**
     * @return array<int, string>
     */
    public static function entityCodesForApproval(string $approvalCode): array
    {
        return array_values((array) data_get(self::approvalAuthorityMap(), $approvalCode, []));
    }

    /**
     * @return array<int, string>
     */
    public static function approvalCodesForEntity(?Entity $entity): array
    {
        if (! $entity) {
            return [];
        }

        $codes = [];

        if (filled($entity->code)) {
            foreach (self::approvalAuthorityMap() as $approvalCode => $entityCodes) {
                if (in_array($entity->code, $entityCodes, true)) {
                    $codes[] = $approvalCode;
                }
            }
        }

        $dynamicCodes = ApprovalRoutingRule::query()
            ->where('request_type', 'application')
            ->where('is_active', true)
            ->where('target_entity_id', $entity->getKey())
            ->pluck('approval_code')
            ->filter()
            ->values()
            ->all();

        return array_values(array_unique([...$codes, ...$dynamicCodes]));
    }
}
