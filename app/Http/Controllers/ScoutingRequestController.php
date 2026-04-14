<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\ScoutingRequestCorrespondence;
use App\Models\ScoutingRequest;
use App\Notifications\InboxMessageNotification;
use App\Support\NotificationRecipients;
use App\Support\ScoutingRequestOverview;
use App\Support\WorkflowMessageMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScoutingRequestController extends Controller
{
    public function index(Request $request): View
    {
        [$user, $entity] = $this->applicantContext($request);

        abort_unless($user->can('applications.view.entity'), 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'draft', 'submitted', 'under_review', 'needs_clarification', 'approved', 'rejected'])],
        ]);

        $query = ScoutingRequest::query()
            ->with(['entity', 'submittedBy', 'reviewedBy'])
            ->where('entity_id', $entity->getKey());

        if (filled($filters['q'] ?? null)) {
            $search = trim((string) $filters['q']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('code', 'like', '%'.$search.'%')
                    ->orWhere('project_name', 'like', '%'.$search.'%');
            });
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }

        $requests = $query->latest()->get();

        return view('scouting.index', [
            'user' => $user,
            'entity' => $entity,
            'requests' => $requests,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
            ],
            'stats' => [
                'total' => $requests->count(),
                'active_reviews' => $requests->whereIn('status', ['submitted', 'under_review'])->count(),
                'approved' => $requests->where('status', 'approved')->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        [$user, $entity] = $this->applicantContext($request);

        abort_unless($user->can('applications.create'), 403);

        return view('scouting.create', [
            'user' => $user,
            'entity' => $entity,
            'requestRecord' => new ScoutingRequest([
                'project_name' => '',
                'project_nationality' => 'jordanian',
                'status' => 'draft',
                'current_stage' => 'draft',
                'metadata' => [
                    'producer' => [
                        'producer_name' => $user->name,
                        'production_company_name' => $entity->displayName(),
                        'producer_email' => $entity->email ?: $user->email,
                        'contact_address' => data_get($entity->metadata, 'address'),
                        'producer_phone' => $entity->phone,
                        'producer_mobile' => $user->phone,
                        'producer_fax' => data_get($entity->metadata, 'fax'),
                    ],
                    'locations' => [
                        [
                            'governorate' => 'amman',
                            'location_name' => '',
                            'google_map_url' => '',
                            'location_nature' => 'public_site',
                            'start_date' => '',
                            'end_date' => '',
                        ],
                    ],
                    'crew' => [
                        [
                            'name' => '',
                            'job_title' => '',
                            'nationality' => 'jordanian',
                            'national_id_passport' => '',
                        ],
                    ],
                ],
            ]),
            'formAction' => route('scouting-requests.store'),
            'submitLabel' => __('app.scouting.save_draft_action'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);

        abort_unless($user->can('applications.create'), 403);

        $validated = $this->validatePayload($request);

        $storyFileMeta = $this->storeStoryFile($request);

        $record = ScoutingRequest::query()->create([
            ...$this->attributes($validated, $storyFileMeta),
            'code' => $this->nextCode(),
            'entity_id' => $entity->getKey(),
            'submitted_by_user_id' => $user->getKey(),
            'status' => 'draft',
            'current_stage' => 'draft',
        ]);

        $this->appendHistory($record, 'draft', __('app.scouting.history.created'), $user->getKey());

        return redirect()
            ->route('scouting-requests.show', $record)
            ->with('status', __('app.scouting.created'));
    }

    public function show(Request $request, string $scoutingRequest): View
    {
        [$user, $entity] = $this->applicantContext($request);

        abort_unless($user->can('applications.view.entity'), 403);

        $record = $this->findApplicantRequest($scoutingRequest, $entity);
        $record->load([
            'statusHistory.user',
            'correspondences.createdBy',
        ]);

        return view('scouting.show', [
            'user' => $user,
            'entity' => $entity,
            'requestRecord' => $record,
            'requestOverview' => ScoutingRequestOverview::forRequest($record),
            'statusHistory' => $record->statusHistory,
            'correspondences' => $record->correspondences,
        ]);
    }

    public function edit(Request $request, string $scoutingRequest): View
    {
        [$user, $entity] = $this->applicantContext($request);

        abort_unless($user->can('applications.update.entity'), 403);

        $record = $this->findApplicantRequest($scoutingRequest, $entity);

        abort_unless($record->canBeEditedByApplicant(), 403);

        return view('scouting.edit', [
            'user' => $user,
            'entity' => $entity,
            'requestRecord' => $record,
            'formAction' => route('scouting-requests.update', $record),
            'submitLabel' => __('app.scouting.update_draft_action'),
        ]);
    }

    public function update(Request $request, string $scoutingRequest): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);

        abort_unless($user->can('applications.update.entity'), 403);

        $record = $this->findApplicantRequest($scoutingRequest, $entity);

        abort_unless($record->canBeEditedByApplicant(), 403);

        $validated = $this->validatePayload($request, true);
        $storyFileMeta = $this->storeStoryFile($request, $record);

        $record->forceFill($this->attributes($validated, $storyFileMeta, $record))->save();

        $this->appendHistory($record, $record->status, __('app.scouting.history.updated'), $user->getKey());

        return redirect()
            ->route('scouting-requests.show', $record)
            ->with('status', __('app.scouting.updated'));
    }

    public function submit(Request $request, string $scoutingRequest): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);

        abort_unless($user->can('applications.submit'), 403);

        $record = $this->findApplicantRequest($scoutingRequest, $entity);

        abort_unless($record->canBeSubmittedByApplicant(), 403);
        $wasClarificationResponse = $record->status === 'needs_clarification';

        $record->forceFill([
            'status' => 'submitted',
            'current_stage' => 'intake',
            'submitted_at' => now(),
        ])->save();

        $this->appendHistory($record, 'submitted', __('app.scouting.history.submitted'), $user->getKey());

        $record->loadMissing('entity');

        NotificationRecipients::except(NotificationRecipients::adminUsers(), $user->getKey())
            ->each(fn ($recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'scouting_submitted',
                title: $record->project_name,
                body: __('app.notifications.scouting_submitted_body', [
                    'code' => $record->code,
                    'entity' => $record->entity?->displayName() ?? __('app.dashboard.no_entity'),
                ]),
                routeName: 'admin.scouting-requests.show',
                routeParameters: ['scoutingRequest' => $record->getKey()],
                meta: [
                    ...WorkflowMessageMetadata::scouting($record),
                    ...$this->adminApplicantResponseNotificationMeta($wasClarificationResponse, __('app.notifications.applicant_response_resubmission')),
                ],
            )));

        return redirect()
            ->route('scouting-requests.show', $record)
            ->with('status', __('app.scouting.submitted'));
    }

    public function storeCorrespondence(Request $request, string $scoutingRequest): RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);

        abort_unless($user->can('applications.view.entity'), 403);

        $record = $this->findApplicantRequest($scoutingRequest, $entity);

        abort_unless($record->canReceiveApplicantCorrespondence(), 403);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png'],
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;

        if ($request->file('attachment')) {
            $attachmentPath = $request->file('attachment')->store('scouting-correspondence/'.$record->getKey(), 'local');
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
                typeKey: 'scouting_correspondence',
                title: $message->subject ?: __('app.contact_center.request_fallback_title', ['code' => $record->code]),
                body: str($message->message)->limit(140)->toString(),
                routeName: 'admin.scouting-requests.show',
                routeParameters: ['scoutingRequest' => $record->getKey()],
                meta: [
                    ...WorkflowMessageMetadata::scouting($record),
                    ...$this->adminApplicantResponseNotificationMeta(
                        $reopenedForReview,
                        __('app.notifications.applicant_response_correspondence', [
                            'item' => $message->subject ?: __('app.correspondence.tab'),
                        ]),
                    ),
                ],
            )));

        return redirect()
            ->route('scouting-requests.show', $record)
            ->with('status', __('app.correspondence.sent'));
    }

    public function downloadCorrespondenceAttachment(Request $request, string $scoutingRequest, string $correspondence): StreamedResponse|RedirectResponse
    {
        [, $entity] = $this->applicantContext($request);

        $record = $this->findApplicantRequest($scoutingRequest, $entity);
        $message = $this->findApplicantCorrespondence($correspondence, $record);

        if (! $message->attachment_path || ! Storage::disk('local')->exists($message->attachment_path)) {
            return redirect()
                ->route('scouting-requests.show', $record)
                ->withErrors(['correspondence' => __('app.correspondence.file_missing')]);
        }

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name ?: basename($message->attachment_path));
    }

    public function downloadStory(Request $request, string $scoutingRequest): StreamedResponse|RedirectResponse
    {
        [$user, $entity] = $this->applicantContext($request);

        abort_unless($user->can('applications.view.entity'), 403);

        $record = $this->findApplicantRequest($scoutingRequest, $entity);

        if (! $record->story_file_path || ! Storage::disk('local')->exists($record->story_file_path)) {
            return redirect()
                ->route('scouting-requests.show', $record)
                ->withErrors(['story_file' => __('app.scouting.story_file_missing')]);
        }

        return Storage::disk('local')->download($record->story_file_path, $record->story_file_name ?: basename($record->story_file_path));
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

    private function findApplicantRequest(string $requestId, Entity $entity): ScoutingRequest
    {
        return ScoutingRequest::query()
            ->with(['entity', 'submittedBy', 'reviewedBy'])
            ->where('entity_id', $entity->getKey())
            ->findOrFail($requestId);
    }

    private function findApplicantCorrespondence(string $correspondence, ScoutingRequest $record): ScoutingRequestCorrespondence
    {
        return ScoutingRequestCorrespondence::query()
            ->where('scouting_request_id', $record->getKey())
            ->findOrFail($correspondence);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'project_nationality' => ['required', Rule::in(['jordanian', 'international'])],
            'producer_name' => ['required', 'string', 'max:255'],
            'producer_nationality' => ['required', Rule::in(['jordanian', 'international'])],
            'production_company_name' => ['required', 'string', 'max:255'],
            'producer_phone' => ['required', 'string', 'max:50'],
            'producer_mobile' => ['required', 'string', 'max:50'],
            'producer_fax' => ['required', 'string', 'max:50'],
            'producer_email' => ['required', 'email', 'max:255'],
            'producer_profile_url' => ['nullable', 'url', 'max:500'],
            'contact_address' => ['required', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'liaison_name' => ['required', 'string', 'max:255'],
            'liaison_job_title' => ['required', 'string', 'max:255'],
            'liaison_email' => ['required', 'email', 'max:255'],
            'liaison_mobile' => ['required', 'string', 'max:50'],
            'responsible_person_name' => ['required', 'string', 'max:255'],
            'responsible_person_nationality' => ['required', Rule::in(['jordanian', 'international'])],
            'production_types' => ['required', 'array', 'min:1'],
            'production_types.*' => [Rule::in(['feature_film', 'short_film', 'animation', 'music_video', 'television', 'commercial', 'documentary', 'other'])],
            'production_type_other' => ['nullable', 'string', 'max:255'],
            'scout_start_date' => ['required', 'date'],
            'scout_end_date' => ['required', 'date', 'after_or_equal:scout_start_date'],
            'production_start_date' => ['nullable', 'date'],
            'production_end_date' => ['nullable', 'date'],
            'project_summary' => ['required', 'string', 'max:5000'],
            'story_text' => ['nullable', 'string', 'max:5000'],
            'story_file' => [$isUpdate ? 'nullable' : 'nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx'],
            'locations' => ['required', 'array', 'min:1'],
            'locations.*.governorate' => ['required', 'string', 'max:50'],
            'locations.*.location_name' => ['required', 'string', 'max:255'],
            'locations.*.google_map_url' => ['nullable', 'string', 'max:500'],
            'locations.*.location_nature' => ['required', 'string', 'max:100'],
            'locations.*.start_date' => ['required', 'date'],
            'locations.*.end_date' => ['required', 'date'],
            'crew' => ['required', 'array', 'min:1'],
            'crew.*.name' => ['required', 'string', 'max:255'],
            'crew.*.job_title' => ['required', 'string', 'max:255'],
            'crew.*.nationality' => ['required', Rule::in(['jordanian', 'international'])],
            'crew.*.national_id_passport' => ['required', 'string', 'max:255'],
        ]);
    }

    /**
     * @return array{path:?string,name:?string,mime:?string}
     */
    private function storeStoryFile(Request $request, ?ScoutingRequest $record = null): array
    {
        if (! $request->file('story_file')) {
            return [
                'path' => $record?->story_file_path,
                'name' => $record?->story_file_name,
                'mime' => $record?->story_file_mime_type,
            ];
        }

        if ($record?->story_file_path && Storage::disk('local')->exists($record->story_file_path)) {
            Storage::disk('local')->delete($record->story_file_path);
        }

        $file = $request->file('story_file');
        $path = $file->store('scouting-story-files/'.($record?->getKey() ?: 'draft'), 'local');

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array{path:?string,name:?string,mime:?string}  $storyFileMeta
     * @return array<string, mixed>
     */
    private function attributes(array $validated, array $storyFileMeta, ?ScoutingRequest $record = null): array
    {
        return [
            'project_name' => $validated['project_name'],
            'project_nationality' => $validated['project_nationality'],
            'scout_start_date' => $validated['scout_start_date'],
            'scout_end_date' => $validated['scout_end_date'],
            'production_start_date' => $validated['production_start_date'] ?: null,
            'production_end_date' => $validated['production_end_date'] ?: null,
            'project_summary' => $validated['project_summary'],
            'story_text' => $validated['story_text'] ?: null,
            'story_file_path' => $storyFileMeta['path'],
            'story_file_name' => $storyFileMeta['name'],
            'story_file_mime_type' => $storyFileMeta['mime'],
            'metadata' => [
                'producer' => [
                    'producer_name' => $validated['producer_name'],
                    'producer_nationality' => $validated['producer_nationality'],
                    'production_company_name' => $validated['production_company_name'],
                    'producer_phone' => $validated['producer_phone'],
                    'producer_mobile' => $validated['producer_mobile'],
                    'producer_fax' => $validated['producer_fax'],
                    'producer_email' => $validated['producer_email'],
                    'producer_profile_url' => $validated['producer_profile_url'] ?? null,
                    'contact_address' => $validated['contact_address'],
                    'website_url' => $validated['website_url'] ?? null,
                    'liaison_name' => $validated['liaison_name'],
                    'liaison_job_title' => $validated['liaison_job_title'],
                    'liaison_email' => $validated['liaison_email'],
                    'liaison_mobile' => $validated['liaison_mobile'],
                ],
                'responsible_person' => [
                    'name' => $validated['responsible_person_name'],
                    'nationality' => $validated['responsible_person_nationality'],
                ],
                'production' => [
                    'types' => array_values($validated['production_types']),
                    'type_other' => $validated['production_type_other'] ?: null,
                ],
                'locations' => array_values($validated['locations']),
                'crew' => array_values($validated['crew']),
            ],
        ];
    }

    private function appendHistory(ScoutingRequest $record, string $status, ?string $note, ?int $userId): void
    {
        $record->statusHistory()->create([
            'user_id' => $userId,
            'status' => $status,
            'note' => $note,
            'happened_at' => now(),
        ]);
    }

    private function nextCode(): string
    {
        $nextId = (ScoutingRequest::query()->max('id') ?? 0) + 1;

        return 'SCOUT-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
    }

    private function requeueAfterApplicantClarification(ScoutingRequest $record): bool
    {
        if ($record->status !== 'needs_clarification') {
            return false;
        }

        $record->forceFill([
            'status' => 'submitted',
            'current_stage' => 'intake',
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
