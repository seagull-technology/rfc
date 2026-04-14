<?php

namespace App\Http\Controllers;

use App\Models\ApplicationCorrespondence;
use App\Models\ContactCenterMessage;
use App\Models\Entity;
use App\Models\ScoutingRequestCorrespondence;
use App\Support\NotificationPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactCenterController extends Controller
{
    public function index(Request $request): View
    {
        [$user, $entity] = $this->applicantContext($request);

        abort_unless($user->can('applications.view.entity'), 403);

        $user->unreadNotifications
            ->filter(fn ($notification) => NotificationPresenter::isInbox($notification))
            ->each
            ->markAsRead();

        return view('contact-center.index', [
            'user' => $user,
            'entity' => $entity,
            'messages' => $this->applicantMessages($entity),
        ]);
    }

    public function download(string $message): StreamedResponse|RedirectResponse
    {
        $record = ContactCenterMessage::query()->with('entity')->findOrFail($message);

        abort_unless($this->canAccessMessage($record), 403);

        if (! $record->attachment_path || ! Storage::disk('local')->exists($record->attachment_path)) {
            return redirect()
                ->route('contact-center.index')
                ->withErrors(['attachment' => __('app.contact_center.file_missing')]);
        }

        return Storage::disk('local')->download($record->attachment_path, $record->attachment_name ?: basename($record->attachment_path));
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

    private function canAccessMessage(ContactCenterMessage $message): bool
    {
        $user = request()->user();
        $entity = $user?->primaryEntity();

        if (! $user || ! $entity) {
            return false;
        }

        return $message->recipient_scope === 'all' || $message->entity_id === $entity->getKey();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function applicantMessages(Entity $entity): Collection
    {
        $broadcasts = ContactCenterMessage::query()
            ->with(['entity', 'createdBy'])
            ->where(function ($query) use ($entity): void {
                $query
                    ->where('recipient_scope', 'all')
                    ->orWhere('entity_id', $entity->getKey());
            })
            ->latest()
            ->get()
            ->map(function (ContactCenterMessage $message): array {
                return [
                    'key' => 'contact-'.$message->getKey(),
                    'title' => $message->title,
                    'type_label' => $message->localizedMessageType(),
                    'sender_label' => $message->sender_name,
                    'station_label' => __('app.contact_center.stations.rfc'),
                    'created_at' => $message->created_at,
                    'body' => $message->message,
                    'attachment_url' => $message->attachment_path ? route('contact-center.messages.download', $message) : null,
                    'attachment_name' => $message->attachment_name,
                    'workflow_checkpoint_label' => null,
                    'workflow_checkpoint_class' => null,
                    'source_url' => null,
                    'source_label' => null,
                ];
            });

        $applicationMessages = ApplicationCorrespondence::query()
            ->with(['application.entity', 'createdBy'])
            ->whereHas('application', fn ($query) => $query->where('entity_id', $entity->getKey()))
            ->latest()
            ->get()
            ->map(function (ApplicationCorrespondence $message): array {
                return [
                    'key' => 'application-'.$message->getKey(),
                    'title' => $message->subject ?: __('app.contact_center.request_fallback_title', ['code' => $message->application?->code]),
                    'type_label' => __('app.contact_center.message_types.production_request'),
                    'sender_label' => $message->sender_name,
                    'station_label' => __('app.contact_center.stations.production_request'),
                    'created_at' => $message->created_at,
                    'body' => $message->message,
                    'attachment_url' => $message->attachment_path ? route('applications.correspondence.download', [$message->application, $message]) : null,
                    'attachment_name' => $message->attachment_name,
                    ...\App\Support\WorkflowMessageMetadata::application($message->application),
                    'source_url' => route('applications.show', $message->application),
                    'source_label' => $message->application?->code,
                ];
            });

        $scoutingMessages = ScoutingRequestCorrespondence::query()
            ->with(['scoutingRequest.entity', 'createdBy'])
            ->whereHas('scoutingRequest', fn ($query) => $query->where('entity_id', $entity->getKey()))
            ->latest()
            ->get()
            ->map(function (ScoutingRequestCorrespondence $message): array {
                return [
                    'key' => 'scouting-'.$message->getKey(),
                    'title' => $message->subject ?: __('app.contact_center.request_fallback_title', ['code' => $message->scoutingRequest?->code]),
                    'type_label' => __('app.contact_center.message_types.scouting_request'),
                    'sender_label' => $message->sender_name,
                    'station_label' => __('app.contact_center.stations.scouting_request'),
                    'created_at' => $message->created_at,
                    'body' => $message->message,
                    'attachment_url' => $message->attachment_path ? route('scouting-requests.correspondence.download', [$message->scoutingRequest, $message]) : null,
                    'attachment_name' => $message->attachment_name,
                    ...\App\Support\WorkflowMessageMetadata::scouting($message->scoutingRequest),
                    'source_url' => route('scouting-requests.show', $message->scoutingRequest),
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
