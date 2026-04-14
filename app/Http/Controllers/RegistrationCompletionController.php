<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegistrationCompletionController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $entity = $user?->primaryEntity();

        if (! $user || ! $entity || ! $this->canCompleteAsAuthenticatedUser($entity)) {
            return redirect()->route('dashboard');
        }

        return view('auth.complete-registration', [
            'entity' => $entity,
            'user' => $user,
            'registrationType' => $entity->registration_type,
            'isSignedLinkFlow' => false,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $entity = $user->primaryEntity();

        abort_unless($entity && $this->canCompleteAsAuthenticatedUser($entity), 404);

        $this->persistCompletion($request, $entity, $user);

        return redirect()
            ->route('dashboard')
            ->with('status', __('app.dashboard.completion_submitted'));
    }

    public function editViaSignedLink(Request $request, Entity $entity): View
    {
        $user = $this->primaryOwner($entity);

        abort_unless($user && $this->canCompleteViaSignedLink($entity), 404);

        return view('auth.complete-registration', [
            'entity' => $entity,
            'user' => $user,
            'registrationType' => $entity->registration_type,
            'isSignedLinkFlow' => true,
        ]);
    }

    public function updateViaSignedLink(Request $request, Entity $entity): RedirectResponse
    {
        $user = $this->primaryOwner($entity);

        abort_unless($user && $this->canCompleteViaSignedLink($entity), 404);

        $this->persistCompletion($request, $entity, $user);

        return redirect()
            ->route('login')
            ->with('status', __('app.dashboard.signed_completion_submitted'));
    }

    private function persistCompletion(Request $request, Entity $entity, User $user): void
    {
        $validated = $request->validate([
            'entity_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:50', Rule::unique('entities', 'registration_no')->ignore($entity->getKey())],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->getKey())],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'registration_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $validated['phone'] = PhoneNumber::normalize($validated['phone']);
        $document = $validated['registration_document'] ?? null;

        DB::transaction(function () use ($validated, $entity, $user, $document): void {
            $metadata = $entity->metadata ?? [];

            if ($document instanceof UploadedFile) {
                $metadata['registration_document_path'] = $this->storeRegistrationDocument($document, (string) $entity->registration_type);
                $metadata['registration_document_name'] = $document->getClientOriginalName();
                $metadata['registration_document_mime'] = $document->getClientMimeType();
            }

            $metadata['address'] = $validated['address'];
            $metadata['description'] = $validated['description'] ?: null;
            $metadata['resubmission'] = [
                'submitted_at' => now()->toDateTimeString(),
                'submitted_by_user_id' => $user->getKey(),
                'via_signed_link' => auth()->guest(),
            ];

            $entity->forceFill([
                'name_en' => $validated['entity_name'],
                'name_ar' => $validated['entity_name'],
                'registration_no' => $validated['registration_number'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'status' => 'pending_review',
                'metadata' => $metadata,
            ])->save();

            $user->forceFill([
                'name' => $validated['entity_name'],
                'username' => $this->makeUsername((string) $entity->registration_type, $validated['registration_number']),
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'status' => 'pending_review',
            ])->save();
        });
    }

    private function canCompleteAsAuthenticatedUser(Entity $entity): bool
    {
        return $this->canComplete($entity)
            && $entity->registration_type === 'company';
    }

    private function canCompleteViaSignedLink(Entity $entity): bool
    {
        return $this->canComplete($entity)
            && in_array($entity->registration_type, ['company', 'ngo', 'school'], true);
    }

    private function canComplete(Entity $entity): bool
    {
        return in_array($entity->registration_type, ['company', 'ngo', 'school'], true)
            && in_array($entity->status, ['needs_completion', 'rejected'], true);
    }

    private function primaryOwner(Entity $entity): ?User
    {
        return $entity->users()
            ->orderByDesc('entity_user.is_primary')
            ->orderBy('users.name')
            ->first();
    }

    private function makeUsername(string $registrationType, string $identifier): string
    {
        $normalizedIdentifier = Str::of(Str::lower($identifier))
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();

        return Str::limit($registrationType.'-'.$normalizedIdentifier, 50, '');
    }

    private function storeRegistrationDocument(UploadedFile $document, string $registrationType): string
    {
        return $document->store('registration-documents/'.$registrationType, 'local');
    }
}
