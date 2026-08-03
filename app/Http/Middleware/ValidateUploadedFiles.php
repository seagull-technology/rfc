<?php

namespace App\Http\Middleware;

use App\Support\DocumentUploadInspector;
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
            if ($this->inspector->inspect($file) !== null) {
                throw ValidationException::withMessages([
                    $attribute => __('validation.secure_upload'),
                ]);
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
}
