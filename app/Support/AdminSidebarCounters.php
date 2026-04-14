<?php

namespace App\Support;

use App\Models\Application as FilmApplication;
use App\Models\ScoutingRequest;
use App\Models\User;

class AdminSidebarCounters
{
    /**
     * @return array{applications:int,scouting_requests:int,contact_center:int}
     */
    public static function forUser(?User $user): array
    {
        if (! $user) {
            return [
                'applications' => 0,
                'scouting_requests' => 0,
                'contact_center' => 0,
            ];
        }

        return [
            'applications' => self::applicationResponseCount(),
            'scouting_requests' => self::scoutingResponseCount(),
            'contact_center' => $user->unreadNotifications
                ->filter(fn ($notification) => NotificationPresenter::isInbox($notification))
                ->count(),
        ];
    }

    private static function applicationResponseCount(): int
    {
        return FilmApplication::query()
            ->whereNotIn('status', ['draft', 'needs_clarification', 'approved', 'rejected'])
            ->withMax([
                'statusHistory as last_clarification_at' => fn ($builder) => $builder->where('status', 'needs_clarification'),
            ], 'happened_at')
            ->withMax([
                'correspondences as last_applicant_correspondence_at' => fn ($builder) => $builder->where('sender_type', 'applicant'),
            ], 'created_at')
            ->withMax('documents as last_applicant_document_at', 'created_at')
            ->get()
            ->filter(fn (FilmApplication $application) => AdminApplicantResponseState::application($application)['active'])
            ->count();
    }

    private static function scoutingResponseCount(): int
    {
        return ScoutingRequest::query()
            ->whereNotIn('status', ['draft', 'needs_clarification', 'approved', 'rejected'])
            ->withMax([
                'statusHistory as last_clarification_at' => fn ($builder) => $builder->where('status', 'needs_clarification'),
            ], 'happened_at')
            ->withMax([
                'correspondences as last_applicant_correspondence_at' => fn ($builder) => $builder->where('sender_type', 'applicant'),
            ], 'created_at')
            ->get()
            ->filter(fn (ScoutingRequest $requestRecord) => AdminApplicantResponseState::scouting($requestRecord)['active'])
            ->count();
    }
}
