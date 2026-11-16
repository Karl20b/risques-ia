# Installation sur un espace dédié

## Prérequis

PHP 8.5 avec PDO MySQL, mbstring, cURL, JSON, sessions, DOM et ZIP ; MariaDB ; HTTPS ; accès à un terminal privé. Aucun Composer nécessaire. Installer sur un **nouvel espace et une nouvelle base**, distincts de vos autres applications.

## 1. Déposer les fichiers

Conserver l’arborescence. Configurer la racine web du site sur **`risques-ia/public/` uniquement**. Les dossiers `config`, `src`, `sql`, `rag`, `help`, `templates`, `tests`, `bin` et `var` restent hors de la racine HTTP. Le `.htaccess` racine refuse l’exposition accidentelle du paquet complet ; il ne remplace pas une bonne racine web.

Utiliser une origine dédiée, par exemple `https://risques.example.org`. Ne pas installer cette première édition dans un sous-chemin. PHP doit pouvoir écrire dans `var/` (0700) et, pour créer les comptes et changer les mots de passe, dans `config/`. Limiter ce droit au compte du serveur et garder les fichiers de configuration en 0600. Désactiver `display_errors` et garder les journaux hors du web.

## 2. Créer la base

Créer une base MariaDB vide et un utilisateur dédié. Importer `sql/001_schema.sql` avec un compte de migration temporaire. Le schéma crée six tables et n’importe aucune donnée. Ne jamais viser une base existante.

Le compte applicatif n’a pas besoin de DDL, DELETE ou GRANT. Droits attendus :

- SELECT, INSERT, UPDATE : `rgia_risks`, `rgia_actions` ;
- SELECT, INSERT : `rgia_events`, `rgia_assessments`, `rgia_decisions` ;
- SELECT, INSERT et UPDATE de la seule colonne `disposition` : `rgia_drafts`.

Faire vérifier ces droits par l’administrateur de l’hébergement. Retirer les droits temporaires de migration après installation.

## 3. Créer la configuration et votre compte

Dans un terminal privé, à la racine du paquet :

```sh
php bin/configure.php
```

Saisir l’origine HTTPS, le serveur, la base et les accès MariaDB, puis un mot de passe personnel d’au moins 14 caractères. Les mots de passe sont saisis de façon masquée. Ils ne passent pas en argument de commande ni sur un service externe. Le script vérifie la présence des tables sans les modifier et refuse d’écraser une configuration existante.

L’identifiant de gestion initial est **`admin`**. Son mot de passe est celui que vous choisissez : aucun mot de passe par défaut. Le fichier `config/config.local.php` reste privé et exclu de Git. Alternative : copier `config.example.php` et configurer manuellement des hashes générés par `password_hash(..., PASSWORD_DEFAULT)` dans un environnement privé.

Ouvrir le site et se connecter. Aucun script d’importation à lancer : le registre est vide. Les deux comptes supplémentaires proposés par l’écran Accès sont génériques : `responsable1` et `responsable2`. Leur mot de passe temporaire doit être changé à la première connexion et expire au bout de sept jours. Les identifiants sont sensibles à la casse. Cette version n’offre pas de réinitialisation automatique par courriel.

## 4. Activer éventuellement l’Assistant IA

La gestion du registre fonctionne sans IA. Avant activation, vérifier les conditions de votre service, les coûts et la destination autorisée des données. Dans votre configuration privée, renseigner `ai.enabled`, l’URL HTTPS complète Chat Completions, `allowed_host`, `api_key` et `model`. Fournir votre propre clé ; aucun compte ou crédit n’est partagé. Le serveur refuse les redirections et les destinations réseau privées.

L’aide à la formulation fonctionne sans corpus et n’enregistre pas une fiche. Le RAG sourcé nécessite un corpus autorisé préparé séparément selon [RAG.md](RAG.md). Les textes sont relus avant envoi ; les propositions et validations humaines restent distinctes.

## 5. Vérifier avant ouverture aux collègues

Contrôler le refus des chemins privés en HTTP (403/404), les rôles, la connexion, le changement de mot de passe temporaire, la déconnexion, les conflits de version, l’export CSV et PPTX, la sauvegarde et la restauration. Tester un cas fictif avant tout partage. Définir conservation, accès et responsable de l’instance.

GitHub héberge le code, pas l’application PHP ni la base MariaDB. Chaque collègue installe sa propre instance.
