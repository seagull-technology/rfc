<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScoutingRequest;
use App\Models\ScoutingRequestCorrespondence;
use App\Models\User;
use App\Notifications\InboxMessageNotification;
use App\Support\AdminApplicantResponseState;
use App\Support\AdminWorkflowState;
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

class ScoutingRequestManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->directoryFilters($request);
        $requests = $this->directoryQuery($filters)
            ->latest()
            ->get();
        $checkpointStats = $requests
            ->map(fn (ScoutingRequest $requestRecord): string => AdminWorkflowState::scoutingCheckpoint($requestRecord)['key'])
            ->countBy();

        return view('admin.scouting.index', [
            'requests' => $requests,
            'openRequests' => $requests->whereNotIn('status', ['approved', 'rejected'])->values(),
            'closedRequests' => $requests->whereIn('status', ['approved', 'rejected'])->values(),
            'applicantResponses' => $requests
                ->mapWithKeys(fn (ScoutingRequest $requestRecord): array => [$requestRecord->getKey() => AdminApplicantResponseState::scouting($requestRecord)]),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
            ],
            'stats' => [
                'total' => $requests->count(),
                'submitted' => $requests->where('status', 'submitted')->count(),
                'under_review' => $requests->where('status', 'under_review')->count(),
                'resolved' => $requests->whereIn('status', ['approved', 'rejected'])->count(),
                'needs_admin_review' => $checkpointStats->get('needs_admin_review', 0),
                'waiting_on_applicant' => $checkpointStats->get('waiting_on_applicant', 0),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->directoryFilters($request);
        $requests = $this->directoryQuery($filters)
            ->latest()
            ->get();

        $rows = $requests->map(fn (ScoutingRequest $record): array => [
            $record->code,
            $record->project_name,
            $record->entity?->displayName() ?? __('app.dashboard.not_available'),
            $record->submittedBy?->displayName() ?? __('app.dashboard.not_available'),
            $record->localizedStatus(),
            $record->localizedStage(),
            $record->submitted_at?->format('Y-m-d H:i') ?? '',
        ])->all();

        return CsvExport::download(
            filename: 'scouting-directory-'.now()->format('Ymd-His').'.csv',
            headers: [
                __('app.admin.scouting.request_code'),
                __('app.applications.project_name'),
                __('app.admin.scouting.applicant_entity'),
                __('app.admin.scouting.submitted_by'),
                __('app.applications.status'),
                __('app.workflow.current_stage'),
                __('app.admin.scouting.submitted_at'),
            ],
            rows: $rows,
        );
    }

    public function show(string $scoutingRequest): View
    {
        $record = $this->findRequest($scoutingRequest);
        $record->load([
            'statusHistory.user',
            'correspondences.createdBy',
        ]);

        return view('admin.scouting.show', [
            'requestRecord' => $record,
            'statusHistory' => $record->statusHistory,
            'correspondences' => $record->correspondences,
            'applicantResponse' => AdminApplicantResponseState::scouting($record),
        ]);
    }

    public function review(Request $request, string $scoutingRequest): RedirectResponse
    {
        $record = $this->findRequest($scoutingRequest);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['under_review', 'needs_clarification', 'approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(in_array($request->input('decision'), ['needs_clarification', 'rejected'], true))],
        ]);

        $record->forceFill([
            'status' => $validated['decision'],
            'current_stage' => match ($validated['decision']) {
                'under_review' => 'rfc_review',
                'needs_clarification' => 'clarification',
                'approved' => 'approved',
                'rejected' => 'rejected',
            },
            'review_note' => $validated['note'] ?: null,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $request->user()?->getKey(),
        ])->save();

        $this->appendHistory($record, $validated['decision'], $validated['note'] ?: null, $request->user()?->getKey());

        NotificationRecipients::except(NotificationRecipients::scoutingApplicants($record), $request->user()?->getKey())
            ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'scouting_status_changed',
                title: $record->project_name,
                body: __('app.notifications.scouting_status_changed_body', [
                    'status' => __('app.statuses.'.$validated['decision']),
                ]),
                routeName: 'scouting-requests.show',
                routeParameters: ['scoutingRequest' => $record->getKey()],
                meta: WorkflowMessageMetadata::scouting($record),
            )));

        return redirect()
            ->route('admin.scouting-requests.show', $record)
            ->with('status', __('app.scouting.review_saved'));
    }

    public function storeCorrespondence(Request $request, string $scoutingRequest): RedirectResponse
    {
        $record = $this->findRequest($scoutingRequest);

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
            'created_by_user_id' => $request->user()?->getKey(),
            'sender_type' => 'admin',
            'sender_name' => $request->user()?->displayName() ?? __('app.admin.navigation.dashboard'),
            'subject' => $validated['subject'] ?: null,
            'message' => $validated['message'],
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime_type' => $attachmentMime,
        ]);

        $this->appendHistory($record, $record->status, __('app.correspondence.history.admin_message'), $request->user()?->getKey());

        NotificationRecipients::except(NotificationRecipients::scoutingApplicants($record), $request->user()?->getKey())
            ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'scouting_correspondence',
                title: $message->subject ?: __('app.contact_center.request_fallback_title', ['code' => $record->code]),
                body: str($message->message)->limit(140)->toString(),
                routeName: 'scouting-requests.show',
                routeParameters: ['scoutingRequest' => $record->getKey()],
                meta: WorkflowMessageMetadata::scouting($record),
            )));

        return redirect()
            ->route('admin.scouting-requests.show', $record)
            ->with('status', __('app.correspondence.sent'));
    }

    public function downloadStory(string $scoutingRequest): StreamedResponse|RedirectResponse
    {
        $record = $this->findRequest($scoutingRequest);

        if (! $record->story_file_path || ! Storage::disk('local')->exists($record->story_file_path)) {
            return redirect()
                ->route('admin.scouting-requests.show', $record)
                ->withErrors(['story_file' => __('app.scouting.story_file_missing')]);
        }

        return Storage::disk('local')->download($record->story_file_path, $record->story_file_name ?: basename($record->story_file_path));
    }

    public function downloadCorrespondenceAttachment(string $scoutingRequest, string $correspondence): StreamedResponse|RedirectResponse
    {
        $record = $this->findRequest($scoutingRequest);
        $message = $this->findCorrespondence($correspondence, $record);

        if (! $message->attachment_path || ! Storage::disk('local')->exists($message->attachment_path)) {
            return redirect()
                ->route('admin.scouting-requests.show', $record)
                ->withErrors(['correspondence' => __('app.correspondence.file_missing')]);
        }

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name ?: basename($message->attachment_path));
    }

    private function findRequest(string $scoutingRequest): ScoutingRequest
    {
        return ScoutingRequest::query()
            ->with(['entity.group', 'submittedBy', 'reviewedBy'])
            ->findOrFail($scoutingRequest);
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
        $query = ScoutingRequest::query()
            ->with(['entity', 'submittedBy', 'reviewedBy']);
        $query->withMax([
            'statusHistory as last_clarification_at' => fn (Builder $builder): Builder => $builder->where('status', 'needs_clarification'),
        ], 'happened_at');
        $query->withMax([
            'correspondences as last_applicant_correspondence_at' => fn (Builder $builder): Builder => $builder->where('sender_type', 'applicant'),
        ], 'created_at');

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

    private function findCorrespondence(string $correspondence, ScoutingRequest $record): ScoutingRequestCorrespondence
    {
        return ScoutingRequestCorrespondence::query()
            ->where('scouting_request_id', $record->getKey())
            ->findOrFail($correspondence);
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
}
