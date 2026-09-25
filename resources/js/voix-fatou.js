/*
 * Voix de l'assistante Fatou.
 * - Dictée : API de reconnaissance vocale du navigateur (Chrome, Edge ; service en ligne de Google,
 *   Internet nécessaire). Le wolof parlé n'est reconnu par aucun navigateur : dictée en français.
 * - Réponses parlées : synthèse vocale du navigateur (voix françaises de Windows / Android), gratuite.
 * - Conversation : la phrase dictée est envoyée automatiquement ; Fatou répond à voix haute.
 */
const ERREURS_DICTEE = {
    'service-not-allowed': "Le navigateur n'autorise pas la dictée vocale sur cette page. Utilisez Google Chrome ou Microsoft Edge (pas Firefox ni la fenêtre d'une autre application).",
    network: 'La dictée vocale utilise un service en ligne de Google : vérifiez la connexion Internet. Vous pouvez toujours écrire votre message.',
    'no-speech': "Je n'ai rien entendu. Appuyez sur le micro, puis parlez tout de suite.",
    'audio-capture': 'Aucun micro détecté (ou il est déjà utilisé par une autre application, par exemple un appel en cours).',
    'language-not-supported': 'Ce navigateur ne reconnaît pas le français parlé.',
};

/** Silence (ms) après la dernière parole avant d'envoyer, et attente maximale si l'on ne dit rien. */
const SILENCE_FIN_MS = 2500;
const SILENCE_INITIAL_MS = 8000;

