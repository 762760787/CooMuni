/*
 * Service worker — Coopérative du personnel de la Commune de Ngoundiane (§12.2).
 *
 * - Ressources statiques (CSS/JS compilés, icônes, logo) : cache d'abord → chargement rapide.
 * - Pages et données : RÉSEAU D'ABORD, pour ne jamais afficher de montants obsolètes
 *   quand le réseau est disponible.
 * - Hors connexion : quelques pages personnelles déjà consultées (historique, reçus,
 *   tableau de bord) restent lisibles, à titre de confort ; sinon page « hors ligne ».
 * - Aucune requête d'écriture (POST, Livewire) n'est mise en file d'attente :
 *   la saisie financière reste bloquée hors connexion (§12.4).
 * - Le cache des pages est purgé à la déconnexion (confidentialité sur appareil partagé).
 */
const VERSION = 'v1.1.0';
const CACHE_STATIQUE = `coop-statique-${VERSION}`;
const CACHE_PAGES = `coop-pages-${VERSION}`;
const PAGE_HORS_LIGNE = '/hors-ligne';

const PRECACHE = [PAGE_HORS_LIGNE, '/logo.png', '/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png', '/favicon.png'];

// Pages autorisées en lecture hors ligne (données déjà chargées uniquement).
const PAGES_LISIBLES_HORS_LIGNE = [/^\/$/, /^\/mon-historique$/, /^\/recus$/, /^\/recus\/\d+\/pdf$/, /^\/membres\/\d+\/historique$/];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_STATIQUE)
            .then((cache) => cache.addAll(PRECACHE.map((u) => new Request(u, { credentials: 'same-origin' }))))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cles) => Promise.all(cles.filter((c) => ![CACHE_STATIQUE, CACHE_PAGES].includes(c)).map((c) => caches.delete(c))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'purger-pages') {
        event.waitUntil(caches.delete(CACHE_PAGES));
    }
});

function estStatique(url) {
    return url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/icons/')
        || url.pathname.startsWith('/images/')
        || url.pathname === '/logo.png'
        || url.pathname === '/favicon.png'
        || /\/livewire[^/]*\/livewire(\.min)?\.js$/.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);

    // Écritures, autres origines, mises à jour Livewire : jamais interceptées.
    if (req.method !== 'GET' || url.origin !== self.location.origin || url.pathname.includes('/livewire/update')) {
        return;
    }

    // Déconnexion / connexion : purge préventive du cache des pages.
    if (url.pathname === '/connexion') {
        event.waitUntil(caches.delete(CACHE_PAGES));
    }

    if (estStatique(url)) {
        event.respondWith(
            caches.match(req).then((trouve) => trouve || fetch(req).then((rep) => {
                if (rep.ok) {
                    const copie = rep.clone();
                    caches.open(CACHE_STATIQUE).then((c) => c.put(req, copie));
                }
                return rep;
            })),
        );
        return;
    }

    const lisibleHorsLigne = PAGES_LISIBLES_HORS_LIGNE.some((re) => re.test(url.pathname));

    // Réseau d'abord pour tout le reste (pages, données financières, reçus).
    event.respondWith(
        fetch(req)
            .then((rep) => {
                if (rep.ok && lisibleHorsLigne && !rep.redirected) {
                    const copie = rep.clone();
                    caches.open(CACHE_PAGES).then((c) => c.put(req, copie));
                }
                return rep;
            })
            .catch(async () => {
                if (lisibleHorsLigne) {
                    const enCache = await caches.match(req, { cacheName: CACHE_PAGES });
                    if (enCache) return enCache;
                }
                if (req.mode === 'navigate') {
                    return (await caches.match(PAGE_HORS_LIGNE)) || new Response('Hors connexion', { status: 503 });
                }
                return new Response('', { status: 503, statusText: 'Serveur injoignable' });
            }),
    );
});
