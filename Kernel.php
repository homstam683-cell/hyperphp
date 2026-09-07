<?php

declare(strict_types=1);

namespace HyperPHP\Core;

use HyperPHP\Http\Request;

/**
 * Kernel
 *
 * Reçoit une action venant du client (data-action + état signé + payload
 * optionnel), rejoue le composant PHP correspondant, et retourne le nouveau
 * HTML + le nouvel état signé.
 *
 * Sécurité : le nom de classe du composant n'est JAMAIS instancié tel quel
 * depuis l'input client. Il doit être déclaré dans le registre fourni au
 * Kernel (allowlist), sinon la requête est rejetée. Sans ça, un attaquant
 * pourrait tenter d'instancier n'importe quelle classe du projet.
 */
final class Kernel
{
    /** @var array<string, class-string<Component>> nom court => classe réelle */
    private array $registry = [];

    public function __construct(
        private readonly StateSigner $signer,
    ) {
    }

    /** Déclare un composant comme autorisé à être piloté depuis le client. */
    public function register(string $name, string $componentClass): self
    {
        if (!is_subclass_of($componentClass, Component::class)) {
            throw new \InvalidArgumentException(
                "HyperPHP: {$componentClass} doit étendre " . Component::class
            );
        }
        $this->registry[$name] = $componentClass;
        return $this;
    }

    /** Rendu initial d'un composant (premier GET, avant toute interaction). */
    public function mount(string $name, array $initialProps = []): string
    {
        $component = $this->instantiate($name);
        $component->hydrate($initialProps);
        return $component->toHtml($this->signer);
    }

    /**
     * Traite une requête d'action envoyée par le runtime JS.
     * Retourne un tableau prêt à être encodé en JSON : {html, error?}.
     */
    public function handle(Request $request): array
    {
        $token = $request->string('state');
        $action = $request->string('action');
        $payload = $request->array('payload');

        $verified = $this->signer->verify($token);
        if ($verified === null) {
            // Signature invalide OU schema_version obsolète : on ne tente
            // jamais de "réparer" un état non fiable, on rejette net.
            return ['error' => 'invalid_or_stale_state'];
        }

        $shortName = array_search($verified->componentClass, $this->registry, true);
        if ($shortName === false) {
            return ['error' => 'component_not_registered'];
        }

        $component = $this->instantiate($shortName);
        $component->hydrate($verified->state);

        if ($action === '' || !method_exists($component, $action)) {
            return ['error' => 'unknown_action'];
        }

        // Seules les méthodes publiques déclarées par le développeur comme
        // actions sont appelables : on refuse explicitement les méthodes
        // héritées du framework lui-même (render, hydrate, extractState...).
        if ($this->isFrameworkMethod($action)) {
            return ['error' => 'forbidden_action'];
        }

        $reflectionMethod = new \ReflectionMethod($component, $action);
        if (!$reflectionMethod->isPublic()) {
            return ['error' => 'forbidden_action'];
        }

        $component->{$action}(...$payload);

        return ['html' => $component->toHtml($this->signer)];
    }

    private function instantiate(string $name): Component
    {
        if (!isset($this->registry[$name])) {
            throw new \InvalidArgumentException("HyperPHP: composant '{$name}' non enregistré.");
        }

        $class = $this->registry[$name];
        /** @var Component $instance */
        $instance = new $class();
        return $instance;
    }

    private function isFrameworkMethod(string $method): bool
    {
        return in_array($method, ['render', 'hydrate', 'extractState', 'toHtml'], true);
    }
}
