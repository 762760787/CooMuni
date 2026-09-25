<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Photos : ré-encodées en JPEG via GD (§13 contrôle du contenu des fichiers) —
 * supprime les métadonnées et tout contenu non-image, et réduit le poids (§21).
 */
final class Images
{
    public static function enregistrerPhoto(UploadedFile $fichier, int $taille = 600): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));
        if (! $source) {
            throw new RuntimeException('Image illisible.');
        }
        [$l, $h] = [imagesx($source), imagesy($source)];
        $ratio = min(1, $taille / max($l, $h));
        $nl = max(1, (int) round($l * $ratio));
        $nh = max(1, (int) round($h * $ratio));

        $dest = imagecreatetruecolor($nl, $nh);
        imagefill($dest, 0, 0, imagecolorallocate($dest, 255, 255, 255));
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $nl, $nh, $l, $h);

        ob_start();
        imagejpeg($dest, null, 82);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($dest);

        $chemin = 'photos/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($chemin, $jpeg);

        return $chemin;
    }
}
