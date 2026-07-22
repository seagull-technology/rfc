<?php

namespace App\Http\Controllers;

use App\Models\Application as FilmApplication;
use App\Models\User;
use App\Support\MinistryInteriorPersonalDetails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationAnnexAttachmentController extends Controller
{
    public function __invoke(
        Request $request,
        FilmApplication $application,
        string $person,
        string $attachment,
    ): StreamedResponse|RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canView($user, $application), 403);

        $rows = MinistryInteriorPersonalDetails::rows(
            data_get($application->metadata, 'annex.ministry_interior_personal_details', []),
        );
        $record = collect((array) data_get($rows, $person.'.attachments', []))
            ->first(fn ($item): bool => is_array($item) && (string) ($item['id'] ?? '') === $attachment);

        abort_unless(is_array($record) && filled($record['path'] ?? null), 404);
        abort_unless(Storage::disk('local')->exists($record['path']), 404);

        return Storage::disk('local')->download(
            $record['path'],
            (string) (($record['name'] ?? null) ?: basename((string) $record['path'])),
        );
    }

    private function canView(User $user, FilmApplication $application): bool
    {
        if ((int) data_get($application->metadata, 'international.account.user_id') === (int) $user->getKey()) {
            return true;
        }

        $entityIds = $user->availableEntities()->modelKeys();

        if (in_array((int) $application->entity_id, array_map('intval', $entityIds), true)) {
            return true;
        }

        if ($application->authorityApprovals()->whereIn('entity_id', $entityIds)->exists()) {
            return true;
        }

        return $user->availableEntities()->contains(fn ($entity): bool => $user->canAccessAdminPanel($entity));
    }
}
