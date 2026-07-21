<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\User;
use App\Services\StudentRegistrationLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentLookupController extends Controller
{
    public function __invoke(Request $request, StudentRegistrationLookupService $lookupService): JsonResponse
    {
        $normalizedBirthDate = $lookupService->normalizeBirthDate((string) $request->input('birth_date'));

        if ($normalizedBirthDate !== null) {
            $request->merge(['birth_date' => $normalizedBirthDate]);
        }

        $validated = $request->validate([
            'national_id' => [
                'required',
                'regex:/^\d{10}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (User::query()->where('national_id', $value)->exists()
                        || Entity::query()->where('national_id', $value)->exists()) {
                        $fail(__('validation.unique', ['attribute' => __('validation.attributes.national_id')]));
                    }
                },
            ],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
        ], [
            'national_id.regex' => __('app.auth.national_id_digits'),
        ]);

        $lookup = $lookupService->lookup(
            (string) $validated['national_id'],
            (string) $validated['birth_date'],
        );

        if (! ($lookup['ok'] ?? false)) {
            $message = match ($lookup['error'] ?? null) {
                'IDENTITY_MISMATCH' => __('app.auth.student_lookup_identity_mismatch'),
                'STUDENT_NOT_FOUND' => __('app.auth.student_lookup_not_found'),
                'NOT_CURRENT_STUDENT' => __('app.auth.student_not_current'),
                default => __('app.auth.student_lookup_failed'),
            };

            return response()->json([
                'message' => $message,
                'error' => $lookup['error'] ?? 'LOOKUP_FAILED',
            ], 422);
        }

        $request->session()->put(
            StudentRegistrationLookupService::SESSION_KEY,
            $lookupService->sessionStateFromLookup($lookup),
        );

        return response()->json([
            'message' => __('app.auth.student_lookup_success'),
            'data' => $lookup['data'],
        ]);
    }
}
