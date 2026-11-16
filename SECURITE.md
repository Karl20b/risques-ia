# Sécurité et données

Édition de formation : données fictives uniquement. Aucun audit de sécurité indépendant ni autorisation de production n’est revendiqué.

Conserver les secrets côté serveur, hors du web et hors Git. HTTPS et cookies Secure/HttpOnly/SameSite, contrôle de session, rôles, origine, CSRF, quotas IA et conflits de version sont intégrés. Les requêtes IA nécessitent une confirmation et ne valident ni mesure ni décision. Le journal peut être altéré par un administrateur technique ; il ne constitue pas une archive inviolable.

Sauvegarder configuration, base et stockage privé par un mécanisme contrôlé. Définir la durée de conservation des événements, preuves et brouillons. Protéger les sauvegardes de configuration créées lors des changements de compte. Ne pas publier de captures, de données, de jetons ou de mots de passe dans une issue GitHub. Vérifier le contenu des exports avant diffusion.

La création des comptes est réservée au compte local `admin`. Les comptes de test dans les tests automatisés sont fictifs et temporaires, jamais activés sur l’instance. Les comptes supplémentaires ont un mot de passe temporaire à usage initial, sans mécanisme de récupération par mail dans cette version.

Installer sur une base dédiée ; ne pas exposer l’application directement aux usages sensibles sans examen de sécurité, juridique et métier.
