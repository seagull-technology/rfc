<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;

class NotificationPresenter
{
    /**
     * @return array<int, string>
     */
    public static function inboxTypeKeys(): array
    {
        return [
            'contact_center_message',
            'application_correspondence',
            'scouting_correspondence',
        ];
    }

    public static function isInbox(DatabaseNotification $notification): bool
    {
        return in_array((string) data_get($notification->data, 'type_key'), self::inboxTypeKeys(), true);
    }

    /**
     * @return array{title:string,body:string,url:string,highlight_active:bool,highlight_title:?string,highlight_summary:?string,highlight_class:string}
     */
    public static function present(DatabaseNotification $notification): array
    {
        $typeKey = (string) data_get($notification->data, 'type_key');
        $body = (string) data_get($notification->data, 'body', __('app.portal.notifications_empty'));
        $checkpointLabel = data_get($notification->data, 'workflow_checkpoint_label');
        $highlight = [
            'highlight_active' => (bool) data_get($notification->data, 'notification_highlight_active', data_get($notification->data, 'applicant_response_active', false)),
            'highlight_title' => data_get($notification->data, 'notification_highlight_title', data_get($notification->data, 'applicant_response_title')),
            'highlight_summary' => data_get($notification->data, 'notification_highlight_summary', data_get($notification->data, 'applicant_response_summary')),
            'highlight_class' => (string) data_get($notification->data, 'notification_highlight_class', data_get($notification->data, 'applicant_response_class', 'primary')),
        ];

        if (filled($checkpointLabel)) {
            $body = __('app.notifications.inbox_with_checkpoint', [
                'checkpoint' => $checkpointLabel,
                'body' => $body,
            ]);
        }

        if ($typeKey === 'authority_approval_requested' && ! $highlight['highlight_active']) {
            $highlight = [
                'highlight_active' => true,
                'highlight_title' => __('app.notifications.authority_action_required_title'),
                'highlight_summary' => __('app.notifications.authority_action_required_summary'),
                'highlight_class' => 'danger',
            ];
        }

        return match ($typeKey) {
            'final_decision_issued' => [
                'title' => (string) data_get($notification->data, 'project_name', __('app.portal.notifications_title')),
                'body' => __('app.notifications.final_decision_issued', [
                    'decision' => __('app.statuses.'.data_get($notification->data, 'decision_status', 'approved')),
                    'permit' => data_get($notification->data, 'permit_number', __('app.dashboard.not_available')),
                ]),
                'url' => route('notifications.redirect', $notification->getKey()),
                ...$highlight,
            ],
            'contact_center_message', 'application_correspondence', 'scouting_correspondence' => [
                'title' => (string) data_get($notification->data, 'title', __('app.contact_center.title')),
                'body' => $body,
                'url' => route('notifications.redirect', $notification->getKey()),
                ...$highlight,
            ],
            default => [
                'title' => (string) data_get($notification->data, 'title', __('app.portal.notifications_title')),
                'body' => $body,
                'url' => route('notifications.redirect', $notification->getKey()),
                ...$highlight,
            ],
        };
    }
}
