<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CompanyRegistrationLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyLookupController extends Controller
{
    public function __invoke(Request $request, CompanyRegistrationLookupService $lookupService): JsonResponse
    {
        $payload = $request->validate([
            'registration_number' => ['required', 'regex:/^\d{1,10}$/'],
        ], [
            'registration_number.regex' => __('app.auth.organization_national_id_digits'),
        ]);

        $result = $lookupService->lookup($payload['registration_number']);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => 'LOOKUP_FAILED',
                'message' => __('app.auth.company_lookup_failed'),
            ], 422);
        }

        $request->session()->put(
            CompanyRegistrationLookupService::SESSION_KEY,
            $lookupService->sessionStateFromLookup($result),
        );

        return response()->json([
            'ok' => true,
            'message' => __('app.auth.company_lookup_success'),
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }
}
