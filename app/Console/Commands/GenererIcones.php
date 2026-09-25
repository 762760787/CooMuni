<?php

namespace App\Console\Commands;

use App\Support\Logo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Génère les icônes PWA (§12.1) à partir du logo : tailles standard (fond blanc),
 * icônes « maskable » (fond vert, zone de sécurité de 20 %), apple-touch-icon et favicon.
 * À relancer après un changement de logo.
 */
class GenererIcones extends Command
{
    protected $signature = 'coop:icones';

    protected $description = 'Génère les icônes de l\'application installable à partir du logo.';

    public function handle(): int
    {
        $source = imagecreatefromstring(file_get_contents(Logo::chemin()));
        if (! $source) {
            $this->error('Logo illisible.');

            return self::FAILURE;
        }
        $source = $this->rogner($source);
        if (Logo::chemin() === public_path(Logo::DEFAUT)) {
            // Logo par défaut débarrassé de ses marges (opération idempotente).
            imagesavealpha($source, true);
            imagepng($source, public_path(Logo::DEFAUT), 9);
        }
        $dossier = public_path('icons');
        File::ensureDirectoryExists($dossier);

        foreach ([72, 96, 128, 144, 152, 192, 384, 512] as $t) {
            $this->ecrire($this->composer($source, $t, [255, 255, 255], 0.08), "{$dossier}/icon-{$t}.png");
        }
        foreach ([192, 512] as $t) {
            $this->ecrire($this->composer($source, $t, [31, 111, 58], 0.2), "{$dossier}/maskable-{$t}.png");
        }
        $this->ecrire($this->composer($source, 180, [255, 255, 255], 0.08), "{$dossier}/apple-touch-icon.png");
        $this->ecrire($this->composer($source, 32, [255, 255, 255], 0.0), "{$dossier}/favicon-32.png");
        copy("{$dossier}/favicon-32.png", public_path('favicon.png'));

        $this->info('Icônes générées dans public/icons.');

        return self::SUCCESS;
    }

    /** Supprime les marges transparentes / blanches autour du logo. */
    private function rogner(\GdImage $img): \GdImage
    {
        [$l, $h] = [imagesx($img), imagesy($img)];
        [$x0, $y0, $x1, $y1] = [$l, $h, -1, -1];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $l; $x++) {
                $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                $visible = $c['alpha'] < 100 && ($c['red'] + $c['green'] + $c['blue']) < 720;
                if ($visible) {
                    [$x0, $y0, $x1, $y1] = [min($x0, $x), min($y0, $y), max($x1, $x), max($y1, $y)];
                }
            }
        }
        if ($x1 < 0) {
            return $img;
        }

        return imagecrop($img, ['x' => $x0, 'y' => $y0, 'width' => $x1 - $x0 + 1, 'height' => $y1 - $y0 + 1]) ?: $img;
    }

    private function composer(\GdImage $logo, int $taille, array $fond, float $marge): \GdImage
    {
        $img = imagecreatetruecolor($taille, $taille);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocate($img, ...$fond));
        imagealphablending($img, true);

        $zone = (int) round($taille * (1 - 2 * $marge));
        [$l, $h] = [imagesx($logo), imagesy($logo)];
        $ratio = min($zone / $l, $zone / $h);
        $nl = (int) round($l * $ratio);
        $nh = (int) round($h * $ratio);
        imagecopyresampled($img, $logo, (int) (($taille - $nl) / 2), (int) (($taille - $nh) / 2), 0, 0, $nl, $nh, $l, $h);

        return $img;
    }

    private function ecrire(\GdImage $img, string $chemin): void
    {
        imagepng($img, $chemin, 9);
        imagedestroy($img);
    }
}
