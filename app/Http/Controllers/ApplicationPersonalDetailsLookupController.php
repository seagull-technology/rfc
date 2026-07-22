<?php

namespace App\Http\Controllers;

use App\Services\Gsb\IndividualPersonalInfoLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationPersonalDetailsLookupController extends Controller
{
    public function __invoke(Request $request, IndividualPersonalInfoLookupService $service): JsonResponse
    {
        $validated = $request->validate([
            'nationality_category' => ['required', 'string', 'in:jordanian,arab,foreign'],
            'personal_number' => ['required', 'string', 'max:20'],
        ]);

        $result = $service->lookup(
            $validated['personal_number'],
            $validated['nationality_category'],
        );

        if (! ($result['ok'] ?? false)) {
            $error = (string) ($result['error'] ?? 'LOOKUP_FAILED');

            return response()->json([
                'ok' => false,
                'message' => match ($error) {
                    'INVALID_NATIONAL_ID' => __('app.applications.ministry_interior_personal_details.lookup_invalid_jordanian'),
                    'INVALID_INDIVIDUAL_NUMBER' => __('app.applications.ministry_interior_personal_details.lookup_invalid_non_jordanian'),
                    'INVALID_NATIONALITY_CATEGORY' => __('app.applications.ministry_interior_personal_details.lookup_select_category'),
                    'NOT_FOUND' => __('app.applications.ministry_interior_personal_details.lookup_not_found'),
                    default => __('app.applications.ministry_interior_personal_details.lookup_unavailable'),
                },
            ], in_array($error, [
                'INVALID_NATIONAL_ID',
                'INVALID_INDIVIDUAL_NUMBER',
                'INVALID_NATIONALITY_CATEGORY',
                'NOT_FOUND',
            ], true) ? 422 : 503);
        }

        $data = (array) ($result['data'] ?? []);
        $nameParts = preg_split('/\s+/u', trim((string) ($data['full_name'] ?? '')), 4) ?: [];

        return response()->json([
            'ok' => true,
            'message' => __('app.applications.ministry_interior_personal_details.lookup_success'),
            'data' => [
                ...$data,
                'first_name' => $data['first_name'] ?? $nameParts[0] ?? null,
                'father_name' => $data['father_name'] ?? $nameParts[1] ?? null,
                'grandfather_name' => $data['grandfather_name'] ?? $nameParts[2] ?? null,
                'family_name' => $data['family_name'] ?? $nameParts[3] ?? null,
            ],
        ]);
    }
}
