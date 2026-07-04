<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application as FilmApplication;
use App\Models\ApplicationAnnexSubmission;
use App\Models\ApplicationAuthorityApproval;
use App\Models\ApplicationCorrespondence;
use App\Models\ApplicationDocument;
use App\Models\ApplicationOfficialLetter;
use App\Models\Entity;
use App\Models\Permit;
use App\Models\User;
use App\Notifications\FinalDecisionIssuedNotification;
use App\Notifications\InboxMessageNotification;
use App\Services\ApprovalRoutingService;
use App\Services\AuthorityApprovalNotificationService;
use App\Services\AuthorityEscalationService;
use App\Services\FinalDecisionDeliveryService;
use App\Support\AdminApplicantResponseState;
use App\Support\AdminWorkflowState;
use App\Support\CsvExport;
use App\Support\NotificationRecipients;
use App\Support\WorkflowMessageMetadata;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationManagementController extends Controller
{
    public function __construct(
        private readonly FinalDecisionDeliveryService $finalDecisionDeliveryService,
        private readonly AuthorityEscalationService $authorityEscalationService,
        private readonly ApprovalRoutingService $approvalRoutingService,
        private readonly AuthorityApprovalNotificationService $authorityApprovalNotificationService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $this->directoryFilters($request);
        $applications = $this->directoryQuery($filters)
            ->newestFirst()
            ->get();
        $checkpointStats = $applications
            ->map(fn (FilmApplication $application): string => AdminWorkflowState::applicationCheckpoint($application)['key'])
            ->countBy();
        $overdueAuthorityCount = $applications
            ->filter(fn (FilmApplication $application): bool => $application->authorityApprovals
                ->whereIn('status', ['pending', 'in_review'])
                ->contains(fn (ApplicationAuthorityApproval $approval): bool => $this->authorityEscalationService->signalForApproval($approval)['is_overdue']))
            ->count();
        $dueSoonAuthorityCount = $applications
            ->filter(fn (FilmApplication $application): bool => $application->authorityApprovals
                ->whereIn('status', ['pending', 'in_review'])
                ->contains(fn (ApplicationAuthorityApproval $approval): bool => $this->authorityEscalationService->signalForApproval($approval)['is_due_soon']))
            ->count();
        $applicationAuthoritySignals = $applications
            ->mapWithKeys(fn (FilmApplication $application): array => [
                $application->getKey() => $application->authorityApprovals
                    ->whereIn('status', ['pending', 'in_review'])
                    ->map(function (ApplicationAuthorityApproval $approval): array {
                        $signal = $this->authorityEscalationService->signalForApproval($approval);

                        return [
                            'label' => $signal['label'],
                            'is_due_soon' => $signal['is_due_soon'],
                            'is_overdue' => $signal['is_overdue'],
                            'is_escalated' => $signal['is_escalated'],
                            'authority' => $approval->localizedAuthority(),
                        ];
                    })
                    ->filter(fn (array $signal): bool => filled($signal['label']))
                    ->sortByDesc(fn (array $signal): int => ((int) $signal['is_overdue'] * 10) + ((int) $signal['is_due_soon'] * 7) + ((int) $signal['is_escalated'] * 5))
                    ->values(),
            ]);

        return view('admin.applications.index', [
            'applications' => $applications,
            'openApplications' => $applications->whereNotIn('status', ['approved', 'rejected'])->values(),
            'closedApplications' => $applications->whereIn('status', ['approved', 'rejected'])->values(),
            'applicationAuthoritySignals' => $applicationAuthoritySignals,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
            ],
            'stats' => [
                'total' => $applications->count(),
                'submitted' => $applications->where('status', 'submitted')->count(),
                'under_review' => $applications->where('status', 'under_review')->count(),
                'resolved' => $applications->whereIn('status', ['approved', 'rejected'])->count(),
                'needs_admin_review' => $checkpointStats->get('needs_admin_review', 0),
                'waiting_on_applicant' => $checkpointStats->get('waiting_on_applicant', 0),
                'waiting_authorities' => $checkpointStats->get('waiting_authorities', 0),
                'due_soon_authorities' => $dueSoonAuthorityCount,
                'overdue_authorities' => $overdueAuthorityCount,
                'ready_final_decision' => $checkpointStats->get('ready_final_decision', 0),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->directoryFilters($request);
        $applications = $this->directoryQuery($filters)
            ->newestFirst()
            ->get();

        $rows = $applications->map(fn (FilmApplication $application): array => [
            $application->code,
            $application->project_name,
            $application->entity?->displayName() ?? __('app.dashboard.not_available'),
            $application->submittedBy?->displayName() ?? __('app.dashboard.not_available'),
            $application->localizedStatus(),
            $application->current_stage ? __('app.workflow.stages.'.$application->current_stage) : __('app.dashboard.not_available'),
            $application->submitted_at?->format('Y-m-d H:i') ?? '',
            $application->assignedTo?->displayName() ?? '',
        ])->all();

        return CsvExport::download(
            filename: 'applications-directory-'.now()->format('Ymd-His').'.csv',
            headers: [
                __('app.admin.applications.application'),
                __('app.applications.project_name'),
                __('app.admin.applications.entity'),
                __('app.admin.applications.applicant'),
                __('app.applications.status'),
                __('app.workflow.current_stage'),
                __('app.admin.scouting.submitted_at'),
                __('app.workflow.assigned_reviewer'),
            ],
            rows: $rows,
        );
    }

    public function show(string $application): View
    {
        $record = $this->findApplication($application);
        $record->load([
            'statusHistory.user',
            'documents.uploadedBy',
            'documents.reviewedBy',
            'correspondences.createdBy',
            'officialLetters.createdBy',
            'officialLetters.updatedBy',
            'officialLetters.targetEntity',
            'officialLetters.authorityApproval.entity',
            'annexSubmissions.submittedBy',
            'annexSubmissions.reviewedBy',
            'wrapReport.submittedBy',
        ]);
        $reviewers = $this->workflowAssignableUsers();
        $authorityApprovals = $record->authorityApprovals()->with(['reviewedBy', 'assignedTo', 'entity'])->get();
        $authorityApprovalSignals = $authorityApprovals
            ->mapWithKeys(fn (ApplicationAuthorityApproval $approval): array => [
                $approval->getKey() => $this->authorityEscalationService->signalForApproval($approval),
            ]);
        $rfcDecisionUserIds = collect([
            data_get($record->metadata ?? [], 'rfc_decision.decided_by_user_id'),
            data_get($record->metadata ?? [], 'rfc_decision.facilitation_issued_by_user_id'),
            $record->reviewed_by_user_id,
            $record->final_decision_issued_by_user_id,
        ])->filter()->unique()->values();

        $officialLetterTargets = $this->officialLetterRouteTargets($record);
        $officialLetters = $this->ensureOfficialLetterSerialNumbers($record, $officialLetterTargets);

        return view('admin.applications.show', [
            'application' => $record,
            'statusHistory' => $record->statusHistory,
            'authorityApprovals' => $authorityApprovals,
            'authorityApprovalSignals' => $authorityApprovalSignals,
            'authorityApprovalSignalStats' => [
                'live' => $authorityApprovals->whereIn('status', ['pending', 'in_review'])->count(),
                'overdue' => $authorityApprovalSignals->where('is_overdue', true)->count(),
                'escalated' => $authorityApprovals->whereNotNull('escalated_at')->count(),
            ],
            'authorityApprovalDelegates' => $authorityApprovals
                ->mapWithKeys(fn (ApplicationAuthorityApproval $approval): array => [
                    $approval->getKey() => $this->authorityApprovalAssignableUsers($approval),
                ]),
            'authorityAuditTrail' => $record->statusHistory
                ->filter(fn ($event): bool => in_array((string) data_get($event->metadata, 'type'), ['authority_status_updated', 'authority_reassigned', 'authority_sla_warning', 'authority_escalated'], true))
                ->values(),
            'reviewers' => $reviewers,
            'documents' => $record->documents,
            'correspondences' => $record->correspondences,
            'officialLetters' => $officialLetters,
            'officialLetterApprovals' => $officialLetterTargets,
            'annexSubmissions' => $record->annexSubmissions,
            'applicantResponse' => AdminApplicantResponseState::application($record),
            'rfcDecisionUsers' => $rfcDecisionUserIds->isEmpty()
                ? collect()
                : User::query()->whereIn('id', $rfcDecisionUserIds)->get()->keyBy('id'),
            'wrapReport' => $record->wrapReport,
            'wrapReportAvailable' => $record->wrapReportIsAvailable(),
            'wrapReportOptions' => $this->wrapReportOptions(),
        ]);
    }

    public function review(Request $request, string $application): RedirectResponse
    {
        $record = $this->findApplication($application);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'returned', 'rejected'])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(in_array($request->input('decision'), ['returned', 'rejected'], true))],
        ]);

        $decision = $validated['decision'];
        $note = ($validated['note'] ?? null) ?: null;
        $actorId = $request->user()?->getKey();
        $decidedAt = now();

        DB::transaction(function () use ($record, $decision, $note, $actorId, $decidedAt): void {
            $metadata = $record->metadata ?? [];
            $existingDecision = (array) data_get($metadata, 'rfc_decision', []);

            data_set($metadata, 'rfc_decision', [
                ...$existingDecision,
                'status' => $decision,
                'note' => $note,
                'decided_at' => $decidedAt->toISOString(),
                'decided_by_user_id' => $actorId,
                'facilitation_issued_at' => $decision === 'accepted'
                    ? data_get($existingDecision, 'facilitation_issued_at')
                    : null,
                'facilitation_issued_by_user_id' => $decision === 'accepted'
                    ? data_get($existingDecision, 'facilitation_issued_by_user_id')
                    : null,
            ]);

            $record->forceFill([
                'status' => match ($decision) {
                    'accepted' => 'under_review',
                    'returned' => 'needs_clarification',
                    'rejected' => 'rejected',
                },
                'current_stage' => match ($decision) {
                    'accepted' => 'rfc_facilitation',
                    'returned' => 'clarification',
                    'rejected' => 'rejected',
                },
                'review_note' => $note,
                'reviewed_at' => $decidedAt,
                'reviewed_by_user_id' => $actorId,
                'final_decision_status' => $decision === 'rejected' ? 'rejected' : null,
                'final_decision_note' => $decision === 'rejected' ? $note : null,
                'final_decision_issued_at' => $decision === 'rejected' ? $decidedAt : null,
                'final_decision_issued_by_user_id' => $decision === 'rejected' ? $actorId : null,
                'final_permit_number' => null,
                'final_letter_path' => null,
                'final_letter_name' => null,
                'final_letter_mime_type' => null,
                'metadata' => $metadata,
            ])->save();

            if ($decision === 'rejected') {
                Permit::query()->where('application_id', $record->getKey())->delete();
            }

            $record->statusHistory()->create([
                'user_id' => $actorId,
                'status' => $record->status,
                'note' => $note ?: __('app.rfc_decision.history.'.$decision),
                'metadata' => [
                    'type' => 'rfc_decision_recorded',
                    'rfc_decision' => $decision,
                ],
                'happened_at' => $decidedAt,
            ]);
        });

        NotificationRecipients::except(NotificationRecipients::applicationApplicants($record), $request->user()?->getKey())
            ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'application_status_changed',
                title: $record->project_name,
                body: __('app.notifications.application_status_changed_body', [
                    'status' => __('app.rfc_decision.statuses.'.$decision),
                ]),
                routeName: 'applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: WorkflowMessageMetadata::application($record),
            )));

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.applications.review_saved'));
    }

    public function issueFacilitationLetter(Request $request, string $application): RedirectResponse
    {
        $record = $this->findApplication($application);
        $actorId = $request->user()?->getKey();

        if (data_get($record->metadata ?? [], 'rfc_decision.status') !== 'accepted') {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['rfc_decision' => __('app.rfc_decision.accept_required')]);
        }

        $issuedApplicantLetters = DB::transaction(function () use ($record, $actorId): Collection {
            $targets = $this->officialLetterRouteTargets($record);
            $letters = $this->prepareOfficialLettersForTargets($record, $targets, $actorId);
            $applicantLetter = $this->prepareApplicantFacilitationLetter($record, $actorId);
            $record->refresh();

            $metadata = $record->metadata ?? [];
            data_set($metadata, 'rfc_decision.status', 'accepted');
            data_set($metadata, 'rfc_decision.facilitation_issued_at', now()->toISOString());
            data_set($metadata, 'rfc_decision.facilitation_issued_by_user_id', $actorId);
            data_set(
                $metadata,
                'requirements.required_approvals',
                $targets->pluck('approval_code')->filter()->unique()->values()->all(),
            );
            data_set(
                $metadata,
                'requirements.official_book_targets',
                $targets
                    ->map(fn (array $target): array => [
                        'approval_code' => $target['approval_code'],
                        'target_entity_id' => $target['target_entity_id'],
                        'target_entity_name' => $target['target_entity_name'],
                    ])
                    ->values()
                    ->all(),
            );

            $record->forceFill([
                'status' => 'under_review',
                'current_stage' => 'rfc_facilitation',
                'metadata' => $metadata,
            ])->save();

            $record->statusHistory()->create([
                'user_id' => $actorId,
                'status' => $record->status,
                'note' => __('app.rfc_decision.history.facilitation_issued'),
                'metadata' => [
                    'type' => 'rfc_facilitation_issued',
                    'official_letter_ids' => $letters->push($applicantLetter)->pluck('id')->all(),
                    'applicant_facilitation_letter_id' => $applicantLetter->getKey(),
                    'official_book_targets' => $targets->values()->all(),
                ],
                'happened_at' => now(),
            ]);

            return collect([$applicantLetter->fresh(['targetEntity'])]);
        });

        $this->notifyOfficialLettersIssued($record->fresh(['entity', 'submittedBy']), $issuedApplicantLetters, $actorId);

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.rfc_decision.facilitation_issued'));
    }

    public function reviewAnnexSubmission(Request $request, string $application, string $annexSubmission): RedirectResponse
    {
        $record = $this->findApplication($application);
        $submission = $record->annexSubmissions()->findOrFail($annexSubmission);

        $validated = $request->validate([
            'decision' => ['required', Rule::in([
                ApplicationAnnexSubmission::STATUS_APPROVED,
                ApplicationAnnexSubmission::STATUS_RETURNED,
                ApplicationAnnexSubmission::STATUS_REJECTED,
            ])],
            'note' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(in_array($request->input('decision'), [
                    ApplicationAnnexSubmission::STATUS_RETURNED,
                    ApplicationAnnexSubmission::STATUS_REJECTED,
                ], true)),
            ],
        ]);

        if (! $submission->isPending()) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['annex_submission' => __('app.annex_submissions.already_reviewed')]);
        }

        $decision = $validated['decision'];
        $note = ($validated['note'] ?? null) ?: null;
        $actorId = $request->user()?->getKey();
        $reviewedAt = now();
        $preparedLetterIds = collect();

        DB::transaction(function () use ($record, $submission, $decision, $note, $actorId, $reviewedAt, &$preparedLetterIds): void {
            $submission->forceFill([
                'status' => $decision,
                'reviewed_by_user_id' => $actorId,
                'reviewed_at' => $reviewedAt,
                'review_note' => $note,
            ])->save();

            $metadata = $record->metadata ?? [];
            data_set($metadata, 'applicant_annex_submission', [
                'id' => $submission->getKey(),
                'status' => $decision,
                'submitted_at' => $submission->submitted_at?->toDateTimeString(),
                'submitted_by_user_id' => $submission->submitted_by_user_id,
                'reviewed_at' => $reviewedAt->toDateTimeString(),
                'reviewed_by_user_id' => $actorId,
                'review_note' => $note,
            ]);

            if ($decision === ApplicationAnnexSubmission::STATUS_APPROVED) {
                data_set($metadata, 'annex', $submission->payload ?? []);
            }

            $record->forceFill(['metadata' => $metadata])->save();

            if ($decision === ApplicationAnnexSubmission::STATUS_APPROVED
                && filled(data_get($metadata, 'rfc_decision.facilitation_issued_at'))
            ) {
                $record->refresh();
                $targets = $this->officialLetterRouteTargets($record);
                $preparedLetters = $this->prepareOfficialLettersForTargets(
                    $record,
                    $targets,
                    $actorId,
                    createRenewalForIssued: true,
                );
                $preparedLetterIds = $preparedLetters->pluck('id');

                $refreshedMetadata = $record->metadata ?? [];
                data_set(
                    $refreshedMetadata,
                    'requirements.required_approvals',
                    $targets->pluck('approval_code')->filter()->unique()->values()->all(),
                );
                data_set(
                    $refreshedMetadata,
                    'requirements.official_book_targets',
                    $targets
                        ->map(fn (array $target): array => [
                            'approval_code' => $target['approval_code'],
                            'target_entity_id' => $target['target_entity_id'],
                            'target_entity_name' => $target['target_entity_name'],
                        ])
                        ->values()
                        ->all(),
                );
                data_set($refreshedMetadata, 'applicant_annex_submission', data_get($metadata, 'applicant_annex_submission'));
                $record->forceFill(['metadata' => $refreshedMetadata])->save();
            }

            $record->statusHistory()->create([
                'user_id' => $actorId,
                'status' => $record->status,
                'note' => $note ?: __('app.annex_submissions.history.'.$decision),
                'metadata' => [
                    'type' => 'applicant_annex_reviewed',
                    'annex_submission_id' => $submission->getKey(),
                    'decision' => $decision,
                    'official_letter_ids' => $preparedLetterIds->values()->all(),
                ],
                'happened_at' => $reviewedAt,
            ]);
        });

        $record->refresh()->loadMissing(['entity', 'submittedBy']);
        NotificationRecipients::except(NotificationRecipients::applicationApplicants($record), $actorId)
            ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'application_annex_reviewed',
                title: $record->project_name,
                body: __('app.notifications.application_annex_reviewed_body', [
                    'code' => $record->code,
                    'status' => __('app.annex_submissions.statuses.'.$decision),
                ]),
                routeName: 'applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: [
                    ...WorkflowMessageMetadata::application($record),
                    'application_id' => $record->getKey(),
                    'annex_submission_id' => $submission->getKey(),
                    'notification_highlight_active' => true,
                    'notification_highlight_title' => __('app.notifications.application_annex_reviewed_title'),
                    'notification_highlight_summary' => __('app.notifications.application_annex_reviewed_summary', [
                        'status' => __('app.annex_submissions.statuses.'.$decision),
                    ]),
                    'notification_highlight_class' => $decision === ApplicationAnnexSubmission::STATUS_APPROVED ? 'success' : 'warning',
                ],
            )));

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.annex_submissions.review_saved'));
    }

    public function finalize(Request $request, string $application): RedirectResponse
    {
        $record = $this->findApplication($application);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('decision') === 'rejected')],
            'permit_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf($request->input('decision') === 'approved'),
                Rule::unique('permits', 'permit_number')->ignore($record->permit?->getKey()),
            ],
            'final_letter' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx'],
        ]);

        if (! $record->canBeFinallyDecided()) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['decision' => __('app.final_decision.not_ready')]);
        }

        if ($validated['decision'] === 'approved' && $record->hasRejectedAuthorityApproval()) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['decision' => __('app.final_decision.approval_blocked')]);
        }

        $existingLetterPath = $record->final_letter_path;
        $letterPath = $record->final_letter_path;
        $letterName = $record->final_letter_name;
        $letterMime = $record->final_letter_mime_type;

        if ($request->file('final_letter')) {
            $letterPath = $request->file('final_letter')->store('application-final-decisions/'.$record->getKey(), 'local');
            $letterName = $request->file('final_letter')->getClientOriginalName();
            $letterMime = $request->file('final_letter')->getClientMimeType();

            if ($existingLetterPath && $existingLetterPath !== $letterPath && Storage::disk('local')->exists($existingLetterPath)) {
                Storage::disk('local')->delete($existingLetterPath);
            }
        }

        $wasApprovedPermit = $record->permit;

        DB::transaction(function () use ($record, $request, $validated, $letterPath, $letterName, $letterMime, $wasApprovedPermit): void {
            $record->forceFill([
                'status' => $validated['decision'],
                'current_stage' => $validated['decision'],
                'review_note' => $validated['note'] ?: null,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $request->user()?->getKey(),
                'final_decision_status' => $validated['decision'],
                'final_decision_note' => $validated['note'] ?: null,
                'final_decision_issued_at' => now(),
                'final_decision_issued_by_user_id' => $request->user()?->getKey(),
                'final_permit_number' => $validated['decision'] === 'approved' ? ($validated['permit_number'] ?: null) : null,
                'final_letter_path' => $letterPath,
                'final_letter_name' => $letterName,
                'final_letter_mime_type' => $letterMime,
            ])->save();

            if ($validated['decision'] === 'approved') {
                $permit = Permit::query()->updateOrCreate(
                    ['application_id' => $record->getKey()],
                    [
                        'entity_id' => $record->entity_id,
                        'permit_number' => $record->final_permit_number,
                        'status' => 'active',
                        'issued_at' => $record->final_decision_issued_at,
                        'issued_by_user_id' => $request->user()?->getKey(),
                        'note' => $record->final_decision_note,
                        'metadata' => [
                            'project_name' => $record->project_name,
                            'application_code' => $record->code,
                        ],
                    ],
                );

                $this->finalDecisionDeliveryService->logIssuance(
                    permit: $permit,
                    application: $record,
                    actor: $request->user(),
                    action: $wasApprovedPermit ? 'reissued' : 'issued',
                );
            } else {
                Permit::query()->where('application_id', $record->getKey())->delete();
            }

            $record->statusHistory()->create([
                'user_id' => $request->user()?->getKey(),
                'status' => $validated['decision'],
                'note' => __('app.final_decision.history.issued', [
                    'decision' => __('app.statuses.'.$validated['decision']),
                    'permit_number' => $record->final_permit_number ?: __('app.dashboard.not_available'),
                ]),
                'metadata' => [
                    'permit_number' => $record->final_permit_number,
                    'final_letter_name' => $record->final_letter_name,
                ],
                'happened_at' => now(),
            ]);
        });

        $record->refresh();
        $this->notifyFinalDecisionStakeholders($record, $request->user()?->getKey());

        if ($record->final_decision_status === 'approved' && $record->permit) {
            $this->finalDecisionDeliveryService->deliver($record, $record->permit, $request->user());
        }

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.final_decision.saved'));
    }

    public function assign(Request $request, string $application): RedirectResponse
    {
        $record = $this->findApplication($application);

        $validated = $request->validate([
            'assigned_to_user_id' => ['required', 'exists:users,id'],
        ]);

        $assignee = User::query()->findOrFail($validated['assigned_to_user_id']);

        if (! $this->isWorkflowAssignableUser($assignee)) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['assigned_to_user_id' => __('app.workflow.invalid_assignee')]);
        }

        $record->forceFill([
            'assigned_to_user_id' => $assignee->getKey(),
            'assigned_at' => now(),
        ])->save();

        $record->statusHistory()->create([
            'user_id' => $request->user()?->getKey(),
            'status' => $record->status,
            'note' => __('app.workflow.history.assigned_to', ['name' => $assignee->displayName()]),
            'metadata' => [
                'type' => 'rfc_internal_assignment',
                'assigned_to_user_id' => $assignee->getKey(),
            ],
            'happened_at' => now(),
        ]);

        NotificationRecipients::except(collect([$assignee]), $request->user()?->getKey())
            ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'application_assignment',
                title: $record->project_name,
                body: __('app.notifications.application_assignment_body', [
                    'code' => $record->code,
                ]),
                routeName: 'admin.applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: WorkflowMessageMetadata::application($record),
            )));

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.workflow.assigned'));
    }

    public function updateApproval(Request $request, string $application, ApplicationAuthorityApproval $approval): RedirectResponse
    {
        $record = $this->findApplication($application);
        abort_unless($approval->application_id === $record->getKey(), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_review', 'approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (
            in_array($validated['status'], ['approved', 'rejected'], true)
            && ! $request->user()?->can('applications.approve')
        ) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['status' => __('app.workflow.approver_required')]);
        }

        $approval->forceFill([
            'status' => $validated['status'],
            'note' => $validated['note'] ?: null,
            'reviewed_by_user_id' => $request->user()?->getKey(),
            'decided_at' => in_array($validated['status'], ['approved', 'rejected'], true) ? now() : null,
        ])->save();

        $statuses = $record->authorityApprovals()->pluck('status');

        $record->forceFill([
            'current_stage' => $statuses->contains('pending') || $statuses->contains('in_review')
                ? 'authority_review'
                : 'final_decision',
        ])->save();

        $record->statusHistory()->create([
            'user_id' => $request->user()?->getKey(),
            'status' => $record->status,
            'note' => __('app.workflow.history.authority_updated', [
                'authority' => $approval->localizedAuthority(),
                'status' => $approval->localizedStatus(),
            ]),
            'metadata' => [
                'type' => 'authority_status_updated',
                'approval_id' => $approval->getKey(),
                'authority_code' => $approval->authority_code,
                'authority_label' => $approval->localizedAuthority(),
                'approval_status' => $approval->status,
                'approval_status_label' => $approval->localizedStatus(),
                'assigned_user_id' => $approval->assigned_user_id,
                'assigned_user_name' => $approval->assignedTo?->displayName(),
                'reason' => $validated['note'] ?: null,
            ],
            'happened_at' => now(),
        ]);

        NotificationRecipients::except(NotificationRecipients::authorityUsersForApproval($approval), $request->user()?->getKey())
            ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'authority_approval_updated',
                title: $record->project_name,
                body: __('app.notifications.authority_approval_updated_body', [
                    'authority' => $approval->localizedAuthority(),
                    'status' => $approval->localizedStatus(),
                ]),
                routeName: 'authority.applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: WorkflowMessageMetadata::application($record),
            )));

        NotificationRecipients::except(NotificationRecipients::applicationApplicants($record), $request->user()?->getKey())
            ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'authority_approval_updated',
                title: $record->project_name,
                body: __('app.notifications.authority_approval_updated_body', [
                    'authority' => $approval->localizedAuthority(),
                    'status' => $approval->localizedStatus(),
                ]),
                routeName: 'applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: WorkflowMessageMetadata::application($record),
            )));

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.workflow.approval_updated'));
    }

    public function assignApproval(Request $request, string $application, ApplicationAuthorityApproval $approval): RedirectResponse
    {
        $record = $this->findApplication($application);
        abort_unless($approval->application_id === $record->getKey(), 404);

        if (! in_array($approval->status, ['pending', 'in_review'], true)) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['assigned_user_id' => __('app.admin.applications.authority_assignment_locked')]);
        }

        $assignableUsers = $this->authorityApprovalAssignableUsers($approval);

        if ($assignableUsers->isEmpty()) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['assigned_user_id' => __('app.admin.applications.authority_assignment_unavailable')]);
        }

        $validated = $request->validate([
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assignment_note' => ['nullable', 'string', 'max:500'],
        ]);

        $assignedUserId = filled($validated['assigned_user_id'] ?? null)
            ? (int) $validated['assigned_user_id']
            : null;

        if ($assignedUserId !== null && ! $assignableUsers->contains(fn (User $user): bool => $user->getKey() === $assignedUserId)) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['assigned_user_id' => __('app.admin.applications.authority_assignment_invalid')]);
        }

        $previousAssignedUserId = $approval->assigned_user_id;
        $previousAssignedUserName = $approval->assignedTo?->displayName()
            ?? ($previousAssignedUserId ? User::query()->find($previousAssignedUserId)?->displayName() : null);

        $assignedUser = $assignedUserId
            ? $assignableUsers->first(fn (User $user): bool => $user->getKey() === $assignedUserId)
            : null;

        $approval->forceFill([
            'assigned_user_id' => $assignedUserId,
            'assigned_at' => $assignedUserId ? now() : null,
        ])->save();

        $record->statusHistory()->create([
            'user_id' => $request->user()?->getKey(),
            'status' => $record->status,
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
            ],
            'happened_at' => now(),
        ]);

        if ($assignedUser) {
            NotificationRecipients::except(collect([$assignedUser]), $request->user()?->getKey())
                ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                    typeKey: 'authority_approval_requested',
                    title: $record->project_name,
                    body: __('app.notifications.authority_approval_requested_body', [
                        'authority' => $approval->localizedAuthority(),
                        'code' => $record->code,
                    ]),
                    routeName: 'authority.applications.show',
                    routeParameters: ['application' => $record->getKey()],
                    meta: WorkflowMessageMetadata::application($record),
                )));
        }

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', $assignedUser
                ? __('app.admin.applications.authority_assignment_saved')
                : __('app.admin.applications.authority_assignment_cleared'));
    }

    public function reviewDocument(Request $request, string $application, string $document): RedirectResponse
    {
        $record = $this->findApplication($application);
        $documentRecord = $this->findDocument($document, $record);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['submitted', 'needs_revision', 'approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $documentRecord->forceFill([
            'status' => $validated['status'],
            'note' => $validated['note'] ?: null,
            'reviewed_by_user_id' => $request->user()?->getKey(),
            'reviewed_at' => now(),
        ])->save();

        if ($validated['status'] === 'needs_revision') {
            $record->forceFill([
                'status' => 'needs_clarification',
                'current_stage' => 'clarification',
                'review_note' => $validated['note'] ?: $record->review_note,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $request->user()?->getKey(),
            ])->save();
        }

        $record->statusHistory()->create([
            'user_id' => $request->user()?->getKey(),
            'status' => $record->status,
            'note' => __('app.documents.history.reviewed', [
                'title' => $documentRecord->title,
                'status' => $documentRecord->localizedStatus(),
            ]),
            'happened_at' => now(),
        ]);

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.documents.review_saved'));
    }

    public function downloadDocument(string $application, string $document): StreamedResponse|RedirectResponse
    {
        $record = $this->findApplication($application);
        $documentRecord = $this->findDocument($document, $record);

        if (! Storage::disk('local')->exists($documentRecord->file_path)) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['document' => __('app.documents.file_missing')]);
        }

        return Storage::disk('local')->download($documentRecord->file_path, $documentRecord->original_name);
    }

    public function downloadApprovalAttachment(string $application, ApplicationAuthorityApproval $approval): StreamedResponse|RedirectResponse
    {
        $record = $this->findApplication($application);
        abort_unless($approval->application_id === $record->getKey(), 404);

        if (! $approval->response_attachment_path || ! Storage::disk('local')->exists($approval->response_attachment_path)) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['response_attachment' => __('app.approvals.response_book_missing')]);
        }

        return Storage::disk('local')->download(
            $approval->response_attachment_path,
            $approval->response_attachment_name ?: basename($approval->response_attachment_path),
        );
    }

    public function storeCorrespondence(Request $request, string $application): RedirectResponse
    {
        $record = $this->findApplication($application);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png'],
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;

        if ($request->file('attachment')) {
            $attachmentPath = $request->file('attachment')->store('application-correspondence/'.$record->getKey(), 'local');
            $attachmentName = $request->file('attachment')->getClientOriginalName();
            $attachmentMime = $request->file('attachment')->getClientMimeType();
        }

        $message = $record->correspondences()->create([
            'created_by_user_id' => $request->user()?->getKey(),
            'sender_type' => 'admin',
            'sender_name' => $request->user()?->displayName() ?? __('app.admin.navigation.dashboard'),
            'subject' => $validated['subject'] ?: null,
            'message' => $validated['message'],
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime_type' => $attachmentMime,
        ]);

        $record->statusHistory()->create([
            'user_id' => $request->user()?->getKey(),
            'status' => $record->status,
            'note' => __('app.correspondence.history.admin_message'),
            'happened_at' => now(),
        ]);

        NotificationRecipients::except(NotificationRecipients::applicationApplicants($record), $request->user()?->getKey())
            ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'application_correspondence',
                title: $message->subject ?: __('app.contact_center.request_fallback_title', ['code' => $record->code]),
                body: str($message->message)->limit(140)->toString(),
                routeName: 'applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: WorkflowMessageMetadata::application($record),
            )));

        NotificationRecipients::except(NotificationRecipients::authorityUsersForApplication($record), $request->user()?->getKey())
            ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'application_correspondence',
                title: $message->subject ?: __('app.contact_center.request_fallback_title', ['code' => $record->code]),
                body: str($message->message)->limit(140)->toString(),
                routeName: 'authority.applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: [
                    ...WorkflowMessageMetadata::application($record),
                    'notification_highlight_active' => true,
                    'notification_highlight_title' => __('app.notifications.authority_request_update_title'),
                    'notification_highlight_summary' => __('app.notifications.authority_request_update_summary', [
                        'item' => $message->subject ?: __('app.correspondence.tab'),
                    ]),
                    'notification_highlight_class' => 'primary',
                ],
            )));

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.correspondence.sent'));
    }

    public function storeOfficialLetter(Request $request, string $application): RedirectResponse
    {
        $record = $this->findApplication($application);
        $validated = $this->validateOfficialLetter($request);
        $letterTargets = $this->officialLetterRouteTargets($record);

        if ($letterTargets->isEmpty()) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['official_letters' => __('app.official_letters.no_routed_authorities')]);
        }

        $letters = DB::transaction(function () use ($record, $validated, $letterTargets, $request): Collection {
            $firstSequence = $record->officialLetters()->count() + 1;

            $createdLetters = $letterTargets
                ->map(fn (array $target, int $index): ApplicationOfficialLetter => $record->officialLetters()->create([
                    ...$validated,
                    'application_authority_approval_id' => null,
                    'target_entity_id' => $target['target_entity_id'],
                    'recipient_type' => 'authority',
                    'created_by_user_id' => $request->user()?->getKey(),
                    'updated_by_user_id' => $request->user()?->getKey(),
                    'serial_number' => $this->officialLetterSerialNumber($record, $firstSequence + $index),
                    'status' => 'draft',
                    'issued_at' => null,
                ]))
                ->values();

            $record->statusHistory()->create([
                'user_id' => $request->user()?->getKey(),
                'status' => $record->status,
                'note' => __('app.official_letters.history.created', ['subject' => $validated['subject']]),
                'happened_at' => now(),
                'metadata' => [
                    'type' => 'official_letter_created',
                    'official_letter_id' => $createdLetters->first()?->getKey(),
                    'official_letter_ids' => $createdLetters->pluck('id')->all(),
                ],
            ]);

            return $createdLetters;
        });

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.official_letters.created'));
    }

    public function updateOfficialLetter(Request $request, string $application, string $letter): RedirectResponse
    {
        $record = $this->findApplication($application);
        $officialLetter = $this->findOfficialLetter($letter, $record);
        $validated = $this->validateOfficialLetter($request);

        if ($officialLetter->status === 'issued') {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['official_letters' => __('app.official_letters.sent_cannot_edit')]);
        }

        $officialLetter->forceFill([
            ...$validated,
            'updated_by_user_id' => $request->user()?->getKey(),
        ])->save();

        $record->statusHistory()->create([
            'user_id' => $request->user()?->getKey(),
            'status' => $record->status,
            'note' => __('app.official_letters.history.updated', ['subject' => $officialLetter->subject]),
            'happened_at' => now(),
            'metadata' => ['type' => 'official_letter_updated', 'official_letter_id' => $officialLetter->getKey()],
        ]);

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.official_letters.updated'));
    }

    public function sendOfficialLetter(Request $request, string $application, string $letter): RedirectResponse
    {
        $record = $this->findApplication($application);
        $officialLetter = $this->findOfficialLetter($letter, $record);

        if ($officialLetter->status === 'issued') {
            return redirect()
                ->route('admin.applications.show', $record)
                ->with('status', __('app.official_letters.already_sent'));
        }

        if (blank($officialLetter->recipient_name) || blank($officialLetter->subject) || blank($officialLetter->body)) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['official_letters' => __('app.official_letters.send_requires_complete')]);
        }

        if ($officialLetter->isApplicantLetter()) {
            DB::transaction(function () use ($record, $officialLetter, $request): void {
                $officialLetter->forceFill([
                    'updated_by_user_id' => $request->user()?->getKey(),
                    'status' => 'issued',
                    'issued_at' => $officialLetter->issued_at ?: now(),
                ])->save();

                $record->statusHistory()->create([
                    'user_id' => $request->user()?->getKey(),
                    'status' => $record->status,
                    'note' => __('app.official_letters.history.sent_to_applicant', ['subject' => $officialLetter->subject]),
                    'happened_at' => now(),
                    'metadata' => [
                        'type' => 'official_letter_sent_to_applicant',
                        'official_letter_id' => $officialLetter->getKey(),
                    ],
                ]);
            });

            $this->notifyOfficialLettersIssued($record->fresh(['entity', 'submittedBy']), collect([$officialLetter->fresh(['targetEntity'])]), $request->user()?->getKey());

            return redirect()
                ->route('admin.applications.show', $record)
                ->with('status', __('app.official_letters.sent_to_applicant'));
        }

        if (! $officialLetter->target_entity_id) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['official_letters' => __('app.official_letters.send_requires_target')]);
        }

        $approval = DB::transaction(function () use ($record, $officialLetter, $request): ApplicationAuthorityApproval {
            $approval = $this->authorityApprovalForOfficialLetter($record, $officialLetter);

            $officialLetter->forceFill([
                'application_authority_approval_id' => $approval->getKey(),
                'updated_by_user_id' => $request->user()?->getKey(),
                'status' => 'issued',
                'issued_at' => $officialLetter->issued_at ?: now(),
            ])->save();

            $record->forceFill([
                'status' => 'under_review',
                'current_stage' => 'authority_review',
            ])->save();

            $record->statusHistory()->create([
                'user_id' => $request->user()?->getKey(),
                'status' => $record->status,
                'note' => __('app.official_letters.history.sent', ['subject' => $officialLetter->subject]),
                'happened_at' => now(),
                'metadata' => [
                    'type' => 'official_letter_sent',
                    'official_letter_id' => $officialLetter->getKey(),
                    'authority_approval_id' => $approval->getKey(),
                ],
            ]);

            return $approval->fresh(['application.entity', 'entity', 'assignedTo']);
        });

        $this->authorityApprovalNotificationService->notifyRecipientsForApproval($approval, $request->user()?->getKey());
        $this->notifyOfficialLettersIssued($record->fresh(['entity', 'submittedBy']), collect([$officialLetter->fresh(['authorityApproval.entity', 'targetEntity'])]), $request->user()?->getKey());

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.official_letters.sent'));
    }

    public function downloadCorrespondenceAttachment(string $application, string $correspondence): StreamedResponse|RedirectResponse
    {
        $record = $this->findApplication($application);
        $message = $this->findCorrespondence($correspondence, $record);

        if (! $message->attachment_path || ! Storage::disk('local')->exists($message->attachment_path)) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['correspondence' => __('app.correspondence.file_missing')]);
        }

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name ?: basename($message->attachment_path));
    }

    public function downloadFinalLetter(string $application): StreamedResponse|RedirectResponse
    {
        $record = $this->findApplication($application);

        if (! $record->final_letter_path || ! Storage::disk('local')->exists($record->final_letter_path)) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['final_letter' => __('app.final_decision.file_missing')]);
        }

        return Storage::disk('local')->download($record->final_letter_path, $record->final_letter_name ?: basename($record->final_letter_path));
    }

    public function printFinalLetter(string $application): View
    {
        $record = $this->findApplication($application);

        abort_unless($record->finalDecisionIssued(), 404);

        return view('letters.final-decision', [
            'application' => $record,
            'entity' => $record->entity,
            'issuedBy' => $record->finalDecisionIssuedBy,
            'permit' => $record->permit,
            'isAdminView' => true,
        ]);
    }

    public function printOfficialLetter(string $application, string $letter): View
    {
        $record = $this->findApplication($application);
        $officialLetter = $this->findOfficialLetter($letter, $record);

        $officialLetter->loadMissing(['targetEntity', 'authorityApproval.entity']);

        return view('letters.official-letter', [
            'application' => $record,
            'letter' => $officialLetter,
            'isAdminView' => true,
        ]);
    }

    private function findApplication(string $application): FilmApplication
    {
        return FilmApplication::query()
            ->with(['entity.group', 'submittedBy', 'reviewedBy', 'assignedTo', 'finalDecisionIssuedBy', 'authorityApprovals.reviewedBy', 'authorityApprovals.entity', 'permit'])
            ->findOrFail($application);
    }

    /**
     * @return array{q:string,status:string}
     */
    private function directoryFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'draft', 'submitted', 'under_review', 'needs_clarification', 'approved', 'rejected'])],
        ]);

        return [
            'q' => $validated['q'] ?? '',
            'status' => $validated['status'] ?? 'all',
        ];
    }

    /**
     * @param  array{q:string,status:string}  $filters
     */
    private function directoryQuery(array $filters): Builder
    {
        $query = FilmApplication::query()->with(['entity', 'submittedBy', 'reviewedBy', 'assignedTo']);
        $query->with(['authorityApprovals.entity']);
        $query->withMax([
            'statusHistory as last_clarification_at' => fn (Builder $builder): Builder => $builder->where('status', 'needs_clarification'),
        ], 'happened_at');
        $query->withMax([
            'correspondences as last_applicant_correspondence_at' => fn (Builder $builder): Builder => $builder->where('sender_type', 'applicant'),
        ], 'created_at');
        $query->withMax('documents as last_applicant_document_at', 'created_at');

        if (filled($filters['q'])) {
            $search = trim($filters['q']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('code', 'like', '%'.$search.'%')
                    ->orWhere('project_name', 'like', '%'.$search.'%')
                    ->orWhereHas('entity', fn (Builder $entityQuery): Builder => $entityQuery->where('name_en', 'like', '%'.$search.'%')->orWhere('name_ar', 'like', '%'.$search.'%'))
                    ->orWhereHas('submittedBy', fn (Builder $userQuery): Builder => $userQuery->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
            });
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function findDocument(string $document, FilmApplication $application): ApplicationDocument
    {
        return ApplicationDocument::query()
            ->where('application_id', $application->getKey())
            ->findOrFail($document);
    }

    private function findCorrespondence(string $correspondence, FilmApplication $application): ApplicationCorrespondence
    {
        return ApplicationCorrespondence::query()
            ->where('application_id', $application->getKey())
            ->findOrFail($correspondence);
    }

    private function findOfficialLetter(string $letter, FilmApplication $application): ApplicationOfficialLetter
    {
        return ApplicationOfficialLetter::query()
            ->where('application_id', $application->getKey())
            ->findOrFail($letter);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOfficialLetter(Request $request): array
    {
        $validated = $request->validate([
            'letter_date' => ['nullable', 'date'],
            'recipient_prefix' => ['nullable', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['string', 'max:255'],
        ]);

        $validated['letter_date'] = $validated['letter_date'] ?? null;
        $validated['recipient_prefix'] = ($validated['recipient_prefix'] ?? null) ?: null;
        $validated['attachments'] = array_values($validated['attachments'] ?? []);

        return $validated;
    }

    /**
     * @return Collection<int, array{approval_code:string,approval_label:string,target_entity_id:int|null,target_entity_name:string|null}>
     */
    private function officialLetterRouteTargets(FilmApplication $application): Collection
    {
        $routes = $this->approvalRoutingService->explainRoutesForApplication($application);
        $targetEntities = Entity::query()
            ->whereIn('id', $routes->pluck('target_entity_id')->filter()->unique()->all())
            ->get()
            ->keyBy(fn (Entity $entity): int => $entity->getKey());

        return $routes
            ->map(function (array $route) use ($targetEntities): array {
                $approvalCode = (string) $route['approval_code'];
                $targetEntityId = filled($route['target_entity_id']) ? (int) $route['target_entity_id'] : null;
                $targetEntity = $targetEntityId ? $targetEntities->get($targetEntityId) : null;
                $approvalLabel = __('app.applications.required_approval_options.'.$approvalCode);

                return [
                    'approval_code' => $approvalCode,
                    'approval_label' => $approvalLabel,
                    'target_entity_id' => $targetEntityId,
                    'target_entity_name' => $targetEntity?->displayName() ?? ($route['target_entity_name'] ?: $approvalLabel),
                ];
            })
            ->unique(fn (array $target): string => $this->officialLetterTargetKey($target))
            ->values();
    }

    /**
     * @param  Collection<int, array{approval_code:string,approval_label:string,target_entity_id:int|null,target_entity_name:string|null}>  $targets
     * @return Collection<int, ApplicationOfficialLetter>
     */
    private function prepareOfficialLettersForTargets(FilmApplication $application, Collection $targets, ?int $actorId, bool $createRenewalForIssued = false): Collection
    {
        $existingLetters = $application
            ->officialLetters()
            ->with('targetEntity')
            ->get()
            ->groupBy(fn (ApplicationOfficialLetter $letter): string => $this->officialLetterKeyForLetter($letter));
        $reservedSerials = $existingLetters
            ->flatMap(fn (Collection $letters): Collection => $letters->pluck('serial_number'))
            ->filter()
            ->values();

        return $targets
            ->map(function (array $target, int $index) use ($application, $actorId, $existingLetters, $createRenewalForIssued, $reservedSerials): ApplicationOfficialLetter {
                $key = $this->officialLetterTargetKey($target);
                $targetLetters = $existingLetters->get($key, collect());
                $existingLetter = $createRenewalForIssued
                    ? $targetLetters->first(fn (ApplicationOfficialLetter $letter): bool => $letter->status !== 'issued')
                    : $targetLetters->first();
                $serialNumber = $this->unusedOfficialLetterSerialNumber($application, $index + 1, $reservedSerials);

                if ($existingLetter instanceof ApplicationOfficialLetter) {
                    if (blank($existingLetter->serial_number)) {
                        $existingLetter->forceFill([
                            'serial_number' => $serialNumber,
                            'updated_by_user_id' => $actorId,
                        ])->save();
                        $reservedSerials->push($serialNumber);
                    }

                    return $existingLetter;
                }

                if ($createRenewalForIssued && $targetLetters->isNotEmpty()) {
                    $serialNumber = $this->unusedOfficialLetterSerialNumber(
                        $application,
                        $application->officialLetters()->count() + $index + 1,
                        $reservedSerials,
                    );
                }

                $reservedSerials->push($serialNumber);

                return $application->officialLetters()->create([
                    'application_authority_approval_id' => null,
                    'target_entity_id' => $target['target_entity_id'],
                    'recipient_type' => 'authority',
                    'created_by_user_id' => $actorId,
                    'updated_by_user_id' => $actorId,
                    'letter_date' => now()->toDateString(),
                    'serial_number' => $serialNumber,
                    'recipient_prefix' => app()->getLocale() === 'ar' ? 'عطوفة' : 'H.E.',
                    'recipient_name' => $target['target_entity_name'] ?: $target['approval_label'],
                    'subject' => __('app.official_letters.default_subject', ['code' => $application->code]),
                    'body' => __('app.official_letters.default_body', [
                        'entity' => $target['target_entity_name'] ?: $target['approval_label'],
                        'project' => $application->project_name,
                        'code' => $application->code,
                    ]),
                    'attachments' => array_values(__('app.official_letters.attachment_options')),
                    'status' => 'draft',
                    'issued_at' => null,
                ]);
            })
            ->values();
    }

    private function prepareApplicantFacilitationLetter(FilmApplication $application, ?int $actorId): ApplicationOfficialLetter
    {
        $application->loadMissing('entity');
        $recipientName = $application->entity?->displayName() ?? $application->submittedBy?->displayName() ?? __('app.official_letters.applicant_recipient');
        $serialNumber = $this->applicantFacilitationLetterSerialNumber($application);
        $defaults = [
            'application_authority_approval_id' => null,
            'target_entity_id' => $application->entity_id,
            'recipient_type' => 'applicant',
            'updated_by_user_id' => $actorId,
            'letter_date' => now()->toDateString(),
            'serial_number' => $serialNumber,
            'recipient_prefix' => app()->getLocale() === 'ar' ? 'السادة' : 'Dear',
            'recipient_name' => $recipientName,
            'subject' => __('app.official_letters.applicant_facilitation_subject', ['code' => $application->code]),
            'body' => __('app.official_letters.applicant_facilitation_body', [
                'entity' => $recipientName,
                'project' => $application->project_name,
                'code' => $application->code,
                'start' => $application->planned_start_date?->format('Y-m-d') ?? __('app.dashboard.not_available'),
                'end' => $application->planned_end_date?->format('Y-m-d') ?? __('app.dashboard.not_available'),
            ]),
            'attachments' => [],
            'status' => 'issued',
            'issued_at' => now(),
        ];

        $letter = $application->officialLetters()
            ->where('recipient_type', 'applicant')
            ->first();

        if ($letter instanceof ApplicationOfficialLetter) {
            $letter->forceFill([
                'target_entity_id' => $letter->target_entity_id ?: $application->entity_id,
                'updated_by_user_id' => $actorId,
                'serial_number' => $letter->serial_number ?: $serialNumber,
                'letter_date' => $letter->letter_date ?: now()->toDateString(),
                'recipient_prefix' => $letter->recipient_prefix ?: $defaults['recipient_prefix'],
                'recipient_name' => $letter->recipient_name ?: $recipientName,
                'subject' => $letter->subject ?: $defaults['subject'],
                'body' => $letter->body ?: $defaults['body'],
                'attachments' => $letter->attachments ?? [],
                'status' => 'issued',
                'issued_at' => $letter->issued_at ?: now(),
            ])->save();

            return $letter;
        }

        return $application->officialLetters()->create([
            ...$defaults,
            'created_by_user_id' => $actorId,
        ]);
    }

    /**
     * @param  Collection<int, array{approval_code:string,approval_label:string,target_entity_id:int|null,target_entity_name:string|null}>|null  $targets
     * @return Collection<int, ApplicationOfficialLetter>
     */
    private function ensureOfficialLetterSerialNumbers(FilmApplication $application, ?Collection $targets = null): Collection
    {
        $targets ??= $this->officialLetterRouteTargets($application);
        $targetSequences = $targets
            ->mapWithKeys(fn (array $target, int $index): array => [$this->officialLetterTargetKey($target) => $index + 1]);

        $letters = $application->officialLetters()
            ->with(['createdBy', 'updatedBy', 'targetEntity', 'authorityApproval.entity'])
            ->reorder()
            ->oldest('id')
            ->get();

        $reservedSerials = $letters
            ->pluck('serial_number')
            ->filter()
            ->map(fn (string $serial): string => $serial)
            ->values();

        $letters->each(function (ApplicationOfficialLetter $letter, int $index) use ($application, $targetSequences, $reservedSerials): void {
            if (filled($letter->serial_number)) {
                return;
            }

            $targetKey = $this->officialLetterKeyForLetter($letter);
            if ($letter->isApplicantLetter()) {
                $letter->forceFill([
                    'serial_number' => $this->applicantFacilitationLetterSerialNumber($application),
                ])->save();

                return;
            }
            $sequence = (int) ($targetSequences->get($targetKey) ?? ($index + 1));
            $serialNumber = $this->officialLetterSerialNumber($application, $sequence);

            while ($reservedSerials->contains($serialNumber)) {
                $sequence++;
                $serialNumber = $this->officialLetterSerialNumber($application, $sequence);
            }

            $reservedSerials->push($serialNumber);

            $letter->forceFill(['serial_number' => $serialNumber])->save();
        });

        return $application->officialLetters()
            ->with(['createdBy', 'updatedBy', 'targetEntity', 'authorityApproval.entity'])
            ->get();
    }

    private function officialLetterSerialNumber(FilmApplication $application, int $sequence): string
    {
        $applicationCode = $application->code ?: 'REQ-'.str_pad((string) $application->getKey(), 5, '0', STR_PAD_LEFT);

        return sprintf('%s-BOOK-%02d', $applicationCode, $sequence);
    }

    /**
     * @param  Collection<int, string>  $reservedSerials
     */
    private function unusedOfficialLetterSerialNumber(FilmApplication $application, int $preferredSequence, Collection $reservedSerials): string
    {
        $sequence = max(1, $preferredSequence);
        $serialNumber = $this->officialLetterSerialNumber($application, $sequence);

        while ($reservedSerials->contains($serialNumber)) {
            $sequence++;
            $serialNumber = $this->officialLetterSerialNumber($application, $sequence);
        }

        return $serialNumber;
    }

    private function applicantFacilitationLetterSerialNumber(FilmApplication $application): string
    {
        $applicationCode = $application->code ?: 'REQ-'.str_pad((string) $application->getKey(), 5, '0', STR_PAD_LEFT);

        return sprintf('%s-RFC-FAC-01', $applicationCode);
    }

    /**
     * @param  array{approval_code:string,target_entity_id:int|null}  $target
     */
    private function officialLetterTargetKey(array $target): string
    {
        return filled($target['target_entity_id'] ?? null)
            ? 'entity:'.$target['target_entity_id']
            : 'target:none';
    }

    private function officialLetterKeyForLetter(ApplicationOfficialLetter $letter): string
    {
        if ($letter->isApplicantLetter()) {
            return 'applicant';
        }

        return filled($letter->target_entity_id)
            ? 'entity:'.$letter->target_entity_id
            : 'target:none';
    }

    private function authorityApprovalForOfficialLetter(FilmApplication $application, ApplicationOfficialLetter $letter): ApplicationAuthorityApproval
    {
        $letter->loadMissing('targetEntity');
        $route = $this->officialLetterRouteForLetter($application, $letter);
        $approvalCode = (string) ($route['approval_code'] ?? '');
        $targetEntity = $letter->targetEntity;

        if (blank($approvalCode) && $targetEntity instanceof Entity) {
            $approvalCode = $targetEntity->authorityApprovalCodes()[0] ?? '';
        }

        abort_if(blank($approvalCode), 422, __('app.official_letters.send_requires_target'));

        $approval = $letter->authorityApproval instanceof ApplicationAuthorityApproval
            ? $letter->authorityApproval
            : $application->authorityApprovals()
                ->where('authority_code', $approvalCode)
                ->where('entity_id', $letter->target_entity_id)
                ->first();

        if (! $approval instanceof ApplicationAuthorityApproval) {
            $approval = new ApplicationAuthorityApproval([
                'application_id' => $application->getKey(),
                'authority_code' => $approvalCode,
                'entity_id' => $letter->target_entity_id,
            ]);
        }

        $assignedUserId = $this->resolveAuthorityApprovalAssigneeId($targetEntity, $approvalCode);
        $shouldRefreshAssignment = ! $approval->exists
            || ! $this->entityHasActiveMember($targetEntity, (int) ($approval->assigned_user_id ?? 0));

        $approval->forceFill([
            'application_id' => $application->getKey(),
            'authority_code' => $approvalCode,
            'entity_id' => $letter->target_entity_id,
            'approval_routing_rule_id' => $route['approval_routing_rule_id'] ?? $approval->approval_routing_rule_id,
            'status' => $approval->status ?: 'pending',
            'assigned_user_id' => $shouldRefreshAssignment ? $assignedUserId : $approval->assigned_user_id,
            'assigned_at' => $shouldRefreshAssignment && $assignedUserId
                ? now()
                : ($shouldRefreshAssignment ? null : $approval->assigned_at),
        ])->save();

        return $approval;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function officialLetterRouteForLetter(FilmApplication $application, ApplicationOfficialLetter $letter): ?array
    {
        if ($letter->isApplicantLetter()) {
            return null;
        }

        if ($letter->authorityApproval instanceof ApplicationAuthorityApproval) {
            return [
                'approval_code' => $letter->authorityApproval->authority_code,
                'target_entity_id' => $letter->authorityApproval->entity_id,
                'approval_routing_rule_id' => $letter->authorityApproval->approval_routing_rule_id,
            ];
        }

        $targetKey = $letter->target_entity_id ? 'entity:'.$letter->target_entity_id : 'target:none';

        return $this->approvalRoutingService
            ->explainRoutesForApplication($application)
            ->first(function (array $route) use ($targetKey): bool {
                $routeKey = filled($route['target_entity_id'] ?? null)
                    ? 'entity:'.$route['target_entity_id']
                    : 'target:none';

                return $routeKey === $targetKey;
            });
    }

    private function resolveAuthorityApprovalAssigneeId(?Entity $entity, string $approvalCode): ?int
    {
        if (! $entity || $entity->group?->code !== 'authorities') {
            return null;
        }

        $candidateUserId = $entity->authorityDelegatedUserIdFor($approvalCode);

        return $this->entityHasActiveMember($entity, $candidateUserId) ? $candidateUserId : null;
    }

    private function entityHasActiveMember(?Entity $entity, ?int $userId): bool
    {
        if (! $entity || ! $userId) {
            return false;
        }

        return $entity->users()
            ->where('users.id', $userId)
            ->where('users.status', 'active')
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * @param  Collection<int, ApplicationOfficialLetter>  $letters
     */
    private function notifyOfficialLettersIssued(FilmApplication $application, Collection $letters, ?int $actorId): void
    {
        $issuedLetters = $letters
            ->filter(fn (?ApplicationOfficialLetter $letter): bool => $letter?->status === 'issued')
            ->values();

        if ($issuedLetters->isEmpty()) {
            return;
        }

        $application->loadMissing(['entity', 'submittedBy']);

        $issuedLetters->each(function (ApplicationOfficialLetter $letter) use ($application, $actorId): void {
            $letter->loadMissing(['authorityApproval.entity', 'targetEntity']);
            $approval = $letter->authorityApproval;

            if (! $approval instanceof ApplicationAuthorityApproval) {
                return;
            }

            NotificationRecipients::except(NotificationRecipients::authorityUsersForApproval($approval), $actorId)
                ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                    typeKey: 'official_letter_issued',
                    title: $application->project_name,
                    body: __('app.notifications.official_letter_issued_authority_body', [
                        'code' => $application->code,
                        'subject' => $letter->subject,
                    ]),
                    routeName: 'authority.applications.show',
                    routeParameters: ['application' => $application->getKey()],
                    meta: [
                        ...WorkflowMessageMetadata::application($application),
                        'application_id' => $application->getKey(),
                        'official_letter_id' => $letter->getKey(),
                        'authority_approval_id' => $approval->getKey(),
                        'authority_code' => $approval->authority_code,
                        'authority_label' => $approval->localizedAuthority(),
                        'notification_highlight_active' => true,
                        'notification_highlight_title' => __('app.notifications.official_letter_issued_title'),
                        'notification_highlight_summary' => __('app.notifications.official_letter_issued_summary', [
                            'subject' => $letter->subject,
                        ]),
                        'notification_highlight_class' => 'info',
                    ],
                )));
        });

        $firstLetter = $issuedLetters->first();
        $isApplicantFacilitationLetter = $issuedLetters->count() === 1 && $firstLetter?->isApplicantLetter();
        $body = match (true) {
            $isApplicantFacilitationLetter => __('app.notifications.applicant_facilitation_letter_issued_body', [
                'code' => $application->code,
                'subject' => $firstLetter?->subject,
            ]),
            $issuedLetters->count() === 1 => __('app.notifications.official_letter_issued_applicant_body', [
                'code' => $application->code,
                'subject' => $firstLetter?->subject,
            ]),
            default => __('app.notifications.official_letters_issued_applicant_body', [
                'code' => $application->code,
                'count' => $issuedLetters->count(),
            ]),
        };

        NotificationRecipients::except(NotificationRecipients::applicationApplicants($application), $actorId)
            ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'official_letter_issued',
                title: $application->project_name,
                body: $body,
                routeName: 'applications.show',
                routeParameters: ['application' => $application->getKey()],
                meta: [
                    ...WorkflowMessageMetadata::application($application),
                    'application_id' => $application->getKey(),
                    'official_letter_ids' => $issuedLetters->pluck('id')->all(),
                    'notification_highlight_active' => true,
                    'notification_highlight_title' => __('app.notifications.official_letter_issued_title'),
                    'notification_highlight_summary' => match (true) {
                        $isApplicantFacilitationLetter => __('app.notifications.applicant_facilitation_letter_issued_summary', ['subject' => $firstLetter?->subject]),
                        $issuedLetters->count() === 1 => __('app.notifications.official_letter_issued_summary', ['subject' => $firstLetter?->subject]),
                        default => __('app.notifications.official_letters_issued_summary', ['count' => $issuedLetters->count()]),
                    },
                    'notification_highlight_class' => 'info',
                ],
            )));
    }

    private function notifyFinalDecisionStakeholders(FilmApplication $application, ?int $actorId): void
    {
        $targets = collect([
            $application->submittedBy,
            $application->assignedTo,
        ])
            ->filter()
            ->unique(fn (User $user): int => $user->getKey())
            ->reject(fn (User $user): bool => $user->getKey() === $actorId);

        foreach ($targets as $user) {
            $user->notify(new FinalDecisionIssuedNotification($application));
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function wrapReportOptions(): array
    {
        return [
            'production_types' => [
                'animation',
                'commercials',
                'corporate_industrial_documentary',
                'documentary_series',
                'feature_documentary',
                'feature_film',
                'interactive_game',
                'music_video',
                'photography',
                'reality_show',
                'series',
                'short_documentary',
                'short_film',
                'student_film',
                'tv_program',
                'other',
            ],
            'accommodation_types' => [
                'hotel',
                'camp',
                'private_accommodation',
            ],
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function workflowAssignableUsers(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('entities.group', fn (Builder $query): Builder => $query->where('code', 'rfc'))
            ->with('entities.group')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $this->isWorkflowAssignableUser($user))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function authorityApprovalAssignableUsers(ApplicationAuthorityApproval $approval): Collection
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

    private function isWorkflowAssignableUser(User $user): bool
    {
        return $user->availableEntities()->contains(function ($entity) use ($user): bool {
            if ($entity->group?->code !== 'rfc') {
                return false;
            }

            $registrar = app(PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($entity->getKey());

            try {
                return $user->canAny(['applications.review', 'applications.approve', 'applications.assign']);
            } finally {
                $registrar->setPermissionsTeamId(null);
            }
        });
    }
}
