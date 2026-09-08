<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OrganizationRegistrationLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationLookupController extends Controller
{
    public function __invoke(Request $request, OrganizationRegistrationLookupService $lookupService): JsonResponse
    {
        $payload = $request->validate([
            'organization_national_id' => ['required', 'string', 'max:50'],
            'organization_registration_no' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $lookupService->lookup(
            $payload['organization_national_id'],
            $payload['organization_registration_no'] ?? null,
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => 'LOOKUP_FAILED',
                'message' => __('app.auth.organization_lookup_failed'),
            ], 422);
        }

        $request->session()->put(
            OrganizationRegistrationLookupService::SESSION_KEY,
            $lookupService->sessionStateFromLookup($result),
        );

        return response()->json([
            'ok' => true,
            'message' => __('app.auth.organization_lookup_success'),
            'data' => $result['data'],
            'meta' => $result['meta'],
            'registration_candidates' => $result['registration_candidates'],
        ]);
    }
}
