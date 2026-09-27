# Séance

Application de séances de salle : on compose ses séances à partir d'une bibliothèque de
86 exercices, puis le lecteur guide série par série — compte à rebours, effort, repos entre
séries et entre exercices, bips et vibrations, écran maintenu allumé. Mise en œuvre de la
maquette « Séance » (Barlow Condensed + Manrope, citron sur noir).

En ligne : https://sports.vallau.com — mise en ligne décrite dans [DEPLOIEMENT.md](DEPLOIEMENT.md).

## Ce que fait l'application

- **Mes séances** — l'accueil. Trois séances d'exemple sont offertes à l'inscription (Push,
  Jambes, HIIT 20 minutes). Chaque carte donne le nombre d'exercices, de séries, une durée
  estimée (3 s par répétition) et la date de la dernière fois.
- **Exercices** — la bibliothèque, par muscle ou par machine, filtrable et cherchable. Chaque
  fiche montre deux images qui alternent (position de départ, d'arrivée), les muscles, les
  étapes, un conseil, et un lien vers une démo vidéo.
- **Éditeur** — nom, exercices dans l'ordre, et pour chacun : répétitions ou durée, séries,
  repos entre séries, repos après l'exercice. Le brouillon reste dans le navigateur jusqu'à
  « Enregistrer ».
- **Lecteur** — tout se joue dans le navigateur, sur des instants absolus : un onglet endormi
  ne fait pas dériver les minuteurs. Une séance interrompue (rechargement, appel) reprend en
  pause. En fin de séance, le journal part au serveur ; sans réseau, il est gardé sur le
  téléphone et renvoyé plus tard, sans jamais être compté deux fois.
- **Réglages** — bips et vibrations, durée du compte à rebours de départ, déconnexion.

## Les images d'exercices

77 exercices sont illustrés par les photos de
[free-exercise-db](https://github.com/yuhonas/free-exercise-db), versé au domaine public
(Unlicense), redimensionnées à 720 px. Les 9 absents de cette base (air bike, burpees, chaise,
hip thrust machine, hollow hold, jumping jacks, kickback machine, SkiErg, tractions assistées)
sont dessinés par [scripts/illustrations.mjs](scripts/illustrations.mjs) : un pictogramme SVG
en deux positions, au même format 3:2.

Le catalogue vit dans le code, [database/data/exercises.php](database/data/exercises.php) : un
contenu éditorial versionné avec l'application. Le slug est la clé stockée dans les séances.

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
