# Séance

Application de séances de salle : on compose ses séances à partir d'une bibliothèque de
365 exercices — toutes les machines d'une salle comme L'Appart Fitness —, puis le lecteur
guide série par série — compte à rebours, effort, repos entre
séries et entre exercices, bips et vibrations, écran maintenu allumé. Mise en œuvre de la
maquette « Séance » (Barlow Condensed + Manrope, citron sur noir).

En ligne : https://sports.vallau.com — mise en ligne décrite dans [DEPLOIEMENT.md](DEPLOIEMENT.md).

## Ce que fait l'application

- **Mes séances** — l'accueil. Trois séances d'exemple sont offertes à l'inscription (Push,
  Jambes, HIIT 20 minutes). Chaque carte se modifie ou se supprime d'un geste ; elle donne le nombre d'exercices, de séries, une durée
  estimée (3 s par répétition) et la date de la dernière fois.
- **Exercices** — la bibliothèque, par muscle ou par machine (83 machines et équipements :
  guidées Matrix et EGYM, machines assistées, iso-latérales Hammer Strength et Panatta,
  squats machines, poulies, zone cross-training / Hyrox, cardio), filtrable et cherchable,
  en dix groupes dont Fonctionnel et Mobilité. Chaque fiche montre deux images qui alternent
  (position de départ, d'arrivée), la silhouette des muscles principaux et secondaires, les
  étapes, un conseil, et un lien vers une démo vidéo.
- **Muscles ciblés** — une silhouette de face et de dos s'allume selon les muscles travaillés :
  pour la séance entière dans l'éditeur (charge comptée en séries, une demie pour un muscle
  secondaire) et sur chaque carte de l'accueil, pour chaque exercice dans l'éditeur et sa
  fiche. Tracé repris de react-muscle-highlighter (MIT).
- **Assistant** — on choisit les muscles à travailler (sur la silhouette ou par raccourcis :
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
- **Éditeur** — nom, exercices dans l'ordre, et pour chacun : charge en kilos (pas de 1 kg
  sous 10 kg puis de 2,5 kg, ou saisie libre), répétitions ou durée, séries, repos entre séries,
  repos après l'exercice. Le lecteur affiche la charge et la laisse ajuster en pleine séance :
  elle devient celle de l'exercice pour la fois suivante. Le brouillon reste dans le navigateur jusqu'à
  « Enregistrer ».
- **Lecteur** — tout se joue dans le navigateur, sur des instants absolus : un onglet endormi
  ne fait pas dériver les minuteurs. Une séance interrompue (rechargement, appel) reprend en
  pause. En fin de séance, le journal part au serveur ; sans réseau, il est gardé sur le
  téléphone et renvoyé plus tard, sans jamais être compté deux fois.
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
