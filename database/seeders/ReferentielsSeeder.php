<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\ModePaiement;
use App\Models\Parametre;
use App\Services\Parametres;
use Illuminate\Database\Seeder;

/**
 * Paramètres métier, modes de paiement et catégories financières initiaux.
 * Idempotent : ne remplace jamais une valeur déjà modifiée par l'administrateur.
 */
class ReferentielsSeeder extends Seeder
{
    public function run(): void
    {
        $o = 0;
        $parametres = [
            // Général
            ['coop_nom', 'Coopérative du personnel de la Commune de Ngoundiane', 'string', 'general', 'Nom de la coopérative'],
            ['coop_nom_court', 'Coop Ngoundiane', 'string', 'general', 'Nom court (application installée)'],
            ['coop_adresse', 'Mairie de Ngoundiane — Département de Thiès, Sénégal', 'string', 'general', 'Adresse'],
            ['coop_telephone', '', 'string', 'general', 'Téléphone de contact'],
            ['coop_logo', '', 'image', 'general', 'Logo', 'Si vide, le logo de la Mairie de Ngoundiane est utilisé.'],
            ['exercice_debut_mois', '1', 'select', 'general', 'Mois de début de l\'exercice', null,
                collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \App\Support\Periode::nomMois($m)])->all()],
            ['solde_initial', '0', 'int', 'general', 'Solde initial de caisse (FCFA)', 'Solde de la caisse au démarrage de l\'application.'],

            // Cotisations
            ['cotisation_montant', '10000', 'int', 'cotisations', 'Montant de la cotisation mensuelle (FCFA)',
                'Un changement ne s\'applique qu\'aux mois non encore générés (jamais rétroactivement).'],
            ['cotisation_jour_echeance', '5', 'int', 'cotisations', 'Jour d\'échéance du mois',
                'Un paiement effectué après ce jour est « en retard » ; non réglé après ce jour, il est « impayé ».'],
            ['cotisation_periode_debut', '2026-05', 'month', 'cotisations', 'Première période de cotisation',
                'Aucune cotisation n\'est générée avant ce mois.'],
            ['regle_adhesion', 'echeance', 'select', 'cotisations', 'Adhésion en cours de mois',
                'Détermine le premier mois dû par un nouveau membre (cotisation toujours complète, jamais proratisée).', [
                    'echeance' => 'Mois d\'adhésion dû si adhésion au plus tard le jour d\'échéance, sinon mois suivant',
                    'mois_complet' => 'Mois d\'adhésion toujours dû',
                    'mois_suivant' => 'Toujours à partir du mois suivant l\'adhésion',
                ]],
            ['paiement_partiel_autorise', '1', 'bool', 'cotisations', 'Autoriser les paiements partiels',
                'Si activé, un versement inférieur au montant dû donne le statut « Partiellement payé ».'],
            ['suspendu_redevable', '1', 'bool', 'cotisations', 'Un membre suspendu reste redevable',
                'Si désactivé, aucune cotisation n\'est générée pendant la suspension.'],
            ['paiement_avance_max_mois', '12', 'int', 'cotisations', 'Paiement d\'avance : nombre de mois maximum'],
            ['doublon_fenetre_jours', '3', 'int', 'cotisations', 'Détection de doublon : fenêtre (jours)',
                'Alerte si un paiement de même montant existe pour le même membre dans cet intervalle.'],

            // Numérotation
            ['matricule_prefixe', 'NGD-', 'string', 'numerotation', 'Préfixe des matricules'],
            ['recu_prefixe', 'REC', 'string', 'numerotation', 'Préfixe des numéros de reçu'],
            ['operation_prefixe', 'OP', 'string', 'numerotation', 'Préfixe des numéros d\'opération'],

            // Notifications
            ['rappel_actif', '1', 'bool', 'notifications', 'Rappels d\'échéance (notification interne)'],
            ['rappel_jours_avant', '3', 'int', 'notifications', 'Rappel : nombre de jours avant l\'échéance'],
            ['notification_retard_actif', '1', 'bool', 'notifications', 'Notification de retard après l\'échéance'],

            // Assistante IA
            ['ia_active', '1', 'bool', 'assistant', 'Activer l\'assistante',
                'Moteur local gratuit : fonctionne sans Internet ni abonnement, aucune donnée ne quitte le serveur.'],
            ['ia_nom', 'Fatou', 'string', 'assistant', 'Nom de l\'assistante'],
            ['ia_moteur', 'local', 'select', 'assistant', 'Moteur de l\'assistante',
                'Local : gratuit, sans Internet (règles et vocabulaire). Claude : agent IA qui raisonne et comprend le wolof librement ; nécessite la clé ANTHROPIC_API_KEY (payant à l\'usage) et transmet les données de la demande à Anthropic. Hybride : local d\'abord, Claude seulement si le local ne comprend pas.', [
                    'local' => 'Local (gratuit)',
                    'hybride' => 'Hybride : local, puis Claude si nécessaire',
                    'claude' => 'Claude : agent IA qui raisonne (payant)',
                ]],

            // Sauvegarde
            ['sauvegarde_retention', '14', 'int', 'sauvegarde', 'Nombre de sauvegardes conservées'],
        ];

        foreach ($parametres as $p) {
            $param = Parametre::firstOrNew(['cle' => $p[0]]);
            if (! $param->exists) {
                $param->valeur = $p[1];
            }
            $param->fill([
                'type' => $p[2], 'groupe' => $p[3], 'libelle' => $p[4],
                'description' => $p[5] ?? null, 'options' => $p[6] ?? null, 'ordre' => ++$o,
            ])->save();
        }
        // Ancien réglage, remplacé par « ia_moteur ».
        Parametre::where('cle', 'ia_claude')->delete();
        app(Parametres::class)->flush();

        $modes = [
            ['Espèces', 'especes', false], ['Wave', 'wave', true], ['Orange Money', 'orange_money', true],
            ['Virement bancaire', 'virement', true], ['Chèque', 'cheque', true], ['Autre', 'autre', false],
        ];
        foreach ($modes as $i => [$nom, $code, $ref]) {
            ModePaiement::firstOrCreate(['code' => $code], ['nom' => $nom, 'reference_requise' => $ref, 'actif' => true, 'ordre' => $i + 1]);
        }

        $categories = [
            ['Contributions exceptionnelles', 'entree', false], ['Dons', 'entree', false],
            ['Intérêts et produits bancaires', 'entree', false], ['Autres recettes', 'entree', false],
            ['Aides sociales aux membres', 'sortie', true], ['Frais de fonctionnement', 'sortie', false],
            ['Fournitures et impression', 'sortie', false], ['Frais bancaires', 'sortie', false],
            ['Remboursement de cotisation', 'sortie', true], ['Autres dépenses', 'sortie', false],
        ];
        foreach ($categories as [$nom, $type, $restreinte]) {
            Categorie::firstOrCreate(['nom' => $nom, 'type' => $type], ['restreinte' => $restreinte, 'actif' => true]);
        }
    }
}
