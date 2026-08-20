<?php

namespace App\Http\Middleware;

use App\Support\DocumentUploadInspector;
use App\Support\RegistrationValidationAudit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ValidateUploadedFiles
{
    public function __construct(private readonly DocumentUploadInspector $inspector) {}

    public function handle(Request $request, Closure $next): Response
    {
        foreach ($this->flattenFiles($request->allFiles()) as $attribute => $file) {
            $rejectionReason = $this->inspector->inspect($file);

            if ($rejectionReason !== null) {
                RegistrationValidationAudit::record(
                    $request,
                    [$attribute => []],
                    'upload_inspection',
                    $rejectionReason,
                );

                $exception = ValidationException::withMessages([
                    $attribute => $this->rejectionMessage($rejectionReason),
                ]);

                if (RegistrationValidationAudit::isRegistrationSubmission($request)) {
                    $exception->redirectTo($this->registrationFormUrl($request));
                }

                throw $exception;
            }
        }

        return $next($request);
    }

    /**
     * @param  array<string|int, mixed>  $files
     * @return array<string, UploadedFile>
     */
    private function flattenFiles(array $files, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($files as $key => $value) {
            $attribute = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if ($value instanceof UploadedFile) {
                $flattened[$attribute] = $value;
            } elseif (is_array($value)) {
                $flattened = array_merge($flattened, $this->flattenFiles($value, $attribute));
            }
        }

        return $flattened;
    }

    private function rejectionMessage(string $reason): string
    {
        $key = in_array($reason, [
            'upload_failed',
            'invalid_size',
            'invalid_extension',
            'unreadable',
            'mime_mismatch',
            'signature_mismatch',
            'active_content',
        ], true) ? $reason : 'generic';

        return __("validation.secure_upload_reasons.{$key}");
    }

    private function registrationFormUrl(Request $request): string
    {
        $locale = strtolower((string) $request->segment(1));
        $supportedLocales = array_keys((array) config('laravellocalization.supportedLocales', []));
        $prefix = in_array($locale, $supportedLocales, true) ? "/{$locale}" : '';

        return url("{$prefix}/register");
    }
}
