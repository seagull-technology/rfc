<?php

namespace Database\Seeders;

use App\Models\ApprovalRoutingRule;
use App\Models\Entity;
use App\Support\ApplicationWorkflowRegistry;
use Illuminate\Database\Seeder;

class ApprovalRoutingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(ApplicationWorkflowRegistry::approvalAuthorityMap()) as $approvalCode) {
            foreach (ApplicationWorkflowRegistry::approvalAuthorityEntriesForApproval((string) $approvalCode) as $entry) {
                $entity = Entity::query()->where('code', $entry['entity_code'])->first();

                if (! $entity) {
                    continue;
                }

                ApprovalRoutingRule::query()->updateOrCreate(
                    [
                        'request_type' => 'application',
                        'approval_code' => $approvalCode,
                        'target_entity_id' => $entity->getKey(),
                        'name' => $entry['name'],
                    ],
                    [
                        'conditions' => $entry['conditions'],
                        'priority' => $entry['priority'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
