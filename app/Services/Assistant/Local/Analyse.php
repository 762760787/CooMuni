<?php

namespace App\Services\Assistant\Local;

/** Résultat de l'analyse d'une phrase par le moteur local. */
final class Analyse
{
    public string $texte = '';

    /** @var list<string> mots restants, candidats au nom du membre */
    public array $motsNom = [];

    // Intentions détectées
    public bool $paiement = false;
    public bool $situation = false;
    public bool $question = false;
    public bool $impayes = false;
    public bool $bilan = false;
    public bool $aide = false;
    public bool $salutation = false;
    public bool $merci = false;
    public bool $oui = false;
    public bool $non = false;

    // Informations extraites
    public ?int $montant = null;
    public ?int $nbMois = null;
    public bool $tout = false;
    /** @var list<string> périodes AAAA-MM complètes */
    public array $periodes = [];
    /** @var list<int> mois cités sans année (1-12), résolus plus tard selon le membre */
    public array $moisSansAnnee = [];
    public ?string $date = null;
    public ?string $mode = null;
    public ?string $reference = null;
    public ?string $matricule = null;
    public ?string $telephone = null;
    public ?int $ordinal = null;
    /** @var list<int> petits nombres isolés (choix « 2 », matricule court…) */
    public array $petitsNombres = [];
    public bool $wolof = false;

    /** La phrase contient-elle une précision d'encaissement (utile pour compléter une demande en cours) ? */
    public function aDesChamps(): bool
    {
        return $this->montant !== null || $this->nbMois !== null || $this->tout || $this->periodes || $this->moisSansAnnee
            || $this->date !== null || $this->mode !== null || $this->reference !== null;
    }

    /** Champs d'encaissement explicitement donnés par l'utilisateur. */
    public function champs(): array
    {
        return array_filter([
            'montant' => $this->montant,
            'nbMois' => $this->nbMois,
            'tout' => $this->tout ?: null,
            'periodes' => $this->periodes ?: null,
            'moisSansAnnee' => $this->moisSansAnnee ?: null,
            'date' => $this->date,
            'mode' => $this->mode,
            'reference' => $this->reference,
        ], fn ($v) => $v !== null);
    }
}
