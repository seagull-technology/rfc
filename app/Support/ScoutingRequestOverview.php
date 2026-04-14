<?php

namespace App\Support;

use App\Models\ScoutingRequest;
use App\Models\ScoutingRequestCorrespondence;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ScoutingRequestOverview
{
    /**
     * @return array{
     *     review_progress: array{label:string,summary:string,detail:?string},
     *     latest_official_step: array{label:string,summary:string,detail:?string},
     *     next_required_step: array{label:string,summary:string,detail:?string}
     * }
     */
    public static function forRequest(ScoutingRequest $request): array
    {
        return [
            'review_progress' => self::reviewProgress($request),
            'latest_official_step' => self::latestOfficialStep($request),
            'next_required_step' => self::nextRequiredStep($request),
        ];
    }

    /**
     * @return array{label:string,summary:string,detail:?string}
     */
    private static function reviewProgress(ScoutingRequest $request): array
    {
        return match ($request->status) {
            'draft' => [
                'label' => __('app.request_state.scouting_review_progress_title'),
                'summary' => __('app.request_state.scouting_review_progress_draft'),
                'detail' => null,
            ],
            'needs_clarification' => [
                'label' => __('app.request_state.scouting_review_progress_title'),
                'summary' => __('app.request_state.scouting_review_progress_clarification'),
                'detail' => filled($request->review_note) ? $request->review_note : null,
            ],
            'approved', 'rejected' => [
                'label' => __('app.request_state.scouting_review_progress_title'),
                'summary' => __('app.request_state.scouting_review_progress_resolved'),
                'detail' => null,
            ],
            default => [
                'label' => __('app.request_state.scouting_review_progress_title'),
                'summary' => __('app.request_state.scouting_review_progress_active'),
                'detail' => __('app.request_state.scouting_review_progress_stage', [
                    'stage' => $request->localizedStage(),
                ]),
            ],
        };
    }

    /**
     * @return array{label:string,summary:string,detail:?string}
     */
    private static function latestOfficialStep(ScoutingRequest $request): array
    {
        $items = collect([
            self::latestReviewNote($request),
            self::latestOfficialCorrespondence($request),
        ])->filter();

        $item = $items
            ->sortByDesc(fn (array $entry) => (($entry['at']?->timestamp ?? 0) * 10) + ($entry['priority'] ?? 0))
            ->first();

        return [
            'label' => __('app.request_state.latest_official_step_title'),
            'summary' => $item['summary'] ?? __('app.request_state.latest_official_step_none'),
            'detail' => $item['detail'] ?? null,
        ];
    }

    /**
     * @return array{label:string,summary:string,detail:?string}
     */
    private static function nextRequiredStep(ScoutingRequest $request): array
    {
        return match ($request->status) {
            'draft' => [
                'label' => __('app.request_state.scouting_next_step_title'),
                'summary' => __('app.request_state.scouting_next_step_submit'),
                'detail' => null,
            ],
            'needs_clarification' => [
                'label' => __('app.request_state.scouting_next_step_title'),
                'summary' => __('app.request_state.scouting_next_step_clarification'),
                'detail' => __('app.request_state.open_correspondence'),
            ],
            'approved', 'rejected' => [
                'label' => __('app.request_state.scouting_next_step_title'),
                'summary' => __('app.request_state.scouting_next_step_resolved'),
                'detail' => null,
            ],
            default => [
                'label' => __('app.request_state.scouting_next_step_title'),
                'summary' => __('app.request_state.scouting_next_step_wait'),
                'detail' => null,
            ],
        };
    }

    /**
     * @return array{summary:string,detail:?string,at:CarbonInterface,priority:int}|null
     */
    private static function latestReviewNote(ScoutingRequest $request): ?array
    {
        $at = self::asCarbon($request->reviewed_at);

        if (! $at || blank($request->review_note)) {
            return null;
        }

        return [
            'summary' => __('app.request_state.latest_official_step_review'),
            'detail' => $request->review_note,
            'at' => $at,
            'priority' => 1,
        ];
    }

    /**
     * @return array{summary:string,detail:?string,at:CarbonInterface,priority:int}|null
     */
    private static function latestOfficialCorrespondence(ScoutingRequest $request): ?array
    {
        $messages = $request->relationLoaded('correspondences')
            ? $request->correspondences
            : $request->correspondences()->get();

        $message = $messages
            ->where('sender_type', 'admin')
            ->sortByDesc('created_at')
            ->first();

        if (! $message instanceof ScoutingRequestCorrespondence || ! $message->created_at) {
            return null;
        }

        return [
            'summary' => __('app.request_state.latest_official_step_admin_correspondence', [
                'item' => $message->subject ?: __('app.correspondence.tab'),
            ]),
            'detail' => $message->message,
            'at' => $message->created_at,
            'priority' => 2,
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
