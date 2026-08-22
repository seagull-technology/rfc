<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class UploadedFileStorage
{
    /**
     * Store an uploaded file without relying exclusively on realpath().
     *
     * Some Windows/IIS upload paths are readable through getPathname() while
     * getRealPath() returns false. Laravel's default UploadedFile::store()
     * passes only getRealPath() to fopen(), which causes a production 500.
     */
    public static function store(UploadedFile $file, string $directory, string $disk = 'local'): string
    {
        $source = self::readablePath($file);

        if ($source === null) {
            throw new RuntimeException('The uploaded file is no longer readable.');
        }

        $stream = @fopen($source, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('The uploaded file could not be opened for storage.');
        }

        $destination = trim($directory, '/').'/'.$file->hashName();

        try {
            $stored = Storage::disk($disk)->put($destination, $stream);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        return $destination;
    }

    private static function readablePath(UploadedFile $file): ?string
    {
        foreach ([$file->getRealPath(), $file->getPathname()] as $path) {
            if (is_string($path) && $path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
