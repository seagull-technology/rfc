<?php

namespace App\Support;

use App\Models\Application;
use App\Models\ApplicationAuthorityApproval;
use App\Models\Entity;
use App\Models\ScoutingRequest;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationRecipients
{
    /**
     * @return Collection<int, User>
     */
    public static function entityUsers(Entity $entity): Collection
    {
        return $entity->users()
            ->where('users.status', 'active')
            ->get()
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public static function applicationApplicants(Application $application): Collection
    {
        return self::entityUsers($application->entity)
            ->push($application->submittedBy)
            ->filter()
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public static function scoutingApplicants(ScoutingRequest $scoutingRequest): Collection
    {
        return self::entityUsers($scoutingRequest->entity)
            ->push($scoutingRequest->submittedBy)
            ->filter()
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public static function adminUsers(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('entities.group', fn ($query) => $query->whereIn('code', ['rfc', 'admins']))
            ->get()
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }

    /**
     * @param  array<int, string>  $approvalCodes
     * @return Collection<int, User>
     */
    public static function authorityUsersForApprovalCodes(array $approvalCodes): Collection
    {
        if ($approvalCodes === []) {
            return collect();
        }

        return User::query()
            ->where('status', 'active')
            ->whereHas('entities.group', fn ($query) => $query->where('code', 'authorities'))
            ->with('entities.group')
            ->get()
            ->filter(function (User $user) use ($approvalCodes): bool {
                return $user->entities->contains(function (Entity $entity) use ($approvalCodes): bool {
                    $entityApprovalCodes = ApplicationWorkflowRegistry::approvalCodesForEntity($entity);

                    return array_intersect($approvalCodes, $entityApprovalCodes) !== [];
                });
            })
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public static function authorityUsersForApproval(ApplicationAuthorityApproval $approval): Collection
    {
        if ($approval->entity && $approval->entity->group?->code === 'authorities') {
            return self::entityUsers($approval->entity);
        }

        return self::authorityUsersForApprovalCodes([$approval->authority_code]);
    }

    /**
     * @return Collection<int, User>
     */
    public static function authorityUsersForApplication(Application $application): Collection
    {
        $approvals = $application->relationLoaded('authorityApprovals')
            ? $application->authorityApprovals
            : $application->authorityApprovals()->with('entity.group')->get();

        return $approvals
            ->flatMap(fn (ApplicationAuthorityApproval $approval): Collection => self::authorityUsersForApproval($approval))
            ->filter()
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    public static function except(Collection $users, ?int $userId): Collection
    {
        if (! $userId) {
            return $users->values();
        }

        return $users
            ->reject(fn (User $user): bool => $user->getKey() === $userId)
            ->values();
    }
}
