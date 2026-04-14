<?php

namespace App\Support;

use App\Models\ApplicationAuthorityApproval;
use App\Models\ApplicationCorrespondence;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AuthorityApprovalSignal
{
    /**
     * @return array{active:bool,key:?string,label:?string,summary:?string,class:string,priority:int,at:?CarbonInterface}
     */
    public static function forApproval(ApplicationAuthorityApproval $approval): array
    {
        if (in_array($approval->status, ['approved', 'rejected'], true)) {
            return self::inactive();
        }

        $latestExternalCorrespondence = self::latestExternalCorrespondence($approval);
        $lastAuthorityTouch = self::lastAuthorityTouch($approval);

        if ($latestExternalCorrespondence && (! $lastAuthorityTouch || $latestExternalCorrespondence['at']->gte($lastAuthorityTouch))) {
            return [
                'active' => true,
                'key' => 'request_update',
                'label' => __('app.authority.applications.signal_request_update'),
                'summary' => __('app.authority.applications.signal_request_update_item', [
                    'item' => $latestExternalCorrespondence['item'],
                ]),
                'class' => 'primary',
                'priority' => 3,
                'at' => $latestExternalCorrespondence['at'],
            ];
        }

        if ($approval->status === 'pending') {
            return [
                'active' => true,
                'key' => 'awaiting_decision',
                'label' => __('app.authority.applications.signal_awaiting_decision'),
                'summary' => __('app.authority.applications.signal_awaiting_decision_summary'),
                'class' => 'danger',
                'priority' => 2,
                'at' => self::asCarbon($approval->created_at),
            ];
        }

        if ($approval->status === 'in_review') {
            return [
                'active' => true,
                'key' => 'continue_review',
                'label' => __('app.authority.applications.signal_continue_review'),
                'summary' => __('app.authority.applications.signal_continue_review_summary'),
                'class' => 'warning',
                'priority' => 1,
                'at' => self::asCarbon($approval->updated_at ?? $approval->created_at),
            ];
        }

        return self::inactive();
    }

    /**
     * @return array{active:bool,key:?string,label:?string,summary:?string,class:string,priority:int,at:?CarbonInterface}
     */
    private static function inactive(): array
    {
        return [
            'active' => false,
            'key' => null,
            'label' => null,
            'summary' => null,
            'class' => 'secondary',
            'priority' => 0,
            'at' => null,
        ];
    }

    /**
     * @return array{item:string,at:CarbonInterface}|null
     */
    private static function latestExternalCorrespondence(ApplicationAuthorityApproval $approval): ?array
    {
        $application = $approval->application;

        if (! $application) {
            return null;
        }

        if (! $application->relationLoaded('correspondences') && $application->getAttribute('last_external_correspondence_at')) {
            $at = self::asCarbon($application->getAttribute('last_external_correspondence_at'));

            if ($at) {
                return [
                    'item' => __('app.correspondence.tab'),
                    'at' => $at,
                ];
            }
        }

        $message = $application->relationLoaded('correspondences')
            ? $application->correspondences
                ->whereIn('sender_type', ['admin', 'applicant'])
                ->sortByDesc('created_at')
                ->first()
            : $application->correspondences()
                ->whereIn('sender_type', ['admin', 'applicant'])
                ->latest()
                ->first();

        if (! $message instanceof ApplicationCorrespondence || ! $message->created_at) {
            return null;
        }

        return [
            'item' => $message->subject ?: __('app.correspondence.tab'),
            'at' => $message->created_at,
        ];
    }

    private static function lastAuthorityTouch(ApplicationAuthorityApproval $approval): ?CarbonInterface
    {
        return self::asCarbon($approval->decided_at ?? $approval->updated_at ?? $approval->created_at);
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
