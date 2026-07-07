<?php

namespace App\Http\Controllers;

use App\Models\Application as FilmApplication;
use App\Models\ApplicationAuthorityApproval;
use App\Models\Entity;
use App\Models\ScoutingRequest;
use App\Models\User;
use App\Notifications\InboxMessageNotification;
use App\Support\ApplicantDashboardState;
use App\Support\PhoneNumber;
use App\Support\ProfileChangeRequests;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $entity = $user->primaryEntity();
        $variant = (string) $request->query('variant', 'default');

        abort_unless($user && $entity, 404);

        $applications = FilmApplication::query()
            ->with(['authorityApprovals', 'submittedBy'])
            ->where('entity_id', $entity->getKey())
            ->newestFirst()
            ->get();
        $scoutingRequests = ScoutingRequest::query()
            ->with('submittedBy')
            ->where('entity_id', $entity->getKey())
            ->newestFirst()
            ->get();

        if ($variant === 'foreign_producer' && $this->isInternationalProducerUser($user)) {
            $applications = $this->applicationsLinkedToInternationalProducer($applications, $user);
            $scoutingRequests = $this->scoutingRequestsLinkedToInternationalProducer($scoutingRequests, $user);
        }

        $canManageEntityProfile = $variant !== 'foreign_producer'
            && $this->canManageEntityProfile($user, $entity);

        $monthlyApplications = collect(range(5, 0))
            ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($offset))
            ->map(function ($month) use ($applications): array {
                $count = $applications->filter(
                    fn (FilmApplication $application): bool => optional($application->created_at)->format('Y-m') === $month->format('Y-m')
                )->count();

                return [
                    'label' => $month->translatedFormat('M'),
                    'count' => $count,
                ];
            })
            ->values();
        $applicationsByCategory = $applications
            ->filter(fn (FilmApplication $application): bool => filled($application->work_category))
            ->groupBy(fn (FilmApplication $application): string => (string) $application->work_category)
            ->map(fn ($group) => $group->count())
            ->sortDesc();
        $budgetByProject = $applications
            ->filter(fn (FilmApplication $application): bool => filled($application->estimated_budget))
            ->take(6)
            ->map(fn (FilmApplication $application): array => [
                'label' => $application->project_name,
                'value' => (float) $application->estimated_budget,
            ])
            ->values();
        $crewByProject = $applications
            ->filter(fn (FilmApplication $application): bool => filled($application->estimated_crew_count))
            ->take(6)
            ->map(fn (FilmApplication $application): array => [
                'label' => $application->project_name,
                'value' => (int) $application->estimated_crew_count,
            ])
            ->values();
        $approvalDurationByAuthority = ApplicationAuthorityApproval::query()
            ->whereHas('application', fn (Builder $query): Builder => $query->where('entity_id', $entity->getKey()))
            ->with('application')
            ->whereNotNull('decided_at')
            ->latest('decided_at')
            ->get()
            ->groupBy('authority_code')
            ->map(function ($approvals, string $authorityCode): ?array {
                $durations = $approvals
                    ->map(function (ApplicationAuthorityApproval $approval): ?float {
                        $startedAt = $approval->application?->submitted_at ?? $approval->application?->created_at;

                        if (! $startedAt || ! $approval->decided_at) {
                            return null;
                        }

                        return round($startedAt->diffInHours($approval->decided_at), 1);
                    })
                    ->filter(fn (?float $value): bool => $value !== null);

                if ($durations->isEmpty()) {
                    return null;
                }

                return [
                    'code' => $authorityCode,
                    'average_hours' => round($durations->avg(), 1),
                ];
            })
            ->filter()
            ->sortByDesc('average_hours')
            ->take(6)
            ->values();
        $resolvedApplications = $applications->whereIn('status', ['approved', 'rejected'])->count();
        $approvedApplications = $applications->where('status', 'approved')->count();
        $previousProjects = $applications
            ->whereIn('status', ['approved', 'rejected'])
            ->sortByDesc(fn (FilmApplication $application) => $application->reviewed_at ?? $application->submitted_at ?? $application->created_at)
            ->values();
        $scoutingRequestsCount = $scoutingRequests->count();
        $approvalAverage = $applications->count() > 0
            ? round(($approvedApplications / $applications->count()) * 100)
            : 0;
        $primaryOwner = $entity->users()
            ->where('users.status', 'active')
            ->orderByDesc('entity_user.is_primary')
            ->orderBy('users.name')
            ->first();
        [$reviewHistory, $reviewerNames] = $this->registrationReviewHistory($entity);
        $activeWorkflowRequests = collect($applications
            ->whereNotIn('status', ['approved', 'rejected'])
            ->map(function (FilmApplication $application): array {
                $state = ApplicantDashboardState::application($application);

                return [
                    'type_label' => __('app.dashboard.production_request_type'),
                    'code' => $application->code,
                    'project_name' => $application->project_name,
                    'summary' => $state['summary'],
                    'status_label' => $application->localizedStatus(),
                    'status_class' => $this->statusClass($application->status),
                    'url' => route('applications.show', $application),
                    'sort_at' => $application->reviewed_at ?? $application->updated_at ?? $application->submitted_at ?? $application->created_at,
                    'sort_id' => $application->getKey(),
                ];
            })
            ->all())
            ->merge($scoutingRequests
                ->whereNotIn('status', ['approved', 'rejected'])
                ->map(function (ScoutingRequest $request): array {
                    $state = ApplicantDashboardState::scouting($request);

                    return [
                        'type_label' => __('app.dashboard.scouting_request_type'),
                        'code' => $request->code,
                        'project_name' => $request->project_name,
                        'summary' => $state['summary'],
                        'status_label' => $request->localizedStatus(),
                        'status_class' => $this->statusClass($request->status),
                        'url' => route('scouting-requests.show', $request),
                        'sort_at' => $request->reviewed_at ?? $request->updated_at ?? $request->submitted_at ?? $request->created_at,
                        'sort_id' => $request->getKey(),
                    ];
                })
                ->all())
            ->sortByDesc(fn (array $item): int => ((data_get($item, 'sort_at')?->timestamp ?? 0) * 1_000_000) + (int) data_get($item, 'sort_id', 0))
            ->values();

        return view($variant === 'foreign_producer' ? 'profile.foreign-producer' : 'profile.show', [
            'user' => $user,
            'entity' => $entity,
            'variant' => $variant,
            'primaryOwner' => $primaryOwner,
            'reviewHistory' => $reviewHistory,
            'reviewerNames' => $reviewerNames,
            'latestRegistrationNotification' => $this->latestRegistrationNotification($user),
            'canManageEntityProfile' => $canManageEntityProfile,
            'profileOfficialFields' => ProfileChangeRequests::officialFields($entity),
            'profileChangeRequests' => ProfileChangeRequests::all($entity),
            'pendingProfileChangeRequest' => ProfileChangeRequests::pending($entity),
            'profileLogoUrl' => data_get($entity->metadata, 'logo_path') ? route('profile.logo') : asset('images/OIP.jpeg'),
            'entityApplications' => $applications,
            'scoutingRequests' => $scoutingRequests,
            'previousProjects' => $previousProjects,
            'activeWorkflowRequests' => $activeWorkflowRequests,
            'entityAnalytics' => [
                'stats' => [
                    'production_requests' => $applications->count(),
                    'scouting_requests' => $scoutingRequestsCount,
                    'previous_projects' => $resolvedApplications,
                    'approval_average' => $approvalAverage,
                ],
                'charts' => [
                    'applications_by_type' => $applicationsByCategory,
                    'budget_by_project' => $budgetByProject,
                    'applications_by_month' => $monthlyApplications,
                    'crew_by_project' => $crewByProject,
                    'authority_response_average' => $approvalDurationByAuthority,
                ],
            ],
        ]);
    }

    public function logo(Request $request): StreamedResponse|RedirectResponse
    {
        $entity = $request->user()?->primaryEntity();
        $path = data_get($entity?->metadata, 'logo_path');

        if (! $entity || ! is_string($path) || ! Storage::disk('local')->exists($path)) {
            return redirect()
                ->route('profile.show')
                ->withErrors(['profile' => __('app.profile.logo_missing')]);
        }

        return Storage::disk('local')->response(
            $path,
            data_get($entity->metadata, 'logo_name', basename($path)),
            ['Content-Type' => data_get($entity->metadata, 'logo_mime', 'image/png')],
        );
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = $request->user();
        $entity = $user?->primaryEntity();

        abort_unless($user && $entity && $this->canManageEntityProfile($user, $entity), 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->getKey())],
            'current_password' => ['required_with:password', 'nullable', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $payload = [
            'email' => $validated['email'],
            'phone' => PhoneNumber::normalize($validated['phone']),
        ];

        if (filled($validated['password'] ?? null)) {
            $payload['password'] = $validated['password'];
        }

        $user->forceFill($payload)->save();

        return redirect()
            ->route('profile.show')
            ->with('status', __('app.profile.account_updated'));
    }

    public function updateContact(Request $request): RedirectResponse
    {
        $user = $request->user();
        $entity = $user?->primaryEntity();

        abort_unless($user && $entity && $this->canManageEntityProfile($user, $entity), 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'logo' => $this->logoValidationRules(),
        ], $this->logoValidationMessages());

        $logo = $validated['logo'] ?? null;

        DB::transaction(function () use ($entity, $validated, $logo): void {
            $metadata = $entity->metadata ?? [];

            if ($logo instanceof UploadedFile) {
                $previousLogoPath = data_get($metadata, 'logo_path');

                if (is_string($previousLogoPath) && Storage::disk('local')->exists($previousLogoPath)) {
                    Storage::disk('local')->delete($previousLogoPath);
                }

                $metadata['logo_path'] = $logo->store('registration-logos/'.$entity->registration_type, 'local');
                $metadata['logo_name'] = $logo->getClientOriginalName();
                $metadata['logo_mime'] = $logo->getClientMimeType();
                $metadata['logo_size'] = $logo->getSize();
            }

            $metadata['address'] = $validated['address'] ?: null;
            $metadata['website_url'] = $validated['website_url'] ?: null;
            $metadata['description'] = $validated['description'] ?: null;

            $entity->forceFill([
                'email' => $validated['email'],
                'phone' => PhoneNumber::normalize($validated['phone']),
                'metadata' => $metadata,
            ])->save();
        });

        return redirect()
            ->route('profile.show')
            ->with('status', __('app.profile.contact_updated'));
    }

    public function storeOfficialChangeRequest(Request $request): RedirectResponse
    {
        $user = $request->user();
        $entity = $user?->primaryEntity();

        abort_unless($user && $entity && $this->canManageEntityProfile($user, $entity), 403);

        if (ProfileChangeRequests::pending($entity)) {
            return redirect()
                ->route('profile.show')
                ->withErrors(['profile' => __('app.profile.official_change_pending_exists')]);
        }

        $validated = $request->validate([
            ...ProfileChangeRequests::validationRules($entity),
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $changes = ProfileChangeRequests::buildChanges($entity, $validated);

        if ($changes === []) {
            return redirect()
                ->route('profile.show')
                ->withErrors(['profile' => __('app.profile.official_change_no_changes')]);
        }

        $changeRequest = [
            'id' => (string) Str::uuid(),
            'status' => 'pending',
            'requested_at' => now()->toDateTimeString(),
            'requested_by_user_id' => $user->getKey(),
            'requested_by_name' => $user->displayName(),
            'note' => $validated['note'] ?? null,
            'fields' => $changes,
        ];

        $metadata = $entity->metadata ?? [];
        $metadata['profile_change_requests'] = collect((array) ($metadata['profile_change_requests'] ?? []))
            ->push($changeRequest)
            ->values()
            ->all();

        $entity->forceFill(['metadata' => $metadata])->save();

        $this->notifyProfileChangeReviewers($entity, $changeRequest);

        return redirect()
            ->route('profile.show')
            ->with('status', __('app.profile.official_change_submitted'));
    }

    public function signForeignProducerDeclaration(Request $request, string $application): RedirectResponse
    {
        $user = $request->user();
        $entity = $user->primaryEntity();

        abort_unless($user && $entity && $this->isInternationalProducerUser($user), 403);

        $record = FilmApplication::query()
            ->where('entity_id', $entity->getKey())
            ->where(fn (Builder $query): Builder => $query
                ->whereKey($application)
                ->orWhere('code', $application)
            )
            ->firstOrFail();

        abort_unless($this->applicationIsLinkedToInternationalProducer($record, $user), 403);

        $request->validate([
            'declaration_accepted' => ['accepted'],
        ]);

        $metadata = $record->metadata ?? [];
        data_set($metadata, 'international.account.declaration', [
            'accepted' => true,
            'signed_at' => now()->toIso8601String(),
            'signed_by_user_id' => $user->getKey(),
            'signed_by_name' => $user->displayName(),
            'signed_by_email' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        $record->forceFill(['metadata' => $metadata])->save();

        return redirect()
            ->route('profile.show', ['variant' => 'foreign_producer'])
            ->with('status', __('app.profile.foreign_producer_declaration_saved'));
    }

    private function statusClass(?string $status): string
    {
        return match ($status) {
            'submitted', 'under_review' => 'warning',
            'needs_clarification', 'rejected' => 'danger',
            'approved' => 'success',
            default => 'secondary',
        };
    }

    private function canManageEntityProfile(User $user, Entity $entity): bool
    {
        if ($this->isInternationalProducerUser($user)) {
            return false;
        }

        return $entity->users()
            ->whereKey($user->getKey())
            ->wherePivot('status', 'active')
            ->wherePivot('is_primary', true)
            ->exists();
    }

    private function notifyProfileChangeReviewers(Entity $entity, array $changeRequest): void
    {
        User::query()
            ->where('status', 'active')
            ->whereHas('entities.group', fn (Builder $query): Builder => $query->whereIn('code', ['rfc', 'admins']))
            ->get()
            ->unique('id')
            ->each(function (User $reviewer) use ($entity, $changeRequest): void {
                $reviewer->notify(new InboxMessageNotification(
                    typeKey: 'profile_change_requested',
                    title: __('app.profile.notifications.change_requested_title'),
                    body: __('app.profile.notifications.change_requested_body', ['entity' => $entity->displayName()]),
                    routeName: 'admin.entities.show',
                    routeParameters: ['entity' => $entity->getKey()],
                    meta: [
                        'entity_id' => $entity->getKey(),
                        'profile_change_request_id' => $changeRequest['id'] ?? null,
                    ],
                ));
            });
    }

    /**
     * @return array<int, string>
     */
    private function logoValidationRules(): array
    {
        return ['nullable', 'file', 'image', 'mimes:png', 'mimetypes:image/png', 'max:2048'];
    }

    /**
     * @return array<string, string>
     */
    private function logoValidationMessages(): array
    {
        return [
            'logo.image' => __('app.auth.logo_png_only'),
            'logo.mimes' => __('app.auth.logo_png_only'),
            'logo.mimetypes' => __('app.auth.logo_png_only'),
            'logo.max' => __('app.auth.logo_max_size'),
        ];
    }

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function registrationReviewHistory(Entity $entity): array
    {
        $history = collect((array) data_get($entity->metadata, 'review_history', []))
            ->sortByDesc('reviewed_at')
            ->values();

        if ($history->isEmpty()) {
            return [$history, []];
        }

        $reviewerNames = User::query()
            ->withTrashed()
            ->whereIn('id', $history->pluck('reviewed_by_user_id')->filter()->unique()->all())
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->displayName()])
            ->all();

        return [$history, $reviewerNames];
    }

    private function latestRegistrationNotification(User $user): ?DatabaseNotification
    {
        return $user->notifications()
            ->whereIn('data->type_key', ['registration_approved', 'registration_completion_requested', 'registration_rejected'])
            ->latest()
            ->first();
    }

    private function isInternationalProducerUser(User $user): bool
    {
        return $user->registration_type === 'international_producer';
    }

    /**
     * @param  Collection<int, FilmApplication>  $applications
     * @return Collection<int, FilmApplication>
     */
    private function applicationsLinkedToInternationalProducer(Collection $applications, User $user): Collection
    {
        return $applications
            ->filter(fn (FilmApplication $application): bool => $this->applicationIsLinkedToInternationalProducer($application, $user))
            ->values();
    }

    private function applicationIsLinkedToInternationalProducer(FilmApplication $application, User $user): bool
    {
        return (int) data_get($application->metadata, 'international.account.user_id') === $user->getKey();
    }

    /**
     * @param  Collection<int, ScoutingRequest>  $scoutingRequests
     * @return Collection<int, ScoutingRequest>
     */
    private function scoutingRequestsLinkedToInternationalProducer(Collection $scoutingRequests, User $user): Collection
    {
        return $scoutingRequests
            ->filter(fn (ScoutingRequest $request): bool => (int) data_get($request->metadata, 'international.account.user_id') === $user->getKey())
            ->values();
    }
}
