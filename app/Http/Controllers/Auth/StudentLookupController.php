<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
            return response()->json([
                'message' => __('app.auth.student_lookup_failed'),
                'error' => 'LOOKUP_FAILED',
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
