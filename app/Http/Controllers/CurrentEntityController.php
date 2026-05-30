<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentEntityController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user, 403);

        $validated = $request->validate([
            'entity_id' => ['required', 'integer'],
        ]);

        $entity = $user->availableEntities()
            ->firstWhere('id', (int) $validated['entity_id']);

        abort_unless($entity, 403);

        $request->session()->put('current_entity_id', $entity->getKey());

        return redirect()
            ->route('dashboard')
            ->with('status', __('app.portal.entity_switched', ['entity' => $entity->displayName()]));
    }
}
