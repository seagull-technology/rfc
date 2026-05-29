<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application as FilmApplication;
use App\Models\ApplicationAuthorityApproval;
use App\Models\ApplicationCorrespondence;
use App\Models\ApplicationDocument;
use App\Models\ApplicationOfficialLetter;
use App\Models\Entity;
use App\Models\Permit;
use App\Models\User;
use App\Notifications\FinalDecisionIssuedNotification;
use App\Notifications\InboxMessageNotification;
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
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $this->directoryFilters($request);
        $applications = $this->directoryQuery($filters)
            ->latest()
            ->get();
        $responsibilitySummaries = $applications
            ->mapWithKeys(function (FilmApplication $application): array {
                $authorityItems = $application->authorityApprovals
                    ->whereIn('status', ['pending', 'in_review'])
                    ->map(function (ApplicationAuthorityApproval $approval): array {
                        $signal = $this->authorityEscalationService->signalForApproval($approval);

                        return [
                            'authority' => $approval->localizedAuthority(),
                            'owner' => $approval->assignedTo?->displayName() ?? __('app.admin.applications.authority_shared_inbox'),
                            'status' => $approval->localizedStatus(),
                            'is_shared' => blank($approval->assigned_user_id),
                            'signal_label' => $signal['label'],
                            'is_overdue' => $signal['is_overdue'],
                            'is_escalated' => $signal['is_escalated'],
                        ];
                    })
                    ->values();

                return [
                    $application->getKey() => [
                        'rfc_owner' => $application->assignedTo?->displayName() ?? __('app.workflow.unassigned'),
                        'authority_items' => $authorityItems,
                        'has_overdue_authority' => $authorityItems->contains(fn (array $item): bool => $item['is_overdue']),
                    ],
                ];
            });
        $checkpointStats = $applications
            ->map(fn (FilmApplication $application): string => AdminWorkflowState::applicationCheckpoint($application)['key'])
            ->countBy();

        return view('admin.applications.index', [
            'applications' => $applications,
            'openApplications' => $applications->whereNotIn('status', ['approved', 'rejected'])->values(),
            'closedApplications' => $applications->whereIn('status', ['approved', 'rejected'])->values(),
            'responsibilitySummaries' => $responsibilitySummaries,
            'applicantResponses' => $applications
                ->mapWithKeys(fn (FilmApplication $application): array => [$application->getKey() => AdminApplicantResponseState::application($application)]),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
            ],
            'stats' => [
                'total' => $applications->count(),
                'submitted' => $applications->where('status', 'submitted')->count(),
                'under_review' => $applications->where('status', 'under_review')->count(),
                'resolved' => $applications->whereIn('status', ['approved', 'rejected'])->count(),
                'assign_reviewer' => $checkpointStats->get('assign_reviewer', 0),
                'waiting_on_applicant' => $checkpointStats->get('waiting_on_applicant', 0),
                'waiting_authorities' => $checkpointStats->get('waiting_authorities', 0),
                'overdue_authorities' => $responsibilitySummaries->where('has_overdue_authority', true)->count(),
                'ready_final_decision' => $checkpointStats->get('ready_final_decision', 0),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->directoryFilters($request);
        $applications = $this->directoryQuery($filters)
            ->latest()
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
        ]);
        $reviewers = $this->workflowAssignableUsers();
        $authorityApprovals = $record->authorityApprovals()->with(['reviewedBy', 'assignedTo', 'entity'])->get();
        $authorityApprovalSignals = $authorityApprovals
            ->mapWithKeys(fn (ApplicationAuthorityApproval $approval): array => [
                $approval->getKey() => $this->authorityEscalationService->signalForApproval($approval),
            ]);

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
                ->filter(fn ($event): bool => in_array((string) data_get($event->metadata, 'type'), ['authority_status_updated', 'authority_reassigned', 'authority_escalated'], true))
                ->values(),
            'reviewers' => $reviewers,
            'documents' => $record->documents,
            'correspondences' => $record->correspondences,
            'officialLetters' => $record->officialLetters,
            'officialLetterApprovals' => $this->officialLetterApprovalTargets($record),
            'applicantResponse' => AdminApplicantResponseState::application($record),
        ]);
    }

    public function review(Request $request, string $application): RedirectResponse
    {
        $record = $this->findApplication($application);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['under_review', 'needs_clarification'])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('decision') === 'needs_clarification')],
        ]);

        $record->forceFill([
            'status' => $validated['decision'],
            'current_stage' => match ($validated['decision']) {
                'under_review' => 'rfc_review',
                'needs_clarification' => 'clarification',
            },
            'review_note' => $validated['note'] ?: null,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $request->user()?->getKey(),
        ])->save();

        $record->statusHistory()->create([
            'user_id' => $request->user()?->getKey(),
            'status' => $validated['decision'],
            'note' => $validated['note'] ?: null,
            'happened_at' => now(),
        ]);

        NotificationRecipients::except(NotificationRecipients::applicationApplicants($record), $request->user()?->getKey())
            ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'application_status_changed',
                title: $record->project_name,
                body: __('app.notifications.application_status_changed_body', [
                    'status' => __('app.statuses.'.$validated['decision']),
                ]),
                routeName: 'applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: WorkflowMessageMetadata::application($record),
            )));

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.applications.review_saved'));
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
            'current_stage' => 'rfc_review',
            'status' => in_array($record->status, ['draft', 'submitted'], true) ? 'under_review' : $record->status,
        ])->save();

        $record->statusHistory()->create([
            'user_id' => $request->user()?->getKey(),
            'status' => $record->status,
            'note' => __('app.workflow.history.assigned_to', ['name' => $assignee->displayName()]),
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
        $approvalTargets = $this->officialLetterApprovalTargets($record);

        if ($approvalTargets->isEmpty()) {
            return redirect()
                ->route('admin.applications.show', $record)
                ->withErrors(['official_letters' => __('app.official_letters.no_routed_authorities')]);
        }

        $letters = DB::transaction(function () use ($record, $validated, $approvalTargets, $request): Collection {
            $createdLetters = $approvalTargets
                ->map(fn (ApplicationAuthorityApproval $approval): ApplicationOfficialLetter => $record->officialLetters()->create([
                    ...$validated,
                    'application_authority_approval_id' => $approval->getKey(),
                    'target_entity_id' => $approval->entity_id,
                    'created_by_user_id' => $request->user()?->getKey(),
                    'updated_by_user_id' => $request->user()?->getKey(),
                    'issued_at' => $validated['status'] === 'issued' ? now() : null,
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

        if ($validated['status'] === 'issued') {
            $this->notifyOfficialLettersIssued($record, $letters, $request->user()?->getKey());
        }

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.official_letters.created'));
    }

    public function updateOfficialLetter(Request $request, string $application, string $letter): RedirectResponse
    {
        $record = $this->findApplication($application);
        $officialLetter = $this->findOfficialLetter($letter, $record);
        $validated = $this->validateOfficialLetter($request);
        $wasIssued = $officialLetter->status === 'issued';
        $issuedAt = $officialLetter->issued_at;

        if ($validated['status'] === 'issued' && ! $issuedAt) {
            $issuedAt = now();
        }

        if ($validated['status'] !== 'issued') {
            $issuedAt = null;
        }

        $officialLetter->forceFill([
            ...$validated,
            'updated_by_user_id' => $request->user()?->getKey(),
            'issued_at' => $issuedAt,
        ])->save();

        $record->statusHistory()->create([
            'user_id' => $request->user()?->getKey(),
            'status' => $record->status,
            'note' => __('app.official_letters.history.updated', ['subject' => $officialLetter->subject]),
            'happened_at' => now(),
            'metadata' => ['type' => 'official_letter_updated', 'official_letter_id' => $officialLetter->getKey()],
        ]);

        if (! $wasIssued && $officialLetter->status === 'issued') {
            $this->notifyOfficialLettersIssued($record, collect([$officialLetter->fresh()]), $request->user()?->getKey());
        }

        return redirect()
            ->route('admin.applications.show', $record)
            ->with('status', __('app.official_letters.updated'));
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
        $query->with(['authorityApprovals.assignedTo', 'authorityApprovals.entity']);
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
            'serial_number' => ['nullable', 'string', 'max:100'],
            'recipient_prefix' => ['nullable', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'issued'])],
        ]);

        $validated['letter_date'] = $validated['letter_date'] ?? null;
        $validated['serial_number'] = ($validated['serial_number'] ?? null) ?: null;
        $validated['recipient_prefix'] = ($validated['recipient_prefix'] ?? null) ?: null;
        $validated['attachments'] = array_values($validated['attachments'] ?? []);

        return $validated;
    }

    private function officialLetterApprovalTargets(FilmApplication $application): Collection
    {
        return $application
            ->authorityApprovals()
            ->with('entity')
            ->orderBy('id')
            ->get()
            ->unique(fn (ApplicationAuthorityApproval $approval): string => $approval->entity_id
                ? 'entity:'.$approval->entity_id
                : 'approval:'.$approval->getKey())
            ->values();
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
        $body = $issuedLetters->count() === 1
            ? __('app.notifications.official_letter_issued_applicant_body', [
                'code' => $application->code,
                'subject' => $firstLetter?->subject,
            ])
            : __('app.notifications.official_letters_issued_applicant_body', [
                'code' => $application->code,
                'count' => $issuedLetters->count(),
            ]);

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
                    'notification_highlight_summary' => $issuedLetters->count() === 1
                        ? __('app.notifications.official_letter_issued_summary', ['subject' => $firstLetter?->subject])
                        : __('app.notifications.official_letters_issued_summary', ['count' => $issuedLetters->count()]),
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
     * @return Collection<int, User>
     */
    private function workflowAssignableUsers(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('entities.group', fn (Builder $query): Builder => $query->whereIn('code', ['rfc', 'admins']))
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
            if (! in_array($entity->group?->code, ['rfc', 'admins'], true)) {
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
