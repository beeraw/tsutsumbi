# Production sous Docker (rossem)

tsutsumbi tourne sur rossem à côté d'autres sites servis par le Caddy, le PHP-FPM et la MariaDB de l'hôte. Il passe
sous Docker, base comprise, comme Stetboard ; rien ne change pour les autres sites.

## Principe

- **Une image système, sans le code** (`Dockerfile`, `docker/`) : PHP 8.5.12 sous FrankenPHP, les extensions
  (imagick, intl, pdo_mysql, zip) et composer. Elle est construite **sur rossem**, par le script de livraison,
  seulement quand `Dockerfile` ou `docker/` change. Pas de registre, pas de jeton.
- **Le code reste le checkout git** `/var/www/tsutsumbi.beeraw.yt/_online`, monté sur `/app`, avec son `.env.local`
  et `var/` (cache, journal d'API `var/log/api.log`, pièces jointes `var/attachments/`).
- **Deux conteneurs** (`deploy/production/compose.yaml`), sur le réseau Docker du projet :
  - `app` : FrankenPHP, publié sur `127.0.0.1:8100` ;
  - `database` : MariaDB 12.3, ses fichiers dans `/srv/tsutsumbi-production/mysql`, publiée sur `127.0.0.1:3316`
    pour un administrateur du serveur. La MariaDB de l'hôte ne sert plus tsutsumbi après la bascule.

  Il n'y a ni `worker` ni `cron` : l'application n'a pas de transport Messenger, pas de commande planifiée et
  n'envoie aucun courriel. `app` tourne en `deploy:www-data` (1001:33), l'identité de l'ancien pool PHP-FPM. Il lit
  son `DATABASE_URL` dans le compose, qui l'emporte sur celle du `.env.local` du checkout ; celle-ci continue de
  désigner l'ancienne base, ce dont le retour arrière a besoin.
- **`/srv/tsutsumbi-production`** contient `compose.yaml` (une copie, que `tsutsumbi-deploy` remet à jour quand un
  commit le modifie), `.env` (ports et mots de passe de la base, tirés au hasard) et `mysql/`.
- **Le Caddy de l'hôte** garde le HTTPS et les filtres, et transmet à `127.0.0.1:8100`. Le bloc est celui d'avant :
  `block_probes`, `block_bots`, `encode`, l'en-tête `?Strict-Transport-Security` (le `?` est voulu : l'application
  envoie le sien), le journal d'accès. Seuls `php_fastcgi` et `file_server` deviennent `reverse_proxy`. Aucun autre
  bloc du Caddyfile ne renvoie vers tsutsumbi.
- **Le serveur MCP** n'est pas déployé : c'est un processus local (stdio) qui appelle l'API en HTTPS avec le jeton
  `Authorization: Bearer`. Rien ne change pour lui, pas de SSE ni de flux : de simples réponses JSON.
- **Une livraison** (`deploy/server/tsutsumbi-deploy`, lancée par le workflow GitHub à chaque push sur `main`) :
  contrôle de `.env.local` (`APP_ENV=prod`, `APP_LOCALE` présent), `git pull`, puis seulement ce que le commit
  demande (image, composer), migrations, cache, redémarrage, attente de `/healthz`. La base n'est jamais
  redémarrée par une livraison. Remplace l'ancien `bin/deploy`, qui n'est plus utilisé.

## Bascule

Elle passe par `deploy/production/cutover`, une phase à la fois, en root. Chaque phase dit ce qu'elle a changé et en
combien de temps. Le retour arrière marche depuis n'importe quelle phase.

**Ne pas pousser sur `main` avant la fin de la bascule** : le workflow appelle `tsutsumbi-deploy`, qui n'existe pas
encore sur le serveur, puis livrerait dans des conteneurs dont la base est vide.

### Avant le jour J

1. Docker est déjà installé sur rossem (Stetboard). Vérifier que les ports `8100` et `3316` sont libres :
   `ss -ltn | grep -E ':(8100|3316) '` ne doit rien afficher.

