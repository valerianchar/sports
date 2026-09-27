# Mise en ligne

Séance se déploie sur le VPS qui héberge déjà **pointage** et **Drop Picture**, et réutilise
tout ce que pointage a installé une fois pour toutes (voir son `DEPLOIEMENT.md`) : le reverse
proxy Traefik, le registre d'images privé, le monitoring, l'utilisateur `deploiement` et sa
clé SSH. Il ne reste que la pile applicative elle-même.

```
/srv
├── proxy/          Traefik (pointage)        — déjà en place
├── registry/       registre privé (pointage) — déjà en place
├── monitoring/     Prometheus + Grafana      — déjà en place
├── pointage/
├── drop-picture/
└── sports/         la pile Séance             ← ce qui suit
```

La pile ne compte que deux conteneurs, `sports-app-1` et `sports-mysql-1` : les minuteurs
tournent dans le navigateur, il n'y a ni file d'attente, ni planificateur, ni temps réel.

## 1. Le domaine

Un enregistrement DNS **A** pour `sports.vallau.com` vers l'IP du serveur
(`217.160.8.59`). Traefik obtient le certificat Let's Encrypt (défi HTTP) à la première
requête qui suit la propagation.

## 2. Le dossier de l'application

Sur le serveur, en tant qu'utilisateur `deploiement` :

```bash
sudo mkdir -p /srv/sports && sudo chown deploiement:deploiement /srv/sports
```

Déposez-y un `.env` à partir de [.env.production.example](.env.production.example) et
remplissez chaque valeur marquée « À REMPLIR » : `APP_KEY`, `DB_PASSWORD`,
`DB_ROOT_PASSWORD`, et le mot de passe SMTP. Les e-mails (mot de passe oublié) partent par la
boîte IONOS `vallau@vallau.com`, comme ceux de Drop Picture.

## 3. Les secrets GitHub

Les mêmes que pointage, avec la même clé de déploiement : `VPS_HOST`, `VPS_USER`,
`VPS_SSH_KEY`, `VPS_KNOWN_HOSTS`, `REGISTRY_USER`, `REGISTRY_PASSWORD` — et `APP_HEALTH_URL`
avec l'adresse de Séance (`https://sports.vallau.com`).

## 4. Déployer

Poussez sur `main` : le workflow [deploiement.yml](.github/workflows/deploiement.yml) lance
les tests sur MySQL 8.4, construit l'image, la publie sur le registre du VPS par tunnel SSH,
dépose `compose.deploy.yaml` et `redeploie.sh` dans `/srv/sports`, puis bascule.
`redeploie.sh` attend que `/up` réponde à travers Traefik avant de déclarer la bascule réussie.

Tant que le DNS n'est pas en place, la dernière étape de la CI (vérification publique) se
contente d'un avertissement : la vérification sur le serveur a déjà eu lieu.

À la main, sur le serveur :

```bash
cd /srv/sports && ./redeploie.sh latest
```

## Dépannage

**`/up` ne répond pas après la bascule.** `docker compose -f compose.deploy.yaml logs app` :
en général la base n'est pas encore prête (le conteneur réessaie 30 fois) ou une variable du
`.env` manque.

**Le certificat n'arrive pas.** `docker logs proxy-traefik-1 2>&1 | grep -i sports` : le défi
HTTP échoue tant que `sports.vallau.com` ne pointe pas sur `217.160.8.59`
(`dig +short sports.vallau.com`).

**Mémoire.** La pile ajoute ~150 Mo à la machine (MySQL 32 Mo de buffer pool, l'application).
