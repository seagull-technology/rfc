<?php

namespace App\Support;

use App\Models\Application as FilmApplication;
use App\Models\ScoutingRequest;

class ApplicantDashboardState
{
    /**
     * @return array{summary:string,priority:int}
     */
    public static function application(FilmApplication $application): array
    {
        if ($application->finalDecisionIssued()) {
            return [
                'summary' => __('app.dashboard.request_summaries.application_final_decision_issued'),
                'priority' => 1,
            ];
        }

        return match ($application->status) {
            'draft' => [
                'summary' => __('app.dashboard.request_summaries.application_draft'),
                'priority' => 4,
            ],
            'needs_clarification' => [
                'summary' => __('app.dashboard.request_summaries.application_clarification'),
                'priority' => 5,
            ],
            'approved', 'rejected' => [
                'summary' => __('app.dashboard.request_summaries.application_resolved'),
                'priority' => 1,
            ],
            default => self::applicationReviewSummary($application),
        };
    }

    /**
     * @return array{summary:string,priority:int}
     */
    public static function scouting(ScoutingRequest $request): array
    {
        return match ($request->status) {
            'draft' => [
                'summary' => __('app.dashboard.request_summaries.scouting_draft'),
                'priority' => 4,
            ],
            'needs_clarification' => [
                'summary' => __('app.dashboard.request_summaries.scouting_clarification'),
                'priority' => 5,
            ],
            'approved', 'rejected' => [
                'summary' => __('app.dashboard.request_summaries.scouting_resolved'),
                'priority' => 1,
            ],
            default => [
                'summary' => __('app.dashboard.request_summaries.scouting_review'),
                'priority' => 3,
            ],
        };
    }

    /**
     * @return array{summary:string,priority:int}
     */
    private static function applicationReviewSummary(FilmApplication $application): array
    {
        $approvals = $application->relationLoaded('authorityApprovals')
            ? $application->authorityApprovals
            : $application->authorityApprovals()->get();

        $total = $approvals->count();
        $resolved = $approvals->whereIn('status', ['approved', 'rejected'])->count();
        $pending = $approvals->whereIn('status', ['pending', 'in_review'])->count();

        if ($total === 0) {
            return [
                'summary' => __('app.dashboard.request_summaries.application_review'),
                'priority' => 3,
            ];
        }

        if ($pending > 0 && $resolved > 0) {
            return [
                'summary' => __('app.dashboard.request_summaries.application_authority_progress', [
                    'resolved' => $resolved,
                    'total' => $total,
                ]),
                'priority' => 3,
            ];
        }

        if ($pending > 0) {
            return [
                'summary' => __('app.dashboard.request_summaries.application_waiting_authorities', [
                    'count' => $pending,
                ]),
                'priority' => 3,
            ];
        }

        return [
            'summary' => __('app.dashboard.request_summaries.application_ready_final_decision'),
            'priority' => 2,
        ];
    }
}
