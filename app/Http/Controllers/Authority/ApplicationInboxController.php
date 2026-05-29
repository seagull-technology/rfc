<?php

namespace App\Http\Controllers\Authority;

use App\Http\Controllers\Controller;
use App\Models\Application as FilmApplication;
use App\Models\ApplicationAuthorityApproval;
use App\Models\ApplicationCorrespondence;
use App\Models\ApplicationDocument;
use App\Models\ApplicationOfficialLetter;
use App\Models\Entity;
use App\Notifications\InboxMessageNotification;
use App\Services\AuthorityEscalationService;
use App\Support\ApplicationWorkflowRegistry;
use App\Support\AuthorityApprovalSignal;
use App\Support\CsvExport;
use App\Support\NotificationRecipients;
use App\Support\WorkflowMessageMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationInboxController extends Controller
{
    public function __construct(
        private readonly AuthorityEscalationService $authorityEscalationService,
    ) {
    }

    public function index(Request $request): View
    {
        [$user, $entity, $approvalCodes] = $this->authorityContext($request);
        $filters = $this->directoryFilters($request);
        $approvals = $this->directoryQuery($filters, $user->getKey(), $entity, $approvalCodes)->latest()->get();
        $approvalSignals = $approvals
            ->mapWithKeys(fn (ApplicationAuthorityApproval $approval): array => [
                $approval->getKey() => AuthorityApprovalSignal::forApproval($approval),
            ]);
        $approvalSlaSignals = $approvals
            ->mapWithKeys(fn (ApplicationAuthorityApproval $approval): array => [
                $approval->getKey() => $this->authorityEscalationService->signalForApproval($approval, null, $entity),
            ]);
        $approvals = $approvals
            ->sortByDesc(function (ApplicationAuthorityApproval $approval) use ($approvalSignals, $approvalSlaSignals): int {
                $signal = $approvalSignals->get($approval->getKey(), [
                    'priority' => 0,
                    'at' => null,
                ]);
                $slaSignal = $approvalSlaSignals->get($approval->getKey(), [
                    'is_overdue' => false,
                    'is_escalated' => false,
                ]);

                return (((int) ($slaSignal['is_overdue'] ?? false) * 10) + ((int) ($slaSignal['is_escalated'] ?? false) * 5)) * 1_000_000_000_000_000
                    + ((int) ($signal['priority'] ?? 0) * 1_000_000_000_000)
                    + (int) (($signal['at'] ?? null)?->timestamp ?? $approval->updated_at?->timestamp ?? 0);
            })
            ->values();

        return view('authority.applications.index', [
            'user' => $user,
            'entity' => $entity,
            'approvals' => $approvals,
            'approvalSignals' => $approvalSignals,
            'approvalSlaSignals' => $approvalSlaSignals,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
                'ownership' => $filters['ownership'] ?? 'all',
            ],
            'stats' => [
                'total' => $approvals->count(),
                'my_assigned' => $approvals->where('assigned_user_id', $user->getKey())->count(),
                'shared_inbox' => $approvals->whereNull('assigned_user_id')->count(),
                'pending' => $approvals->where('status', 'pending')->count(),
                'in_review' => $approvals->where('status', 'in_review')->count(),
                'resolved' => $approvals->whereIn('status', ['approved', 'rejected'])->count(),
                'updates' => $approvalSignals->where('key', 'request_update')->count(),
                'official_books' => $approvalSignals->where('key', 'official_book_issued')->count(),
                'overdue' => $approvalSlaSignals->where('is_overdue', true)->count(),
                'escalated' => $approvals->whereNotNull('escalated_at')->count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$user, $entity, $approvalCodes] = $this->authorityContext($request);
        $filters = $this->directoryFilters($request);
        $approvals = $this->directoryQuery($filters, $user->getKey(), $entity, $approvalCodes)->latest()->get();

        $rows = $approvals->map(fn (ApplicationAuthorityApproval $approval): array => [
            $approval->application?->code ?? '',
            $approval->application?->project_name ?? '',
            $approval->application?->entity?->displayName() ?? '',
            $approval->application?->submittedBy?->displayName() ?? '',
            $approval->localizedAuthority(),
            $approval->localizedStatus(),
            $approval->application?->submitted_at?->format('Y-m-d H:i') ?? '',
        ])->all();

        return CsvExport::download(
            filename: 'authority-inbox-'.now()->format('Ymd-His').'.csv',
            headers: [
                __('app.admin.applications.application'),
                __('app.applications.project_name'),
                __('app.admin.applications.entity'),
                __('app.authority.applications.applicant'),
                __('app.admin.applications.authority'),
                __('app.applications.status'),
                __('app.admin.scouting.submitted_at'),
            ],
            rows: $rows,
        );
    }

    public function show(Request $request, string $application): View
    {
        [$user, $entity, $approvalCodes] = $this->authorityContext($request);
        $record = $this->findAuthorityApplication($application, $user->getKey(), $entity, $approvalCodes);
        $record->load([
            'statusHistory.user',
            'documents.uploadedBy',
            'documents.reviewedBy',
            'correspondences.createdBy',
            'authorityApprovals.reviewedBy',
            'officialLetters.targetEntity',
            'officialLetters.createdBy',
            'officialLetters.authorityApproval.entity',
        ]);
        $currentApproval = $this->currentApprovalForEntity($record, $entity, $approvalCodes);

        abort_unless($currentApproval, 404);

        $officialLetters = $record->officialLetters
            ->filter(fn (ApplicationOfficialLetter $letter): bool => $letter->status === 'issued')
            ->filter(function (ApplicationOfficialLetter $letter) use ($currentApproval, $entity): bool {
                if ((int) $letter->application_authority_approval_id === (int) $currentApproval->getKey()) {
                    return true;
                }

                return (int) $letter->target_entity_id === (int) $entity->getKey();
            })
            ->values();

        return view('authority.applications.show', [
            'user' => $user,
            'entity' => $entity,
            'application' => $record,
            'currentApproval' => $currentApproval,
            'approvalSignal' => AuthorityApprovalSignal::forApproval($currentApproval),
            'approvalSlaSignal' => $this->authorityEscalationService->signalForApproval($currentApproval, null, $entity),
            'statusHistory' => $record->statusHistory,
            'documents' => $record->documents,
            'correspondences' => $record->correspondences,
            'authorityApprovals' => $record->authorityApprovals,
            'officialLetters' => $officialLetters,
        ]);
    }

    public function updateApproval(Request $request, string $application): RedirectResponse
    {
        [$user, $entity, $approvalCodes] = $this->authorityContext($request);
        $record = $this->findAuthorityApplication($application, $user->getKey(), $entity, $approvalCodes);
        $approval = $this->currentApprovalForEntity($record, $entity, $approvalCodes);

        abort_unless($approval, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_review', 'approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array($validated['status'], ['approved', 'rejected'], true)) {
            abort_unless($user->can('applications.approve'), 403);
        }

        $approval->forceFill([
            'status' => $validated['status'],
            'note' => $validated['note'] ?: null,
            'reviewed_by_user_id' => $user->getKey(),
            'decided_at' => in_array($validated['status'], ['approved', 'rejected'], true) ? now() : null,
        ])->save();

        $statuses = $record->authorityApprovals()->pluck('status');
        $record->forceFill([
            'current_stage' => $statuses->contains('pending') || $statuses->contains('in_review')
                ? 'authority_review'
                : 'final_decision',
        ])->save();

        $record->statusHistory()->create([
            'user_id' => $user->getKey(),
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

        NotificationRecipients::except(NotificationRecipients::adminUsers(), $user->getKey())
            ->each(fn ($recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'authority_approval_updated',
                title: $record->project_name,
                body: __('app.notifications.authority_approval_updated_body', [
                    'authority' => $approval->localizedAuthority(),
                    'status' => $approval->localizedStatus(),
                ]),
                routeName: 'admin.applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: WorkflowMessageMetadata::application($record),
            )));

        NotificationRecipients::except(NotificationRecipients::applicationApplicants($record), $user->getKey())
            ->each(fn ($recipient) => $recipient->notify(new InboxMessageNotification(
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
            ->route('authority.applications.show', $record)
            ->with('status', __('app.workflow.approval_updated'));
    }

    public function storeCorrespondence(Request $request, string $application): RedirectResponse
    {
        [$user, $entity, $approvalCodes] = $this->authorityContext($request);
        $record = $this->findAuthorityApplication($application, $user->getKey(), $entity, $approvalCodes);

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
            'created_by_user_id' => $user->getKey(),
            'sender_type' => 'authority',
            'sender_name' => $entity->displayName(),
            'subject' => $validated['subject'] ?: null,
            'message' => $validated['message'],
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime_type' => $attachmentMime,
        ]);

        $record->statusHistory()->create([
            'user_id' => $user->getKey(),
            'status' => $record->status,
            'note' => __('app.correspondence.history.authority_message'),
            'happened_at' => now(),
        ]);

        NotificationRecipients::except(NotificationRecipients::adminUsers(), $user->getKey())
            ->each(fn ($recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'application_correspondence',
                title: $message->subject ?: __('app.contact_center.request_fallback_title', ['code' => $record->code]),
                body: str($message->message)->limit(140)->toString(),
                routeName: 'admin.applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: WorkflowMessageMetadata::application($record),
            )));

        NotificationRecipients::except(NotificationRecipients::applicationApplicants($record), $user->getKey())
            ->each(fn ($recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'application_correspondence',
                title: $message->subject ?: __('app.contact_center.request_fallback_title', ['code' => $record->code]),
                body: str($message->message)->limit(140)->toString(),
                routeName: 'applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: WorkflowMessageMetadata::application($record),
            )));

        return redirect()
            ->route('authority.applications.show', $record)
            ->with('status', __('app.correspondence.sent'));
    }

    public function downloadDocument(Request $request, string $application, string $document): StreamedResponse|RedirectResponse
    {
        [$user, $entity, $approvalCodes] = $this->authorityContext($request);
        $record = $this->findAuthorityApplication($application, $user->getKey(), $entity, $approvalCodes);
        $documentRecord = ApplicationDocument::query()
            ->where('application_id', $record->getKey())
            ->findOrFail($document);

        if (! Storage::disk('local')->exists($documentRecord->file_path)) {
            return redirect()
                ->route('authority.applications.show', $record)
                ->withErrors(['document' => __('app.documents.file_missing')]);
        }

        return Storage::disk('local')->download($documentRecord->file_path, $documentRecord->original_name);
    }

    public function downloadCorrespondenceAttachment(Request $request, string $application, string $correspondence): StreamedResponse|RedirectResponse
    {
        [$user, $entity, $approvalCodes] = $this->authorityContext($request);
        $record = $this->findAuthorityApplication($application, $user->getKey(), $entity, $approvalCodes);
        $message = ApplicationCorrespondence::query()
            ->where('application_id', $record->getKey())
            ->findOrFail($correspondence);

        if (! $message->attachment_path || ! Storage::disk('local')->exists($message->attachment_path)) {
            return redirect()
                ->route('authority.applications.show', $record)
                ->withErrors(['correspondence' => __('app.correspondence.file_missing')]);
        }

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name ?: basename($message->attachment_path));
    }

    /**
     * @return array{0: \App\Models\User, 1: Entity, 2: array<int, string>}
     */
    private function authorityContext(Request $request): array
    {
        $user = $request->user();
        $entity = $user?->primaryEntity();

        abort_unless($user && $entity && $entity->group?->code === 'authorities', 404);
        abort_unless($user->can('applications.view.entity'), 403);

        $approvalCodes = ApplicationWorkflowRegistry::approvalCodesForEntity($entity);
        return [$user, $entity, $approvalCodes];
    }

    /**
     * @param  array<int, string>  $approvalCodes
     */
    private function findAuthorityApplication(string $application, int $userId, Entity $entity, array $approvalCodes): FilmApplication
    {
        return FilmApplication::query()
            ->with(['entity.group', 'submittedBy', 'reviewedBy', 'assignedTo', 'authorityApprovals.reviewedBy', 'authorityApprovals.assignedTo', 'authorityApprovals.entity'])
            ->whereHas('authorityApprovals', fn (Builder $query): Builder => $this->restrictApprovalsToAuthority($query, $userId, $entity, $approvalCodes))
            ->findOrFail($application);
    }

    /**
     * @return array{q:string,status:string}
     */
    private function directoryFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'pending', 'in_review', 'approved', 'rejected'])],
            'ownership' => ['nullable', Rule::in(['all', 'mine', 'shared'])],
        ]);

        return [
            'q' => $validated['q'] ?? '',
            'status' => $validated['status'] ?? 'all',
            'ownership' => $validated['ownership'] ?? 'all',
        ];
    }

    /**
     * @param  array{q:string,status:string}  $filters
     * @param  array<int, string>  $approvalCodes
     */
    private function directoryQuery(array $filters, int $userId, Entity $entity, array $approvalCodes): Builder
    {
        $query = ApplicationAuthorityApproval::query()
            ->with([
                'application' => fn ($builder) => $builder
                    ->with(['entity', 'submittedBy', 'officialLetters'])
                    ->withMax([
                        'correspondences as last_external_correspondence_at' => fn (Builder $query): Builder => $query->whereIn('sender_type', ['admin', 'applicant']),
                    ], 'created_at'),
                'assignedTo',
                'reviewedBy',
                'entity',
            ])
            ->where(fn (Builder $builder): Builder => $this->restrictApprovalsToAuthority($builder, $userId, $entity, $approvalCodes));

        if (filled($filters['q'])) {
            $search = trim($filters['q']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->whereHas('application', fn (Builder $applicationQuery): Builder => $applicationQuery
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('project_name', 'like', '%'.$search.'%'))
                    ->orWhereHas('application.entity', fn (Builder $entityQuery): Builder => $entityQuery
                        ->where('name_en', 'like', '%'.$search.'%')
                        ->orWhere('name_ar', 'like', '%'.$search.'%'));
            });
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (($filters['ownership'] ?? 'all') === 'mine') {
            $query->where('assigned_user_id', $userId);
        }

        if (($filters['ownership'] ?? 'all') === 'shared') {
            $query->whereNull('assigned_user_id');
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $approvalCodes
     */
    private function restrictApprovalsToAuthority(Builder $query, int $userId, Entity $entity, array $approvalCodes): Builder
    {
        return $query->where(function (Builder $builder) use ($entity, $approvalCodes): void {
            $builder->where('entity_id', $entity->getKey());

            if ($approvalCodes !== []) {
                $builder->orWhere(function (Builder $legacyQuery) use ($approvalCodes): void {
                    $legacyQuery
                        ->whereNull('entity_id')
                        ->whereIn('authority_code', $approvalCodes);
                });
            }
        })->where(function (Builder $builder) use ($userId): void {
            $builder
                ->whereNull('assigned_user_id')
                ->orWhere('assigned_user_id', $userId);
        });
    }

    /**
     * @param  array<int, string>  $approvalCodes
     */
    private function currentApprovalForEntity(FilmApplication $application, Entity $entity, array $approvalCodes): ?ApplicationAuthorityApproval
    {
        $userId = request()->user()?->getKey();

        return $application->authorityApprovals
            ->filter(function (ApplicationAuthorityApproval $approval) use ($entity, $approvalCodes): bool {
                if ($approval->entity_id === $entity->getKey()) {
                    return true;
                }

                return $approval->entity_id === null && in_array($approval->authority_code, $approvalCodes, true);
            })
            ->filter(fn (ApplicationAuthorityApproval $approval): bool => $approval->assigned_user_id === null || $approval->assigned_user_id === $userId)
            ->sortBy(fn (ApplicationAuthorityApproval $approval): int => match ($approval->status) {
                'pending' => 0,
                'in_review' => 1,
                default => 2,
            })
            ->first();
    }
}
