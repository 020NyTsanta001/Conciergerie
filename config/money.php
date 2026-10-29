<?php

/*
|--------------------------------------------------------------------------
| PRIX ET PAIEMENTS : tous les réglages au même endroit
|--------------------------------------------------------------------------
| Les montants sont stockés et échangés en Ariary (Ar), en nombres entiers.
*/
return [
    'currencies' => ['MGA', 'USD'],

    'usd_rate' => 5000,        // 1 $ = 5 000 Ar (fixe)

    'deposit_rate' => 0.30,     // acompte demandé après la validation par l'hôte
    'commission_rate' => 0.15,  // part de la plateforme sur un séjour payé en intégralité

    // Bonus de test (simulation) : crédités une seule fois par bouton et par compte
    'rewards' => [
        'about' => 1_000_000,       // « À propos de moi »
        'github' => 1_000_000,      // « Mon GitHub »
        'org_github' => 1_000_000,  // « GitHub de l'entreprise »
        'cv' => 1_000_000,          // « Mon CV »
    ],

    // Pac-Man : un palier toutes les 20 s de jeu, 2 parties rémunérées par compte
    'pacman' => [
        'max_paid_games' => 2,
        'step_seconds' => 20,
        'step_amount' => 1_000_000,
        'max_steps' => 5,      // 100 s = 5 000 000 Ar
    ],

    // Encaissement du voyageur : 'wallet' (simulé). Plus tard : 'mvola', 'stripe'…
    'payment_driver' => env('PAYMENT_DRIVER', 'wallet'),
];