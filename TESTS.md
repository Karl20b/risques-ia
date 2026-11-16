# Vérification

Les fixtures de `tests/fixtures/` sont des scénarios synthétiques uniquement. Elles ne sont jamais importées dans l’application.

Sur un environnement de test disposant des extensions requises :

```sh
php tests/domain_store.php
php tests/access_accounts.php
php tests/exports.php
php tests/exports_em.php
php tests/rag_empty.php
node tests/ai_text.mjs
```

Les tests de stockage adaptent certaines fonctions MariaDB à SQLite en mémoire (extension PDO SQLite nécessaire pour ces tests). Ils ne testent pas les privilèges, le moteur ou les transactions d’un hébergement MariaDB réel. Les tests de compte utilisent un fichier de configuration fictif temporaire, détruit après le test. Les exports utilisent un modèle générique et des scénarios fictifs.

Le test du corpus vide vérifie que l’application ne prétend pas trouver des sources inexistantes et refuse la génération documentaire. Aucun appel IA externe n’est réalisé par ces tests. Tester séparément l’hébergement HTTPS, les rôles, les privilèges MariaDB et le fournisseur choisi avant utilisation.