2. Autoriser l'utilisateur du workflow (secret `MAIN_USERNAME`) à lancer le script de livraison, et lui seul, dans
   `/etc/sudoers.d/tsutsumbi-deploy` :

   ```
   deploy ALL=(root) NOPASSWD: /usr/local/bin/tsutsumbi-deploy /srv/tsutsumbi-production *
   ```

3. Récupérer le script de bascule du commit à livrer, sans toucher au checkout qui sert le site :

   ```bash
   sudo -u deploy git -C /var/www/tsutsumbi.beeraw.yt/_online fetch origin
   sudo -u deploy git -C /var/www/tsutsumbi.beeraw.yt/_online show <commit>:deploy/production/cutover | sudo tee /root/tsutsumbi-cutover >/dev/null
   sudo chmod 700 /root/tsutsumbi-cutover
   ```

4. Préparer, toujours sans toucher au site :

   ```bash
   sudo /root/tsutsumbi-cutover prepare <commit>
   ```

   Ce que fait cette phase :
   - elle crée `/srv/tsutsumbi-production`, avec son `.env` et des mots de passe tirés au hasard ;
   - elle note l'ancien commit et la dernière migration de l'ancien site, pour le retour arrière ;
   - elle installe `compose.yaml` et `tsutsumbi-deploy` du commit ;
   - elle construit l'image et démarre la base, vide ;
   - elle écrit et valide deux Caddyfiles : maintenance et conteneurs.

### Le jour J (quelques minutes de coupure)

```bash
sudo /root/tsutsumbi-cutover freeze    # page de maintenance (503 + Retry-After, y compris pour l'API)
sudo /root/tsutsumbi-cutover data      # copie de la base de l'hôte dans le conteneur
sudo /root/tsutsumbi-cutover start     # tsutsumbi-deploy : checkout sur le commit, migrations, app
sudo /root/tsutsumbi-cutover open      # Caddy vers les conteneurs, fin de la coupure
```

Pas de crontab ni de service systemd à toucher : tsutsumbi n'en a pas. Les lignes backup.kds et Ujuzi de la crontab de
`deploy` ne sont jamais modifiées.

Puis vérifier le site, une connexion, un appel de l'API avec un jeton (le client MCP), et pousser sur `main` le commit
livré : la première livraison automatique doit finir par « Livré ».

### Retour arrière

```bash
sudo /root/tsutsumbi-cutover rollback
```

Après `open`, il remet la page de maintenance et arrête le conteneur. Il recopie ensuite la base du conteneur dans
celle de l'hôte, pour ne rien perdre de ce qui a été saisi depuis la bascule, puis ramène les migrations à la dernière
que connaissait l'ancien site. Dans tous les cas, il remet le checkout sur l'ancien commit, avec ses dépendances,
rétablit l'ancien Caddyfile et recharge PHP-FPM.

### Après la bascule

Une fois le site stable, ces fichiers peuvent être supprimés : `bascule.sql`, `retour.sql`, `Caddyfile.avant-bascule`,
`Caddyfile.maintenance`, `Caddyfile.containers`. L'ancienne base `tsutsumbi` de la MariaDB de l'hôte peut l'être à son
tour.

## Sur le Mac

La même image et le même conteneur de base servent le site de développement, sur
`https://tsutsumbi.local.beeraw.yt` (le Caddy du Mac transmet à `127.0.0.1:8100`) : `deploy/local/compose.yaml`,
environnement `dev`. La base du conteneur, publiée sur `127.0.0.1:3326`, est la base de développement : `bin/console`
lancé sur le Mac s'y connecte (`.env.dev`). `deploy/local/load-database` la remplace par la base du MariaDB du Mac.
Les tests restent sur le MariaDB du Mac (`tsutsumbi_test`).

## Au quotidien

```bash
cd /srv/tsutsumbi-production
sudo docker compose ps
sudo docker compose logs -f --tail 100 app
sudo docker compose exec app php bin/console …
sudo docker compose exec database sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" tsutsumbi'
```

Livrer un commit précis, ou revenir en arrière : onglet **Actions** du dépôt, workflow « CD main », **Run workflow**,
champ « Commit à livrer ».
