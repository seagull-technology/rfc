<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationCorrespondence;
use App\Models\ContactCenterMessage;
use App\Models\Entity;
use App\Models\ScoutingRequestCorrespondence;
use App\Models\User;
use App\Notifications\InboxMessageNotification;
use App\Support\NotificationPresenter;
use App\Support\NotificationRecipients;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactCenterController extends Controller
{
    public function index(Request $request): View
    {
        $request->user()?->unreadNotifications
            ->filter(fn ($notification) => NotificationPresenter::isInbox($notification))
            ->each
            ->markAsRead();

        return view('admin.contact-center.index', [
            'messages' => $this->adminMessages(),
            'recipientEntities' => Entity::query()
                ->whereIn('registration_type', ['student', 'company', 'ngo', 'school'])
                ->where('status', 'active')
                ->orderBy('name_ar')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message_type' => ['required', Rule::in(['general_notice', 'official_reply', 'decision', 'follow_up'])],
            'message' => ['required', 'string', 'max:5000'],
            'recipient_scope' => ['required', Rule::in(['all', 'specific'])],
            'entity_id' => ['nullable', 'integer', Rule::requiredIf($request->input('recipient_scope') === 'specific'), 'exists:entities,id'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png'],
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;

        if ($request->file('attachment')) {
            $attachmentPath = $request->file('attachment')->store('contact-center-messages', 'local');
            $attachmentName = $request->file('attachment')->getClientOriginalName();
            $attachmentMime = $request->file('attachment')->getClientMimeType();
        }

        $message = ContactCenterMessage::query()->create([
            'created_by_user_id' => $request->user()?->getKey(),
            'entity_id' => $validated['recipient_scope'] === 'specific' ? (int) $validated['entity_id'] : null,
            'recipient_scope' => $validated['recipient_scope'],
            'sender_name' => $request->user()?->displayName() ?? __('app.admin.navigation.dashboard'),
            'title' => $validated['title'],
            'message_type' => $validated['message_type'],
            'message' => $validated['message'],
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime_type' => $attachmentMime,
        ]);

        $recipients = $validated['recipient_scope'] === 'specific'
            ? NotificationRecipients::entityUsers(Entity::query()->findOrFail((int) $validated['entity_id']))
            : User::query()
                ->where('status', 'active')
                ->whereHas('entities', fn ($query) => $query->whereIn('entities.registration_type', ['student', 'company', 'ngo', 'school']))
                ->get()
                ->unique(fn (User $user): int => $user->getKey())
                ->values();

        NotificationRecipients::except($recipients, $request->user()?->getKey())
            ->each(fn (User $recipient) => $recipient->notify(new InboxMessageNotification(
                typeKey: 'contact_center_message',
                title: $message->title,
                body: str($message->message)->limit(140)->toString(),
                routeName: 'contact-center.index',
                routeParameters: [],
            )));

        return redirect()
            ->route('admin.contact-center.index')
            ->with('status', __('app.contact_center.created'));
    }

    public function download(string $message): StreamedResponse|RedirectResponse
    {
        $record = ContactCenterMessage::query()->findOrFail($message);

        if (! $record->attachment_path || ! Storage::disk('local')->exists($record->attachment_path)) {
            return redirect()
                ->route('admin.contact-center.index')
                ->withErrors(['attachment' => __('app.contact_center.file_missing')]);
        }

        return Storage::disk('local')->download($record->attachment_path, $record->attachment_name ?: basename($record->attachment_path));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function adminMessages(): Collection
    {
        $broadcasts = ContactCenterMessage::query()
            ->with(['entity', 'createdBy'])
            ->latest()
            ->get()
            ->map(function (ContactCenterMessage $message): array {
                return [
                    'key' => 'contact-'.$message->getKey(),
                    'title' => $message->title,
                    'type_label' => $message->localizedMessageType(),
                    'recipient_label' => $message->recipient_scope === 'all'
                        ? __('app.contact_center.recipient_scopes.all')
                        : ($message->entity?->displayName() ?? __('app.dashboard.not_available')),
                    'station_label' => __('app.contact_center.stations.rfc'),
                    'created_at' => $message->created_at,
                    'body' => $message->message,
                    'attachment_url' => $message->attachment_path ? route('admin.contact-center.messages.download', $message) : null,
                    'attachment_name' => $message->attachment_name,
                    'workflow_checkpoint_label' => null,
                    'workflow_checkpoint_class' => null,
                    'source_url' => null,
                    'source_label' => null,
                ];
            });

        $applicationMessages = ApplicationCorrespondence::query()
            ->with(['application.entity', 'createdBy'])
            ->latest()
            ->get()
            ->map(function (ApplicationCorrespondence $message): array {
                return [
                    'key' => 'application-'.$message->getKey(),
                    'title' => $message->subject ?: __('app.contact_center.request_fallback_title', ['code' => $message->application?->code]),
                    'type_label' => __('app.contact_center.message_types.production_request'),
                    'recipient_label' => $message->application?->entity?->displayName() ?? __('app.dashboard.not_available'),
                    'station_label' => __('app.contact_center.stations.production_request'),
                    'created_at' => $message->created_at,
                    'body' => $message->message,
                    'attachment_url' => $message->attachment_path ? route('admin.applications.correspondence.download', [$message->application, $message]) : null,
                    'attachment_name' => $message->attachment_name,
                    ...\App\Support\WorkflowMessageMetadata::application($message->application),
                    'source_url' => route('admin.applications.show', $message->application),
                    'source_label' => $message->application?->code,
                ];
            });

        $scoutingMessages = ScoutingRequestCorrespondence::query()
            ->with(['scoutingRequest.entity', 'createdBy'])
            ->latest()
            ->get()
            ->map(function (ScoutingRequestCorrespondence $message): array {
                return [
                    'key' => 'scouting-'.$message->getKey(),
                    'title' => $message->subject ?: __('app.contact_center.request_fallback_title', ['code' => $message->scoutingRequest?->code]),
                    'type_label' => __('app.contact_center.message_types.scouting_request'),
                    'recipient_label' => $message->scoutingRequest?->entity?->displayName() ?? __('app.dashboard.not_available'),
                    'station_label' => __('app.contact_center.stations.scouting_request'),
                    'created_at' => $message->created_at,
                    'body' => $message->message,
                    'attachment_url' => $message->attachment_path ? route('admin.scouting-requests.correspondence.download', [$message->scoutingRequest, $message]) : null,
                    'attachment_name' => $message->attachment_name,
                    ...\App\Support\WorkflowMessageMetadata::scouting($message->scoutingRequest),
                    'source_url' => route('admin.scouting-requests.show', $message->scoutingRequest),
                    'source_label' => $message->scoutingRequest?->code,
                ];
            });

        return $broadcasts
            ->concat($applicationMessages)
            ->concat($scoutingMessages)
            ->sortByDesc(fn (array $message) => $message['created_at']?->timestamp ?? 0)
            ->values();
    }
}
