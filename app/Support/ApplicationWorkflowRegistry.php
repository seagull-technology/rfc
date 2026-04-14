<?php

namespace App\Support;

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
        if (! $entity || ! filled($entity->code)) {
            return [];
        }

        $codes = [];

        foreach (self::approvalAuthorityMap() as $approvalCode => $entityCodes) {
            if (in_array($entity->code, $entityCodes, true)) {
                $codes[] = $approvalCode;
            }
        }

        return array_values(array_unique($codes));
    }
}
