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
            $application->current_stage === 'rfc_facilitation' && ! $application->authorityRoutingStarted() => self::state('review_official_books'),
            $pendingApprovals > 0 => self::state('waiting_authorities'),
            $application->canBeFinallyDecided() => self::state('ready_final_decision'),
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
                ->whereIn('status', ['pending', 'in_review', 'changes_requested'])
                ->count();
        }

        return $application->authorityApprovals()
            ->whereIn('status', ['pending', 'in_review', 'changes_requested'])
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
                'waiting_authorities', 'review_official_books' => 'warning',
                'draft' => 'secondary',
                default => 'info',
            },
        ];
    }
}
