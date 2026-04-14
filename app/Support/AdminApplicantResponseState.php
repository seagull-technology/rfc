<?php

namespace App\Support;

use App\Models\Application as FilmApplication;
use App\Models\ApplicationCorrespondence;
use App\Models\ApplicationDocument;
use App\Models\ScoutingRequest;
use App\Models\ScoutingRequestCorrespondence;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AdminApplicantResponseState
{
    /**
     * @return array{active:bool,title:?string,summary:?string,at:?CarbonInterface}
     */
    public static function application(FilmApplication $application): array
    {
        $lastClarificationAt = self::applicationLastClarificationAt($application);

        if (! $lastClarificationAt || in_array($application->status, ['draft', 'needs_clarification', 'approved', 'rejected'], true)) {
            return self::inactive();
        }

        $latestResponse = collect([
            self::applicationDocumentResponse($application, $lastClarificationAt),
            self::applicationCorrespondenceResponse($application, $lastClarificationAt),
            self::applicationResubmissionResponse($application, $lastClarificationAt),
        ])
            ->filter()
            ->sortByDesc(fn (array $item) => $item['at']?->timestamp ?? 0)
            ->first();

        if (! $latestResponse) {
            return self::inactive();
        }

        return [
            'active' => true,
            'title' => __('app.admin_request_state.applicant_response_title'),
            'summary' => $latestResponse['summary'],
            'at' => $latestResponse['at'],
        ];
    }

    /**
     * @return array{active:bool,title:?string,summary:?string,at:?CarbonInterface}
     */
    public static function scouting(ScoutingRequest $requestRecord): array
    {
        $lastClarificationAt = self::scoutingLastClarificationAt($requestRecord);

        if (! $lastClarificationAt || in_array($requestRecord->status, ['draft', 'needs_clarification', 'approved', 'rejected'], true)) {
            return self::inactive();
        }

        $latestResponse = collect([
            self::scoutingCorrespondenceResponse($requestRecord, $lastClarificationAt),
            self::scoutingResubmissionResponse($requestRecord, $lastClarificationAt),
        ])
            ->filter()
            ->sortByDesc(fn (array $item) => $item['at']?->timestamp ?? 0)
            ->first();

        if (! $latestResponse) {
            return self::inactive();
        }

        return [
            'active' => true,
            'title' => __('app.admin_request_state.applicant_response_title'),
            'summary' => $latestResponse['summary'],
            'at' => $latestResponse['at'],
        ];
    }

    /**
     * @return array{active:bool,title:?string,summary:?string,at:?CarbonInterface}
     */
    private static function inactive(): array
    {
        return [
            'active' => false,
            'title' => null,
            'summary' => null,
            'at' => null,
        ];
    }

    private static function applicationLastClarificationAt(FilmApplication $application): ?CarbonInterface
    {
        if ($application->getAttribute('last_clarification_at')) {
            return self::asCarbon($application->getAttribute('last_clarification_at'));
        }

        if ($application->relationLoaded('statusHistory')) {
            $lastClarificationAt = $application->statusHistory
                ->where('status', 'needs_clarification')
                ->max('happened_at');

            return $lastClarificationAt
                ? self::asCarbon($lastClarificationAt)
                : (filled($application->review_note) ? self::asCarbon($application->reviewed_at) : null);
        }

        $lastClarificationAt = $application->statusHistory()
            ->where('status', 'needs_clarification')
            ->max('happened_at');

        return $lastClarificationAt
            ? self::asCarbon($lastClarificationAt)
            : (filled($application->review_note) ? self::asCarbon($application->reviewed_at) : null);
    }

    private static function scoutingLastClarificationAt(ScoutingRequest $requestRecord): ?CarbonInterface
    {
        if ($requestRecord->getAttribute('last_clarification_at')) {
            return self::asCarbon($requestRecord->getAttribute('last_clarification_at'));
        }

        if ($requestRecord->relationLoaded('statusHistory')) {
            $lastClarificationAt = $requestRecord->statusHistory
                ->where('status', 'needs_clarification')
                ->max('happened_at');

            return $lastClarificationAt
                ? self::asCarbon($lastClarificationAt)
                : (filled($requestRecord->review_note) ? self::asCarbon($requestRecord->reviewed_at) : null);
        }

        $lastClarificationAt = $requestRecord->statusHistory()
            ->where('status', 'needs_clarification')
            ->max('happened_at');

        return $lastClarificationAt
            ? self::asCarbon($lastClarificationAt)
            : (filled($requestRecord->review_note) ? self::asCarbon($requestRecord->reviewed_at) : null);
    }

    /**
     * @return array{summary:string,at:CarbonInterface}|null
     */
    private static function applicationDocumentResponse(FilmApplication $application, CarbonInterface $lastClarificationAt): ?array
    {
        if (! $application->relationLoaded('documents') && $application->getAttribute('last_applicant_document_at')) {
            $at = self::asCarbon($application->getAttribute('last_applicant_document_at'));

            if ($at && $at->gte($lastClarificationAt)) {
                return [
                    'summary' => __('app.admin_request_state.applicant_response_document', [
                        'item' => __('app.documents.tab'),
                    ]),
                    'at' => $at,
                ];
            }
        }

        $document = $application->relationLoaded('documents')
            ? $application->documents
                ->filter(fn (ApplicationDocument $item) => $item->created_at && $item->created_at->gte($lastClarificationAt))
                ->sortByDesc('created_at')
                ->first()
            : $application->documents()
                ->where('created_at', '>=', $lastClarificationAt)
                ->latest()
                ->first();

        if (! $document?->created_at) {
            return null;
        }

        return [
            'summary' => __('app.admin_request_state.applicant_response_document', [
                'item' => $document->title ?: __('app.documents.tab'),
            ]),
            'at' => $document->created_at,
        ];
    }

    /**
     * @return array{summary:string,at:CarbonInterface}|null
     */
    private static function applicationCorrespondenceResponse(FilmApplication $application, CarbonInterface $lastClarificationAt): ?array
    {
        if (! $application->relationLoaded('correspondences') && $application->getAttribute('last_applicant_correspondence_at')) {
            $at = self::asCarbon($application->getAttribute('last_applicant_correspondence_at'));

            if ($at && $at->gte($lastClarificationAt)) {
                return [
                    'summary' => __('app.admin_request_state.applicant_response_correspondence', [
                        'item' => __('app.correspondence.tab'),
                    ]),
                    'at' => $at,
                ];
            }
        }

        $message = $application->relationLoaded('correspondences')
            ? $application->correspondences
                ->where('sender_type', 'applicant')
                ->filter(fn (ApplicationCorrespondence $item) => $item->created_at && $item->created_at->gte($lastClarificationAt))
                ->sortByDesc('created_at')
                ->first()
            : $application->correspondences()
                ->where('sender_type', 'applicant')
                ->where('created_at', '>=', $lastClarificationAt)
                ->latest()
                ->first();

        if (! $message?->created_at) {
            return null;
        }

        return [
            'summary' => __('app.admin_request_state.applicant_response_correspondence', [
                'item' => $message->subject ?: __('app.correspondence.tab'),
            ]),
            'at' => $message->created_at,
        ];
    }

    /**
     * @return array{summary:string,at:CarbonInterface}|null
     */
    private static function applicationResubmissionResponse(FilmApplication $application, CarbonInterface $lastClarificationAt): ?array
    {
        if (! $application->submitted_at || ! $application->submitted_at->gte($lastClarificationAt)) {
            return null;
        }

        return [
            'summary' => __('app.admin_request_state.applicant_response_resubmission'),
            'at' => $application->submitted_at,
        ];
    }

    /**
     * @return array{summary:string,at:CarbonInterface}|null
     */
    private static function scoutingCorrespondenceResponse(ScoutingRequest $requestRecord, CarbonInterface $lastClarificationAt): ?array
    {
        if (! $requestRecord->relationLoaded('correspondences') && $requestRecord->getAttribute('last_applicant_correspondence_at')) {
            $at = self::asCarbon($requestRecord->getAttribute('last_applicant_correspondence_at'));

            if ($at && $at->gte($lastClarificationAt)) {
                return [
                    'summary' => __('app.admin_request_state.applicant_response_correspondence', [
                        'item' => __('app.correspondence.tab'),
                    ]),
                    'at' => $at,
                ];
            }
        }

        $message = $requestRecord->relationLoaded('correspondences')
            ? $requestRecord->correspondences
                ->where('sender_type', 'applicant')
                ->filter(fn (ScoutingRequestCorrespondence $item) => $item->created_at && $item->created_at->gte($lastClarificationAt))
                ->sortByDesc('created_at')
                ->first()
            : $requestRecord->correspondences()
                ->where('sender_type', 'applicant')
                ->where('created_at', '>=', $lastClarificationAt)
                ->latest()
                ->first();

        if (! $message?->created_at) {
            return null;
        }

        return [
            'summary' => __('app.admin_request_state.applicant_response_correspondence', [
                'item' => $message->subject ?: __('app.correspondence.tab'),
            ]),
            'at' => $message->created_at,
        ];
    }

    /**
     * @return array{summary:string,at:CarbonInterface}|null
     */
    private static function scoutingResubmissionResponse(ScoutingRequest $requestRecord, CarbonInterface $lastClarificationAt): ?array
    {
        if (! $requestRecord->submitted_at || ! $requestRecord->submitted_at->gte($lastClarificationAt)) {
            return null;
        }

        return [
            'summary' => __('app.admin_request_state.applicant_response_resubmission'),
            'at' => $requestRecord->submitted_at,
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
