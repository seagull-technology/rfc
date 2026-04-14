<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationRedirectController extends Controller
{
    public function __invoke(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()?->notifications()->findOrFail($notification);

        if ($record && $record->read_at === null) {
            $record->markAsRead();
        }

        $url = data_get($record?->data, 'url');

        if (filled($url)) {
            return redirect()->to((string) $url);
        }

        $routeName = (string) data_get($record?->data, 'route_name', 'dashboard');
        $routeParameters = (array) data_get($record?->data, 'route_parameters', []);

        return redirect()->route($routeName, $routeParameters);
    }
}
