# Documents et recherche RAG

Le moteur de recherche et les contrôles sont présents, mais `rag/corpus.json` contient zéro source et zéro passage. Ce choix évite de distribuer un corpus interne, une traduction non validée ou un texte sous licence. Aucun document n’est téléchargé automatiquement.

Sans corpus, la bibliothèque est vide et la recherche sourcée refuse la génération faute de passage. Le registre, les exports et l’aide à la formulation restent utilisables ; cette dernière exige votre propre service IA activé. Ne pas présenter une piste non sourcée comme une réponse documentaire.

## Préparer un corpus autorisé

Le schéma JSON contient `version`, `indexed_at`, `description`, `rights_note`, `sources` et `chunks`. Chaque source doit préciser `id`, `title` (ou aussi `title_fr`), `edition`, `url` HTTPS officielle, `publisher`, `language`, `sha256_pdf`, `rights_url`, `attribution`, `transformation` et `pages_indexed`. Chaque passage doit préciser :

```json
{
  "id": "document-page-passage",
  "source_id": "id-source",
  "page_pdf": 1,
  "text": "Texte original autorisé",
  "sha256": "empreinte SHA-256 du texte UTF-8 exact",
  "text_fr": "Version française relue",
  "sha256_fr": "empreinte SHA-256 de la version française UTF-8 exacte",
  "translation": "Statut, auteur ou méthode de traduction, limites"
}
```

Ce fragment illustre la structure ; ce n’est pas une source à importer. Le fichier est limité à 2 Mo par le chargeur actuel. Conserver le fichier hors du web et vérifier les identifiants, les pages, les droits de copie et la fidélité du français. Une empreinte vérifie l’intégrité, pas la qualité du contenu. Changer `version` lors d’une mise à jour. La recherche lexicale peut manquer un passage pertinent ; vérifier les citations et l’interprétation.

Les publications publiques NIST AI RMF 1.0 et NIST AI 600-1 sont des pistes documentaires. Consulter les originaux et leurs conditions avant indexation. Ne pas importer de norme ISO achetée sans vérifier sa licence. Le parcours est une aide locale, pas une procédure normative, une analyse juridique ou une certification.

Cette distribution ne contient pas d’interface d’importation documentaire : la constitution et le contrôle du corpus sont une tâche technique séparée. Les traductions automatiques doivent être clairement identifiées et relues. Les brouillons et relectures sont privés à chaque installation ; aucune purge automatique dans cette version.
