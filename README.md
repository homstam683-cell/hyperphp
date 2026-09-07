# HyperPHP

**Framework PHP réactif, server-driven, sans build JS obligatoire.**

Chaque composant vit en PHP. Les interactions transitent par HTTP, l'état est
signé cryptographiquement (HMAC) côté client, et le DOM est patché par un
runtime JS minimaliste (< 5 Ko) que vous n'écrivez jamais.

> **Statut : v0.1 — early stage.** Le cœur (composants, signature HMAC, patch
> DOM, routeur/DI minimalistes) est fonctionnel et testé. Ce n'est **pas**
> encore un framework complet niveau Laravel/Symfony : pas d'ORM, pas de
> migrations, pas de queue. Voir [Roadmap](#roadmap).

```php
final class Counter extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): string
    {
        return <<<HTML
            <p>Compteur : {$this->count}</p>
            <button data-action="increment">+1</button>
            HTML;
    }
}
```

C'est tout le code nécessaire pour un composant interactif. Aucun JavaScript
à écrire.

---

## Pourquoi HyperPHP existe

Le problème n'est pas "PHP est lent" — c'est réglé depuis PHP 8 + JIT. Le
vrai problème, c'est la **double stack mentale** : un dev PHP solo ou une
petite équipe doit maîtriser PHP côté serveur *et* React/Vue/Alpine côté
client, deux systèmes de build, deux façons de gérer l'état.

Livewire, Inertia, Datastar, htmx+Alpine existent déjà et adressent ce
problème, chacun avec des compromis différents. HyperPHP ne prétend pas les
remplacer du jour au lendemain — il explore un point précis que ces
solutions ne couvrent pas ensemble :

- Un **framework complet** (pas une lib à greffer sur Laravel/Craft/WordPress)
- dont le modèle de composant utilise nativement un **protocole de patch
  différentiel signé** (façon Datastar),
- plutôt que le re-render de template complet (façon Livewire),
- **pensé pour le worker mode (FrankenPHP/Octane) dès la conception**, pas
  ajouté après coup.

Le différenciateur le plus concret : parce que l'état voyage signé dans le
DOM plutôt que dans une session serveur, **n'importe quel worker peut traiter
n'importe quelle requête** — pas d'affinité de session à gérer en cluster,
contrairement à Livewire (session) ou Phoenix LiveView (socket sticky).

Une analyse plus détaillée de la concurrence (Livewire 4, Datastar, Inertia,
Symfony UX, Phoenix LiveView) est disponible dans
[`docs/analyse-concurrentielle.md`](docs/analyse-concurrentielle.md).

## Ce que le v0.1 fournit réellement

- `HyperPHP\Core\Component` — classe de base : état = propriétés publiques,
  hydratation/extraction automatiques par réflexion.
- `HyperPHP\Core\StateSigner` — signature/vérification HMAC-SHA256 de
  l'état, avec versionnage de schéma (un état signé avant un déploiement
  incompatible est rejeté proprement, pas accepté à moitié).
- `HyperPHP\Core\Kernel` — reçoit une action du client, vérifie la
  signature, réhydrate le composant, exécute l'action, rend le nouveau HTML.
  Allowlist de composants obligatoire (pas d'instanciation de classe
  arbitraire depuis l'input client).
- `HyperPHP\Core\Container` — DI minimaliste, avec purge de scope explicite
  pensée pour le worker mode.
- `HyperPHP\Core\Router` — routage GET/POST basique.
- `runtime/hyperphp.js` — runtime client : envoi des actions, **morphing DOM**
  (pas de remplacement `innerHTML` brutal — préserve focus, scroll, valeur en
  cours de saisie).

## Essayer la démo

```bash
composer install
php -S localhost:8000 -t examples/counter
```

Ouvrez `http://localhost:8000` : le compteur s'incrémente sans rechargement
de page, sans une ligne de JavaScript écrite par vous.

## Sécurité — ce qui est déjà couvert

- **État jamais fait confiance tel quel** : toute modification côté client
  (inspecteur du navigateur) invalide la signature HMAC → requête rejetée.
- **Clé de développement bloquante en "production"** : `StateSigner` refuse
  de démarrer si la clé vaut `change-me` — vous ne pouvez pas oublier de la
  changer silencieusement.
- **Allowlist de composants** dans le `Kernel` : le nom de classe envoyé par
  le client n'est jamais utilisé directement pour instancier — seuls les
  composants explicitement enregistrés sont joignables.
- **Méthodes du framework non appelables comme actions** (`render`,
  `hydrate`, etc.) : seules les méthodes publiques que *vous* définissez le
  sont.

Ce n'est pas exhaustif (pas encore de rate-limiting, pas de CSRF token
dédié — le HMAC protège l'intégrité de l'état, pas le CSRF classique) : voir
[Roadmap](#roadmap) et n'hésitez pas à ouvrir une issue sécurité en privé
plutôt qu'un rapport public si vous trouvez une faille.

## Roadmap

- [ ] Réconciliation de listes par clé dans le morphing DOM (actuellement
      comparaison par position — ne pas utiliser sur des listes réordonnables)
- [ ] Transport SSE en complément du POST classique (pour le push serveur→client)
- [ ] Auto-wiring par réflexion dans le Container
- [ ] Pool de connexions DB pensé worker-mode (le vrai risque technique n°1)
- [ ] CSRF token dédié en plus du HMAC d'état
- [ ] Benchmarks chiffrés vs Livewire 4 / Datastar

## Contribuer

Le projet est ouvert aux contributions dès aujourd'hui — voir
[`CONTRIBUTING.md`](CONTRIBUTING.md). Les "good first issues" sont taguées
sur le tracker. Les retours, même critiques, sont bienvenus : ce projet
préfère être précis sur ses limites plutôt que de survendre.

## Licence

[MIT](LICENSE) — libre pour un usage personnel ou commercial, sans
attribution obligatoire.
