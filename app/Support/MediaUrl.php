<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    public static function public(?string $path, string $fallback): string
    {
        $value = trim((string) $path);

        if ($value === '') {
            return $fallback;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
            return $value;
        }

        return Storage::disk('public')->url(ltrim($value, '/'));
    }
}
