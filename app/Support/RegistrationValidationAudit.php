<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class RegistrationValidationAudit
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public static function record(
        Request $request,
        array $errors,
        string $stage = 'request_validation',
        ?string $uploadReason = null,
    ): void {
        if (! self::isRegistrationSubmission($request)) {
            return;
        }

        $fields = array_keys($errors);
        sort($fields);

        $context = [
            'route' => self::safeRouteName($request),
            'registration_type' => self::safeRegistrationType($request),
            'stage' => $stage,
            'fields' => $fields,
        ];

        if ($stage === 'upload_inspection' && self::isSafeUploadReason($uploadReason)) {
            $context['reason'] = $uploadReason;
        }

        Log::warning('Registration validation rejected', $context);
    }

    public static function isRegistrationSubmission(Request $request): bool
    {
        if (! $request->isMethod('post')) {
            return false;
        }

        return $request->routeIs([
            'register.store',
            'register.organization.store',
            'register.individual.store',
        ]) || $request->is([
            'register',
            '*/register',
            'register/organization',
            '*/register/organization',
            'register/individual',
            '*/register/individual',
        ]);
    }

    private static function safeRouteName(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();

        return in_array($routeName, [
            'register.store',
            'register.organization.store',
            'register.individual.store',
        ], true) ? $routeName : 'registration.store';
    }

    private static function safeRegistrationType(Request $request): string
    {
        $registrationType = strtolower(trim((string) $request->input('registration_type')));

        return in_array($registrationType, ['student', 'company', 'ngo', 'school'], true)
            ? $registrationType
            : 'unknown';
    }

    private static function isSafeUploadReason(?string $reason): bool
    {
        return in_array($reason, [
            'upload_failed',
            'invalid_size',
            'invalid_extension',
            'unreadable',
            'mime_mismatch',
            'signature_mismatch',
            'active_content',
        ], true);
    }
}
