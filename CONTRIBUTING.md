# Contribuer à HyperPHP

Merci de vous intéresser au projet. C'est un projet jeune (v0.1) : les
contributions, même petites, ont un impact réel sur sa direction.

## Recherché en priorité

- **Co-mainteneurs** : si vous contribuez régulièrement pendant quelques
  semaines et que ça vous intéresse, les droits d'admin sur le dépôt vous
  seront proposés. Ce projet est volontairement géré de façon légère par son
  créateur — l'autonomie technique des mainteneurs est encouragée, pas juste
  tolérée.
- Retours sur l'architecture (`src/Core/`), en particulier sur la
  robustesse en worker mode (FrankenPHP/Octane) : c'est le risque technique
  le plus sérieux du projet.
- Tests supplémentaires, en particulier des cas limites de `StateSigner` et
  du morphing DOM.

## Avant d'ouvrir une PR

1. Vérifiez qu'une issue ou discussion existe déjà pour un changement
   significatif — évite le travail en double.
2. `composer install && composer test` doit passer.
3. Le style suit PSR-12. Pas d'outillage de lint imposé pour l'instant
   (roadmap).
4. Les commits en français ou en anglais sont acceptés indifféremment.

## Bonnes premières contributions

Cherchez le label `good first issue` sur le tracker. À défaut, des pistes
concrètes déjà identifiées :

- Ajouter la réconciliation par clé dans `runtime/hyperphp.js` (actuellement
  les enfants sont comparés par position, pas par identité — limite connue
  documentée dans le README).
- Ajouter un exemple avec un formulaire multi-champs (au-delà du compteur).
- Documenter un cas d'usage avec base de données + pool de connexions.

## Signaler une faille de sécurité

Ne pas ouvrir d'issue publique. Contacter directement le mainteneur (voir
profil GitHub) pour une divulgation responsable.

## Code de conduite

Soyez respectueux. Les désaccords techniques sont bienvenus et encouragés ;
les attaques personnelles ne le sont pas.
