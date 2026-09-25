<?php

namespace App\Http\Controllers;

use App\Support\Logo;

/** Manifeste PWA dynamique (§12.1) et ressources associées. */
class PwaController extends Controller
{
    public function manifest()
    {
        $icones = collect([72, 96, 128, 144, 152, 192, 384, 512])->map(fn ($t) => [
            'src' => asset("icons/icon-{$t}.png"), 'sizes' => "{$t}x{$t}", 'type' => 'image/png', 'purpose' => 'any',
        ])->push(
            ['src' => asset('icons/maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => asset('icons/maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        );

        return response()->json([
            'id' => '/',
            'name' => parametre('coop_nom', config('app.name')),
            'short_name' => parametre('coop_nom_court', 'Coopérative'),
            'description' => 'Gestion des cotisations et de la caisse de la coopérative du personnel municipal.',
            'lang' => 'fr',
            'dir' => 'ltr',
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => '#1f6f3a',
            'background_color' => '#f4f7f2',
            'categories' => ['finance', 'productivity'],
            'icons' => $icones->values(),
            'shortcuts' => [
                ['name' => 'Enregistrer un paiement', 'short_name' => 'Paiement', 'url' => '/paiements/nouveau',
                    'icons' => [['src' => asset('icons/icon-96.png'), 'sizes' => '96x96']]],
                ['name' => 'Mon historique', 'short_name' => 'Historique', 'url' => '/mon-historique',
                    'icons' => [['src' => asset('icons/icon-96.png'), 'sizes' => '96x96']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function horsLigne()
    {
        return response()->view('hors-ligne');
    }

    public function logo()
    {
        return response()->file(Logo::chemin(), ['Cache-Control' => 'public, max-age=86400']);
    }
}
