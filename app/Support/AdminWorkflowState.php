<?php

namespace App\Support;

use App\Models\Application as FilmApplication;
use App\Models\ScoutingRequest;

class AdminWorkflowState
{
    /**
     * @return array{key:string,label:string,class:string}
     */
    public static function applicationCheckpoint(FilmApplication $application): array
    {
        $pendingApprovals = self::applicationPendingApprovalsCount($application);

        return match (true) {
            $application->status === 'needs_clarification' => self::state('waiting_on_applicant'),
            in_array($application->status, ['approved', 'rejected'], true) => self::state('resolved'),
            $application->status === 'draft' => self::state('draft'),
            blank($application->assigned_to_user_id) => self::state('assign_reviewer'),
            $pendingApprovals > 0 => self::state('waiting_authorities'),
            in_array($application->status, ['submitted', 'under_review'], true) && $application->hasResolvedAuthorityApprovals() => self::state('ready_final_decision'),
            default => self::state('needs_admin_review'),
        };
    }

    /**
     * @return array{key:string,label:string,class:string}
     */
    public static function scoutingCheckpoint(ScoutingRequest $request): array
    {
        return match (true) {
            $request->status === 'needs_clarification' => self::state('waiting_on_applicant'),
            in_array($request->status, ['approved', 'rejected'], true) => self::state('resolved'),
            $request->status === 'draft' => self::state('draft'),
            default => self::state('needs_admin_review'),
        };
    }

    private static function applicationPendingApprovalsCount(FilmApplication $application): int
    {
        if ($application->relationLoaded('authorityApprovals')) {
            return $application->authorityApprovals
                ->whereIn('status', ['pending', 'in_review'])
                ->count();
        }

        return $application->authorityApprovals()
            ->whereIn('status', ['pending', 'in_review'])
            ->count();
    }

    /**
     * @return array{key:string,label:string,class:string}
     */
    private static function state(string $key): array
    {
        return [
            'key' => $key,
            'label' => __('app.admin.workflow_states.'.$key),
            'class' => match ($key) {
                'ready_final_decision', 'resolved' => 'success',
                'waiting_on_applicant' => 'danger',
                'waiting_authorities', 'assign_reviewer' => 'warning',
                'draft' => 'secondary',
                default => 'info',
            },
        ];
    }
}
