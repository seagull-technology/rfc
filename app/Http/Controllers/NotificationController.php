<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('notifications.index', [
            'notificationLayout' => $this->layoutFor($user),
            'notifications' => $user->notifications()->latest()->paginate(20),
            'title' => __('app.portal.notifications_all_title'),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $unreadNotifications = $user->unreadNotifications();
        $markedRead = $unreadNotifications->count();

        $unreadNotifications->update(['read_at' => now()]);

        return response()->json([
            'marked_read' => $markedRead,
            'unread_count' => 0,
        ]);
    }

    private function layoutFor(User $user): string
    {
        $entity = $user->primaryEntity();

        if ($user->isOperationallyActive() && $entity?->isOperationallyActive() && $user->canAccessAdminPanel($entity)) {
            return 'layouts.admin-dashboard';
        }

        if ($user->isOperationallyActive() && $entity?->isOperationallyActive() && $entity->group?->code === 'authorities') {
            return 'layouts.authority-dashboard';
        }

        return 'layouts.portal-dashboard';
    }
}
