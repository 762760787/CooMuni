/*
 * Comportements transverses : état réseau, notifications « toast »,
 * installation PWA et enregistrement du service worker (§11.5, §12).
 * Alpine.js est fourni par Livewire.
 */

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    // État de la connexion : bloque la saisie financière hors ligne (§12.4).
    Alpine.store('reseau', {
        enLigne: navigator.onLine,
    });
    window.addEventListener('online', () => {
        Alpine.store('reseau').enLigne = true;
        Alpine.store('toasts').ajouter('Connexion rétablie.', 'succes');
    });
    window.addEventListener('offline', () => {
        Alpine.store('reseau').enLigne = false;
    });

    Alpine.store('toasts', {
        liste: [],
        prochainId: 1,
        ajouter(message, type = 'succes', duree = 4500) {
            const id = this.prochainId++;
            this.liste.push({ id, message, type });
            setTimeout(() => this.retirer(id), duree);
        },
        retirer(id) {
            this.liste = this.liste.filter((t) => t.id !== id);
        },
    });

    // Invitation à installer l'application (Android / Chrome / Edge).
    Alpine.store('installation', {
        evenement: null,
        disponible: false,
        async installer() {
            if (!this.evenement) return;
            this.evenement.prompt();
            await this.evenement.userChoice;
            this.evenement = null;
            this.disponible = false;
        },
    });
});

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    const store = window.Alpine?.store('installation');
    if (store) {
        store.evenement = e;
        store.disponible = true;
    }
});

// Messages émis par les composants Livewire : $this->dispatch('toast', message: ..., type: ...)
window.addEventListener('toast', (e) => {
    const d = Array.isArray(e.detail) ? e.detail[0] : e.detail;
    window.Alpine?.store('toasts').ajouter(d.message, d.type ?? 'succes');
});

// Erreur réseau pendant une action Livewire : message clair plutôt qu'une page d'erreur.
document.addEventListener('livewire:init', () => {
    window.Livewire.interceptRequest(({ onFailure, onError }) => {
        onFailure(() => {
            window.Alpine?.store('toasts').ajouter(
                navigator.onLine
                    ? 'Le serveur ne répond pas. Réessayez dans un instant.'
                    : 'Hors connexion : l\'action n\'a pas été envoyée. Réessayez une fois la connexion rétablie.',
                'erreur',
                7000,
            );
        });
        onError(({ response, preventDefault }) => {
            if (response.status === 419) {
                preventDefault();
                window.Alpine?.store('toasts').ajouter('Votre session a expiré. La page va être rechargée.', 'erreur');
                setTimeout(() => window.location.reload(), 1500);
            }
        });
    });
});

// Service worker (§12.2).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});

        // Après déconnexion : purge des pages mises en cache pour la lecture hors ligne.
        if (document.body.dataset.purgerCache === '1') {
            navigator.serviceWorker.ready.then((reg) => reg.active?.postMessage({ type: 'purger-pages' }));
        }
    });
}

// Voix de l'assistante (dictée, réponses parlées).
import './voix-fatou.js';
