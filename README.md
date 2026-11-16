# Risques IA

Application pédagogique en français pour décrire les risques liés à l’IA, préparer des mesures, suivre les preuves et consigner les décisions humaines.

**PHP 8.5 · MariaDB · sans framework ni Composer.** Cette édition démarre avec un **registre vide**, sans compte, mot de passe, clé API ni document institutionnel.

## Fonctions

- Fiches de risque versionnées, matrice 4 × 4 et suivi des revues.
- Distinction entre risque initial proposé, cible conditionnelle et risque actuel sur preuves.
- Mesures, responsables, échéances, preuves et contrôle de l’efficacité.
- Décisions motivées et journal des modifications.
- Rôles lecteur, rédacteur et décideur ; changement obligatoire du mot de passe temporaire.
- Assistant IA facultatif : aide à formuler une question, analyse et propositions à relire, en français.
- Recherche documentaire RAG : moteur inclus, **corpus vide à configurer séparément**.
- Exports CSV et PowerPoint éditables : synthèse, matrice, calendrier, annexes et notes.
- Mode d’emploi textuel intégré.

## Commencer

1. Télécharger **Code → Download ZIP** ou cloner ce dépôt.
2. Suivre [INSTALLATION.md](INSTALLATION.md) pour une nouvelle base et un hébergement HTTPS.
3. Configurer ses propres comptes ; aucun accès à une instance existante n’est fourni.
4. Créer un premier risque fictif avec **Nouveau risque**.

L’IA reste désactivée tant que votre propre service n’est pas configuré. La gestion manuelle fonctionne sans IA. Voir [RAG.md](RAG.md) pour les documents et [SECURITE.md](SECURITE.md) pour les limites.

## Ce qui n’est pas distribué

Aucun registre existant, mesure, preuve, décision, journal, compte utilisateur, jeton, sauvegarde ou export d’une instance. Aucun logo ou modèle officiel d’organisation, aucune capsule montrant un registre privé, aucun texte ISO payant. Le PowerPoint livré est un modèle générique créé pour cette édition.

Les seuls scénarios présents sous `tests/fixtures/` sont synthétiques et réservés aux tests automatisés ; ils ne sont jamais chargés dans le registre. Le RAG n’inclut ni corpus privé ni copie de normes.

## Usage et limites

Prototype de formation à adapter et à vérifier sur son propre hébergement. Une proposition de l’Assistant IA n’est ni une preuve, ni une décision, ni une certification. Le rôle décideur ne confère aucune délégation institutionnelle. Les seuils de la matrice sont des conventions de travail. Utiliser des cas fictifs et définir son cadre juridique avant tout usage réel.

Édition de partage dérivée de la version 1.16. Installation complète sur un nouvel hébergement et nouvel appel à un service IA non vérifiés pour cette distribution. Les contrôles locaux sont décrits dans [TESTS.md](TESTS.md).
