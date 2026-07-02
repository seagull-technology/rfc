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
        ], [
            'national_id.regex' => __('app.auth.national_id_digits'),
        ]);

        $lookup = $lookupService->lookup((string) $validated['national_id']);

        if (! ($lookup['ok'] ?? false)) {
            return response()->json([
                'message' => __('app.auth.student_lookup_failed'),
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
