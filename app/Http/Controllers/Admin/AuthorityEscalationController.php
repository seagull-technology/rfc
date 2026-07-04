<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationAuthorityApproval;
use App\Models\ApplicationStatusHistory;
use App\Models\Entity;
use App\Models\User;
use App\Notifications\InboxMessageNotification;
use App\Services\AuthorityEscalationService;
use App\Support\CsvExport;
use App\Support\NotificationRecipients;
use App\Support\WorkflowMessageMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuthorityEscalationController extends Controller
{
    public function __construct(
        private readonly AuthorityEscalationService $authorityEscalationService,
    ) {
    }

    public function index(): View
    {
        $authorities = $this->authorityEscalationService->manageableAuthorities();
        $now = now();

        return view('admin.authority-escalations.index', [
            'authorities' => $authorities->map(function (Entity $authority) use ($now): array {
                $liveApprovals = ApplicationAuthorityApproval::query()
                    ->with(['assignedTo'])
                    ->where('entity_id', $authority->getKey())
                    ->whereIn('status', ['pending', 'in_review'])
                    ->latest()
                    ->get();

                $signals = $liveApprovals->map(fn (ApplicationAuthorityApproval $approval): array => [
                    'approval' => $approval,
                    'signal' => $this->authorityEscalationService->signalForApproval($approval, $now),
                ]);

                return [
                    'entity' => $authority,
                    'settings' => $this->authorityEscalationService->settingsForEntity($authority),
                    'approval_codes' => $authority->authorityApprovalCodes(),
                    'live_approvals' => $liveApprovals->count(),
                    'due_soon_approvals' => $signals->where('signal.is_due_soon', true)->count(),
                    'overdue_approvals' => $signals->where('signal.is_overdue', true)->count(),
                    'escalated_approvals' => $liveApprovals->whereNotNull('escalated_at')->count(),
                ];
            })->values(),
            'escalationUsers' => $this->authorityEscalationService->escalationAssignableUsers(),
            'escalationRoles' => $this->authorityEscalationService->escalationAssignableRoles(),
            'stats' => [
                'authorities' => $authorities->count(),
                'configured' => $authorities->filter(fn (Entity $authority): bool => $authority->authorityResponseTimeDays() !== null)->count(),
                'live_approvals' => ApplicationAuthorityApproval::query()
                    ->whereIn('status', ['pending', 'in_review'])
                    ->whereHas('entity.group', fn (Builder $query) => $query->where('code', 'authorities'))
                    ->count(),
                'overdue_approvals' => ApplicationAuthorityApproval::query()
                    ->with('entity.group')
                    ->whereIn('status', ['pending', 'in_review'])
                    ->whereHas('entity.group', fn (Builder $query) => $query->where('code', 'authorities'))
                    ->get()
                    ->filter(fn (ApplicationAuthorityApproval $approval): bool => $this->authorityEscalationService->signalForApproval($approval, $now)['is_overdue'])
                    ->count(),
                'due_soon_approvals' => ApplicationAuthorityApproval::query()
                    ->with('entity.group')
                    ->whereIn('status', ['pending', 'in_review'])
                    ->whereHas('entity.group', fn (Builder $query) => $query->where('code', 'authorities'))
                    ->get()
                    ->filter(fn (ApplicationAuthorityApproval $approval): bool => $this->authorityEscalationService->signalForApproval($approval, $now)['is_due_soon'])
                    ->count(),
            ],
        ]);
    }

    public function report(Request $request): View
    {
        return view('admin.authority-escalations.report', $this->buildReportPayload($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $payload = $this->buildReportPayload($request);

        $rows = collect($payload['rows'])->map(fn (array $row): array => [
            $row['entity']->displayName(),
            $row['response_time_days'] ?? __('app.dashboard.not_available'),
            implode(', ', $row['approval_labels']),
            $row['live_approvals'],
            $row['due_soon_live_approvals'],
            $row['overdue_live_approvals'],
            $row['escalated_live_approvals'],
            $row['shared_inbox_live_approvals'],
            $row['assigned_live_approvals'],
            $row['approvals_in_window'],
            $row['resolved_in_window'],
            $row['recent_escalations_count'],
            $row['average_resolution_hours'] !== null ? number_format($row['average_resolution_hours'], 1) : '',
            $row['oldest_live_age_hours'] !== null ? number_format($row['oldest_live_age_hours'], 1) : '',
            $row['last_escalated_at']?->format('Y-m-d H:i') ?? '',
        ]);

        return CsvExport::download(
            filename: 'authority-escalation-report-'.now()->format('Ymd-His').'.csv',
            headers: [
                __('app.admin.authority_escalations.authority'),
                __('app.admin.authority_escalations.response_time_days'),
                __('app.admin.authority_escalations.approval_codes'),
                __('app.admin.authority_escalations.metrics.live_approvals'),
                __('app.admin.authority_escalations.metrics.due_soon_approvals'),
                __('app.admin.authority_escalations.metrics.overdue_approvals'),
                __('app.admin.authority_escalations.metrics.escalated_approvals'),
                __('app.admin.authority_escalations.shared_inbox_live'),
                __('app.admin.authority_escalations.assigned_live'),
                __('app.admin.authority_escalations.approvals_in_window'),
                __('app.admin.authority_escalations.resolved_in_window'),
                __('app.admin.authority_escalations.recent_escalations'),
                __('app.admin.authority_escalations.average_resolution_hours'),
                __('app.admin.authority_escalations.oldest_live_age'),
                __('app.admin.authority_escalations.last_escalated_at'),
            ],
            rows: $rows,
        );
    }

    public function update(Request $request, string $entity): RedirectResponse
    {
        $authority = Entity::query()
            ->whereHas('group', fn (Builder $query) => $query->where('code', 'authorities'))
            ->findOrFail($entity);

        $validUserIds = $this->authorityEscalationService->escalationAssignableUsers()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $validRoleNames = $this->authorityEscalationService->escalationAssignableRoles()->pluck('name')->all();

        $request->merge([
            'response_time_days' => $this->normalizeLocalizedInteger($request->input('response_time_days')),
        ]);

        $validated = $request->validate([
            'response_time_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'escalation_user_ids' => ['nullable', 'array'],
            'escalation_user_ids.*' => ['integer', 'exists:users,id'],
            'escalation_role_names' => ['nullable', 'array'],
            'escalation_role_names.*' => ['string', Rule::in($validRoleNames)],
        ]);

        $selectedUserIds = array_values(array_map('intval', $validated['escalation_user_ids'] ?? []));
        $invalidUserIds = array_diff($selectedUserIds, $validUserIds);

        if ($invalidUserIds !== []) {
            throw ValidationException::withMessages([
                'escalation_user_ids' => __('validation.in', ['attribute' => 'escalation_user_ids']),
            ]);
        }

        $metadata = $authority->metadata ?? [];

        data_set($metadata, 'authority_sla.response_time_days', filled($validated['response_time_days'] ?? null) ? (int) $validated['response_time_days'] : null);
        data_set($metadata, 'authority_sla.escalation_user_ids', $selectedUserIds);
        data_set($metadata, 'authority_sla.escalation_role_names', array_values($validated['escalation_role_names'] ?? []));
        data_set($metadata, 'authority_sla.updated_by_user_id', $request->user()?->getKey());
        data_set($metadata, 'authority_sla.updated_at', now()->toIso8601String());

        $authority->forceFill([
            'metadata' => $metadata,
        ])->save();

        return redirect()
            ->route('admin.authority-escalations.index')
            ->with('status', __('app.admin.authority_escalations.saved', ['authority' => $authority->displayName()]));
    }

    public function bulkAssign(Request $request, string $entity): RedirectResponse
    {
        $authority = Entity::query()
            ->whereHas('group', fn (Builder $query) => $query->where('code', 'authorities'))
            ->findOrFail($entity);

        $validated = $request->validate([
            'approval_ids' => ['required', 'array', 'min:1'],
            'approval_ids.*' => ['integer'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assignment_note' => ['nullable', 'string', 'max:500'],
            'window' => ['nullable', 'string'],
        ]);

        $approvalIds = collect($validated['approval_ids'])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $approvals = ApplicationAuthorityApproval::query()
            ->with(['application', 'assignedTo', 'entity'])
            ->where('entity_id', $authority->getKey())
            ->whereIn('id', $approvalIds)
            ->whereIn('status', ['pending', 'in_review'])
            ->get();

        if ($approvals->count() !== $approvalIds->count()) {
            return redirect()
                ->route('admin.authority-escalations.report', $this->reportRouteParameters($authority, (string) ($validated['window'] ?? '30')))
                ->withErrors(['approval_ids' => __('app.admin.authority_escalations.bulk_assign_invalid_selection')]);
        }

        $assignableUsers = $approvals->isNotEmpty()
            ? $this->authorityApprovalAssignableUsers($approvals->first())
            : collect();

        if ($assignableUsers->isEmpty()) {
            return redirect()
                ->route('admin.authority-escalations.report', $this->reportRouteParameters($authority, (string) ($validated['window'] ?? '30')))
                ->withErrors(['approval_ids' => __('app.admin.applications.authority_assignment_unavailable')]);
        }

        $assignedUserId = filled($validated['assigned_user_id'] ?? null)
            ? (int) $validated['assigned_user_id']
            : null;

        if ($assignedUserId !== null && ! $assignableUsers->contains(fn (User $user): bool => $user->getKey() === $assignedUserId)) {
            return redirect()
                ->route('admin.authority-escalations.report', $this->reportRouteParameters($authority, (string) ($validated['window'] ?? '30')))
                ->withErrors(['assigned_user_id' => __('app.admin.applications.authority_assignment_invalid')]);
        }

        $assignedUser = $assignedUserId
            ? $assignableUsers->first(fn (User $user): bool => $user->getKey() === $assignedUserId)
            : null;

        $updatedCount = 0;

        foreach ($approvals as $approval) {
            $application = $approval->application;

            if (! $application) {
                continue;
            }

            if ((int) ($approval->assigned_user_id ?? 0) === (int) ($assignedUserId ?? 0)) {
                continue;
            }

            $previousAssignedUserId = $approval->assigned_user_id;
            $previousAssignedUserName = $approval->assignedTo?->displayName()
                ?? ($previousAssignedUserId ? User::query()->find($previousAssignedUserId)?->displayName() : null);

            $approval->forceFill([
                'assigned_user_id' => $assignedUserId,
                'assigned_at' => $assignedUserId ? now() : null,
            ])->save();

            $application->statusHistory()->create([
                'user_id' => $request->user()?->getKey(),
                'status' => $application->status,
                'note' => __('app.workflow.history.authority_reassigned', [
                    'authority' => $approval->localizedAuthority(),
                    'assignee' => $assignedUser?->displayName() ?? __('app.admin.applications.authority_shared_inbox'),
                ]),
                'metadata' => [
                    'type' => 'authority_reassigned',
                    'approval_id' => $approval->getKey(),
                    'authority_code' => $approval->authority_code,
                    'authority_label' => $approval->localizedAuthority(),
                    'from_user_id' => $previousAssignedUserId,
                    'from_user_name' => $previousAssignedUserName,
                    'to_user_id' => $assignedUser?->getKey(),
                    'to_user_name' => $assignedUser?->displayName(),
                    'reason' => $validated['assignment_note'] ?: null,
                    'bulk' => true,
                ],
                'happened_at' => now(),
            ]);

            if ($assignedUser) {
                NotificationRecipients::except(collect([$assignedUser]), $request->user()?->getKey())
                    ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                        typeKey: 'authority_approval_requested',
                        title: $application->project_name,
                        body: __('app.notifications.authority_approval_requested_body', [
                            'authority' => $approval->localizedAuthority(),
                            'code' => $application->code,
                        ]),
                        routeName: 'authority.applications.show',
                        routeParameters: ['application' => $application->getKey()],
                        meta: WorkflowMessageMetadata::application($application),
                    )));
            }

            $updatedCount++;
        }

        return redirect()
            ->route('admin.authority-escalations.report', $this->reportRouteParameters($authority, (string) ($validated['window'] ?? '30')))
            ->with('status', __('app.admin.authority_escalations.bulk_assign_saved', [
                'count' => $updatedCount,
                'authority' => $authority->displayName(),
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportPayload(Request $request): array
    {
        $filters = $this->reportFilters($request);
        $now = now();
        $authorities = $this->authorityEscalationService->manageableAuthorities();
        $filteredAuthorities = $filters['authority_id'] !== null
            ? $authorities->where('id', $filters['authority_id'])->values()
            : $authorities->values();

        $authorityApprovals = ApplicationAuthorityApproval::query()
            ->with(['application', 'entity.group', 'assignedTo'])
            ->whereHas('entity.group', fn (Builder $query) => $query->where('code', 'authorities'))
            ->when($filters['authority_id'] !== null, fn (Builder $query) => $query->where('entity_id', $filters['authority_id']))
            ->latest()
            ->get();

        $liveApprovals = $authorityApprovals
            ->whereIn('status', ['pending', 'in_review'])
            ->values();

        $windowStart = $filters['window_days'] !== null ? $now->copy()->subDays($filters['window_days']) : null;
        $windowApprovals = $authorityApprovals
            ->filter(function (ApplicationAuthorityApproval $approval) use ($windowStart): bool {
                if ($windowStart === null) {
                    return true;
                }

                return optional($approval->created_at)?->greaterThanOrEqualTo($windowStart)
                    || optional($approval->decided_at)?->greaterThanOrEqualTo($windowStart)
                    || optional($approval->escalated_at)?->greaterThanOrEqualTo($windowStart);
            })
            ->values();
        $selectedAuthority = $filters['authority_id'] !== null
            ? $authorities->firstWhere('id', $filters['authority_id'])
            : null;
        $selectedAuthorityDetails = $selectedAuthority
            ? $this->selectedAuthorityDetails(
                authority: $selectedAuthority,
                liveApprovals: $liveApprovals,
                windowApprovals: $windowApprovals,
                asOf: $now,
            )
            : null;

        $rows = $filteredAuthorities
            ->map(function (Entity $authority) use ($liveApprovals, $windowApprovals, $now): array {
                $authorityLiveApprovals = $liveApprovals
                    ->where('entity_id', $authority->getKey())
                    ->values();
                $authorityWindowApprovals = $windowApprovals
                    ->where('entity_id', $authority->getKey())
                    ->values();
                $liveSignals = $authorityLiveApprovals->map(fn (ApplicationAuthorityApproval $approval): array => [
                    'approval' => $approval,
                    'signal' => $this->authorityEscalationService->signalForApproval($approval, $now),
                ]);
                $resolvedWindowApprovals = $authorityWindowApprovals
                    ->filter(fn (ApplicationAuthorityApproval $approval): bool => $approval->decided_at !== null)
                    ->values();
                $resolutionHours = $resolvedWindowApprovals
                    ->filter(fn (ApplicationAuthorityApproval $approval): bool => $approval->created_at !== null)
                    ->map(fn (ApplicationAuthorityApproval $approval): float => round($approval->created_at->diffInHours($approval->decided_at), 1));
                $oldestLiveAgeHours = $authorityLiveApprovals
                    ->filter(fn (ApplicationAuthorityApproval $approval): bool => $approval->created_at !== null)
                    ->map(fn (ApplicationAuthorityApproval $approval): float => round($approval->created_at->diffInHours($now), 1))
                    ->max();

                return [
                    'entity' => $authority,
                    'settings' => $this->authorityEscalationService->settingsForEntity($authority),
                    'response_time_days' => $authority->authorityResponseTimeDays(),
                    'approval_labels' => collect($authority->authorityApprovalCodes())
                        ->map(fn (string $code): string => __('app.applications.required_approval_options.'.$code))
                        ->values()
                        ->all(),
                    'live_approvals' => $authorityLiveApprovals->count(),
                    'due_soon_live_approvals' => $liveSignals->where('signal.is_due_soon', true)->count(),
                    'overdue_live_approvals' => $liveSignals->where('signal.is_overdue', true)->count(),
                    'escalated_live_approvals' => $authorityLiveApprovals->whereNotNull('escalated_at')->count(),
                    'shared_inbox_live_approvals' => $authorityLiveApprovals->whereNull('assigned_user_id')->count(),
                    'assigned_live_approvals' => $authorityLiveApprovals->whereNotNull('assigned_user_id')->count(),
                    'approvals_in_window' => $authorityWindowApprovals->count(),
                    'resolved_in_window' => $resolvedWindowApprovals->count(),
                    'recent_escalations_count' => $authorityWindowApprovals->whereNotNull('escalated_at')->count(),
                    'average_resolution_hours' => $resolutionHours->isNotEmpty() ? round($resolutionHours->avg(), 1) : null,
                    'oldest_live_age_hours' => $oldestLiveAgeHours !== null ? (float) $oldestLiveAgeHours : null,
                    'last_escalated_at' => $authorityWindowApprovals
                        ->whereNotNull('escalated_at')
                        ->sortByDesc('escalated_at')
                        ->first()?->escalated_at,
                ];
            })
            ->sortByDesc(fn (array $row): array => [
                $row['overdue_live_approvals'],
                $row['due_soon_live_approvals'],
                $row['escalated_live_approvals'],
                $row['live_approvals'],
                $row['recent_escalations_count'],
            ])
            ->values();

        $recentOverdueApprovals = $liveApprovals
            ->map(function (ApplicationAuthorityApproval $approval) use ($now): ?array {
                $signal = $this->authorityEscalationService->signalForApproval($approval, $now);

                if (! $signal['is_overdue']) {
                    return null;
                }

                return [
                    'approval' => $approval,
                    'signal' => $signal,
                    'overdue_hours' => $signal['due_at'] !== null ? round($signal['due_at']->diffInHours($now), 1) : 0.0,
                ];
            })
            ->filter()
            ->sortByDesc('overdue_hours')
            ->take(10)
            ->values();

        $recentDueSoonApprovals = $liveApprovals
            ->map(function (ApplicationAuthorityApproval $approval) use ($now): ?array {
                $signal = $this->authorityEscalationService->signalForApproval($approval, $now);

                if (! $signal['is_due_soon']) {
                    return null;
                }

                return [
                    'approval' => $approval,
                    'signal' => $signal,
                    'remaining_hours' => $signal['due_at'] !== null ? round($now->diffInHours($signal['due_at']), 1) : null,
                ];
            })
            ->filter()
            ->sortBy('remaining_hours')
            ->take(10)
            ->values();

        $averageResolutionHours = $rows
            ->pluck('average_resolution_hours')
            ->filter(fn ($value): bool => $value !== null)
            ->avg();

        return [
            'filters' => [
                ...$filters,
                'window_options' => ['7', '30', '90', 'all'],
            ],
            'availableAuthorities' => $authorities,
            'rows' => $rows,
            'recentOverdueApprovals' => $recentOverdueApprovals,
            'recentDueSoonApprovals' => $recentDueSoonApprovals,
            'selectedAuthority' => $selectedAuthority,
            'selectedAuthorityDetails' => $selectedAuthorityDetails,
            'stats' => [
                'authorities' => $rows->count(),
                'configured' => $rows->filter(fn (array $row): bool => $row['response_time_days'] !== null)->count(),
                'live_approvals' => $rows->sum('live_approvals'),
                'due_soon_approvals' => $rows->sum('due_soon_live_approvals'),
                'overdue_approvals' => $rows->sum('overdue_live_approvals'),
                'escalated_approvals' => $rows->sum('escalated_live_approvals'),
                'recent_escalations' => $rows->sum('recent_escalations_count'),
                'average_resolution_hours' => $averageResolutionHours !== null ? round($averageResolutionHours, 1) : null,
            ],
        ];
    }

    /**
     * @return array{window:string,window_days:?int,authority_id:?int}
     */
    private function reportFilters(Request $request): array
    {
        $window = (string) $request->query('window', '30');

        if (! in_array($window, ['7', '30', '90', 'all'], true)) {
            $window = '30';
        }

        $authorityId = $request->query('authority');
        $authorityId = is_numeric($authorityId) ? (int) $authorityId : null;

        if ($authorityId !== null && ! Entity::query()
            ->whereHas('group', fn (Builder $query) => $query->where('code', 'authorities'))
            ->whereKey($authorityId)
            ->exists()) {
            $authorityId = null;
        }

        return [
            'window' => $window,
            'window_days' => $window === 'all' ? null : (int) $window,
            'authority_id' => $authorityId,
        ];
    }

    /**
     * @return array{liveQueue:\Illuminate\Support\Collection<int, array<string, mixed>>,escalationHistory:\Illuminate\Support\Collection<int, \App\Models\ApplicationStatusHistory>}
     */
    private function selectedAuthorityDetails(
        Entity $authority,
        \Illuminate\Support\Collection $liveApprovals,
        \Illuminate\Support\Collection $windowApprovals,
        \Carbon\CarbonInterface $asOf,
    ): array {
        $selectedLiveApprovals = $liveApprovals
            ->where('entity_id', $authority->getKey())
            ->sortByDesc(function (ApplicationAuthorityApproval $approval) use ($asOf): array {
                $signal = $this->authorityEscalationService->signalForApproval($approval, $asOf);

                return [
                    $signal['is_overdue'] ? 1 : 0,
                    $signal['is_due_soon'] ? 1 : 0,
                    $signal['is_escalated'] ? 1 : 0,
                    $approval->created_at?->timestamp ?? 0,
                ];
            })
            ->values()
            ->map(function (ApplicationAuthorityApproval $approval) use ($asOf): array {
                $signal = $this->authorityEscalationService->signalForApproval($approval, $asOf);

                return [
                    'approval' => $approval,
                    'signal' => $signal,
                    'live_age_hours' => $approval->created_at !== null ? round($approval->created_at->diffInHours($asOf), 1) : null,
                    'assignableDelegates' => $this->authorityApprovalAssignableUsers($approval),
                ];
            });

        $applicationIds = $windowApprovals
            ->where('entity_id', $authority->getKey())
            ->pluck('application_id')
            ->filter()
            ->unique()
            ->values();

        $escalationHistory = $applicationIds->isEmpty()
            ? collect()
            : ApplicationStatusHistory::query()
                ->with(['application', 'user'])
                ->whereIn('application_id', $applicationIds->all())
                ->latest('happened_at')
                ->get()
                ->filter(function (ApplicationStatusHistory $event) use ($authority): bool {
                    return data_get($event->metadata, 'type') === 'authority_escalated'
                        && in_array((string) data_get($event->metadata, 'authority_code'), $authority->authorityApprovalCodes(), true);
                })
                ->take(10)
                ->values();

        return [
            'liveQueue' => $selectedLiveApprovals,
            'escalationHistory' => $escalationHistory,
            'bulkAssignableDelegates' => $selectedLiveApprovals->pluck('assignableDelegates')->first() ?? collect(),
        ];
    }

    /**
     * @return array{window:string,authority:int}
     */
    private function reportRouteParameters(Entity $authority, string $window): array
    {
        $window = in_array($window, ['7', '30', '90', 'all'], true) ? $window : '30';

        return [
            'window' => $window,
            'authority' => $authority->getKey(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function authorityApprovalAssignableUsers(ApplicationAuthorityApproval $approval): \Illuminate\Support\Collection
    {
        $entity = $approval->entity;

        if (! $entity || $entity->group?->code !== 'authorities') {
            return collect();
        }

        return $entity->activeMembers()
            ->filter(fn (User $user): bool => $this->isAuthorityApprovalAssignableUser($user, $entity))
            ->values();
    }

    private function isAuthorityApprovalAssignableUser(User $user, Entity $entity): bool
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($entity->getKey());

        try {
            return $user->can('applications.view.entity');
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }

    private function normalizeLocalizedInteger(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtr((string) $value, [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]);

        $normalized = trim($normalized);

        return $normalized === '' ? null : $normalized;
    }
}
