<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/** Logo de la coopérative : paramètre « coop_logo » ou, à défaut, logo de la Mairie. */
final class Logo
{
    public const DEFAUT = 'images/logo-mairie-ngoundiane.png';

    public static function chemin(): string
    {
        $logo = parametre('coop_logo');
        if ($logo && Storage::disk('local')->exists($logo)) {
            return Storage::disk('local')->path($logo);
        }

        return public_path(self::DEFAUT);
    }

    public static function dataUri(): string
    {
        $chemin = self::chemin();
        $mime = mime_content_type($chemin) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($chemin));
    }
}