/** Texte adapté à la lecture : partie française, montants et numéros lisibles, sans symboles. */
export function texteAPrononcer(texte) {
    const t = texte.includes('\n— ') ? texte.split('\n— ').slice(1).join(' ') : texte; // réponses bilingues : partie française
    return t
        .replace(/(\d)[   ](?=\d{3}\b)/g, '$1')
        .replace(/\bFCFA\b/g, 'francs CFA')
        .replace(/\bREC-\d{4}-0*(\d+)/g, 'numéro $1')
        .replace(/\bNGD-0*(\d+)/g, 'matricule $1')
        .replace(/[✓•»«—#*]/g, ' ')
        .replace(/\s*\n\s*/g, '. ')
        .replace(/\s{2,}/g, ' ')
        .trim();
}

window.dicteeFatou = () => ({
    ecoute: false,
    parle: false,
    message: '',
    reco: null,
    base: '',
    voixActive: localStorage.getItem('fatou.voix') !== 'non',
    envoiAuto: localStorage.getItem('fatou.envoiAuto') !== 'non',

    init() {
        // Les voix de synthèse sont chargées de façon asynchrone par le navigateur.
        window.speechSynthesis?.getVoices();
    },

    basculerVoix() {
        this.voixActive = !this.voixActive;
        localStorage.setItem('fatou.voix', this.voixActive ? 'oui' : 'non');
        if (!this.voixActive) window.speechSynthesis?.cancel();
    },

    basculerEnvoiAuto() {
        this.envoiAuto = !this.envoiAuto;
        localStorage.setItem('fatou.envoiAuto', this.envoiAuto ? 'oui' : 'non');
    },

    /** Lit une réponse de Fatou à voix haute (événement Livewire « fatou-parle »). */
    parler(detail) {
        const texte = (Array.isArray(detail) ? detail[0]?.texte : detail?.texte) ?? '';
        if (!this.voixActive || !texte || !('speechSynthesis' in window)) return;
        const synth = window.speechSynthesis;
        synth.cancel();
        const enonce = new SpeechSynthesisUtterance(texteAPrononcer(texte));
        const voix = synth.getVoices().filter((v) => v.lang?.toLowerCase().startsWith('fr'));
        enonce.voice =
            voix.find((v) => /hortense|julie|denise|amelie|female|femme/i.test(v.name)) ??
            voix.find((v) => /fr-FR/i.test(v.lang)) ??
            voix[0] ??
            null;
        enonce.lang = enonce.voice?.lang ?? 'fr-FR';
        enonce.onstart = () => {
            this.parle = true;
        };
        enonce.onend = enonce.onerror = () => {
            this.parle = false;
        };
        synth.speak(enonce);
    },

    /** Explication précise quand la dictée est refusée. */
    async diagnostic() {
        const politique = document.permissionsPolicy ?? document.featurePolicy;
        if (politique && !politique.allowsFeature('microphone')) {
            return "Le micro est bloqué par la configuration du site (en-tête Permissions-Policy). Prévenez l'administrateur.";
        }
        let etat = null;
        try {
            etat = (await navigator.permissions.query({ name: 'microphone' })).state;
        } catch (e) {
            /* non pris en charge par ce navigateur */
        }
        if (etat === 'denied') {
            return "Le micro est refusé pour ce site. Cliquez sur l'icône à gauche de l'adresse (réglages du site) › Micro › Autoriser, puis rechargez la page. " +
                "(Application installée : menu ⋮ › Informations sur l'application › Autorisations.)";
        }
        try {
            const flux = await navigator.mediaDevices.getUserMedia({ audio: true });
            flux.getTracks().forEach((p) => p.stop());
            return 'Le micro fonctionne, mais le service de dictée a refusé la demande. Rechargez la page (Ctrl + F5) et réessayez ; sinon essayez Microsoft Edge.';
        } catch (e) {
            if (e.name === 'NotFoundError') return ERREURS_DICTEE['audio-capture'];
            return `Accès au micro impossible (${e.name}). Vérifiez Paramètres Windows › Confidentialité et sécurité › Microphone : accès activé, y compris pour les applications de bureau.`;
        }
    },

    async basculer() {
        if (this.ecoute) {
            this.reco?.stop();
            return;
        }
        window.speechSynthesis?.cancel(); // Fatou se tait quand on lui parle
        this.message = '';
        if (!window.isSecureContext) {
            this.message = "Le micro est bloqué car la page n'est pas sécurisée : utilisez l'adresse https://coomuni.sn.";
            return;
        }
        const Reconnaissance = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!Reconnaissance) {
            this.message = 'Ce navigateur ne permet pas la dictée (Firefox, par exemple). Utilisez Google Chrome ou Microsoft Edge, ou le micro du clavier de votre téléphone.';
            return;
        }
        const reco = new Reconnaissance();
        reco.lang = 'fr-FR';
        reco.interimResults = true;
        // Écoute continue : on n'arrête pas à la première pause (« Ibra… »), mais après un vrai silence.
        reco.continuous = true;
        this.base = (this.$refs.saisie.value || '').trim();
        let entendu = '';
        let minuteur = null;
        const arreterApres = (ms) => {
            clearTimeout(minuteur);
            minuteur = setTimeout(() => reco.stop(), ms);
        };

        reco.onstart = () => {
            this.ecoute = true;
            this.message = 'Je vous écoute… Prenez votre temps ; j\'envoie après un silence (ou touchez 🎤 pour terminer).';
            arreterApres(SILENCE_INITIAL_MS);
        };
        reco.onresult = (e) => {
            entendu = Array.from(e.results)
                .map((r) => r[0].transcript)
                .join(' ')
                .replace(/\s+/g, ' ')
                .trim();
            this.$refs.saisie.value = `${this.base} ${entendu}`.trim();
            this.$refs.saisie.dispatchEvent(new Event('input', { bubbles: true })); // synchronise wire:model
            arreterApres(SILENCE_FIN_MS);
        };
        reco.onerror = async (e) => {
            if (e.error === 'aborted') return;
            this.message = e.error === 'not-allowed' ? await this.diagnostic() : (ERREURS_DICTEE[e.error] ?? `La dictée a échoué (${e.error}).`);
        };
        reco.onend = () => {
            clearTimeout(minuteur);
            this.ecoute = false;
            if (this.message.startsWith('Je vous écoute')) this.message = '';
            if (entendu && this.envoiAuto) {
                this.$wire.envoyer();
            } else if (entendu) {
                this.message = 'Vérifiez le texte, puis appuyez sur Envoyer.';
                this.$refs.saisie.focus();
            }
        };
        this.reco = reco;
        try {
            reco.start();
        } catch (err) {
            this.message = `Impossible de démarrer le micro : ${err.message}`;
        }
    },
});
