<?php

namespace App\Support;

use App\Models\Application as FilmApplication;
use App\Models\ApplicationAuthorityApproval;
use App\Models\ApplicationCorrespondence;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ApplicantRequestOverview
{
    /**
     * @return array{
     *     authority_progress: array{label:string,summary:string,detail:?string},
     *     latest_official_step: array{label:string,summary:string},
     *     final_decision_readiness: array{label:string,summary:string}
     * }
     */
    public static function forApplication(FilmApplication $application): array
    {
        $approvals = $application->relationLoaded('authorityApprovals')
            ? $application->authorityApprovals
            : $application->authorityApprovals()->get();

        $totalApprovals = $approvals->count();
        $resolvedApprovals = $approvals->whereIn('status', ['approved', 'rejected'])->count();
        $pendingApprovals = $approvals->whereIn('status', ['pending', 'in_review'])->count();

        return [
            'authority_progress' => [
                'label' => __('app.request_state.authority_progress'),
                'summary' => $totalApprovals === 0
                    ? __('app.request_state.authority_progress_none')
                    : __('app.request_state.authority_progress_summary', [
                        'resolved' => $resolvedApprovals,
                        'total' => $totalApprovals,
                    ]),
                'detail' => $totalApprovals === 0
                    ? null
                    : ($pendingApprovals > 0
                        ? __('app.request_state.authority_progress_pending', ['count' => $pendingApprovals])
                        : __('app.request_state.authority_progress_complete')),
            ],
            'latest_official_step' => [
                'label' => __('app.request_state.latest_official_step_title'),
                'summary' => self::latestOfficialStepSummary($application),
            ],
            'final_decision_readiness' => [
                'label' => __('app.request_state.final_decision_readiness'),
                'summary' => self::finalDecisionReadinessSummary($application, $pendingApprovals),
            ],
        ];
    }

    private static function latestOfficialStepSummary(FilmApplication $application): string
    {
        $items = collect([
            self::latestAdminReview($application),
            self::latestAuthorityDecision($application),
            self::latestOfficialCorrespondence($application),
            self::finalDecisionIssued($application),
        ])->filter();

        return $items
            ->sortByDesc(fn (array $item) => (($item['at']?->timestamp ?? 0) * 10) + ($item['priority'] ?? 0))
            ->first()['summary'] ?? __('app.request_state.latest_official_step_none');
    }

    private static function finalDecisionReadinessSummary(FilmApplication $application, int $pendingApprovals): string
    {
        if ($application->finalDecisionIssued()) {
            return __('app.request_state.final_decision_issued_body');
        }

        if ($application->status === 'draft') {
            return __('app.request_state.final_decision_waiting_submit');
        }

        if ($application->status === 'needs_clarification') {
            return __('app.request_state.final_decision_waiting_clarification');
        }

        if ($pendingApprovals > 0) {
            return __('app.request_state.final_decision_waiting_authorities', [
                'count' => $pendingApprovals,
            ]);
        }

        return __('app.request_state.final_decision_ready');
    }

    /**
     * @return array{summary:string,at:CarbonInterface,priority:int}|null
     */
    private static function latestAdminReview(FilmApplication $application): ?array
    {
        $at = self::asCarbon($application->reviewed_at);

        if (! $at || blank($application->review_note)) {
            return null;
        }

        return [
            'summary' => __('app.request_state.latest_official_step_review'),
            'at' => $at,
            'priority' => 1,
        ];
    }

    /**
     * @return array{summary:string,at:CarbonInterface,priority:int}|null
     */
    private static function latestAuthorityDecision(FilmApplication $application): ?array
    {
        $approvals = $application->relationLoaded('authorityApprovals')
            ? $application->authorityApprovals
            : $application->authorityApprovals()->get();

        $approval = $approvals
            ->filter(fn (ApplicationAuthorityApproval $item): bool => $item->status !== 'pending' || filled($item->note) || filled($item->reviewed_by_user_id))
            ->sortByDesc(fn (ApplicationAuthorityApproval $item) => (self::asCarbon($item->decided_at ?? $item->updated_at ?? $item->created_at))?->timestamp ?? 0)
            ->first();

        $at = $approval ? self::asCarbon($approval->decided_at ?? $approval->updated_at ?? $approval->created_at) : null;

        if (! $approval || ! $at) {
            return null;
        }

        return [
            'summary' => __('app.request_state.latest_official_step_authority_decision', [
                'authority' => $approval->localizedAuthority(),
                'status' => $approval->localizedStatus(),
            ]),
            'at' => $at,
            'priority' => 2,
        ];
    }

    /**
     * @return array{summary:string,at:CarbonInterface,priority:int}|null
     */
    private static function latestOfficialCorrespondence(FilmApplication $application): ?array
    {
        $messages = $application->relationLoaded('correspondences')
            ? $application->correspondences
            : $application->correspondences()->get();

        $message = $messages
            ->whereIn('sender_type', ['admin', 'authority'])
            ->sortByDesc('created_at')
            ->first();

        if (! $message instanceof ApplicationCorrespondence || ! $message->created_at) {
            return null;
        }

        return [
            'summary' => $message->sender_type === 'admin'
                ? __('app.request_state.latest_official_step_admin_correspondence', [
                    'item' => $message->subject ?: __('app.correspondence.tab'),
                ])
                : __('app.request_state.latest_official_step_authority_correspondence', [
                    'item' => $message->subject ?: __('app.correspondence.tab'),
                ]),
            'at' => $message->created_at,
            'priority' => 3,
        ];
    }

    /**
     * @return array{summary:string,at:CarbonInterface,priority:int}|null
     */
    private static function finalDecisionIssued(FilmApplication $application): ?array
    {
        $at = self::asCarbon($application->final_decision_issued_at);

        if (! $at || ! $application->finalDecisionIssued()) {
            return null;
        }

        return [
            'summary' => __('app.request_state.final_decision_issued_body'),
            'at' => $at,
            'priority' => 4,
        ];
    }

    private static function asCarbon(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value);
    }
}
