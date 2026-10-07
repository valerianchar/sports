<?php

/*
 * Les zones de chaque grand muscle : les parties qu'une séance complète
 * devrait toucher. Le muscle ne se découpe pas toujours ainsi pour un
 * anatomiste — on ne contracte pas « l'intérieur » d'un pectoral seul —,
 * mais c'est ainsi qu'une salle parle de l'angle, de l'étirement ou de la
 * contraction qu'un exercice privilégie.
 *
 * Chaque groupe couvre des clés de muscles (App\Enums\Muscle) ; chaque
 * exercice de database/data/exercises.php liste les zones qu'il travaille
 * vraiment (`zones`).
 */

return [
    'pectoraux' => [
        'label' => 'Pectoraux',
        'muscles' => ['chest'],
        'zones' => [
            'pectoraux.haut' => ['label' => 'Haut', 'hint' => 'faisceau claviculaire : développés et écartés inclinés'],
            'pectoraux.milieu' => ['label' => 'Milieu', 'hint' => 'faisceau sternal : développé couché, presses à plat'],
            'pectoraux.bas' => ['label' => 'Bas', 'hint' => 'développé décliné, dips, écarté poulie haute vers le bas'],
            'pectoraux.interieur' => ['label' => 'Intérieur', 'hint' => 'fin de contraction, bras qui se croisent : pec deck, vis-à-vis, presses convergentes'],
            'pectoraux.exterieur' => ['label' => 'Extérieur', 'hint' => 'étirement et prise large : écarté haltères, dips, développé prise large'],
        ],
    ],
    'dos' => [
        'label' => 'Dos',
        'muscles' => ['upper-back', 'trapezius'],
        'zones' => [
            'dos.largeur' => ['label' => 'Largeur', 'hint' => 'grand dorsal : tractions, tirages verticaux, pull-over'],
            'dos.epaisseur' => ['label' => 'Épaisseur', 'hint' => 'milieu du dos : rowings, tirage horizontal'],
            'dos.haut' => ['label' => 'Haut / trapèzes', 'hint' => 'trapèzes et rhomboïdes : shrugs, face pull, rowing menton'],
        ],
    ],
    'epaules' => [
        'label' => 'Épaules',
        'muscles' => ['front-deltoids', 'rear-deltoids'],
        'zones' => [
            'epaules.avant' => ['label' => 'Avant', 'hint' => 'développés militaires, élévations frontales'],
            'epaules.cote' => ['label' => 'Côté', 'hint' => 'élévations latérales, rowing menton'],
            'epaules.arriere' => ['label' => 'Arrière', 'hint' => 'oiseau, face pull, reverse pec deck'],
        ],
    ],
    'biceps' => [
        'label' => 'Biceps',
        'muscles' => ['biceps'],
        'zones' => [
            'biceps.longue' => ['label' => 'Longue portion', 'hint' => 'bras en arrière ou prise serrée : curl incliné, curl prise serrée'],
            'biceps.courte' => ['label' => 'Courte portion', 'hint' => 'bras devant ou prise large : curl pupitre, curl prise large'],
            'biceps.brachial' => ['label' => 'Brachial', 'hint' => 'prise neutre ou en pronation : curl marteau, curl inversé'],
        ],
    ],
    'triceps' => [
        'label' => 'Triceps',
        'muscles' => ['triceps'],
        'zones' => [
            'triceps.longue' => ['label' => 'Longue portion', 'hint' => 'bras au-dessus de la tête : extension nuque, barre au front'],
            'triceps.lateral' => ['label' => 'Vastes (côté)', 'hint' => 'bras le long du corps : extension poulie, dips, kickback'],
        ],
    ],
    'quadriceps' => [
        'label' => 'Quadriceps',
        'muscles' => ['quadriceps'],
        'zones' => [
            'quadriceps.droit' => ['label' => 'Droit fémoral', 'hint' => 'genou qui s\'étend hanche ouverte : leg extension, sissy squat'],
            'quadriceps.vastes' => ['label' => 'Vastes', 'hint' => 'genou qui fléchit sous charge : squat, presse, fentes, hack squat'],
        ],
    ],
    'ischios' => [
        'label' => 'Ischio-jambiers',
        'muscles' => ['hamstring'],
        'zones' => [
            'ischios.flexion' => ['label' => 'Flexion du genou', 'hint' => 'leg curl assis, allongé, debout, nordic'],
            'ischios.hanche' => ['label' => 'Extension de hanche', 'hint' => 'soulevé de terre roumain, good morning'],
        ],
    ],
    'fessiers' => [
        'label' => 'Fessiers',
        'muscles' => ['gluteal'],
        'zones' => [
            'fessiers.grand' => ['label' => 'Grand fessier', 'hint' => 'hip thrust, squat profond, fentes, kickback'],
            'fessiers.moyen' => ['label' => 'Moyen fessier', 'hint' => 'abduction, marche latérale, fente bulgare'],
        ],
    ],
    'abdos' => [
        'label' => 'Abdos',
        'muscles' => ['abs', 'obliques'],
        'zones' => [
            'abdos.haut' => ['label' => 'Haut', 'hint' => 'le buste s\'enroule : crunch, crunch poulie'],
            'abdos.bas' => ['label' => 'Bas', 'hint' => 'le bassin s\'enroule : relevés de jambes, crunch inversé'],
            'abdos.obliques' => ['label' => 'Obliques', 'hint' => 'rotation et flexion latérale : russian twist, gainage latéral'],
            'abdos.gainage' => ['label' => 'Gainage', 'hint' => 'résister au mouvement : planche, roue, pallof press'],
        ],
    ],
    'mollets' => [
        'label' => 'Mollets',
        'muscles' => ['calves'],
        'zones' => [
            'mollets.gastrocnemiens' => ['label' => 'Jumeaux', 'hint' => 'jambes tendues : mollets debout, à la presse'],
            'mollets.soleaire' => ['label' => 'Soléaire', 'hint' => 'genoux fléchis : mollets assis ; aussi la poussée de sled et la corde à sauter'],
        ],
    ],
];
