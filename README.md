# Séance

Application de séances de salle : on compose ses séances à partir d'une bibliothèque de
370 exercices — toutes les machines d'une salle comme L'Appart Fitness —, puis le lecteur
guide série par série — compte à rebours, effort, repos entre
séries et entre exercices, bips et vibrations, écran maintenu allumé. Mise en œuvre de la
maquette « Séance » (Barlow Condensed + Manrope, citron sur noir).

En ligne : https://sports.vallau.com — mise en ligne décrite dans [DEPLOIEMENT.md](DEPLOIEMENT.md).

## Ce que fait l'application

- **Aujourd'hui** — l'accueil : reprendre la séance laissée en cours sur ce téléphone, composer
  une séance (l'assistant, déjà orienté muscu, perte de poids ou cardio) ou partir d'une séance
  vide, refaire une des trois séances les plus récentes, et la semaine en chiffres. Un compte neuf
  part sans séance.
- **Séances** — toutes les séances enregistrées, cherchables : les lancer, les modifier, les
  supprimer ; chaque carte donne le nombre d'exercices, de séries, une durée estimée (3 s par
  répétition), les muscles et la dernière fois. Les réglages ont leur page (`/reglages`).
- **Exercices** — la bibliothèque, par muscle ou par machine (87 machines et équipements :
  guidées Matrix et EGYM, machines assistées, iso-latérales Hammer Strength et Panatta,
  squats machines, poulies, zone cross-training / Hyrox, cardio), filtrable et cherchable —
  y compris par le nom écrit sur la machine (« Seated Leg Curl », « Calf Press »… : champ `aka`),
  en dix groupes dont Fonctionnel et Mobilité. Chaque fiche montre deux images qui alternent
  (position de départ, d'arrivée), la silhouette des muscles principaux et secondaires, les
  étapes, un conseil, et un lien vers une démo vidéo.
- **Muscles ciblés** — une silhouette de face et de dos s'allume selon les muscles travaillés :
  pour la séance entière dans l'éditeur (charge comptée en séries, une demie pour un muscle
  secondaire) et sur chaque carte de l'accueil, pour chaque exercice dans l'éditeur et sa
  fiche. Tracé repris de react-muscle-highlighter (MIT).
- **Assistant** — en trois étapes (la séance, les muscles, le temps ; deux pour le cardio) : on choisit les muscles à travailler (sur la silhouette ou par raccourcis :
  Push, Pull, Jambes, Full body…), la durée, l'objectif (force, volume, endurance) et le
  matériel (machines, charges libres, poids du corps), avec en option un échauffement et des
  étirements. Les séries par exercice (Auto ou 2 à 6), les répétitions par série, le repos
  entre séries et entre exercices se règlent (préremplis selon l'objectif) : moins de repos,
  plus d'exercices dans le même temps ; avec des séries fixées, c'est le nombre d'exercices
  qui s'ajuste au temps. Il propose une séance qui couvre chaque muscle demandé et tient dans le temps
  (à ±15 %), les classiques de salle et les gros mouvements en tête ; « Autre proposition »
  en tire une nouvelle. Un exercice qui ne plaît pas se change d'un toucher (⟳ : l'équivalent
  suivant, qui travaille les mêmes muscles) ou se choisit parmi les équivalents classés, voire
  dans toute la bibliothèque. On la lance tout de suite, on l'enregistre ou on la retouche dans
  l'éditeur. Le choix vit dans `app/Actions/SuggestWorkout.php`.
  Deux autres types de séance : **perte de poids** (muscles facultatifs, tout le corps sinon ;
  15 répétitions, repos courts, mouvements complets et fonctionnels, puis un bloc de fractionné
  en finisher) et **cardio** (ni muscles ni séries : échauffement, blocs continus ou fractionnés
  — 30/30, 40/20, Tabata, 1 min/1 min… — sur les machines ou au poids du corps, retour au calme ;
  `app/Actions/SuggestCardio.php`).
- **Éditeur** — nom, puis une ligne par exercice (« 3 × 10 reps · 60 kg · repos 1:30 ») ; un
  toucher ouvre ses réglages dans une feuille : charge en kilos (pas de 1 kg
  sous 10 kg puis de 2,5 kg, ou saisie libre) — fixe ou une par série (dégressif, pyramide ;
  « Dégressif auto » baisse de 10 % par série) —, drop set (jusqu'à quatre paliers enchaînés
  sans repos, sur la dernière série ou sur chacune), répétitions ou durée, séries, repos entre séries,
  repos après l'exercice. Le lecteur affiche la charge et la laisse ajuster en pleine séance :
  elle devient celle de l'exercice pour la fois suivante. Une machine de cardio a ses réglages
  (vitesse et inclinaison du tapis, niveau du vélo, du rameur… : `app/Support/MachineSettings.php`).
  ⟳ change un exercice pour une variante qui travaille les mêmes muscles ; « Compléter avec
  l'assistant » ajoute des exercices dans l'esprit de la séance (mêmes répétitions, séries,
  repos), avant les étirements. Le brouillon reste dans le navigateur jusqu'à « Enregistrer ».
