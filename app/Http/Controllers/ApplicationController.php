<?php

namespace App\Http\Controllers;

use App\Models\Application as FilmApplication;
use App\Models\ApplicationAnnexSubmission;
use App\Models\ApplicationCorrespondence;
use App\Models\ApplicationDocument;
use App\Models\Entity;
use App\Models\FilmingLocationType;
use App\Models\FormLookupOption;
use App\Models\Governorate;
use App\Models\Nationality;
use App\Models\ReleaseMethod;
use App\Models\User;
use App\Models\WorkCategory;
use App\Notifications\InboxMessageNotification;
use App\Rules\SupportRequirementNotesRequired;
use App\Services\ApprovalRoutingService;
use App\Support\ApplicantRequestOverview;
use App\Support\JordanBusinessDays;
use App\Support\NotificationRecipients;
use App\Support\WorkflowMessageMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApprovalRoutingService $approvalRoutingService,
    ) {}

    public function index(Request $request): View
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantHasAnyPermission($user, ['applications.view.entity', 'applications.view.own']);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'draft', 'submitted', 'under_review', 'needs_clarification', 'approved', 'rejected'])],
        ]);

        $query = FilmApplication::query()
            ->with(['entity', 'submittedBy', 'reviewedBy'])
            ->where('entity_id', $entity->getKey());

        $this->restrictApplicantApplicationsQuery($query, $user);

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
            ->newestFirst()
            ->get();

        if ($this->isInternationalProducerUser($user)) {
            $applications = $applications
                ->filter(fn (FilmApplication $application): bool => $this->applicationIsLinkedToInternationalProducer($application, $user))
                ->values();
        }

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
        $lockedProducerFields = $this->lockedApplicantProducerFields($user, $entity);

        $application = new FilmApplication([
            'project_name' => '',
            'project_nationality' => 'jordanian',
            'project_nationalities' => ['jordanian'],
            'work_category' => '',
            'release_method' => '',
            'status' => 'draft',
            'metadata' => [
                'producer' => $lockedProducerFields,
            ],
        ]);

        return view('applications.create', [
            'user' => $user,
            'entity' => $entity,
            'application' => $application,
            'formAction' => route('applications.store'),
            'formMethod' => 'POST',
            'submitLabel' => __('app.applications.save_draft_action'),
            'approvalRoutePreviewRules' => $this->approvalRoutingService->applicationRoutingPreviewRules(),
            'nationalityOptions' => $this->nationalityOptionsForApplication($application),
            'locationLookupOptions' => $this->filmingLocationLookupOptions(),
            'workLookupOptions' => $this->workLookupOptionsForApplication($application),
            'formLookupOptions' => $this->formLookupOptionsForApplication(),
            'lockedProducerFields' => $lockedProducerFields,
            'canUseInternationalProjectSection' => $this->canUseInternationalProjectSection($user, $entity),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantPermission($user, 'applications.create');

        $this->mergeLockedApplicantProducerFields($request, $user, $entity);
        $validated = $this->validateApplicationPayload($request, false, $entity, $user);
        $requiresInternationalProject = $this->projectRequiresInternationalSection($validated, $entity, $user);

        $application = DB::transaction(function () use ($validated, $user, $entity, $requiresInternationalProject): FilmApplication {
            $application = FilmApplication::query()->create([
                ...$this->applicationAttributes($validated, $entity, $user),
                'code' => $this->nextCode(),
                'entity_id' => $entity->getKey(),
                'submitted_by_user_id' => $user->getKey(),
                'status' => 'draft',
                'current_stage' => 'draft',
            ]);

            $this->appendHistory($application, 'draft', __('app.applications.history.draft_created'), $user->getKey());

            if ($requiresInternationalProject) {
                $this->syncInternationalProducerAccount($application, $validated, $entity);
            }

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
            'authorityApprovals.entity',
            'authorityApprovals.reviewedBy',
            'documents.uploadedBy',
            'documents.reviewedBy',
            'correspondences.createdBy',
            'officialLetters.targetEntity',
            'officialLetters.createdBy',
            'annexSubmissions.submittedBy',
            'annexSubmissions.reviewedBy',
            'wrapReport.submittedBy',
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
            'officialLetters' => $record->officialLetters->where('status', 'issued')->values(),
            'annexSubmissions' => $record->annexSubmissions,
            'nationalityOptions' => $this->nationalityOptionsForApplication($record),
            'locationLookupOptions' => $this->filmingLocationLookupOptions(),
            'workLookupOptions' => $this->workLookupOptionsForApplication($record),
            'formLookupOptions' => $this->formLookupOptionsForApplication(),
            'wrapReport' => $record->wrapReport,
            'wrapReportAvailable' => $record->wrapReportIsAvailable(),
            'wrapReportOptions' => $this->wrapReportOptions(),
        ]);
    }

    public function edit(Request $request, string $application): View
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantHasAnyPermission($user, ['applications.update.entity', 'applications.update.own']);
        $record = $this->findApplicantApplication($application, $entity);
        $this->ensureApplicantCanUpdateApplication($user, $record);

        abort_unless($record->canBeEditedByApplicant(), 403);

        return view('applications.edit', [
            'user' => $user,
            'entity' => $entity,
            'application' => $record,
            'formAction' => route('applications.update', $record),
            'formMethod' => 'POST',
            'submitLabel' => __('app.applications.update_draft_action'),
            'approvalRoutePreviewRules' => $this->approvalRoutingService->applicationRoutingPreviewRules(),
            'nationalityOptions' => $this->nationalityOptionsForApplication($record),
            'locationLookupOptions' => $this->filmingLocationLookupOptions(),
            'workLookupOptions' => $this->workLookupOptionsForApplication($record),
            'formLookupOptions' => $this->formLookupOptionsForApplication(),
            'lockedProducerFields' => $this->lockedApplicantProducerFields($user, $entity),
            'canUseInternationalProjectSection' => $this->canUseInternationalProjectSection($user, $entity),
        ]);
    }

    public function update(Request $request, string $application): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantHasAnyPermission($user, ['applications.update.entity', 'applications.update.own']);
        $record = $this->findApplicantApplication($application, $entity);
        $this->ensureApplicantCanUpdateApplication($user, $record);

        abort_unless($record->canBeEditedByApplicant(), 403);

        $this->mergeLockedApplicantProducerFields($request, $user, $entity);
        $validated = $this->validateApplicationPayload($request, false, $entity, $user);

        $attributes = $this->applicationAttributes(
            $validated,
            $entity,
            $user,
            (array) data_get($record->metadata ?? [], 'annex', []),
        );
        $requiresInternationalProject = $this->projectRequiresInternationalSection($validated, $entity, $user);
        $existingInternationalAccount = data_get($record->metadata, 'international.account');

        if ($requiresInternationalProject && $existingInternationalAccount) {
            data_set($attributes, 'metadata.international.account', $existingInternationalAccount);
        }

        $record->forceFill($attributes)->save();

        if ($requiresInternationalProject) {
            $this->syncInternationalProducerAccount($record, $validated, $entity);
        }

        return redirect()
            ->route('applications.show', $record)
            ->with('status', __('app.applications.updated'));
    }

    public function updateAnnex(Request $request, string $application): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantHasAnyPermission($user, ['applications.update.entity', 'applications.update.own']);
        $record = $this->findApplicantApplication($application, $entity);
        $this->ensureApplicantCanUpdateApplication($user, $record);

        abort_unless($record->canUpdateApplicantAnnex(), 403);

        $validated = $this->validateAnnexPayload($request);
        $submittedAt = now();

        $submission = DB::transaction(function () use ($record, $validated, $submittedAt, $user): ApplicationAnnexSubmission {
            $metadata = $record->metadata ?? [];
            $submission = $record->annexSubmissions()->create([
                'submitted_by_user_id' => $user->getKey(),
                'status' => ApplicationAnnexSubmission::STATUS_SUBMITTED,
                'payload' => $this->annexMetadata($validated, (array) data_get($metadata, 'annex', [])),
                'previous_payload' => (array) data_get($metadata, 'annex', []),
                'submitted_at' => $submittedAt,
            ]);

            data_set($metadata, 'applicant_annex_submission', [
                'id' => $submission->getKey(),
                'status' => ApplicationAnnexSubmission::STATUS_SUBMITTED,
                'submitted_at' => $submittedAt->toDateTimeString(),
                'submitted_by_user_id' => $user->getKey(),
            ]);

            $record->forceFill(['metadata' => $metadata])->save();
            $this->appendHistory($record, $record->status, __('app.applications.history.annex_submitted'), $user->getKey(), [
                'type' => 'applicant_annex_submitted',
                'annex_submission_id' => $submission->getKey(),
            ]);

            return $submission;
        });

        $record->loadMissing(['entity', 'submittedBy']);
        NotificationRecipients::except(NotificationRecipients::adminUsers(), $user->getKey())
            ->each(fn ($recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'application_annex_submitted',
                title: $record->project_name,
                body: __('app.notifications.application_annex_submitted_body', [
                    'code' => $record->code,
                    'entity' => $record->entity?->displayName() ?? __('app.dashboard.no_entity'),
                ]),
                routeName: 'admin.applications.show',
                routeParameters: ['application' => $record->getKey()],
                meta: [
                    ...WorkflowMessageMetadata::application($record),
                    'application_id' => $record->getKey(),
                    'annex_submission_id' => $submission->getKey(),
                    'notification_highlight_active' => true,
                    'notification_highlight_title' => __('app.notifications.application_annex_submitted_title'),
                    'notification_highlight_summary' => __('app.notifications.application_annex_submitted_summary'),
                    'notification_highlight_class' => 'warning',
                ],
            )));

        return redirect()
            ->route('applications.show', $record)
            ->with('status', __('app.applications.annex_submitted'));
    }

    public function updateWrapReport(Request $request, string $application): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantHasAnyPermission($user, ['applications.update.entity', 'applications.update.own']);
        $record = $this->findApplicantApplication($application, $entity);
        $this->ensureApplicantCanUpdateApplication($user, $record);

        abort_unless($record->wrapReportIsAvailable(), 403);

        $validated = $this->validateWrapReportPayload($request);

        $record->wrapReport()->updateOrCreate(
            ['application_id' => $record->getKey()],
            [
                'submitted_by_user_id' => $user->getKey(),
                'status' => 'submitted',
                'payload' => $validated,
                'submitted_at' => now(),
            ],
        );

        $this->appendHistory($record, $record->status, __('app.applications.history.wrap_report_updated'), $user->getKey());

        return redirect(route('applications.show', $record).'#profile-wrap-report')
            ->with('status', __('app.wrap_report.saved'));
    }

    public function submit(Request $request, string $application): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantPermission($user, 'applications.submit');
        $record = $this->findApplicantApplication($application, $entity);

        abort_unless($record->canBeSubmittedByApplicant(), 403);

        $this->syncLockedApplicantProducerFields($record, $user, $entity);
        $this->validateApplicationForSubmission($record);

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

            $this->appendHistory($record, 'submitted', __('app.applications.history.submitted'), $user->getKey());
        });

        $record->load('entity');

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
            'document_type' => ['required', Rule::in(['site_request', 'work_content_summary', 'cast_crew_list', 'location_list', 'safety_guidelines', 'security_clearance', 'imported_equipment', 'airport_filming', 'governmental_scenes', 'other'])],
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
        $this->ensureApplicantHasAnyPermission($user, ['documents.view.entity', 'documents.view.own']);
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
        $this->ensureApplicantHasAnyPermission($user, ['applications.view.entity', 'applications.view.own']);
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
        [$user, $entity] = $this->applicantContext($request);
        $this->ensureApplicantHasAnyPermission($user, ['applications.view.entity', 'applications.view.own']);
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
        $this->ensureApplicantHasAnyPermission($user, ['applications.view.entity', 'applications.view.own']);
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
        $this->ensureApplicantHasAnyPermission($user, ['applications.view.entity', 'applications.view.own']);
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
     * @return array{0: User, 1: Entity}
     */
    private function applicantContext(Request $request): array
    {
        $user = $request->user();
        $entity = $user?->primaryEntity();

        abort_unless($user && $entity, 404);

        return [$user, $entity];
    }

    /**
     * @return array<string, string|null>
     */
    private function lockedApplicantProducerFields(User $user, Entity $entity): array
    {
        return [
            'producer_name' => $user->name,
            'production_company_name' => $entity->displayName(),
            'contact_address' => data_get($entity->metadata, 'address'),
            'contact_phone' => $entity->phone ?: $user->phone,
            'contact_email' => $entity->email ?: $user->email,
        ];
    }

    private function mergeLockedApplicantProducerFields(Request $request, User $user, Entity $entity): void
    {
        $request->merge($this->lockedApplicantProducerFields($user, $entity));
    }

    private function syncLockedApplicantProducerFields(FilmApplication $application, User $user, Entity $entity): void
    {
        $metadata = $application->metadata ?? [];

        foreach ($this->lockedApplicantProducerFields($user, $entity) as $field => $value) {
            data_set($metadata, 'producer.'.$field, $value);
        }

        $application->forceFill(['metadata' => $metadata])->save();
    }

    private function ensureApplicantPermission($user, string $permission): void
    {
        abort_unless($user->can($permission), 403);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function ensureApplicantHasAnyPermission(User $user, array $permissions): void
    {
        abort_unless(collect($permissions)->contains(fn (string $permission): bool => $user->can($permission)), 403);
    }

    private function restrictApplicantApplicationsQuery(Builder $query, User $user): Builder
    {
        if (! $user->can('applications.view.entity')) {
            $query->where('submitted_by_user_id', $user->getKey());
        }

        return $query;
    }

    private function ensureApplicantCanViewApplication(User $user, FilmApplication $application): void
    {
        if ($this->isInternationalProducerUser($user)) {
            abort_unless($this->applicationIsLinkedToInternationalProducer($application, $user), 403);

            return;
        }

        if ($user->can('applications.view.entity')) {
            return;
        }

        abort_unless(
            $user->can('applications.view.own')
            && (int) $application->submitted_by_user_id === (int) $user->getKey(),
            403,
        );
    }

    private function ensureApplicantCanUpdateApplication(User $user, FilmApplication $application): void
    {
        if ($user->can('applications.update.entity')) {
            return;
        }

        abort_unless(
            $user->can('applications.update.own')
            && (int) $application->submitted_by_user_id === (int) $user->getKey(),
            403,
        );
    }

    private function findApplicantApplication(string $application, Entity $entity): FilmApplication
    {
        $record = FilmApplication::query()
            ->with(['entity', 'submittedBy', 'reviewedBy', 'assignedTo', 'finalDecisionIssuedBy', 'authorityApprovals.entity', 'authorityApprovals.reviewedBy', 'permit'])
            ->where('entity_id', $entity->getKey())
            ->findOrFail($application);

        $user = auth()->user();

        if ($user instanceof User) {
            $this->ensureApplicantCanViewApplication($user, $record);
        }

        return $record;
    }

    private function isInternationalProducerUser(User $user): bool
    {
        return $user->registration_type === 'international_producer';
    }

    private function applicationIsLinkedToInternationalProducer(FilmApplication $application, User $user): bool
    {
        return (int) data_get($application->metadata, 'international.account.user_id') === $user->getKey();
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
     * @return array<string, Collection<int, Nationality>>
     */
    private function nationalityOptionsForApplication(FilmApplication $application): array
    {
        return [
            'project' => $this->nationalityOptionsForUsage(
                Nationality::USAGE_PROJECT,
                $application->projectNationalityCodes(),
            ),
            'director' => $this->nationalityOptionsForUsage(
                Nationality::USAGE_DIRECTOR,
                data_get($application->metadata, 'director.director_nationality'),
            ),
            'international_producer' => $this->nationalityOptionsForUsage(
                Nationality::USAGE_INTERNATIONAL_PRODUCER,
                data_get($application->metadata, 'international.international_producer_nationality'),
            ),
        ];
    }

    /**
     * @return Collection<int, Nationality>
     */
    private function nationalityOptionsForUsage(string $usage, mixed $currentCode)
    {
        $options = Nationality::query()
            ->active()
            ->forUsage($usage)
            ->ordered()
            ->get();

        $currentCodes = collect((array) $currentCode)
            ->filter(fn ($code): bool => filled($code))
            ->map(fn ($code): string => (string) $code)
            ->unique()
            ->values();

        foreach ($currentCodes as $code) {
            if ($options->contains('code', $code)) {
                continue;
            }

            $currentNationality = Nationality::query()
                ->where('code', $code)
                ->first();

            if ($currentNationality) {
                $options->push($currentNationality);
            }
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function filmingLocationLookupOptions(): array
    {
        $governorates = Governorate::query()
            ->active()
            ->ordered()
            ->get();
        $locationTypes = FilmingLocationType::query()
            ->active()
            ->with(['governorates' => fn ($query) => $query->active()->ordered()])
            ->ordered()
            ->get();

        return [
            'governorates' => $governorates,
            'location_types' => $locationTypes,
            'location_types_by_governorate' => $governorates
                ->mapWithKeys(fn (Governorate $governorate): array => [
                    $governorate->code => $locationTypes
                        ->filter(fn (FilmingLocationType $locationType): bool => $locationType->governorates->contains('code', $governorate->code))
                        ->pluck('code')
                        ->values()
                        ->all(),
                ])
                ->all(),
            'location_type_labels' => $locationTypes
                ->mapWithKeys(fn (FilmingLocationType $locationType): array => [$locationType->code => $locationType->displayName()])
                ->all(),
            'location_type_approval_days' => $locationTypes
                ->filter(fn (FilmingLocationType $locationType): bool => (int) $locationType->approval_days > 0)
                ->mapWithKeys(fn (FilmingLocationType $locationType): array => [$locationType->code => (int) $locationType->approval_days])
                ->all(),
            'governorate_labels' => $governorates
                ->mapWithKeys(fn (Governorate $governorate): array => [$governorate->code => $governorate->displayName()])
                ->all(),
        ];
    }

    /**
     * @return array<string, Collection<int, WorkCategory|ReleaseMethod>>
     */
    private function workLookupOptionsForApplication(FilmApplication $application): array
    {
        return [
            'work_categories' => $this->lookupOptionsWithCurrentValues(
                WorkCategory::class,
                collect(data_get($application->metadata, 'project.work_categories', []))
                    ->push($application->work_category)
                    ->filter()
                    ->all(),
            ),
            'release_methods' => $this->lookupOptionsWithCurrentValues(
                ReleaseMethod::class,
                collect(data_get($application->metadata, 'project.release_methods', []))
                    ->push($application->release_method)
                    ->filter()
                    ->all(),
            ),
        ];
    }

    /**
     * @template TLookup of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TLookup>  $modelClass
     * @param  array<int, mixed>  $currentCodes
     * @return Collection<int, TLookup>
     */
    private function lookupOptionsWithCurrentValues(string $modelClass, array $currentCodes)
    {
        $options = $modelClass::query()
            ->active()
            ->ordered()
            ->get();

        if ($options->isEmpty() && method_exists($modelClass, 'activeCodes') && method_exists($modelClass, 'labelFor')) {
            $options = collect($modelClass::activeCodes())
                ->map(function (string $code, int $index) use ($modelClass) {
                    $option = new $modelClass();
                    $option->forceFill([
                        'code' => $code,
                        'name_en' => $modelClass::labelFor($code),
                        'name_ar' => $modelClass::labelFor($code),
                        'is_active' => true,
                        'sort_order' => ($index + 1) * 10,
                    ]);

                    return $option;
                });
        }

        foreach (array_unique(array_filter(array_map('strval', $currentCodes))) as $currentCode) {
            if ($options->contains('code', $currentCode)) {
                continue;
            }

            $currentOption = $modelClass::query()
                ->where('code', $currentCode)
                ->first();

            if ($currentOption) {
                $options->push($currentOption);
            }
        }

        return $options;
    }

    /**
     * @return array<string, Collection<int, FormLookupOption>>
     */
    private function formLookupOptionsForApplication(): array
    {
        return [
            'equipment_categories' => FormLookupOption::activeForType(FormLookupOption::TYPE_EQUIPMENT_CATEGORY),
            'equipment_shipping_methods' => FormLookupOption::activeForType(FormLookupOption::TYPE_EQUIPMENT_SHIPPING_METHOD),
            'equipment_entry_points' => FormLookupOption::activeForType(FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT),
            'airports' => FormLookupOption::activeForType(FormLookupOption::TYPE_AIRPORT),
            'special_location_requirements' => FormLookupOption::activeForType(FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT),
            'budget_spending_categories' => FormLookupOption::activeForType(FormLookupOption::TYPE_BUDGET_SPENDING_CATEGORY),
            'drone_request_types' => FormLookupOption::activeForType(FormLookupOption::TYPE_DRONE_REQUEST_TYPE),
        ];
    }

    private function locationTypeBelongsToGovernorateRule(Request $request, string $collection): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request, $collection): void {
            if (! filled($value)) {
                return;
            }

            if (! preg_match('/^'.preg_quote($collection, '/').'\.(\d+)\.location_type$/', $attribute, $matches)) {
                return;
            }

            $governorate = data_get((array) $request->input($collection, []), $matches[1].'.governorate');

            if (! filled($governorate)) {
                return;
            }

            if (! in_array((string) $value, FilmingLocationType::activeCodesForGovernorate((string) $governorate), true)) {
                $fail(__('validation.location_type_governorate'));
            }
        };
    }

    private function filmingLocationSupportDateWithinRangeRule(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            if (blank($value) || ! preg_match('/^filming_locations\.([^\.]+)\.support_requirements\.([^\.]+)\.date$/', $attribute, $matches)) {
                return;
            }

            $location = (array) data_get((array) $request->input('filming_locations', []), $matches[1], []);
            $supportDate = strtotime((string) $value);
            $startDate = filled($location['start_date'] ?? null) ? strtotime((string) $location['start_date']) : false;
            $endDate = filled($location['end_date'] ?? null) ? strtotime((string) $location['end_date']) : false;

            if ($supportDate === false) {
                return;
            }

            if ($startDate !== false && $supportDate < $startDate) {
                $fail(__('app.applications.location_support_date_range'));

                return;
            }

            if ($endDate !== false && $supportDate > $endDate) {
                $fail(__('app.applications.location_support_date_range'));
            }
        };
    }

    /**
     * @param  array<string, int>  $approvalDaysByLocationType
     */
    private function filmingLocationStartRespectsApprovalLeadTimeRule(Request $request, string $collection, array $approvalDaysByLocationType): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request, $collection, $approvalDaysByLocationType): void {
            if (blank($value) || ! preg_match('/^'.preg_quote($collection, '/').'\.([^\.]+)\.start_date$/', $attribute, $matches)) {
                return;
            }

            $locationType = (string) data_get((array) $request->input($collection, []), $matches[1].'.location_type', '');
            $approvalDays = (int) ($approvalDaysByLocationType[$locationType] ?? 0);

            if ($approvalDays <= 0) {
                return;
            }

            try {
                $selectedStartDate = JordanBusinessDays::parse((string) $value);
            } catch (\Throwable) {
                return;
            }

            $minimumStartDate = JordanBusinessDays::addBusinessDays(JordanBusinessDays::today(), $approvalDays);

            if ($selectedStartDate->lt($minimumStartDate)) {
                $fail(__('app.applications.location_type_approval_business_days', [
                    'days' => $approvalDays,
                    'date' => $minimumStartDate->toDateString(),
                ]));
            }
        };
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function lookupCodesForValidation(string $type, Request $request, array $paths = []): array
    {
        $codes = FormLookupOption::activeCodesForType($type);

        foreach ($paths as $path) {
            $codes = [
                ...$codes,
                ...Arr::flatten((array) data_get($request->all(), $path, [])),
            ];
        }

        return array_values(array_unique(array_filter(array_map('strval', $codes), fn (string $value): bool => filled($value))));
    }

    /**
     * @param  array<int, string>  $allowedKeys
     */
    private function lookupArrayKeysRule(array $allowedKeys): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($allowedKeys): void {
            foreach (array_keys((array) $value) as $key) {
                if (! in_array((string) $key, $allowedKeys, true)) {
                    $fail(__('validation.in'));

                    return;
                }
            }
        };
    }

    private function castCrewIdentityNumberRule(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            if (blank($value) || ! preg_match('/^cast_crew\.([^\.]+)\.identity_number$/', $attribute, $matches)) {
                return;
            }

            $nationality = (string) data_get((array) $request->input('cast_crew', []), $matches[1].'.nationality');

            if ($this->castCrewNationalityIsJordanian($nationality) && ! preg_match('/^\d{10}$/', (string) $value)) {
                $fail(__('app.applications.cast_crew_national_id_digits'));
            }
        };
    }

    private function airportPeopleIdentityNumberRule(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            if (blank($value) || ! preg_match('/^airport_people\.([^\.]+)\.identity_number$/', $attribute, $matches)) {
                return;
            }

            $nationality = (string) data_get((array) $request->input('airport_people', []), $matches[1].'.nationality');

            if ($this->castCrewNationalityIsJordanian($nationality) && ! preg_match('/^\d{10}$/', (string) $value)) {
                $fail(__('app.applications.airport_people_national_id_digits'));
            }
        };
    }

    private function castCrewNationalityIsJordanian(?string $nationality): bool
    {
        $value = trim((string) $nationality);

        return Str::lower($value) === 'jordanian'
            || in_array($value, ['أردني', 'اردني'], true);
    }

    /**
     * @return array<int, mixed>
     */
    private function workContentSummarySynopsisRules(): array
    {
        return ['required', 'string', 'max:12000', $this->arabicTextRule(), $this->minimumWordCountRule(500)];
    }

    private function arabicTextRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $text = trim((string) $value);

            if ($text === '') {
                return;
            }

            if (preg_match('/[^\p{Arabic}\p{N}\s\.,،؛;:!؟\?\-\(\)\[\]"\'\/%&]+/u', $text)) {
                $fail(__('validation.arabic_text'));
            }
        };
    }

    private function minimumWordCountRule(int $minimum): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($minimum): void {
            $text = trim((string) $value);

            if ($text === '') {
                return;
            }

            if ($this->arabicWordCount($text) < $minimum) {
                $fail(__('validation.min_words', ['min' => $minimum]));
            }
        };
    }

    private function arabicWordCount(string $text): int
    {
        preg_match_all('/[\p{Arabic}\p{N}]+/u', $text, $matches);

        return count($matches[0] ?? []);
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
     * @return array<string, mixed>
     */
    private function validateWrapReportPayload(Request $request): array
    {
        $options = $this->wrapReportOptions();

        $validated = $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'production_company' => ['required', 'string', 'max:255'],
            'local_producer_services_company' => ['required', 'string', 'max:255'],
            'production_types' => ['required', 'array', 'min:1'],
            'production_types.*' => [Rule::in($options['production_types'])],
            'production_type_other' => ['nullable', 'string', 'max:255', Rule::requiredIf(in_array('other', (array) $request->input('production_types', []), true))],
            'nationalities' => ['required', 'string', 'max:500'],
            'production_year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'local_crew_count' => ['required', 'integer', 'min:0', 'max:100000'],
            'foreign_crew_count' => ['required', 'integer', 'min:0', 'max:100000'],
            'hotel_nights_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'accommodation_types' => ['required', 'array', 'min:1'],
            'accommodation_types.*' => [Rule::in($options['accommodation_types'])],
            'hotel_stars' => ['nullable', 'integer', 'min:1', 'max:5', Rule::requiredIf(in_array('hotel', (array) $request->input('accommodation_types', []), true))],
            'national_carrier_ticket_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'rented_cars_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'rental_days_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'production_days_pre_production' => ['required', 'integer', 'min:0', 'max:1000000'],
            'production_days_production' => ['required', 'integer', 'min:0', 'max:1000000'],
            'production_days_post_production' => ['required', 'integer', 'min:0', 'max:1000000'],
            'total_local_spending_jod' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'additional_notes' => ['nullable', 'string', 'max:5000'],
            'submitted_by' => ['required', 'string', 'max:255'],
            'submitted_position' => ['required', 'string', 'max:255'],
            'submitted_date' => ['required', 'date'],
        ]);

        $validated['rented_cars_total_days'] = ((int) $validated['rented_cars_count']) * ((int) $validated['rental_days_count']);
        $validated['total_production_days'] = ((int) $validated['production_days_pre_production'])
            + ((int) $validated['production_days_production'])
            + ((int) $validated['production_days_post_production']);

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateApplicationPayload(Request $request, bool $requireComplete = false, ?Entity $entity = null, ?User $user = null): array
    {
        $rules = $this->applicationValidationRules($request, $entity, $user);

        if (! $requireComplete) {
            $rules = $this->draftApplicationValidationRules($rules);
            $rules['work_content_summary_synopsis'] = ['nullable', 'string', 'max:12000'];
        }

        return $this->normalizeApplicationPayload($request->validate($rules));
    }

    private function validateApplicationForSubmission(FilmApplication $application): void
    {
        $application->loadMissing('entity');

        $payload = $this->applicationPayloadFromRecord($application);
        $request = Request::create('/', 'POST', $payload);

        Validator::make($payload, $this->applicationValidationRules($request, $application->entity, $application->submittedBy))->validate();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function applicationValidationRules(Request $request, ?Entity $entity = null, ?User $user = null): array
    {
        $workCategories = WorkCategory::activeCodes();
        $releaseMethods = ReleaseMethod::activeCodes();
        $governorateCodes = Governorate::activeCodes();
        $locationTypeCodes = FilmingLocationType::activeCodes();
        $locationTypeApprovalDays = FilmingLocationType::query()
            ->active()
            ->where('approval_days', '>', 0)
            ->pluck('approval_days', 'code')
            ->map(fn ($days): int => (int) $days)
            ->all();
        $specialRequirementCodes = $this->lookupCodesForValidation(FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT, $request, [
            'filming_locations.*.special_requirements',
        ]);
        $specialRequirementCodes = array_values(array_unique([
            ...$specialRequirementCodes,
            ...array_map('strval', array_keys((array) $request->input('special_location_requirements', []))),
        ]));
        $budgetSpendingCategoryCodes = FormLookupOption::activeCodesForType(FormLookupOption::TYPE_BUDGET_SPENDING_CATEGORY);
        $equipmentCategoryCodes = $this->lookupCodesForValidation(FormLookupOption::TYPE_EQUIPMENT_CATEGORY, $request, [
            'imported_equipment.*.classification',
        ]);
        $equipmentShippingMethodCodes = $this->lookupCodesForValidation(FormLookupOption::TYPE_EQUIPMENT_SHIPPING_METHOD, $request, [
            'imported_equipment.*.shipping_method',
        ]);
        $equipmentEntryPointCodes = $this->lookupCodesForValidation(FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT, $request, [
            'imported_equipment.*.customs_center',
            'imported_equipment.*.entry_point',
        ]);
        $airportCodes = $this->lookupCodesForValidation(FormLookupOption::TYPE_AIRPORT, $request, [
            'airport_filming_airport_name',
        ]);
        $requiresInternationalProject = $this->projectRequiresInternationalSection($request->all(), $entity, $user);
        $hasInternationalAccount = filter_var($request->input('international_account_exists'), FILTER_VALIDATE_BOOLEAN)
            || filled($request->input('international_account_user_id'));
        $requiresInternationalAccountPassword = $requiresInternationalProject && ! $hasInternationalAccount;
        $requiresBudgetBreakdown = ((float) $request->input('local_spend_estimate', 0)) >= 175000;
        $requiresTravelerEquipmentAcknowledgement = $this->travelerEquipmentAcknowledgementRequired($request);
        $travelerEquipmentAcknowledgementRules = $requiresTravelerEquipmentAcknowledgement
            ? ['required', 'accepted']
            : ['nullable', 'boolean'];

        $preparationEndDate = $request->input('schedule_phases.preparation.end_date');

        $rules = [
            'project_name' => ['required', 'string', 'max:255'],
            'project_nationality' => ['nullable', Rule::in(Nationality::activeCodesFor(Nationality::USAGE_PROJECT))],
            'project_nationalities' => ['required', 'array', 'min:1'],
            'project_nationalities.*' => ['required', Rule::in(Nationality::activeCodesFor(Nationality::USAGE_PROJECT))],
            'work_category' => ['required', Rule::in($workCategories)],
            'work_categories' => ['nullable', 'array'],
            'work_categories.*' => ['nullable', Rule::in($workCategories)],
            'work_category_other' => ['nullable', 'string', 'max:255'],
            'release_method' => ['required', Rule::in($releaseMethods)],
            'release_methods' => ['nullable', 'array'],
            'release_methods.*' => ['nullable', Rule::in($releaseMethods)],
            'release_method_other' => ['nullable', 'string', 'max:255'],
            'planned_start_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($preparationEndDate): void {
                    if (blank($value) || blank($preparationEndDate)) {
                        return;
                    }

                    $minimumStartDate = strtotime((string) $preparationEndDate);
                    $shootingStartDate = strtotime((string) $value);

                    if ($minimumStartDate === false || $shootingStartDate === false) {
                        return;
                    }

                    if ($shootingStartDate < $minimumStartDate) {
                        $fail(__('app.applications.schedule_validation.shooting_start_after_preparation'));
                    }
                },
            ],
            'planned_end_date' => ['required', 'date', 'after_or_equal:planned_start_date'],
            'schedule_phases' => ['required', 'array'],
            'schedule_phases.preparation.start_date' => ['required', 'date'],
            'schedule_phases.preparation.end_date' => ['required', 'date', 'after_or_equal:schedule_phases.preparation.start_date'],
            'schedule_phases.wrap.start_date' => ['required', 'date', 'after_or_equal:planned_end_date'],
            'schedule_phases.wrap.end_date' => ['required', 'date', 'after_or_equal:schedule_phases.wrap.start_date'],
            'schedule_phases.post_production.start_date' => ['required', 'date', 'after_or_equal:schedule_phases.wrap.end_date'],
            'schedule_phases.post_production.end_date' => ['required', 'date', 'after_or_equal:schedule_phases.post_production.start_date'],
            'estimated_crew_count' => ['required', 'integer', 'min:1', 'max:100000'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'local_spend_estimate' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'budget_items' => [Rule::requiredIf($requiresBudgetBreakdown), 'nullable', 'array', $this->lookupArrayKeysRule($budgetSpendingCategoryCodes)],
            'budget_items.*.units' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'budget_items.*.total' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'project_summary' => ['nullable', 'string', 'max:5000'],
            'producer_name' => ['required', 'string', 'max:255'],
            'production_company_name' => ['required', 'string', 'max:255'],
            'contact_address' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'contact_mobile' => ['nullable', 'string', 'max:50'],
            'contact_fax' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['required', 'email', 'max:255'],
            'director_name' => ['required', 'string', 'max:255'],
            'director_nationality' => ['required', Rule::in(Nationality::activeCodesFor(Nationality::USAGE_DIRECTOR))],
            'director_email' => ['required', 'email', 'max:255'],
            'director_profile_url' => ['required', 'url', 'max:500'],
            'international_producer_name' => [Rule::requiredIf($requiresInternationalProject), 'nullable', 'string', 'max:255'],
            'international_producer_nationality' => [Rule::requiredIf($requiresInternationalProject), 'nullable', Rule::in(Nationality::activeCodesFor(Nationality::USAGE_INTERNATIONAL_PRODUCER))],
            'international_producer_company' => [Rule::requiredIf($requiresInternationalProject), 'nullable', 'string', 'max:255'],
            'international_producer_email' => [Rule::requiredIf($requiresInternationalProject), 'nullable', 'email', 'max:255'],
            'international_producer_profile_url' => [Rule::requiredIf($requiresInternationalProject), 'nullable', 'url', 'max:500'],
            'international_producer_address' => [Rule::requiredIf($requiresInternationalProject), 'nullable', 'string', 'max:500'],
            'international_producer_website' => [Rule::requiredIf($requiresInternationalProject), 'nullable', 'url', 'max:500'],
            'international_liaison_email' => [Rule::requiredIf($requiresInternationalProject), 'nullable', 'email', 'max:255'],
            'international_liaison_mobile' => [Rule::requiredIf($requiresInternationalProject), 'nullable', 'string', 'max:50'],
            'international_account_exists' => $requiresInternationalProject ? ['required', 'accepted'] : ['nullable', 'boolean'],
            'international_account_user_id' => ['nullable', 'integer'],
            'international_account_password' => [Rule::requiredIf($requiresInternationalAccountPassword), 'nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'international_account_password_confirmation' => [Rule::requiredIf($requiresInternationalAccountPassword), 'nullable', 'string'],
            'work_content_summary_synopsis' => $this->workContentSummarySynopsisRules(),
            'work_content_summary_confirmed' => ['accepted'],
            'cast_crew' => ['nullable', 'array'],
            'cast_crew.*.name' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.first_name' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.second_name' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.third_name' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.family_name' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.role' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.nationality' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.gender' => ['nullable', Rule::in(['male', 'female'])],
            'cast_crew.*.birth_date' => ['nullable', 'date', 'before:today'],
            'cast_crew.*.identity_number' => ['nullable', 'string', 'max:255', $this->castCrewIdentityNumberRule($request)],
            'filming_locations' => ['nullable', 'array'],
            'filming_locations.*.governorate' => ['nullable', 'string', Rule::in($governorateCodes)],
            'filming_locations.*.location_name' => ['nullable', 'string', 'max:255'],
            'filming_locations.*.address' => ['nullable', 'string', 'max:500'],
            'filming_locations.*.nature' => ['nullable', 'string', 'max:255'],
            'filming_locations.*.location_type' => ['nullable', 'string', Rule::in($locationTypeCodes), $this->locationTypeBelongsToGovernorateRule($request, 'filming_locations')],
            'filming_locations.*.special_requirements' => ['nullable', 'array'],
            'filming_locations.*.special_requirements.*' => ['nullable', 'string', Rule::in($specialRequirementCodes)],
            'filming_locations.*.support_requirements' => ['nullable', 'array'],
            'filming_locations.*.support_requirements.*.authority' => ['nullable', 'string', Rule::in(['public_security', 'military'])],
            'filming_locations.*.support_requirements.*.requirement' => ['nullable', 'string', 'max:255'],
            'filming_locations.*.support_requirements.*.date' => ['nullable', 'date', $this->filmingLocationSupportDateWithinRangeRule($request)],
            'filming_locations.*.support_requirements.*.time_from' => ['nullable', 'date_format:H:i'],
            'filming_locations.*.support_requirements.*.time_to' => ['nullable', 'date_format:H:i'],
            'filming_locations.*.support_requirements.*.notes' => [new SupportRequirementNotesRequired(), 'nullable', 'string', 'max:1000'],
            'filming_locations.*.start_date' => ['nullable', 'date', 'after_or_equal:today', $this->filmingLocationStartRespectsApprovalLeadTimeRule($request, 'filming_locations', $locationTypeApprovalDays)],
            'filming_locations.*.end_date' => ['nullable', 'date'],
            'special_location_requirements' => ['nullable', 'array', $this->lookupArrayKeysRule($specialRequirementCodes)],
            'special_location_requirements.*.locations' => ['nullable', 'array'],
            'special_location_requirements.*.locations.*' => ['nullable', 'string', 'max:255'],
            'special_location_requirements.*.notes' => ['nullable', 'string', 'max:1000'],
            'safety_guidelines_acknowledged' => ['accepted'],
            'safety_guidelines_notes' => ['nullable', 'string', 'max:3000'],
            'equipment_travelers' => ['nullable', 'array'],
            'equipment_travelers.*.traveler_name' => ['nullable', 'string', 'max:255'],
            'equipment_travelers.*.arrival_date' => ['nullable', 'date'],
            'equipment_travelers.*.arrival_flight_number' => ['nullable', 'string', 'max:100'],
            'equipment_travelers.*.departure_date' => ['nullable', 'date'],
            'equipment_travelers.*.departure_flight_number' => ['nullable', 'string', 'max:100'],
            'traveler_equipment_acknowledged' => $travelerEquipmentAcknowledgementRules,
            'imported_equipment' => ['nullable', 'array'],
            'imported_equipment.*.transport_group' => ['nullable', 'string', 'max:100'],
            'imported_equipment.*.item' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.serial_number' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.flight_reference' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.traveler_name' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'imported_equipment.*.unit_value' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'imported_equipment.*.total_value' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'imported_equipment.*.classification' => ['nullable', 'string', 'max:255', Rule::in($equipmentCategoryCodes)],
            'imported_equipment.*.shipping_method' => ['nullable', 'string', 'max:255', Rule::in($equipmentShippingMethodCodes)],
            'imported_equipment.*.origin_country' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.entry_point' => ['nullable', 'string', 'max:255', Rule::in($equipmentEntryPointCodes)],
            'imported_equipment.*.shipping_company_name' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.invoice_number' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.bill_of_lading_number' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.arrival_date' => ['nullable', 'date'],
            'imported_equipment.*.departure_date' => ['nullable', 'date'],
            'imported_equipment.*.customs_center' => ['nullable', 'string', 'max:255', Rule::in($equipmentEntryPointCodes)],
            'imported_equipment.*.attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png'],
            'imported_equipment.*.attachment_path' => ['nullable', 'string', 'max:1000'],
            'imported_equipment.*.attachment_name' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.attachment_mime_type' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.attachment_size' => ['nullable', 'integer', 'min:0'],
            'imported_equipment.*.attachment_uploaded_at' => ['nullable', 'string', 'max:255'],
            'public_security_support' => ['nullable', 'array'],
            'public_security_support.*.day' => ['nullable', 'string', 'max:255'],
            'public_security_support.*.date' => ['nullable', 'date'],
            'public_security_support.*.time_from' => ['nullable', 'date_format:H:i'],
            'public_security_support.*.time_to' => ['nullable', 'date_format:H:i'],
            'public_security_support.*.location' => ['nullable', 'string', 'max:255'],
            'public_security_support.*.requirement' => ['nullable', 'string', 'max:1000'],
            'public_security_support.*.notes' => ['nullable', 'string', 'max:1000'],
            'military_support' => ['nullable', 'array'],
            'military_support.*.day' => ['nullable', 'string', 'max:255'],
            'military_support.*.date' => ['nullable', 'date'],
            'military_support.*.time_from' => ['nullable', 'date_format:H:i'],
            'military_support.*.time_to' => ['nullable', 'date_format:H:i'],
            'military_support.*.location' => ['nullable', 'string', 'max:255'],
            'military_support.*.requirement' => ['nullable', 'string', 'max:1000'],
            'military_support.*.notes' => ['nullable', 'string', 'max:1000'],
            'airport_filming_airport_name' => ['nullable', 'string', 'max:255', Rule::in($airportCodes)],
            'airport_filming_area' => ['nullable', 'string', 'max:255'],
            'airport_filming_date' => ['nullable', 'date'],
            'airport_filming_crew_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'airport_filming_notes' => ['nullable', 'string', 'max:3000'],
            'airport_people' => ['nullable', 'array'],
            'airport_people.*.full_name' => ['nullable', 'string', 'max:255'],
            'airport_people.*.first_name' => ['nullable', 'string', 'max:255'],
            'airport_people.*.second_name' => ['nullable', 'string', 'max:255'],
            'airport_people.*.third_name' => ['nullable', 'string', 'max:255'],
            'airport_people.*.family_name' => ['nullable', 'string', 'max:255'],
            'airport_people.*.nationality' => ['nullable', 'string', 'max:255'],
            'airport_people.*.mother_name' => ['nullable', 'string', 'max:255'],
            'airport_people.*.identity_number' => ['nullable', 'string', 'max:255', $this->airportPeopleIdentityNumberRule($request)],
            'airport_people.*.profession' => ['nullable', 'string', 'max:255'],
            'airport_people.*.address_phone' => ['nullable', 'string', 'max:500'],
            'airport_people.*.entry_reason' => ['nullable', 'string', 'max:500'],
            'airport_people.*.target_area' => ['nullable', 'string', 'max:255'],
            'governmental_scenes' => ['nullable', 'array'],
            'governmental_scenes.*.site_name' => ['nullable', 'string', 'max:255'],
            'governmental_scenes.*.authority' => ['nullable', 'string', 'max:255'],
            'governmental_scenes.*.scene_description' => ['nullable', 'string', 'max:1000'],
            'governmental_scenes.*.filming_date' => ['nullable', 'date'],
            'governmental_scenes_confirmed' => ['nullable', 'boolean'],
            'supporting_notes' => ['nullable', 'string', 'max:3000'],
        ];

        if ($requiresBudgetBreakdown) {
            foreach ($budgetSpendingCategoryCodes as $code) {
                $rules["budget_items.{$code}.units"] = ['required', 'numeric', 'min:0', 'max:1000000'];
                $rules["budget_items.{$code}.total"] = ['required', 'numeric', 'min:0', 'max:999999999.99'];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAnnexPayload(Request $request): array
    {
        $governorateCodes = Governorate::activeCodes();
        $locationTypeCodes = FilmingLocationType::activeCodes();
        $locationTypeApprovalDays = FilmingLocationType::query()
            ->active()
            ->where('approval_days', '>', 0)
            ->pluck('approval_days', 'code')
            ->map(fn ($days): int => (int) $days)
            ->all();
        $specialRequirementCodes = $this->lookupCodesForValidation(FormLookupOption::TYPE_SPECIAL_LOCATION_REQUIREMENT, $request, [
            'filming_locations.*.special_requirements',
        ]);
        $specialRequirementCodes = array_values(array_unique([
            ...$specialRequirementCodes,
            ...array_map('strval', array_keys((array) $request->input('special_location_requirements', []))),
        ]));
        $equipmentEntryPointCodes = $this->lookupCodesForValidation(FormLookupOption::TYPE_EQUIPMENT_ENTRY_POINT, $request, [
            'imported_equipment.*.customs_center',
            'imported_equipment.*.entry_point',
        ]);
        $equipmentCategoryCodes = $this->lookupCodesForValidation(FormLookupOption::TYPE_EQUIPMENT_CATEGORY, $request, [
            'imported_equipment.*.classification',
        ]);
        $equipmentShippingMethodCodes = $this->lookupCodesForValidation(FormLookupOption::TYPE_EQUIPMENT_SHIPPING_METHOD, $request, [
            'imported_equipment.*.shipping_method',
        ]);
        $airportCodes = $this->lookupCodesForValidation(FormLookupOption::TYPE_AIRPORT, $request, [
            'airport_filming_airport_name',
        ]);
        $requiresTravelerEquipmentAcknowledgement = $this->travelerEquipmentAcknowledgementRequired($request);
        $travelerEquipmentAcknowledgementRules = $requiresTravelerEquipmentAcknowledgement
            ? ['required', 'accepted']
            : ['nullable', 'boolean'];

        return $request->validate([
            'work_content_summary_synopsis' => $this->workContentSummarySynopsisRules(),
            'work_content_summary_confirmed' => ['accepted'],
            'cast_crew' => ['nullable', 'array'],
            'cast_crew.*.name' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.first_name' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.second_name' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.third_name' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.family_name' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.role' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.nationality' => ['nullable', 'string', 'max:255'],
            'cast_crew.*.gender' => ['nullable', Rule::in(['male', 'female'])],
            'cast_crew.*.birth_date' => ['nullable', 'date', 'before:today'],
            'cast_crew.*.identity_number' => ['nullable', 'string', 'max:255', $this->castCrewIdentityNumberRule($request)],
            'filming_locations' => ['nullable', 'array'],
            'filming_locations.*.governorate' => ['nullable', 'string', Rule::in($governorateCodes)],
            'filming_locations.*.location_name' => ['nullable', 'string', 'max:255'],
            'filming_locations.*.address' => ['nullable', 'string', 'max:500'],
            'filming_locations.*.nature' => ['nullable', 'string', 'max:255'],
            'filming_locations.*.location_type' => ['nullable', 'string', Rule::in($locationTypeCodes), $this->locationTypeBelongsToGovernorateRule($request, 'filming_locations')],
            'filming_locations.*.special_requirements' => ['nullable', 'array'],
            'filming_locations.*.special_requirements.*' => ['nullable', 'string', Rule::in($specialRequirementCodes)],
            'filming_locations.*.support_requirements' => ['nullable', 'array'],
            'filming_locations.*.support_requirements.*.authority' => ['nullable', 'string', Rule::in(['public_security', 'military'])],
            'filming_locations.*.support_requirements.*.requirement' => ['nullable', 'string', 'max:255'],
            'filming_locations.*.support_requirements.*.date' => ['nullable', 'date', $this->filmingLocationSupportDateWithinRangeRule($request)],
            'filming_locations.*.support_requirements.*.time_from' => ['nullable', 'date_format:H:i'],
            'filming_locations.*.support_requirements.*.time_to' => ['nullable', 'date_format:H:i'],
            'filming_locations.*.support_requirements.*.notes' => [new SupportRequirementNotesRequired(), 'nullable', 'string', 'max:1000'],
            'filming_locations.*.start_date' => ['nullable', 'date', 'after_or_equal:today', $this->filmingLocationStartRespectsApprovalLeadTimeRule($request, 'filming_locations', $locationTypeApprovalDays)],
            'filming_locations.*.end_date' => ['nullable', 'date'],
            'special_location_requirements' => ['nullable', 'array', $this->lookupArrayKeysRule($specialRequirementCodes)],
            'special_location_requirements.*.locations' => ['nullable', 'array'],
            'special_location_requirements.*.locations.*' => ['nullable', 'string', 'max:255'],
            'special_location_requirements.*.notes' => ['nullable', 'string', 'max:1000'],
            'safety_guidelines_acknowledged' => ['nullable', 'boolean'],
            'safety_guidelines_notes' => ['nullable', 'string', 'max:3000'],
            'equipment_travelers' => ['nullable', 'array'],
            'equipment_travelers.*.traveler_name' => ['nullable', 'string', 'max:255'],
            'equipment_travelers.*.arrival_date' => ['nullable', 'date'],
            'equipment_travelers.*.arrival_flight_number' => ['nullable', 'string', 'max:100'],
            'equipment_travelers.*.departure_date' => ['nullable', 'date'],
            'equipment_travelers.*.departure_flight_number' => ['nullable', 'string', 'max:100'],
            'traveler_equipment_acknowledged' => $travelerEquipmentAcknowledgementRules,
            'imported_equipment' => ['nullable', 'array'],
            'imported_equipment.*.transport_group' => ['nullable', 'string', 'max:100'],
            'imported_equipment.*.item' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.serial_number' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.flight_reference' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.traveler_name' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'imported_equipment.*.unit_value' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'imported_equipment.*.total_value' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'imported_equipment.*.classification' => ['nullable', 'string', 'max:255', Rule::in($equipmentCategoryCodes)],
            'imported_equipment.*.shipping_method' => ['nullable', 'string', 'max:255', Rule::in($equipmentShippingMethodCodes)],
            'imported_equipment.*.origin_country' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.entry_point' => ['nullable', 'string', 'max:255', Rule::in($equipmentEntryPointCodes)],
            'imported_equipment.*.shipping_company_name' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.invoice_number' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.bill_of_lading_number' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.arrival_date' => ['nullable', 'date'],
            'imported_equipment.*.departure_date' => ['nullable', 'date'],
            'imported_equipment.*.customs_center' => ['nullable', 'string', 'max:255', Rule::in($equipmentEntryPointCodes)],
            'imported_equipment.*.attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png'],
            'imported_equipment.*.attachment_path' => ['nullable', 'string', 'max:1000'],
            'imported_equipment.*.attachment_name' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.attachment_mime_type' => ['nullable', 'string', 'max:255'],
            'imported_equipment.*.attachment_size' => ['nullable', 'integer', 'min:0'],
            'imported_equipment.*.attachment_uploaded_at' => ['nullable', 'string', 'max:255'],
            'public_security_support' => ['nullable', 'array'],
            'public_security_support.*.day' => ['nullable', 'string', 'max:255'],
            'public_security_support.*.date' => ['nullable', 'date'],
            'public_security_support.*.time_from' => ['nullable', 'date_format:H:i'],
            'public_security_support.*.time_to' => ['nullable', 'date_format:H:i'],
            'public_security_support.*.location' => ['nullable', 'string', 'max:255'],
            'public_security_support.*.requirement' => ['nullable', 'string', 'max:1000'],
            'public_security_support.*.notes' => ['nullable', 'string', 'max:1000'],
            'military_support' => ['nullable', 'array'],
            'military_support.*.day' => ['nullable', 'string', 'max:255'],
            'military_support.*.date' => ['nullable', 'date'],
            'military_support.*.time_from' => ['nullable', 'date_format:H:i'],
            'military_support.*.time_to' => ['nullable', 'date_format:H:i'],
            'military_support.*.location' => ['nullable', 'string', 'max:255'],
            'military_support.*.requirement' => ['nullable', 'string', 'max:1000'],
            'military_support.*.notes' => ['nullable', 'string', 'max:1000'],
            'airport_filming_airport_name' => ['nullable', 'string', 'max:255', Rule::in($airportCodes)],
            'airport_filming_area' => ['nullable', 'string', 'max:255'],
            'airport_filming_date' => ['nullable', 'date'],
            'airport_filming_crew_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'airport_filming_notes' => ['nullable', 'string', 'max:3000'],
            'airport_people' => ['nullable', 'array'],
            'airport_people.*.full_name' => ['nullable', 'string', 'max:255'],
            'airport_people.*.first_name' => ['nullable', 'string', 'max:255'],
            'airport_people.*.second_name' => ['nullable', 'string', 'max:255'],
            'airport_people.*.third_name' => ['nullable', 'string', 'max:255'],
            'airport_people.*.family_name' => ['nullable', 'string', 'max:255'],
            'airport_people.*.nationality' => ['nullable', 'string', 'max:255'],
            'airport_people.*.mother_name' => ['nullable', 'string', 'max:255'],
            'airport_people.*.identity_number' => ['nullable', 'string', 'max:255', $this->airportPeopleIdentityNumberRule($request)],
            'airport_people.*.profession' => ['nullable', 'string', 'max:255'],
            'airport_people.*.address_phone' => ['nullable', 'string', 'max:500'],
            'airport_people.*.entry_reason' => ['nullable', 'string', 'max:500'],
            'airport_people.*.target_area' => ['nullable', 'string', 'max:255'],
            'governmental_scenes' => ['nullable', 'array'],
            'governmental_scenes.*.site_name' => ['nullable', 'string', 'max:255'],
            'governmental_scenes.*.authority' => ['nullable', 'string', 'max:255'],
            'governmental_scenes.*.scene_description' => ['nullable', 'string', 'max:1000'],
            'governmental_scenes.*.filming_date' => ['nullable', 'date'],
            'governmental_scenes_confirmed' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, array<int, mixed>>
     */
    private function draftApplicationValidationRules(array $rules): array
    {
        foreach ($rules as $attribute => $attributeRules) {
            $relaxedRules = [];

            foreach ($attributeRules as $rule) {
                if ($rule === 'required') {
                    if (! in_array('nullable', $relaxedRules, true)) {
                        $relaxedRules[] = 'nullable';
                    }

                    continue;
                }

                if ($rule instanceof RequiredIf) {
                    if (! in_array('nullable', $relaxedRules, true)) {
                        $relaxedRules[] = 'nullable';
                    }

                    continue;
                }

                if ($rule === 'accepted') {
                    if (! in_array('nullable', $relaxedRules, true)) {
                        $relaxedRules[] = 'nullable';
                    }

                    if (! in_array('boolean', $relaxedRules, true)) {
                        $relaxedRules[] = 'boolean';
                    }

                    continue;
                }

                if ($attribute === 'filming_locations.*.start_date' && $rule === 'after_or_equal:today') {
                    continue;
                }

                if ($attribute === 'filming_locations.*.start_date' && $rule instanceof \Closure) {
                    continue;
                }

                if ($attribute === 'filming_locations.*.support_requirements.*.date' && $rule instanceof \Closure) {
                    continue;
                }

                if ($attribute === 'filming_locations.*.support_requirements.*.notes' && $rule instanceof SupportRequirementNotesRequired) {
                    continue;
                }

                $relaxedRules[] = $rule;
            }

            $rules[$attribute] = $relaxedRules;
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeApplicationPayload(array $validated): array
    {
        $defaults = [
            'project_name' => '',
            'project_nationality' => '',
            'project_nationalities' => [],
            'work_category' => '',
            'work_categories' => [],
            'work_category_other' => null,
            'release_method' => '',
            'release_methods' => [],
            'release_method_other' => null,
            'planned_start_date' => null,
            'planned_end_date' => null,
            'schedule_phases' => [],
            'estimated_crew_count' => null,
            'estimated_budget' => null,
            'local_spend_estimate' => null,
            'budget_items' => [],
            'project_summary' => null,
            'producer_name' => null,
            'production_company_name' => null,
            'contact_address' => null,
            'contact_phone' => null,
            'contact_mobile' => null,
            'contact_fax' => null,
            'contact_email' => null,
            'director_name' => null,
            'director_nationality' => null,
            'director_email' => null,
            'director_profile_url' => null,
            'international_producer_name' => null,
            'international_producer_nationality' => null,
            'international_producer_company' => null,
            'international_producer_email' => null,
            'international_producer_profile_url' => null,
            'international_producer_address' => null,
            'international_producer_website' => null,
            'international_liaison_email' => null,
            'international_liaison_mobile' => null,
            'international_account_exists' => false,
            'international_account_user_id' => null,
            'international_account_password' => null,
            'international_account_password_confirmation' => null,
            'supporting_notes' => null,
        ];

        $validated = array_replace($defaults, $validated);
        $projectNationalities = $this->normalizedProjectNationalities($validated);
        $validated['project_nationalities'] = $projectNationalities;
        $validated['project_nationality'] = $projectNationalities[0] ?? (($validated['project_nationality'] ?? null) ?: '');
        $workCategory = $this->normalizedSingleWorkCategory($validated);
        $validated['work_category'] = $workCategory ?: '';
        $validated['work_categories'] = $workCategory ? [$workCategory] : [];
        $validated['schedule_phases'] = array_replace_recursive([
            'preparation' => ['start_date' => null, 'end_date' => null],
            'wrap' => ['start_date' => null, 'end_date' => null],
            'post_production' => ['start_date' => null, 'end_date' => null],
        ], (array) ($validated['schedule_phases'] ?? []));

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function normalizedProjectNationalities(array $payload): array
    {
        $codes = (array) ($payload['project_nationalities'] ?? []);

        if (blank($codes) && filled($payload['project_nationality'] ?? null)) {
            $codes = [(string) $payload['project_nationality']];
        }

        return collect($codes)
            ->filter(fn ($code): bool => filled($code))
            ->map(fn ($code): string => (string) $code)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizedSingleWorkCategory(array $payload): ?string
    {
        if (filled($payload['work_category'] ?? null)) {
            return (string) $payload['work_category'];
        }

        $codes = collect((array) ($payload['work_categories'] ?? []))
            ->filter(fn ($code): bool => filled($code))
            ->map(fn ($code): string => (string) $code)
            ->values();

        return $codes->first(fn (string $code): bool => $code !== 'other') ?: $codes->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function projectRequiresInternationalSection(array $payload, ?Entity $entity = null, ?User $user = null): bool
    {
        if (! $this->canUseInternationalProjectSection($user, $entity)) {
            return false;
        }

        return collect($this->normalizedProjectNationalities($payload))
            ->contains(fn (string $code): bool => $code !== 'jordanian');
    }

    private function canUseInternationalProjectSection(?User $user = null, ?Entity $entity = null): bool
    {
        return ($user?->registration_type !== 'student')
            && ($entity?->registration_type !== 'student');
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationPayloadFromRecord(FilmApplication $application): array
    {
        $metadata = $application->metadata ?? [];
        $annex = (array) data_get($metadata, 'annex', []);

        return $this->normalizeApplicationPayload([
            'project_name' => $application->project_name,
            'project_nationality' => $application->project_nationality,
            'project_nationalities' => $application->projectNationalityCodes(),
            'work_category' => $application->work_category,
            'work_categories' => (array) data_get($metadata, 'project.work_categories', []),
            'work_category_other' => data_get($metadata, 'project.work_category_other'),
            'release_method' => $application->release_method,
            'release_methods' => (array) data_get($metadata, 'project.release_methods', []),
            'release_method_other' => data_get($metadata, 'project.release_method_other'),
            'planned_start_date' => $application->planned_start_date?->toDateString(),
            'planned_end_date' => $application->planned_end_date?->toDateString(),
            'schedule_phases' => (array) data_get($metadata, 'schedule.phases', []),
            'estimated_crew_count' => $application->estimated_crew_count,
            'estimated_budget' => $application->estimated_budget,
            'local_spend_estimate' => data_get($metadata, 'budget.local_spend_estimate'),
            'budget_items' => (array) data_get($metadata, 'budget.items', []),
            'project_summary' => $application->project_summary,
            'producer_name' => data_get($metadata, 'producer.producer_name'),
            'production_company_name' => data_get($metadata, 'producer.production_company_name'),
            'contact_address' => data_get($metadata, 'producer.contact_address'),
            'contact_phone' => data_get($metadata, 'producer.contact_phone'),
            'contact_mobile' => data_get($metadata, 'producer.contact_mobile'),
            'contact_fax' => data_get($metadata, 'producer.contact_fax'),
            'contact_email' => data_get($metadata, 'producer.contact_email'),
            'director_name' => data_get($metadata, 'director.director_name'),
            'director_nationality' => data_get($metadata, 'director.director_nationality'),
            'director_email' => data_get($metadata, 'director.director_email'),
            'director_profile_url' => data_get($metadata, 'director.director_profile_url'),
            'international_producer_name' => data_get($metadata, 'international.international_producer_name'),
            'international_producer_nationality' => data_get($metadata, 'international.international_producer_nationality'),
            'international_producer_company' => data_get($metadata, 'international.international_producer_company'),
            'international_producer_email' => data_get($metadata, 'international.international_producer_email'),
            'international_producer_profile_url' => data_get($metadata, 'international.international_producer_profile_url'),
            'international_producer_address' => data_get($metadata, 'international.international_producer_address'),
            'international_producer_website' => data_get($metadata, 'international.international_producer_website'),
            'international_liaison_email' => data_get($metadata, 'international.international_liaison_email'),
            'international_liaison_mobile' => data_get($metadata, 'international.international_liaison_mobile'),
            'international_account_exists' => filled(data_get($metadata, 'international.account.user_id')) || filled(data_get($metadata, 'international.account.email')),
            'international_account_user_id' => data_get($metadata, 'international.account.user_id'),
            'work_content_summary_synopsis' => data_get($annex, 'work_content_summary.synopsis'),
            'work_content_summary_confirmed' => data_get($annex, 'work_content_summary.confirmed') ? '1' : '0',
            'cast_crew' => (array) data_get($annex, 'cast_crew', []),
            'filming_locations' => (array) data_get($annex, 'filming_locations', []),
            'special_location_requirements' => (array) data_get($annex, 'special_location_requirements', []),
            'safety_guidelines_acknowledged' => data_get($annex, 'safety_guidelines.acknowledged') ? '1' : '0',
            'safety_guidelines_notes' => data_get($annex, 'safety_guidelines.notes'),
            'equipment_travelers' => (array) data_get($annex, 'equipment_travelers', []),
            'traveler_equipment_acknowledged' => data_get($annex, 'traveler_equipment_acknowledged') ? '1' : '0',
            'imported_equipment' => (array) data_get($annex, 'imported_equipment', []),
            'public_security_support' => (array) data_get($annex, 'public_security_support', []),
            'military_support' => (array) data_get($annex, 'military_support', []),
            'airport_filming_airport_name' => data_get($annex, 'airport_filming.airport_name'),
            'airport_filming_area' => data_get($annex, 'airport_filming.area'),
            'airport_filming_date' => data_get($annex, 'airport_filming.filming_date'),
            'airport_filming_crew_count' => data_get($annex, 'airport_filming.crew_count'),
            'airport_filming_notes' => data_get($annex, 'airport_filming.notes'),
            'airport_people' => (array) data_get($annex, 'airport_people', []),
            'governmental_scenes' => (array) data_get($annex, 'governmental_scenes', []),
            'governmental_scenes_confirmed' => data_get($annex, 'governmental_scenes_confirmed') ? '1' : '0',
            'supporting_notes' => data_get($metadata, 'requirements.supporting_notes'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applicationAttributes(array $validated, ?Entity $entity = null, ?User $user = null, array $existingAnnex = []): array
    {
        $workCategory = $this->normalizedSingleWorkCategory($validated);
        $workCategories = $workCategory ? [$workCategory] : [];
        $releaseMethods = array_values(array_filter((array) ($validated['release_methods'] ?? []), fn ($value): bool => filled($value)));
        $releaseMethod = ($validated['release_method'] ?? null) ?: collect($releaseMethods)->first(fn ($value) => $value !== 'other');
        $projectNationalities = $this->normalizedProjectNationalities($validated);
        $requiresInternationalProject = $this->projectRequiresInternationalSection(['project_nationalities' => $projectNationalities], $entity, $user);
        $schedulePhases = (array) ($validated['schedule_phases'] ?? []);
        $internationalMetadata = $requiresInternationalProject ? [
            'international_producer_name' => ($validated['international_producer_name'] ?? null) ?: null,
            'international_producer_nationality' => ($validated['international_producer_nationality'] ?? null) ?: null,
            'international_producer_company' => ($validated['international_producer_company'] ?? null) ?: null,
            'international_producer_email' => ($validated['international_producer_email'] ?? null) ?: null,
            'international_producer_profile_url' => ($validated['international_producer_profile_url'] ?? null) ?: null,
            'international_producer_address' => ($validated['international_producer_address'] ?? null) ?: null,
            'international_producer_website' => ($validated['international_producer_website'] ?? null) ?: null,
            'international_liaison_email' => ($validated['international_liaison_email'] ?? null) ?: null,
            'international_liaison_mobile' => ($validated['international_liaison_mobile'] ?? null) ?: null,
            'account_email' => (($validated['international_liaison_email'] ?? null) ?: ($validated['international_producer_email'] ?? null)) ?: null,
        ] : [];

        $schedulePhases['shooting'] = [
            'start_date' => $validated['planned_start_date'] ?? null,
            'end_date' => $validated['planned_end_date'] ?? null,
        ];

        return [
            'project_name' => $validated['project_name'] ?? '',
            'project_nationality' => $projectNationalities[0] ?? '',
            'project_nationalities' => $projectNationalities,
            'work_category' => $workCategory ?: null,
            'release_method' => $releaseMethod ?: null,
            'planned_start_date' => $validated['planned_start_date'] ?? null,
            'planned_end_date' => $validated['planned_end_date'] ?? null,
            'estimated_crew_count' => ($validated['estimated_crew_count'] ?? null) ?: null,
            'estimated_budget' => ($validated['estimated_budget'] ?? null) ?: null,
            'project_summary' => $validated['project_summary'] ?? null,
            'metadata' => [
                'project' => [
                    'nationalities' => $projectNationalities,
                    'work_categories' => $workCategories ?: ($workCategory ? [$workCategory] : []),
                    'work_category_other' => ($validated['work_category_other'] ?? null) ?: null,
                    'release_methods' => $releaseMethods ?: ($releaseMethod ? [$releaseMethod] : []),
                    'release_method_other' => ($validated['release_method_other'] ?? null) ?: null,
                ],
                'producer' => [
                    'producer_name' => $validated['producer_name'] ?? null,
                    'production_company_name' => $validated['production_company_name'] ?? null,
                    'contact_address' => $validated['contact_address'] ?? null,
                    'contact_phone' => $validated['contact_phone'] ?? null,
                    'contact_mobile' => $validated['contact_mobile'] ?: null,
                    'contact_fax' => $validated['contact_fax'] ?: null,
                    'contact_email' => $validated['contact_email'] ?? null,
                ],
                'director' => [
                    'director_name' => $validated['director_name'] ?? null,
                    'director_nationality' => $validated['director_nationality'] ?? null,
                    'director_email' => $validated['director_email'] ?? null,
                    'director_profile_url' => ($validated['director_profile_url'] ?? null) ?: null,
                ],
                'international' => $internationalMetadata,
                'schedule' => [
                    'phases' => $this->filledKeyedRows($schedulePhases, ['start_date', 'end_date']),
                ],
                'budget' => [
                    'local_spend_estimate' => ($validated['local_spend_estimate'] ?? null) ?: null,
                    'items' => $this->filledKeyedRows((array) ($validated['budget_items'] ?? []), ['units', 'total']),
                ],
                'requirements' => [
                    'required_approvals' => [],
                    'supporting_notes' => ($validated['supporting_notes'] ?? null) ?: null,
                ],
                'annex' => $this->annexMetadata($validated, $existingAnnex),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function annexMetadata(array $validated, array $existingAnnex = []): array
    {
        $filmingLocations = $this->filmingLocationRows((array) ($validated['filming_locations'] ?? []));
        $specialLocationRequirements = $this->specialLocationRequirementRows(
            (array) ($validated['special_location_requirements'] ?? []),
            $filmingLocations,
        );
        $locationSupportRows = $this->locationSupportRows(
            $filmingLocations,
            (array) ($validated['public_security_support'] ?? []),
            (array) ($validated['military_support'] ?? []),
        );

        return [
            'work_content_summary' => [
                'synopsis' => ($validated['work_content_summary_synopsis'] ?? null) ?: null,
                'confirmed' => (bool) ($validated['work_content_summary_confirmed'] ?? false),
            ],
            'cast_crew' => $this->castCrewRows((array) ($validated['cast_crew'] ?? [])),
            'filming_locations' => $filmingLocations,
            'special_location_requirements' => $specialLocationRequirements,
            'safety_guidelines' => [
                'acknowledged' => (bool) ($validated['safety_guidelines_acknowledged'] ?? false),
                'notes' => ($validated['safety_guidelines_notes'] ?? null) ?: null,
            ],
            'equipment_travelers' => $this->filledRows((array) ($validated['equipment_travelers'] ?? []), ['traveler_name', 'arrival_date', 'arrival_flight_number', 'departure_date', 'departure_flight_number']),
            'traveler_equipment_acknowledged' => (bool) ($validated['traveler_equipment_acknowledged'] ?? false),
            'imported_equipment' => $this->importedEquipmentRows(
                (array) ($validated['imported_equipment'] ?? []),
                (array) data_get($existingAnnex, 'imported_equipment', []),
            ),
            'public_security_support' => $locationSupportRows['public_security_support'],
            'military_support' => $locationSupportRows['military_support'],
            'airport_filming' => [
                'airport_name' => ($validated['airport_filming_airport_name'] ?? null) ?: null,
                'area' => ($validated['airport_filming_area'] ?? null) ?: null,
                'filming_date' => ($validated['airport_filming_date'] ?? null) ?: null,
                'crew_count' => ($validated['airport_filming_crew_count'] ?? null) ?: null,
                'notes' => ($validated['airport_filming_notes'] ?? null) ?: null,
            ],
            'airport_people' => $this->airportPeopleRows((array) ($validated['airport_people'] ?? [])),
            'governmental_scenes' => $this->filledRows((array) ($validated['governmental_scenes'] ?? []), ['site_name', 'authority', 'scene_description', 'filming_date']),
            'governmental_scenes_confirmed' => (bool) ($validated['governmental_scenes_confirmed'] ?? false),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    private function filledRows(array $rows, array $keys): array
    {
        return collect($rows)
            ->map(function (array $row) use ($keys): array {
                $normalized = [];

                foreach ($keys as $key) {
                    $value = $row[$key] ?? null;
                    $normalized[$key] = is_string($value) ? trim($value) : $value;
                }

                return $normalized;
            })
            ->filter(fn (array $row): bool => collect($row)
                ->filter(fn ($value): bool => filled($value))
                ->isNotEmpty())
            ->values()
            ->all();
    }

    private function travelerEquipmentAcknowledgementRequired(Request $request): bool
    {
        $travelerRowsHaveData = collect((array) $request->input('equipment_travelers', []))
            ->contains(fn ($row): bool => is_array($row) && collect($row)
                ->filter(fn ($value): bool => filled($value))
                ->isNotEmpty());

        $travelerEquipmentRowsHaveData = collect((array) $request->input('imported_equipment', []))
            ->contains(function ($row): bool {
                if (! is_array($row) || ($row['transport_group'] ?? null) !== 'traveler') {
                    return false;
                }

                return collect(Arr::except($row, ['transport_group']))
                    ->filter(fn ($value): bool => filled($value))
                    ->isNotEmpty();
            });

        return $travelerRowsHaveData || $travelerEquipmentRowsHaveData;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $rows
     * @param  array<int|string, array<string, mixed>>  $existingRows
     * @return array<int, array<string, mixed>>
     */
    private function importedEquipmentRows(array $rows, array $existingRows = []): array
    {
        $existingValues = array_values($existingRows);
        $normalizedRows = [];
        $position = 0;

        foreach ($rows as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $existingRow = (array) ($existingRows[$key] ?? $existingValues[$position] ?? []);
            $transportGroup = $this->nullableTrimmedString($row['transport_group'] ?? data_get($existingRow, 'transport_group')) ?: 'shipping';
            $hasNewShipmentFields = collect(['shipping_company_name', 'invoice_number', 'bill_of_lading_number', 'arrival_date', 'departure_date', 'customs_center', 'attachment'])
                ->contains(fn (string $field): bool => filled($row[$field] ?? null));
            $hasLegacyEquipmentFields = collect(['item', 'serial_number', 'flight_reference', 'traveler_name', 'quantity', 'unit_value', 'total_value', 'classification', 'shipping_method', 'origin_country', 'entry_point'])
                ->contains(fn (string $field): bool => filled($row[$field] ?? null));

            if ($transportGroup === 'traveler' || ($hasLegacyEquipmentFields && ! $hasNewShipmentFields)) {
                $normalized = [
                    'transport_group' => $transportGroup === 'traveler' ? 'traveler' : 'shipping',
                    'item' => $this->nullableTrimmedString($row['item'] ?? null),
                    'serial_number' => $this->nullableTrimmedString($row['serial_number'] ?? null),
                    'flight_reference' => $this->nullableTrimmedString($row['flight_reference'] ?? null),
                    'traveler_name' => $this->nullableTrimmedString($row['traveler_name'] ?? null),
                    'quantity' => $row['quantity'] ?? null,
                    'unit_value' => $row['unit_value'] ?? null,
                    'total_value' => $row['total_value'] ?? null,
                    'classification' => $this->nullableTrimmedString($row['classification'] ?? null),
                    'shipping_method' => $this->nullableTrimmedString($row['shipping_method'] ?? null),
                    'origin_country' => $this->nullableTrimmedString($row['origin_country'] ?? null),
                    'entry_point' => $this->nullableTrimmedString($row['entry_point'] ?? null),
                ];
            } else {
                $attachment = $this->importedEquipmentAttachmentMetadata($row, $existingRow);
                $normalized = [
                    'transport_group' => 'shipping',
                    'shipping_company_name' => $this->nullableTrimmedString($row['shipping_company_name'] ?? null),
                    'invoice_number' => $this->nullableTrimmedString($row['invoice_number'] ?? null),
                    'bill_of_lading_number' => $this->nullableTrimmedString($row['bill_of_lading_number'] ?? null),
                    'arrival_date' => $row['arrival_date'] ?? null,
                    'departure_date' => $row['departure_date'] ?? null,
                    'customs_center' => $this->nullableTrimmedString($row['customs_center'] ?? ($row['entry_point'] ?? null)),
                    ...$attachment,
                ];
            }

            if (collect(Arr::except($normalized, ['transport_group']))->contains(fn ($value): bool => filled($value))) {
                $normalizedRows[] = $normalized;
            }

            $position++;
        }

        return $normalizedRows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $existingRow
     * @return array<string, mixed>
     */
    private function importedEquipmentAttachmentMetadata(array $row, array $existingRow = []): array
    {
        $file = $row['attachment'] ?? null;

        if ($file instanceof UploadedFile) {
            $path = $file->store('application-annex/imported-equipment', 'local');

            return [
                'attachment_path' => $path,
                'attachment_name' => $file->getClientOriginalName(),
                'attachment_mime_type' => $file->getClientMimeType(),
                'attachment_size' => $file->getSize(),
                'attachment_uploaded_at' => now()->toDateTimeString(),
            ];
        }

        return [
            'attachment_path' => $this->nullableTrimmedString($row['attachment_path'] ?? data_get($existingRow, 'attachment_path')),
            'attachment_name' => $this->nullableTrimmedString($row['attachment_name'] ?? data_get($existingRow, 'attachment_name')),
            'attachment_mime_type' => $this->nullableTrimmedString($row['attachment_mime_type'] ?? data_get($existingRow, 'attachment_mime_type')),
            'attachment_size' => $row['attachment_size'] ?? data_get($existingRow, 'attachment_size'),
            'attachment_uploaded_at' => $this->nullableTrimmedString($row['attachment_uploaded_at'] ?? data_get($existingRow, 'attachment_uploaded_at')),
        ];
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return filled($trimmed) ? $trimmed : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function filmingLocationRows(array $rows): array
    {
        return collect($this->filledRows($rows, ['governorate', 'location_name', 'address', 'nature', 'location_type', 'special_requirements', 'support_requirements', 'start_date', 'end_date']))
            ->map(function (array $row): array {
                $row['special_requirements'] = collect((array) ($row['special_requirements'] ?? []))
                    ->map(fn ($value): string => trim((string) $value))
                    ->filter(fn (string $value): bool => filled($value))
                    ->unique()
                    ->values()
                    ->all();
                $row['support_requirements'] = $this->filledRows(
                    (array) ($row['support_requirements'] ?? []),
                    ['authority', 'requirement', 'date', 'time_from', 'time_to', 'notes'],
                );

                return $row;
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $filmingLocations
     * @param  array<int, array<string, mixed>>  $publicSecurityRows
     * @param  array<int, array<string, mixed>>  $militaryRows
     * @return array{public_security_support: array<int, array<string, mixed>>, military_support: array<int, array<string, mixed>>}
     */
    private function locationSupportRows(array $filmingLocations, array $publicSecurityRows, array $militaryRows): array
    {
        $publicSecurity = $this->filledRows($publicSecurityRows, ['day', 'date', 'time_from', 'time_to', 'location', 'requirement', 'notes']);
        $military = $this->filledRows($militaryRows, ['day', 'date', 'time_from', 'time_to', 'location', 'requirement', 'notes']);

        foreach ($filmingLocations as $index => $location) {
            $locationLabel = trim((string) ($location['location_name'] ?? ''));
            $locationLabel = filled($locationLabel) ? $locationLabel : 'Location '.($index + 1);

            foreach ((array) ($location['support_requirements'] ?? []) as $supportRequirement) {
                $authority = (string) ($supportRequirement['authority'] ?? '');

                if (! in_array($authority, ['public_security', 'military'], true)) {
                    continue;
                }

                $row = [
                    'day' => null,
                    'date' => $supportRequirement['date'] ?? null,
                    'time_from' => $supportRequirement['time_from'] ?? null,
                    'time_to' => $supportRequirement['time_to'] ?? null,
                    'location' => $locationLabel,
                    'requirement' => $supportRequirement['requirement'] ?? null,
                    'notes' => $supportRequirement['notes'] ?? null,
                ];

                if ($authority === 'public_security') {
                    $publicSecurity[] = $row;
                } else {
                    $military[] = $row;
                }
            }
        }

        return [
            'public_security_support' => $this->filledRows($publicSecurity, ['day', 'date', 'time_from', 'time_to', 'location', 'requirement', 'notes']),
            'military_support' => $this->filledRows($military, ['day', 'date', 'time_from', 'time_to', 'location', 'requirement', 'notes']),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $submittedRows
     * @param  array<int, array<string, mixed>>  $filmingLocations
     * @return array<string, array<string, mixed>>
     */
    private function specialLocationRequirementRows(array $submittedRows, array $filmingLocations): array
    {
        $rows = $this->filledKeyedRows($submittedRows, ['locations', 'notes']);

        foreach ($filmingLocations as $index => $location) {
            $locationLabel = trim((string) ($location['location_name'] ?? ''));
            $locationLabel = filled($locationLabel) ? $locationLabel : 'Location '.($index + 1);

            foreach ((array) ($location['special_requirements'] ?? []) as $requirement) {
                $requirement = trim((string) $requirement);

                if (blank($requirement)) {
                    continue;
                }

                $locations = (array) data_get($rows, $requirement.'.locations', []);
                $locations[] = $locationLabel;

                $rows[$requirement] = [
                    'locations' => collect($locations)
                        ->filter(fn ($value): bool => filled($value))
                        ->map(fn ($value): string => trim((string) $value))
                        ->unique()
                        ->values()
                        ->all(),
                    'notes' => data_get($rows, $requirement.'.notes'),
                ];
            }
        }

        return $this->filledKeyedRows($rows, ['locations', 'notes']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function castCrewRows(array $rows): array
    {
        $rows = collect($rows)
            ->map(function (array $row): array {
                $nameParts = collect(['first_name', 'second_name', 'third_name', 'family_name'])
                    ->map(fn (string $key): string => trim((string) ($row[$key] ?? '')))
                    ->filter()
                    ->values()
                    ->all();

                if (blank($row['name'] ?? null) && $nameParts !== []) {
                    $row['name'] = implode(' ', $nameParts);
                }

                return $row;
            })
            ->all();

        return $this->filledRows($rows, ['name', 'first_name', 'second_name', 'third_name', 'family_name', 'role', 'nationality', 'gender', 'birth_date', 'identity_number']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function airportPeopleRows(array $rows): array
    {
        $rows = collect($rows)
            ->map(function (array $row): array {
                $nameParts = collect(['first_name', 'second_name', 'third_name', 'family_name'])
                    ->map(fn (string $key): string => trim((string) ($row[$key] ?? '')))
                    ->filter()
                    ->values()
                    ->all();

                if (blank($row['full_name'] ?? null) && $nameParts !== []) {
                    $row['full_name'] = implode(' ', $nameParts);
                }

                return $row;
            })
            ->all();

        return $this->filledRows($rows, ['full_name', 'first_name', 'second_name', 'third_name', 'family_name', 'nationality', 'mother_name', 'identity_number', 'profession', 'address_phone', 'entry_reason', 'target_area']);
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<int, string>  $keys
     * @return array<string, array<string, mixed>>
     */
    private function filledKeyedRows(array $rows, array $keys): array
    {
        return collect($rows)
            ->mapWithKeys(function (array $row, string|int $rowKey) use ($keys): array {
                $normalized = [];

                foreach ($keys as $key) {
                    $value = $row[$key] ?? null;
                    $normalized[$key] = is_string($value) ? trim($value) : $value;
                }

                return [(string) $rowKey => $normalized];
            })
            ->filter(fn (array $row): bool => collect($row)
                ->filter(function ($value): bool {
                    if (is_array($value)) {
                        return collect($value)->filter(fn ($item): bool => filled($item))->isNotEmpty();
                    }

                    return filled($value);
                })
                ->isNotEmpty())
            ->all();
    }

    private function nextCode(): string
    {
        $nextId = (FilmApplication::query()->max('id') ?? 0) + 1;

        return 'REQ-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function appendHistory(FilmApplication $application, string $status, ?string $note, ?int $userId, array $metadata = []): void
    {
        $application->statusHistory()->create([
            'user_id' => $userId,
            'status' => $status,
            'note' => $note,
            'metadata' => $metadata === [] ? null : $metadata,
            'happened_at' => now(),
        ]);
    }

    private function syncInternationalProducerAccount(FilmApplication $application, array $validated, Entity $entity): void
    {
        $email = Str::of((string) (($validated['international_liaison_email'] ?? null) ?: ($validated['international_producer_email'] ?? null)))
            ->trim()
            ->lower()
            ->value();
        $password = $validated['international_account_password'] ?? null;

        if (! filled($email) || ! filled($password)) {
            return;
        }

        $user = User::withTrashed()->where('email', $email)->first() ?? new User(['email' => $email]);

        if ($user->exists && $user->trashed()) {
            $user->restore();
        }

        if (! filled($user->username)) {
            $user->username = $this->uniqueInternationalUsername($email, $user->exists ? $user->getKey() : null);
        }

        $phone = ($validated['international_liaison_mobile'] ?? null) ?: null;
        $attributes = [
            'name' => ($validated['international_producer_name'] ?? null)
                ?: ($validated['international_producer_company'] ?? null)
                ?: $email,
            'email' => $email,
            'status' => 'active',
            'registration_type' => 'international_producer',
            'password' => Hash::make($password),
        ];

        if (filled($phone) && ! User::withTrashed()
            ->where('phone', $phone)
            ->when($user->exists, fn (Builder $query): Builder => $query->whereKeyNot($user->getKey()))
            ->exists()
        ) {
            $attributes['phone'] = $phone;
        }

        $user->forceFill($attributes)->save();

        $user->entities()->syncWithoutDetaching([
            $entity->getKey() => [
                'job_title' => __('app.applications.international_producer_name'),
                'is_primary' => false,
                'status' => 'active',
                'joined_at' => now(),
                'left_at' => null,
            ],
        ]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($entity->getKey());

        try {
            $user->givePermissionTo('applications.view.entity');
        } finally {
            $registrar->setPermissionsTeamId(null);
        }

        $metadata = $application->metadata ?? [];
        data_set($metadata, 'international.account', [
            'user_id' => $user->getKey(),
            'email' => $email,
            'read_only' => true,
        ]);

        $application->forceFill(['metadata' => $metadata])->save();
    }

    private function uniqueInternationalUsername(string $email, ?int $ignoreUserId = null): string
    {
        $base = Str::of(Str::before($email, '@'))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->prepend('intl-')
            ->limit(45, '')
            ->value();

        if (! filled($base) || $base === 'intl-') {
            $base = 'intl-producer';
        }

        $username = $base;
        $counter = 2;

        while (User::withTrashed()
            ->where('username', $username)
            ->when($ignoreUserId, fn (Builder $query): Builder => $query->whereKeyNot($ignoreUserId))
            ->exists()
        ) {
            $suffix = '-'.$counter;
            $username = Str::limit($base, 50 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $username;
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
