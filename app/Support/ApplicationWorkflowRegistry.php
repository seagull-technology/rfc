<?php

namespace App\Support;

use App\Models\ApprovalRoutingRule;
use App\Models\Entity;

class ApplicationWorkflowRegistry
{
    /**
     * @return array<string, array<int, array{entity_code:string,name:string,conditions:array<string, mixed>,priority:int}>>
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
        return collect(self::approvalAuthorityEntriesForApproval($approvalCode))
            ->pluck('entity_code')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{entity_code:string,name:string,conditions:array<string, mixed>,priority:int}>
     */
    public static function approvalAuthorityEntriesForApproval(string $approvalCode): array
    {
        return collect((array) data_get(self::approvalAuthorityMap(), $approvalCode, []))
            ->filter(fn (mixed $entry): bool => self::isStructuredAuthorityEntry($entry))
            ->map(fn (array $entry): array => [
                'entity_code' => (string) $entry['entity_code'],
                'name' => (string) $entry['name'],
                'conditions' => (array) $entry['conditions'],
                'priority' => max(1, (int) $entry['priority']),
            ])
            ->values()
            ->all();
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
            foreach (array_keys(self::approvalAuthorityMap()) as $approvalCode) {
                if (in_array($entity->code, self::entityCodesForApproval((string) $approvalCode), true)) {
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

    private static function isStructuredAuthorityEntry(mixed $entry): bool
    {
        return is_array($entry)
            && filled($entry['entity_code'] ?? null)
            && filled($entry['name'] ?? null)
            && array_key_exists('conditions', $entry)
            && array_key_exists('priority', $entry);
    }
}
