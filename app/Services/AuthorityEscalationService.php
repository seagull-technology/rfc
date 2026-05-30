<?php

namespace App\Services;

use App\Models\ApplicationAuthorityApproval;
use App\Models\ApprovalRoutingRule;
use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use App\Notifications\InboxMessageNotification;
use App\Support\ApplicationWorkflowRegistry;
use App\Support\NotificationRecipients;
use App\Support\WorkflowMessageMetadata;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AuthorityEscalationService
{
    /**
     * @var array<string, Entity|null>
     */
    private array $approvalCodeAuthorityCache = [];

    /**
     * @return array{response_time_days:?int,escalation_user_ids:array<int, int>,escalation_role_names:array<int, string>}
     */
    public function settingsForEntity(Entity $entity): array
    {
        return [
            'response_time_days' => $entity->authorityResponseTimeDays(),
            'escalation_user_ids' => $entity->authorityEscalationUserIds(),
            'escalation_role_names' => $entity->authorityEscalationRoleNames(),
        ];
    }

    /**
     * @return array{enabled:bool,due_at:?CarbonInterface,is_overdue:bool,is_escalated:bool,label:?string}
     */
    public function signalForApproval(ApplicationAuthorityApproval $approval, ?CarbonInterface $asOf = null, ?Entity $authorityEntity = null): array
    {
        $asOf ??= now();
        $dueAt = $this->dueAt($approval, $authorityEntity);

        if (! $dueAt || ! in_array($approval->status, ['pending', 'in_review'], true)) {
            return [
                'enabled' => false,
                'due_at' => null,
                'is_overdue' => false,
                'is_escalated' => false,
                'label' => null,
            ];
        }

        if ($dueAt->lessThanOrEqualTo($asOf)) {
            return [
                'enabled' => true,
                'due_at' => $dueAt,
                'is_overdue' => true,
                'is_escalated' => $approval->escalated_at !== null,
                'label' => __('app.admin.authority_escalations.overdue_badge', [
                    'value' => $dueAt->diffForHumans($asOf, [
                        'parts' => 2,
                        'short' => false,
                        'syntax' => CarbonInterface::DIFF_ABSOLUTE,
                    ]),
                ]),
            ];
        }

        return [
            'enabled' => true,
            'due_at' => $dueAt,
            'is_overdue' => false,
            'is_escalated' => $approval->escalated_at !== null,
            'label' => __('app.admin.authority_escalations.due_badge', [
                'value' => $asOf->diffForHumans($dueAt, [
                    'parts' => 2,
                    'short' => false,
                    'syntax' => CarbonInterface::DIFF_ABSOLUTE,
                ]),
            ]),
        ];
    }

    public function dueAt(ApplicationAuthorityApproval $approval, ?Entity $authorityEntity = null): ?CarbonInterface
    {
        $authorityEntity ??= $this->authorityEntityForApproval($approval);
        $days = $authorityEntity?->authorityResponseTimeDays();

        if (! $days || ! $approval->created_at) {
            return null;
        }

        return $approval->created_at->copy()->addDays($days);
    }

    public function authorityEntityForApproval(ApplicationAuthorityApproval $approval, ?Entity $fallbackEntity = null): ?Entity
    {
        $approvalEntity = $approval->relationLoaded('entity')
            ? $approval->entity
            : ($approval->entity_id ? $approval->entity()->first() : null);

        if ($approvalEntity) {
            return $approvalEntity;
        }

        if (
            $fallbackEntity
            && in_array($approval->authority_code, ApplicationWorkflowRegistry::approvalCodesForEntity($fallbackEntity), true)
        ) {
            return $fallbackEntity;
        }

        return $this->authorityEntityForApprovalCode((string) $approval->authority_code);
    }

    private function authorityEntityForApprovalCode(string $approvalCode): ?Entity
    {
        if ($approvalCode === '') {
            return null;
        }

        if (array_key_exists($approvalCode, $this->approvalCodeAuthorityCache)) {
            return $this->approvalCodeAuthorityCache[$approvalCode];
        }

        $entityCodes = ApplicationWorkflowRegistry::entityCodesForApproval($approvalCode);

        if ($entityCodes !== []) {
            $mappedEntities = Entity::query()
                ->whereHas('group', fn ($query) => $query->where('code', 'authorities'))
                ->whereIn('code', $entityCodes)
                ->get();

            $mappedEntity = collect($entityCodes)
                ->map(fn (string $entityCode): ?Entity => $mappedEntities->firstWhere('code', $entityCode))
                ->filter()
                ->sortByDesc(fn (Entity $entity): bool => $entity->authorityResponseTimeDays() !== null)
                ->first();

            if ($mappedEntity) {
                return $this->approvalCodeAuthorityCache[$approvalCode] = $mappedEntity;
            }
        }

        $routingEntity = ApprovalRoutingRule::query()
            ->with('targetEntity.group')
            ->where('request_type', 'application')
            ->where('approval_code', $approvalCode)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->pluck('targetEntity')
            ->filter(fn (?Entity $entity): bool => $entity?->group?->code === 'authorities')
            ->unique(fn (Entity $entity): int => $entity->getKey())
            ->sortByDesc(fn (Entity $entity): bool => $entity->authorityResponseTimeDays() !== null)
            ->first();

        return $this->approvalCodeAuthorityCache[$approvalCode] = $routingEntity;
    }

    /**
     * @return Collection<int, Entity>
     */
    public function manageableAuthorities(): Collection
    {
        return Entity::query()
            ->with(['group', 'users'])
            ->whereHas('group', fn ($query) => $query->where('code', 'authorities'))
            ->whereNull('deleted_at')
            ->orderBy('name_en')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function escalationAssignableUsers(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->with(['entities.group'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->availableEntities()->contains(
                fn (Entity $entity): bool => in_array($entity->group?->code, ['admins', 'rfc'], true)
            ))
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }

    /**
     * @return Collection<int, Role>
     */
    public function escalationAssignableRoles(): Collection
    {
        return Group::query()
            ->whereIn('code', ['admins', 'rfc'])
            ->with('roles')
            ->get()
            ->pluck('roles')
            ->flatten()
            ->unique(fn (Role $role): string => $role->name)
            ->sortBy('name')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function escalationRecipientsForEntity(Entity $entity): Collection
    {
        $settings = $this->settingsForEntity($entity);
        $directUsers = $this->escalationAssignableUsers()
            ->whereIn('id', $settings['escalation_user_ids'])
            ->values();
        $roleUsers = $this->usersForRoles($settings['escalation_role_names']);

        $recipients = $directUsers
            ->concat($roleUsers)
            ->filter()
            ->unique(fn (User $user): int => $user->getKey())
            ->values();

        return $recipients->isNotEmpty()
            ? $recipients
            : NotificationRecipients::adminUsers();
    }

    public function escalateOverdueApprovals(?CarbonInterface $asOf = null): int
    {
        $asOf ??= now();

        $approvals = ApplicationAuthorityApproval::query()
            ->with(['application.entity', 'entity.group', 'assignedTo'])
            ->whereIn('status', ['pending', 'in_review'])
            ->whereNull('escalated_at')
            ->whereHas('entity.group', fn ($query) => $query->where('code', 'authorities'))
            ->get()
            ->filter(fn (ApplicationAuthorityApproval $approval): bool => $this->signalForApproval($approval, $asOf)['is_overdue'])
            ->values();

        $count = 0;

        foreach ($approvals as $approval) {
            $application = $approval->application;
            $authority = $this->authorityEntityForApproval($approval);

            if (! $application || ! $authority) {
                continue;
            }

            $recipients = NotificationRecipients::except(
                $this->escalationRecipientsForEntity($authority),
                $approval->assigned_user_id,
            );

            $approval->forceFill([
                'escalated_at' => $asOf,
            ])->save();

            $application->statusHistory()->create([
                'user_id' => null,
                'status' => $application->status,
                'note' => __('app.workflow.history.authority_escalated', [
                    'authority' => $approval->localizedAuthority(),
                ]),
                'metadata' => [
                    'type' => 'authority_escalated',
                    'approval_id' => $approval->getKey(),
                    'authority_code' => $approval->authority_code,
                    'authority_label' => $approval->localizedAuthority(),
                    'sla_days' => $authority->authorityResponseTimeDays(),
                    'assigned_user_id' => $approval->assigned_user_id,
                    'assigned_user_name' => $approval->assignedTo?->displayName(),
                    'escalated_at' => $asOf->toIso8601String(),
                ],
                'happened_at' => $asOf,
            ]);

            $recipients->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'authority_approval_escalated',
                title: $application->project_name,
                body: __('app.notifications.authority_approval_escalated_body', [
                    'authority' => $approval->localizedAuthority(),
                    'code' => $application->code,
                    'days' => $authority->authorityResponseTimeDays(),
                ]),
                routeName: 'admin.applications.show',
                routeParameters: ['application' => $application->getKey()],
                meta: [
                    ...WorkflowMessageMetadata::application($application),
                    'notification_highlight_active' => true,
                    'notification_highlight_title' => __('app.admin.authority_escalations.overdue_notification_title'),
                    'notification_highlight_summary' => __('app.admin.authority_escalations.overdue_notification_summary', [
                        'authority' => $authority->displayName(),
                    ]),
                    'notification_highlight_class' => 'danger',
                ],
            )));

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, string>  $roleNames
     * @return Collection<int, User>
     */
    private function usersForRoles(array $roleNames): Collection
    {
        if ($roleNames === []) {
            return collect();
        }

        $users = $this->escalationAssignableUsers();
        $registrar = app(PermissionRegistrar::class);

        return $users
            ->filter(function (User $user) use ($roleNames, $registrar): bool {
                foreach ($user->availableEntities() as $entity) {
                    if (! in_array($entity->group?->code, ['admins', 'rfc'], true)) {
                        continue;
                    }

                    $registrar->setPermissionsTeamId($entity->getKey());

                    try {
                        if ($user->hasAnyRole($roleNames)) {
                            return true;
                        }
                    } finally {
                        $registrar->setPermissionsTeamId(null);
                    }
                }

                return false;
            })
            ->values();
    }
}
