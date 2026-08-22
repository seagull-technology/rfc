<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

class DocumentUploadInspector
{
    /**
     * Return null when the file passes inspection, otherwise a safe error reason.
     */
    public function inspect(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return 'upload_failed';
        }

        $size = $file->getSize();
        $maxBytes = max(1, (int) config('security.uploads.max_kilobytes', 10240)) * 1024;

        if (! is_int($size) || $size <= 0 || $size > $maxBytes) {
            return 'invalid_size';
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = config('security.uploads.allowed_extensions', []);

        if (! in_array($extension, $allowedExtensions, true)) {
            return 'invalid_extension';
        }

        $path = $this->readablePath($file);

        if ($path === null) {
            return 'unreadable';
        }

        $mime = strtolower((string) $file->getMimeType());

        if (! $this->mimeMatchesExtension($extension, $mime)) {
            return 'mime_mismatch';
        }

        return match ($extension) {
            'pdf' => $this->inspectPdf($path),
            'png' => $this->hasPrefix($path, "\x89PNG\r\n\x1a\n") ? null : 'signature_mismatch',
            'jpg', 'jpeg' => $this->hasPrefix($path, "\xFF\xD8\xFF") ? null : 'signature_mismatch',
            'tif', 'tiff' => $this->isTiff($path) ? null : 'signature_mismatch',
            'doc', 'xls' => $this->inspectLegacyOffice($path),
            'docx', 'xlsx' => $this->inspectOfficeArchive($path, $extension),
            'csv' => $this->inspectCsv($path),
            default => 'invalid_extension',
        };
    }

    private function inspectPdf(string $path): ?string
    {
        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            return 'unreadable';
        }

        $headerPosition = strpos(substr($contents, 0, 1024), '%PDF-');

        if ($headerPosition === false || ! str_contains(substr($contents, -2048), '%%EOF')) {
            return 'signature_mismatch';
        }

        if ($this->containsActivePdfContent($contents)) {
            return 'active_content';
        }

        foreach ($this->decodedPdfStreams($contents) as $stream) {
            if ($this->containsActivePdfContent($stream)) {
                return 'active_content';
            }
        }

        return null;
    }

    private function inspectOfficeArchive(string $path, string $extension): ?string
    {
        if (! class_exists(ZipArchive::class) || ! $this->hasPrefix($path, 'PK')) {
            return 'signature_mismatch';
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return 'signature_mismatch';
        }

        try {
            $requiredEntry = $extension === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';

            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName($requiredEntry) === false) {
                return 'signature_mismatch';
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = strtolower((string) $zip->getNameIndex($index));

                if (str_ends_with($entry, 'vbaproject.bin')
                    || str_contains($entry, '/embeddings/')
                    || str_contains($entry, '/oleobjects/')
                    || str_contains($entry, '/activex/')
                    || str_contains($entry, '/externallinks/')
                    || str_contains($entry, '/macrosheets/')
                    || str_contains($entry, '/customui/')) {
                    return 'active_content';
                }

                if (str_ends_with($entry, '.rels')) {
                    $relationships = $zip->getFromIndex($index, 2 * 1024 * 1024);

                    if (is_string($relationships)
                        && preg_match('/\bTargetMode\s*=\s*["\']External["\']/i', $relationships) === 1) {
                        return 'active_content';
                    }
                }
            }

            return null;
        } finally {
            $zip->close();
        }
    }

    private function inspectCsv(string $path): ?string
    {
        $sample = file_get_contents($path, false, null, 0, 8192);

        if (! is_string($sample) || str_contains($sample, "\0")) {
            return 'signature_mismatch';
        }

        return preg_match('/(?:^|[,;\t])\s*[=+@]/m', $sample) === 1
            ? 'active_content'
            : null;
    }

    private function inspectLegacyOffice(string $path): ?string
    {
        if (! $this->hasPrefix($path, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            return 'signature_mismatch';
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            return 'unreadable';
        }

        $macroMarkers = [
            '_VBA_PROJECT',
            'VBA/dir',
            'Macros',
            'AutoOpen',
            'Auto_Open',
            'Document_Open',
            'Workbook_Open',
            'WScript.Shell',
        ];

        foreach ($macroMarkers as $marker) {
            if (stripos($contents, $marker) !== false) {
                return 'active_content';
            }
        }

        return null;
    }

    private function containsActivePdfContent(string $contents): bool
    {
        $normalized = preg_replace_callback(
            '/#([0-9a-f]{2})/i',
            static fn (array $match): string => chr((int) hexdec($match[1])),
            $contents,
        ) ?? $contents;

        return preg_match(
            '/(\/JavaScript\b|\/JS\b|\/OpenAction\b|\/AA\b|\/Launch\b|\/EmbeddedFiles?\b|\/RichMedia\b|\/XFA\b|\/Encrypt\b|\/URI\b|\/SubmitForm\b|\/ImportData\b|\/GoToR\b|javascript\s*:)/i',
            $normalized,
        ) === 1;
    }

    /** @return list<string> */
    private function decodedPdfStreams(string $contents): array
    {
        preg_match_all(
            '/stream(?:\r\n|\r|\n)(.*?)(?:\r\n|\r|\n)endstream/s',
            $contents,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        $decoded = [];

        foreach ($matches as $match) {
            $offset = (int) $match[0][1];
            $dictionary = substr($contents, max(0, $offset - 4096), min(4096, $offset));

            if (! str_contains($dictionary, '/FlateDecode')) {
                continue;
            }

            $payload = (string) $match[1][0];
            $inflated = $this->inflatePdfStream($payload);

            if ($inflated !== null) {
                $decoded[] = $inflated;
            }
        }

        return $decoded;
    }

    private function inflatePdfStream(string $payload): ?string
    {
        $maximumBytes = 20 * 1024 * 1024;

        try {
            $decoded = @gzuncompress($payload, $maximumBytes);

            if (! is_string($decoded)) {
                $decoded = @gzinflate($payload, $maximumBytes);
            }
        } catch (\Throwable) {
            return null;
        }

        return is_string($decoded) && strlen($decoded) <= $maximumBytes
            ? $decoded
            : null;
    }

    private function hasPrefix(string $path, string $prefix): bool
    {
        $contents = file_get_contents($path, false, null, 0, strlen($prefix));

        return is_string($contents) && hash_equals($prefix, $contents);
    }

    private function readablePath(UploadedFile $file): ?string
    {
        $paths = [
            $file->getRealPath(),
            $file->getPathname(),
        ];

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function isTiff(string $path): bool
    {
        return $this->hasPrefix($path, "II\x2A\x00")
            || $this->hasPrefix($path, "MM\x00\x2A");
    }

    private function mimeMatchesExtension(string $extension, string $mime): bool
    {
        $allowed = [
            'pdf' => ['application/pdf', 'application/x-pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'tif' => ['image/tiff'],
            'tiff' => ['image/tiff'],
            'doc' => ['application/msword', 'application/x-ole-storage', 'application/cdfv2'],
            'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/cdfv2'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
        ];

        return in_array($mime, $allowed[$extension] ?? [], true);
    }
}