- **Lecteur** — tout se joue dans le navigateur, sur des instants absolus : un onglet endormi
  ne fait pas dériver les minuteurs. Une séance interrompue (rechargement, appel) reprend en
  pause. Le programme (touche le compteur « Exercice n / N » ou « Changer ») réordonne la suite
  en pleine séance — machine prise : « Plus tard » (avant les étirements), « Maintenant », ↑ ↓ — ou
  prend une variante pour le jour, sans réseau ; l'exercice en cours garde son chrono. En fin de séance, le journal part au serveur ; sans réseau, il est gardé sur le
  téléphone et renvoyé plus tard, sans jamais être compté deux fois. Charge et répétitions se
  tapent au clavier (toucher la valeur) ; pendant le repos, on règle la charge de la série qui
  vient et on corrige la série faite. Les bips se mêlent à la musique sans la couper (Audio
  Session « transient ») ; le mode « Prioritaire » les fait sonner en silencieux, au prix de la
  musique. Appli en arrière-plan : iOS endort la page, le lecteur confie alors au serveur les fins
  de repos, envoyées en notifications web par le conteneur `alertes` (`php artisan
  alertes:envoyer`, clés VAPID du .env via `php artisan alertes:cles` ; sur iPhone, appli ajoutée
  à l'écran d'accueil).
- **Côtés** — chaque exercice dit s'il se fait des deux côtés à la fois, d'un côté puis de
  l'autre (`each` : le lecteur joue « côté droit » puis « côté gauche ») ou en alternant
  (`alternate` : la valeur compte par côté ou au total, au choix dans l'éditeur) ; champ
  `sides` du catalogue, `per_side` de chaque exercice d'une séance (durée et volume doublés).
- **Comme une vidéo** — le mode de son « Comme une vidéo » fait de la séance un média en
  lecture (`resources/js/nowPlaying.js`) : une piste fabriquée à la volée, silence et bips du
  décompte à la seconde, garde la page éveillée en arrière-plan ; l'écran verrouillé et le
  centre de contrôle montrent l'exercice, la série, la fin du repos et la progression, avec
  pause, « suivant » (série faite) et « précédent ». La musique se met en pause. Sinon, une
  notification silencieuse « Séance en cours » tient le centre de notifications à jour.
- **Zones** — chaque grand muscle a ses zones (`database/data/zones.php` : haut, milieu, bas,
  intérieur, extérieur des pectoraux…), et chaque exercice celles qu'il travaille (`zones`).
  L'éditeur et l'assistant montrent la couverture d'une séance ; toucher une zone oubliée
  propose de quoi la travailler, et l'assistant varie lui-même les angles.
- **Progrès** — seules les séances menées au bout (toutes les séries prévues faites) comptent ;
  une séance interrompue est gardée mais n'entre dans aucune statistique. Chaque série réellement faite est enregistrée (charge, répétitions faites,
  ajustables au « − / + » du lecteur, objectif, paliers de drop), avec la difficulté ressentie
  notée en fin de séance et les pesées. En découlent : sur l'accueil, la semaine (séances,
  semaines d'affilée, tonnage comparé à la semaine dernière au même moment, temps, records,
  muscles délaissés depuis 14 jours) ; dans « Progrès », les séances et le tonnage par semaine,
  le calendrier, la charge d'entraînement (difficulté × minutes), les records ; par exercice, le
  1RM estimé (Epley) et sa courbe, la tendance sur 4 semaines, les records, l'historique et la
  charge conseillée (surcharge progressive) ; par muscle, les séries de la semaine face au
  repère de 10 à 20, l'évolution de la force et les équilibres poussée/tirage, haut/bas,
  quadriceps/ischios ; côté corps, le poids, le poids visé et la force relative des grands
  mouvements. Le cardio a ses minutes par semaine, et chaque séance ses calories estimées en MET
  (`app/Support/Energy.php`, au poids de la dernière pesée, 75 kg à défaut). Le
  lecteur rappelle la dernière fois, propose la charge conseillée et annonce les records battus.
- **Réglages** — bips et vibrations, bips avant la fin d'un repos (3, 5 ou 10 s, le dernier plus aigu
  pour annoncer la reprise), volume des bips avec un bouton « Tester », durée du compte à
  rebours de départ, déconnexion. Le son du décompte se choisit : bip (par défaut), double bip,
  cloche, sifflet, claquement — synthétisés par le navigateur — voix française (« 3, 2, 1… Go ! »)
  ou « Mon son », un fichier envoyé (mp3, m4a, wav, ogg ; 2 Mo) rangé sur le disque privé.
  Les sons passent outre le mode silencieux de l'iPhone.

## Les images d'exercices

- 276 exercices sont illustrés par les photos de
  [free-exercise-db](https://github.com/yuhonas/free-exercise-db), versé au domaine public
  (Unlicense), redimensionnées à 720 px ;
- 25 par des images de [wger](https://wger.de) sous licence Creative Commons, dont l'auteur et
  la licence sont affichés sur la fiche ;
- les 64 autres, absents de ces banques (machines récentes, zone Hyrox, mobilité…), sont
  dessinés par [scripts/illustrations.mjs](scripts/illustrations.mjs) : un pictogramme SVG en
  deux positions, au même format 3:2 (outils dans `scripts/illustrations/lib.mjs`).

Le catalogue vit dans le code, [database/data/exercises.php](database/data/exercises.php) : un
contenu éditorial versionné avec l'application. Le slug est la clé stockée dans les séances ;
les 86 exercices de la première version gardent le leur (un test y veille).

## Stack

Les mêmes choix que pointage et Drop Picture : Laravel 13, Inertia 3, Vue 3 en JavaScript,
Tailwind 4 (`@theme` + `@utility` dans `resources/css/app.css`), Reka UI, authentification
maison, PHPUnit 12, URLs en français, FrankenPHP en production derrière le Traefik du VPS.

## Développer

Aucun PHP n'est nécessaire en local : tout passe par Docker.

```bash
cp .env.example .env
docker compose up -d            # Sail : application + MySQL
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate --seed
npm install && npm run dev
```

Compte de démonstration : `demo@seance.test` / `password`.

Tests :

```bash
docker compose exec laravel.test php artisan test
```
