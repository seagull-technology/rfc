<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationAuthorityApproval;
use App\Models\Entity;
use Illuminate\Support\Collection;

class ApplicationAuthorityApprovalSyncService
{
    public function __construct(
        private readonly ApprovalRoutingService $approvalRoutingService,
    ) {
    }

    /**
     * @return Collection<int, ApplicationAuthorityApproval>
     */
    public function sync(Application $application): Collection
    {
        $application->refresh();

        $routes = $this->approvalRoutingService->routesForApplication($application);
        $metadata = $application->metadata ?? [];

        data_set(
            $metadata,
            'requirements.required_approvals',
            $routes->pluck('approval_code')->unique()->values()->all(),
        );

        $application->forceFill(['metadata' => $metadata])->save();

        $wantedKeys = $routes
            ->map(fn (array $route): string => $route['approval_code'].'|'.($route['target_entity_id'] ?? 'none'))
            ->all();
        $existingApprovals = $application->authorityApprovals()->get()
            ->keyBy(fn (ApplicationAuthorityApproval $approval): string => $approval->authority_code.'|'.($approval->entity_id ?? 'none'));
        $targetEntities = Entity::query()
            ->whereIn('id', $routes->pluck('target_entity_id')->filter()->unique()->all())
            ->get()
            ->keyBy(fn (Entity $entity): int => $entity->getKey());

        $application->authorityApprovals()
            ->get()
            ->reject(fn (ApplicationAuthorityApproval $approval): bool => in_array(
                $approval->authority_code.'|'.($approval->entity_id ?? 'none'),
                $wantedKeys,
                true
            ))
            ->each
            ->delete();

        $syncedApprovals = collect();

        foreach ($routes as $route) {
            $approvalKey = $route['approval_code'].'|'.($route['target_entity_id'] ?? 'none');
            $approval = $existingApprovals->get($approvalKey)
                ?? new ApplicationAuthorityApproval([
                    'application_id' => $application->getKey(),
                    'authority_code' => $route['approval_code'],
                    'entity_id' => $route['target_entity_id'],
                ]);
            $targetEntity = filled($route['target_entity_id'])
                ? $targetEntities->get((int) $route['target_entity_id'])
                : null;
            $resolvedAssignedUserId = $this->resolveAuthorityApprovalAssigneeId($targetEntity, $route['approval_code']);
            $shouldRefreshAssignment = ! $approval->exists
                || ! $this->entityHasActiveMember($targetEntity, (int) ($approval->assigned_user_id ?? 0));

            $approval->forceFill([
                'approval_routing_rule_id' => $route['approval_routing_rule_id'],
                'status' => 'pending',
                'note' => null,
                'reviewed_by_user_id' => null,
                'decided_at' => null,
                'escalated_at' => null,
                'sla_warning_notified_at' => null,
                'assigned_user_id' => $shouldRefreshAssignment ? $resolvedAssignedUserId : $approval->assigned_user_id,
                'assigned_at' => $shouldRefreshAssignment && $resolvedAssignedUserId
                    ? now()
                    : ($shouldRefreshAssignment ? null : $approval->assigned_at),
            ])->save();

            $syncedApprovals->push($approval->fresh(['entity', 'assignedTo']));
        }

        return $syncedApprovals->values();
    }

    private function resolveAuthorityApprovalAssigneeId(?Entity $entity, string $approvalCode): ?int
    {
        if (! $entity || $entity->group?->code !== 'authorities') {
            return null;
        }

        $candidateUserId = $entity->authorityDelegatedUserIdFor($approvalCode);

        return $this->entityHasActiveMember($entity, $candidateUserId) ? $candidateUserId : null;
    }

    private function entityHasActiveMember(?Entity $entity, ?int $userId): bool
    {
        if (! $entity || ! $userId) {
            return false;
        }

        return $entity->users()
            ->where('users.id', $userId)
            ->where('users.status', 'active')
            ->wherePivot('status', 'active')
            ->exists();
    }
}
