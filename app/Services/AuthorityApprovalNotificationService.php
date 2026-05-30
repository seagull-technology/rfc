<?php

namespace App\Services;

use App\Models\ApplicationAuthorityApproval;
use App\Models\Entity;
use App\Models\User;
use App\Notifications\InboxMessageNotification;
use App\Support\ApplicationWorkflowRegistry;
use App\Support\NotificationRecipients;
use App\Support\WorkflowMessageMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

class AuthorityApprovalNotificationService
{
    public function notifyRecipientsForApproval(ApplicationAuthorityApproval $approval, ?int $exceptUserId = null): int
    {
        return NotificationRecipients::except(NotificationRecipients::authorityUsersForApproval($approval), $exceptUserId)
            ->sum(fn (User $recipient): int => $this->notifyUserForApproval($recipient, $approval) ? 1 : 0);
    }

    public function notifyUserAboutOpenApprovals(User $user, Entity $entity, ?int $exceptUserId = null): int
    {
        if ($exceptUserId !== null && $user->getKey() === $exceptUserId) {
            return 0;
        }

        if (! $this->canReceiveAuthorityApprovals($user, $entity)) {
            return 0;
        }

        return $this->openApprovalsForUser($user, $entity)
            ->sum(fn (ApplicationAuthorityApproval $approval): int => $this->notifyUserForApproval($user, $approval) ? 1 : 0);
    }

    private function notifyUserForApproval(User $user, ApplicationAuthorityApproval $approval): bool
    {
        $approval->loadMissing(['application.entity', 'entity', 'assignedTo']);
        $application = $approval->application;

        if (! $application || $this->alreadyNotified($user, $approval)) {
            return false;
        }

        $user->notify(new InboxMessageNotification(
            typeKey: 'authority_approval_requested',
            title: $application->project_name,
            body: __('app.notifications.authority_approval_requested_body', [
                'authority' => $approval->localizedAuthority(),
                'code' => $application->code,
            ]),
            routeName: 'authority.applications.show',
            routeParameters: ['application' => $application->getKey()],
            meta: [
                ...WorkflowMessageMetadata::application($application),
                'application_id' => $application->getKey(),
                'authority_approval_id' => $approval->getKey(),
                'authority_code' => $approval->authority_code,
                'authority_label' => $approval->localizedAuthority(),
            ],
        ));

        return true;
    }

    private function alreadyNotified(User $user, ApplicationAuthorityApproval $approval): bool
    {
        return $user->notifications()
            ->where('data->type_key', 'authority_approval_requested')
            ->get()
            ->contains(function ($notification) use ($approval): bool {
                $data = $notification->data ?? [];

                if ((int) data_get($data, 'authority_approval_id') === $approval->getKey()) {
                    return true;
                }

                return (int) data_get($data, 'application_id') === (int) $approval->application_id
                    && (string) data_get($data, 'authority_code') === (string) $approval->authority_code;
            });
    }

    /**
     * @return Collection<int, ApplicationAuthorityApproval>
     */
    private function openApprovalsForUser(User $user, Entity $entity): Collection
    {
        $approvalCodes = ApplicationWorkflowRegistry::approvalCodesForEntity($entity);

        return ApplicationAuthorityApproval::query()
            ->with(['application.entity', 'entity', 'assignedTo'])
            ->whereIn('status', ['pending', 'in_review'])
            ->where(function (Builder $query) use ($entity, $approvalCodes): void {
                $query->where('entity_id', $entity->getKey());

                if ($approvalCodes !== []) {
                    $query->orWhere(function (Builder $legacyQuery) use ($approvalCodes): void {
                        $legacyQuery
                            ->whereNull('entity_id')
                            ->whereIn('authority_code', $approvalCodes);
                    });
                }
            })
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->whereNull('assigned_user_id')
                    ->orWhere('assigned_user_id', $user->getKey());
            })
            ->latest()
            ->get();
    }

    private function canReceiveAuthorityApprovals(User $user, Entity $entity): bool
    {
        if ($user->status !== 'active' || $entity->group?->code !== 'authorities') {
            return false;
        }

        if (! $entity->users()
            ->where('users.id', $user->getKey())
            ->wherePivot('status', 'active')
            ->exists()) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($entity->getKey());

        try {
            return $user->can('applications.view.entity');
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }
}
