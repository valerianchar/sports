<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inscriptions ouvertes
    |--------------------------------------------------------------------------
    |
    | Une instance personnelle peut vouloir fermer la porte une fois ses
    | proches inscrits : l'écran d'inscription répond alors 404.
    |
    */

    'registration_open' => (bool) env('SPORT_REGISTRATION_OPEN', true),

    /*
    |--------------------------------------------------------------------------
    | Tempo d'estimation
    |--------------------------------------------------------------------------
    |
    | Secondes comptées par répétition pour estimer la durée d'une séance
    | (« ~45 min »). Une estimation, pas une consigne : le lecteur attend que
    | l'on valide la série, quel que soit le temps passé.
    |
    */

    'seconds_per_rep' => (float) env('SPORT_SECONDS_PER_REP', 3),

    /*
    |--------------------------------------------------------------------------
    | Limites d'une séance
    |--------------------------------------------------------------------------
    */

    'max_items' => 40,

];
