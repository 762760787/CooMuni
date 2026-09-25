<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeconnexionController extends Controller
{
    public function __invoke(Request $request)
    {
        Audit::log('auth.deconnexion', $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Le service worker purge son cache de pages consultées (voir public/sw.js).
        return redirect()->route('login')->with('succes', 'Vous êtes déconnecté.')->with('purger_cache', true);
    }
}
