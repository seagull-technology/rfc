<?php

namespace Database\Seeders;

use App\Models\ApprovalRoutingRule;
use App\Models\Entity;
use Illuminate\Database\Seeder;

class ApprovalRoutingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((array) config('application_workflow.approval_authority_map', []) as $approvalCode => $entityCodes) {
            foreach ((array) $entityCodes as $entityCode) {
                $entity = Entity::query()->where('code', $entityCode)->first();

                if (! $entity) {
                    continue;
                }

                ApprovalRoutingRule::query()->updateOrCreate(
                    [
                        'request_type' => 'application',
                        'approval_code' => $approvalCode,
                        'target_entity_id' => $entity->getKey(),
                        'name' => $this->defaultRuleName($approvalCode, $entity),
                    ],
                    [
                        'conditions' => [],
                        'priority' => 100,
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    private function defaultRuleName(string $approvalCode, Entity $entity): string
    {
        return sprintf(
            'Application %s -> %s',
            str($approvalCode)->replace('_', ' ')->title()->toString(),
            $entity->name_en ?: $entity->name_ar ?: $entity->code
        );
    }
}
