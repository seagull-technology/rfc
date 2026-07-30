<?php

namespace App\Support;

use App\Models\Application as FilmApplication;
use App\Models\ScoutingRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

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

        $ttl = now()->addSeconds((int) config('cache.sidebar_ttl', 30));

        return [
            'applications' => Cache::remember(
                'admin-sidebar:application-responses:v1',
                $ttl,
                fn (): int => self::applicationResponseCount(),
            ),
            'scouting_requests' => Cache::remember(
                'admin-sidebar:scouting-responses:v1',
                $ttl,
                fn (): int => self::scoutingResponseCount(),
            ),
            'contact_center' => Cache::remember(
                'admin-sidebar:contact-center:v1:'.$user->getKey(),
                $ttl,
                fn (): int => $user->unreadNotifications()
                    ->whereIn('data->type_key', NotificationPresenter::inboxTypeKeys())
                    ->count(),
            ),
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
