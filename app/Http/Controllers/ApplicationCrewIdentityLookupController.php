<?php

namespace App\Http\Controllers;

use App\Services\Gsb\CrewIdentityVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationCrewIdentityLookupController extends Controller
{
    public function __invoke(Request $request, CrewIdentityVerificationService $service): JsonResponse
    {
        $jordanian = $request->input('nationality_category') === 'jordanian';
        $standard = (bool) config('services.gsb.services.'.($jordanian ? 'cspd_personal_info_standard' : 'psd_personal_standard').'.enabled', false);
        $validated = $request->validate([
            'nationality_category' => ['required', Rule::in(['jordanian', 'foreign'])],
            'identifier' => ['required', 'string', 'max:20'],
            'birth_date' => [Rule::requiredIf($standard), 'nullable', 'date_format:Y-m-d', 'before:today'],
            'civil_number' => [Rule::requiredIf($standard && $jordanian), 'nullable', 'string', 'max:7', 'regex:/^[0-9]{3}\/?[0-9]{3}$/'],
        ], [
            'civil_number.regex' => __('app.applications.cast_crew_verification.invalid_civil_number'),
            'civil_number.max' => __('app.applications.cast_crew_verification.invalid_civil_number'),
        ], ['civil_number' => __('app.applications.cast_crew_verification.civil_number')]);

        $arguments = [(string) $validated['identifier'], (string) $validated['nationality_category'], (int) $request->user()->getKey()];
        if ($standard) {
            $arguments[] = $validated['birth_date'];
            $arguments[] = $jordanian ? ($validated['civil_number'] ?? null) : null;
        }
        $result = $service->lookup(...$arguments);

        if (! ($result['ok'] ?? false)) {
            $error = (string) ($result['error'] ?? 'LOOKUP_FAILED');

            return response()->json([
                'ok' => false,
                'status' => CrewIdentityVerificationService::STATUS_UNVERIFIED,
                'message' => match ($error) {
                    'INVALID_NATIONAL_ID' => __('app.applications.cast_crew_verification.invalid_national_id'),
                    'INVALID_INDIVIDUAL_NUMBER' => __('app.applications.cast_crew_verification.invalid_individual_number'),
                    'INVALID_CIVIL_NUMBER' => __('app.applications.cast_crew_verification.invalid_civil_number'),
                    'INVALID_BIRTH_DATE' => __('app.applications.cast_crew_verification.verification_fields_required'),
                    'NOT_FOUND' => __('app.applications.cast_crew_verification.not_found'),
                    default => __('app.applications.cast_crew_verification.unavailable'),
                },
            ], 422);
        }

        $pending = ($result['status'] ?? null) === CrewIdentityVerificationService::STATUS_PENDING;

        return response()->json([
            'ok' => true,
            'status' => $result['status'],
            'message' => $pending
                ? __('app.applications.cast_crew_verification.pending_message')
                : __('app.applications.cast_crew_verification.success'),
            'data' => $result['data'] ?? [],
            'source' => $result['source'] ?? null,
            'verified_at' => $result['verified_at'] ?? null,
            'proof' => $result['proof'] ?? null,
        ], $pending ? 202 : 200);
    }
}
