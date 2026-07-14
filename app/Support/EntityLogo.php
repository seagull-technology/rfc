<?php

namespace App\Support;

use App\Models\Entity;
use Illuminate\Support\Facades\Storage;

class EntityLogo
{
    public static function url(?Entity $entity, string $fallback = 'images/OIP.jpeg'): string
    {
        $path = data_get($entity?->metadata, 'logo_path');

        if (! $entity || ! is_string($path) || $path === '' || ! Storage::disk('local')->exists($path)) {
            return asset($fallback);
        }

        $version = substr(sha1(implode('|', [
            $path,
            (string) data_get($entity->metadata, 'logo_size', ''),
            (string) data_get($entity->metadata, 'logo_mime', ''),
        ])), 0, 12);

        return route('entities.logo', ['entity' => $entity->getKey(), 'v' => $version]);
    }
}
