<?php

namespace App\Http\Controllers;

use App\Models\Application as FilmApplication;
use App\Models\ApplicationAuthorityApproval;
use App\Models\ApplicationCorrespondence;
use App\Models\ApplicationDocument;
use App\Models\Entity;
use App\Notifications\InboxMessageNotification;
use App\Support\ApplicantRequestOverview;
use App\Support\NotificationRecipients;
use App\Support\WorkflowMessageMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantPermission($user, 'applications.view.entity');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'draft', 'submitted', 'under_review', 'needs_clarification', 'approved', 'rejected'])],
        ]);

        $query = FilmApplication::query()
            ->with(['entity', 'submittedBy', 'reviewedBy'])
            ->where('entity_id', $entity->getKey());

        if (filled($filters['q'] ?? null)) {
            $search = trim((string) $filters['q']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('code', 'like', '%'.$search.'%')
                    ->orWhere('project_name', 'like', '%'.$search.'%')
                    ->orWhere('project_nationality', 'like', '%'.$search.'%')
                    ->orWhere('work_category', 'like', '%'.$search.'%');
            });
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }

        $applications = $query
            ->latest()
            ->get();

        return view('applications.index', [
            'user' => $user,
            'entity' => $entity,
            'applications' => $applications,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
            ],
            'stats' => [
                'total' => $applications->count(),
                'drafts' => $applications->where('status', 'draft')->count(),
                'active_reviews' => $applications->whereIn('status', ['submitted', 'under_review', 'needs_clarification'])->count(),
                'approved' => $applications->where('status', 'approved')->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantPermission($user, 'applications.create');

        return view('applications.create', [
            'user' => $user,
            'entity' => $entity,
            'application' => new FilmApplication([
                'project_name' => '',
                'project_nationality' => 'jordanian',
                'work_category' => '',
                'release_method' => '',
                'status' => 'draft',
                'metadata' => [
                    'producer' => [
                        'producer_name' => $user->name,
                        'production_company_name' => $entity->displayName(),
                        'contact_address' => data_get($entity->metadata, 'address'),
                        'contact_phone' => $entity->phone,
                        'contact_email' => $entity->email ?: $user->email,
                    ],
                ],
            ]),
            'formAction' => route('applications.store'),
            'formMethod' => 'POST',
            'submitLabel' => __('app.applications.save_draft_action'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantPermission($user, 'applications.create');

        $validated = $this->validateApplicationPayload($request);

        $application = DB::transaction(function () use ($validated, $user, $entity): FilmApplication {
            $application = FilmApplication::query()->create([
                ...$this->applicationAttributes($validated),
                'code' => $this->nextCode(),
                'entity_id' => $entity->getKey(),
                'submitted_by_user_id' => $user->getKey(),
                'status' => 'draft',
                'current_stage' => 'draft',
            ]);

            $this->appendHistory($application, 'draft', __('app.applications.history.draft_created'), $user->getKey());

            return $application;
        });

        return redirect()
            ->route('applications.show', $application)
            ->with('status', __('app.applications.created'));
    }

    public function show(Request $request, string $application): View
    {
        [$user, $entity] = $this->applicantContext($request);
        $record = $this->findApplicantApplication($application, $entity);
        $record->load([
            'statusHistory.user',
            'authorityApprovals.reviewedBy',
            'documents.uploadedBy',
            'documents.reviewedBy',
            'correspondences.createdBy',
        ]);

        return view('applications.show', [
            'user' => $user,
            'entity' => $entity,
            'application' => $record,
            'requestOverview' => ApplicantRequestOverview::forApplication($record),
            'statusHistory' => $record->statusHistory,
            'authorityApprovals' => $record->authorityApprovals,
            'documents' => $record->documents,
            'correspondences' => $record->correspondences,
        ]);
    }

    public function edit(Request $request, string $application): View
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantPermission($user, 'applications.update.entity');
        $record = $this->findApplicantApplication($application, $entity);

        abort_unless($record->canBeEditedByApplicant(), 403);

        return view('applications.edit', [
            'user' => $user,
            'entity' => $entity,
            'application' => $record,
            'formAction' => route('applications.update', $record),
            'formMethod' => 'POST',
            'submitLabel' => __('app.applications.update_draft_action'),
        ]);
    }

    public function update(Request $request, string $application): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantPermission($user, 'applications.update.entity');
        $record = $this->findApplicantApplication($application, $entity);

        abort_unless($record->canBeEditedByApplicant(), 403);

        $validated = $this->validateApplicationPayload($request);

        $record->forceFill($this->applicationAttributes($validated))->save();

        return redirect()
            ->route('applications.show', $record)
            ->with('status', __('app.applications.updated'));
    }

    public function submit(Request $request, string $application): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantPermission($user, 'applications.submit');
        $record = $this->findApplicantApplication($application, $entity);

        abort_unless($record->canBeSubmittedByApplicant(), 403);
        $wasClarificationResponse = $record->status === 'needs_clarification';

        DB::transaction(function () use ($record, $user): void {
            $record->forceFill([
                'status' => 'submitted',
                'current_stage' => 'intake',
                'submitted_at' => now(),
                'review_note' => null,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'final_decision_status' => null,
                'final_decision_note' => null,
                'final_decision_issued_at' => null,
                'final_decision_issued_by_user_id' => null,
                'final_permit_number' => null,
                'final_letter_path' => null,
                'final_letter_name' => null,
                'final_letter_mime_type' => null,
                'assigned_to_user_id' => null,
                'assigned_at' => null,
            ])->save();

            $this->syncAuthorityApprovals($record);
            $this->appendHistory($record, 'submitted', __('app.applications.history.submitted'), $user->getKey());
        });

        $record->load(['entity', 'authorityApprovals']);

        NotificationRecipients::except(NotificationRecipients::adminUsers(), $user->getKey())
            ->each(fn ($recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'application_submitted',
                title: $record->project_name,
                body: __('app.notifications.application_submitted_body', [
                    'code' => $record->code,
                    'entity' => $record->entity?->displayName() ?? __('app.dashboard.no_entity'),
                ]),
                routeName: 'admin.applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: [
                    ...WorkflowMessageMetadata::application($record),
                    ...$this->adminApplicantResponseNotificationMeta($wasClarificationResponse, __('app.notifications.applicant_response_resubmission')),
                ],
            )));

        $record->authorityApprovals
            ->each(function (ApplicationAuthorityApproval $approval) use ($record, $user): void {
                NotificationRecipients::except(NotificationRecipients::authorityUsersForApproval($approval), $user->getKey())
                    ->each(fn ($recipient) => $recipient->notify(new InboxMessageNotification(
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
            });

        return redirect()
            ->route('applications.show', $record)
            ->with('status', __('app.applications.submitted'));
    }

    public function storeDocument(Request $request, string $application): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantPermission($user, 'documents.upload.own');
        $record = $this->findApplicantApplication($application, $entity);

        abort_unless($record->canReceiveApplicantDocuments(), 403);

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(['site_request', 'work_content_summary', 'cast_crew_list', 'location_list', 'security_clearance', 'other'])],
            'title' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png'],
        ]);

        $file = $validated['file'];
        $path = $file->store('application-documents/'.$record->getKey(), 'local');

        $record->documents()->create([
            'uploaded_by_user_id' => $user->getKey(),
            'document_type' => $validated['document_type'],
            'title' => $validated['title'],
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'status' => 'submitted',
            'note' => $validated['note'] ?: null,
        ]);

        $reopenedForReview = $this->requeueAfterApplicantClarification($record);

        $this->appendHistory($record, $record->status, __('app.documents.history.uploaded', ['title' => $validated['title']]), $user->getKey());

        if ($reopenedForReview) {
            $record->loadMissing('entity');

            NotificationRecipients::except(NotificationRecipients::adminUsers(), $user->getKey())
                ->each(fn ($recipient) => $recipient->notify(new InboxMessageNotification(
                    typeKey: 'application_submitted',
                    title: $record->project_name,
                body: __('app.notifications.application_submitted_body', [
                    'code' => $record->code,
                    'entity' => $record->entity?->displayName() ?? __('app.dashboard.no_entity'),
                ]),
                routeName: 'admin.applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: [
                    ...WorkflowMessageMetadata::application($record),
                    ...$this->adminApplicantResponseNotificationMeta(
                        true,
                        __('app.admin_request_state.applicant_response_document', [
                            'item' => __('app.documents.tab'),
                        ]),
                    ),
                ],
            )));
        }

        return redirect()
            ->route('applications.show', $record)
            ->with('status', __('app.documents.uploaded'));
    }

    public function downloadDocument(Request $request, string $application, string $document): StreamedResponse|RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantPermission($user, 'documents.view.entity');
        $record = $this->findApplicantApplication($application, $entity);
        $documentRecord = $this->findApplicantDocument($document, $record);

        if (! Storage::disk('local')->exists($documentRecord->file_path)) {
            return redirect()
                ->route('applications.show', $record)
                ->withErrors(['document' => __('app.documents.file_missing')]);
        }

        return Storage::disk('local')->download($documentRecord->file_path, $documentRecord->original_name);
    }

    public function storeCorrespondence(Request $request, string $application): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);
        $record = $this->findApplicantApplication($application, $entity);

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
            'sender_type' => 'applicant',
            'sender_name' => $entity->displayName(),
            'subject' => $validated['subject'] ?: null,
            'message' => $validated['message'],
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime_type' => $attachmentMime,
        ]);

        $reopenedForReview = $this->requeueAfterApplicantClarification($record);

        $this->appendHistory($record, $record->status, __('app.correspondence.history.applicant_reply'), $user->getKey());

        NotificationRecipients::except(NotificationRecipients::adminUsers(), $user->getKey())
            ->each(fn ($recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'application_correspondence',
                title: $message->subject ?: __('app.contact_center.request_fallback_title', ['code' => $record->code]),
                body: str($message->message)->limit(140)->toString(),
                routeName: 'admin.applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: [
                    ...WorkflowMessageMetadata::application($record),
                    ...$this->adminApplicantResponseNotificationMeta(
                        $reopenedForReview,
                        __('app.notifications.applicant_response_correspondence', [
                            'item' => $message->subject ?: __('app.correspondence.tab'),
                        ]),
                    ),
                ],
            )));

        NotificationRecipients::except(NotificationRecipients::authorityUsersForApplication($record), $user->getKey())
            ->each(fn ($recipient) => $recipient->notify(new InboxMessageNotification(
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
            ->route('applications.show', $record)
            ->with('status', __('app.correspondence.sent'));
    }

    public function downloadCorrespondenceAttachment(Request $request, string $application, string $correspondence): StreamedResponse|RedirectResponse
    {
        [, $entity] = $this->applicantContext($request);
        $record = $this->findApplicantApplication($application, $entity);
        $message = $this->findApplicantCorrespondence($correspondence, $record);

        if (! $message->attachment_path || ! Storage::disk('local')->exists($message->attachment_path)) {
            return redirect()
                ->route('applications.show', $record)
                ->withErrors(['correspondence' => __('app.correspondence.file_missing')]);
        }

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name ?: basename($message->attachment_path));
    }

    public function downloadFinalLetter(Request $request, string $application): StreamedResponse|RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantPermission($user, 'applications.view.entity');
        $record = $this->findApplicantApplication($application, $entity);

        if (! $record->final_letter_path || ! Storage::disk('local')->exists($record->final_letter_path)) {
            return redirect()
                ->route('applications.show', $record)
                ->withErrors(['final_letter' => __('app.final_decision.file_missing')]);
        }

        return Storage::disk('local')->download($record->final_letter_path, $record->final_letter_name ?: basename($record->final_letter_path));
    }

    public function printFinalLetter(Request $request, string $application): View
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantPermission($user, 'applications.view.entity');
        $record = $this->findApplicantApplication($application, $entity);

        abort_unless($record->finalDecisionIssued(), 404);

        return view('letters.final-decision', [
            'application' => $record,
            'entity' => $entity,
            'issuedBy' => $record->finalDecisionIssuedBy,
            'permit' => $record->permit,
            'isAdminView' => false,
        ]);
    }

    /**
     * @return array{0: \App\Models\User, 1: Entity}
     */
    private function applicantContext(Request $request): array
    {
        $user = $request->user();
        $entity = $user?->primaryEntity();

        abort_unless($user && $entity, 404);

        return [$user, $entity];
    }

    private function ensureApplicantPermission($user, string $permission): void
    {
        abort_unless($user->can($permission), 403);
    }

    private function findApplicantApplication(string $application, Entity $entity): FilmApplication
    {
        return FilmApplication::query()
            ->with(['entity', 'submittedBy', 'reviewedBy', 'assignedTo', 'finalDecisionIssuedBy', 'authorityApprovals', 'permit'])
            ->where('entity_id', $entity->getKey())
            ->findOrFail($application);
    }

    private function findApplicantDocument(string $document, FilmApplication $application): ApplicationDocument
    {
        return ApplicationDocument::query()
            ->where('application_id', $application->getKey())
            ->findOrFail($document);
    }

    private function findApplicantCorrespondence(string $correspondence, FilmApplication $application): ApplicationCorrespondence
    {
        return ApplicationCorrespondence::query()
            ->where('application_id', $application->getKey())
            ->findOrFail($correspondence);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateApplicationPayload(Request $request): array
    {
        return $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'project_nationality' => ['required', Rule::in(['jordanian', 'international'])],
            'work_category' => ['required', Rule::in(['feature_film', 'documentary', 'series', 'commercial', 'tv_program', 'student_project'])],
            'release_method' => ['required', Rule::in(['cinema', 'television', 'streaming', 'festival', 'digital'])],
            'planned_start_date' => ['required', 'date'],
            'planned_end_date' => ['required', 'date', 'after_or_equal:planned_start_date'],
            'estimated_crew_count' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'project_summary' => ['required', 'string', 'max:5000'],
            'producer_name' => ['required', 'string', 'max:255'],
            'production_company_name' => ['required', 'string', 'max:255'],
            'contact_address' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'contact_mobile' => ['nullable', 'string', 'max:50'],
            'contact_fax' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['required', 'email', 'max:255'],
            'liaison_name' => ['required', 'string', 'max:255'],
            'liaison_position' => ['required', 'string', 'max:255'],
            'liaison_email' => ['required', 'email', 'max:255'],
            'liaison_mobile' => ['required', 'string', 'max:50'],
            'director_name' => ['required', 'string', 'max:255'],
            'director_nationality' => ['required', 'string', 'max:255'],
            'director_profile_url' => ['nullable', 'url', 'max:500'],
            'international_producer_name' => ['nullable', 'string', 'max:255'],
            'international_producer_company' => ['nullable', 'string', 'max:255'],
            'required_approvals' => ['nullable', 'array'],
            'required_approvals.*' => [Rule::in(['public_security', 'digital_economy', 'environment', 'municipalities', 'airports', 'drones', 'heritage'])],
            'supporting_notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applicationAttributes(array $validated): array
    {
        return [
            'project_name' => $validated['project_name'],
            'project_nationality' => $validated['project_nationality'],
            'work_category' => $validated['work_category'],
            'release_method' => $validated['release_method'],
            'planned_start_date' => $validated['planned_start_date'],
            'planned_end_date' => $validated['planned_end_date'],
            'estimated_crew_count' => $validated['estimated_crew_count'] ?: null,
            'estimated_budget' => $validated['estimated_budget'] ?: null,
            'project_summary' => $validated['project_summary'],
            'metadata' => [
                'producer' => [
                    'producer_name' => $validated['producer_name'],
                    'production_company_name' => $validated['production_company_name'],
                    'contact_address' => $validated['contact_address'],
                    'contact_phone' => $validated['contact_phone'],
                    'contact_mobile' => $validated['contact_mobile'] ?: null,
                    'contact_fax' => $validated['contact_fax'] ?: null,
                    'contact_email' => $validated['contact_email'],
                    'liaison_name' => $validated['liaison_name'],
                    'liaison_position' => $validated['liaison_position'],
                    'liaison_email' => $validated['liaison_email'],
                    'liaison_mobile' => $validated['liaison_mobile'],
                ],
                'director' => [
                    'director_name' => $validated['director_name'],
                    'director_nationality' => $validated['director_nationality'],
                    'director_profile_url' => $validated['director_profile_url'] ?: null,
                ],
                'international' => [
                    'international_producer_name' => $validated['international_producer_name'] ?: null,
                    'international_producer_company' => $validated['international_producer_company'] ?: null,
                ],
                'requirements' => [
                    'required_approvals' => array_values($validated['required_approvals'] ?? []),
                    'supporting_notes' => $validated['supporting_notes'] ?: null,
                ],
            ],
        ];
    }

    private function nextCode(): string
    {
        $nextId = (FilmApplication::query()->max('id') ?? 0) + 1;

        return 'REQ-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
    }

    private function appendHistory(FilmApplication $application, string $status, ?string $note, ?int $userId): void
    {
        $application->statusHistory()->create([
            'user_id' => $userId,
            'status' => $status,
            'note' => $note,
            'happened_at' => now(),
        ]);
    }

    private function syncAuthorityApprovals(FilmApplication $application): void
    {
        $requiredApprovals = array_values(array_unique(data_get($application->metadata, 'requirements.required_approvals', [])));

        ApplicationAuthorityApproval::query()
            ->where('application_id', $application->getKey())
            ->whereNotIn('authority_code', $requiredApprovals)
            ->delete();

        foreach ($requiredApprovals as $approvalCode) {
            ApplicationAuthorityApproval::query()->updateOrCreate(
                [
                    'application_id' => $application->getKey(),
                    'authority_code' => $approvalCode,
                ],
                [
                    'status' => 'pending',
                    'note' => null,
                    'reviewed_by_user_id' => null,
                    'decided_at' => null,
                ],
            );
        }
    }

    private function requeueAfterApplicantClarification(FilmApplication $application): bool
    {
        if ($application->status !== 'needs_clarification') {
            return false;
        }

        $application->forceFill([
            'status' => 'submitted',
            'current_stage' => 'intake',
            'assigned_to_user_id' => null,
            'assigned_at' => null,
        ])->save();

        return true;
    }

    /**
     * @return array<string, string|bool>
     */
    private function adminApplicantResponseNotificationMeta(bool $active, string $summary): array
    {
        if (! $active) {
            return [];
        }

        return [
            'applicant_response_active' => true,
            'applicant_response_title' => __('app.notifications.applicant_response_title'),
            'applicant_response_summary' => $summary,
            'applicant_response_class' => 'primary',
        ];
    }
}
