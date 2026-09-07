<?php

declare(strict_types=1);

namespace HyperPHP\Core;

/**
 * Component
 *
 * Un composant HyperPHP est une classe PHP dont les propriétés publiques
 * constituent l'état réactif. Le développeur n'écrit ni JS, ni logique de
 * sérialisation : le Kernel s'en charge via réflexion.
 *
 * Cycle de vie d'une interaction :
 *  1. Le client envoie {component, state signé, action, payload}
 *  2. Le Kernel vérifie la signature, hydrate un nouveau composant
 *  3. La méthode d'action est appelée
 *  4. render() est ré-exécuté avec le nouvel état
 *  5. Le nouvel état est re-signé, le HTML est renvoyé pour être morphé
 */
abstract class Component
{
    /**
     * Rend le composant en HTML. Le HTML retourné DOIT être englobé dans un
     * unique élément racine portant l'attribut data-component (voir
     * ComponentRenderer::wrap()) pour que le runtime JS puisse le localiser
     * et le morpher.
     */
    abstract public function render(): string;

    /**
     * Extrait l'état réactif du composant = ses propriétés publiques
     * non-statiques. Les propriétés protégées/privées ne sont jamais
     * envoyées au client (c'est la manière d'avoir de l'état "serveur only"
     * dans un composant : ne pas la déclarer publique).
     */
    final public function extractState(): array
    {
        $reflection = new \ReflectionClass($this);
        $state = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $state[$property->getName()] = $property->getValue($this);
        }

        return $state;
    }

    /**
     * Réhydrate les propriétés publiques du composant à partir d'un état
     * précédemment vérifié. Les clés inconnues sont ignorées (tolérance aux
     * évolutions mineures de schéma).
     */
    final public function hydrate(array $state): static
    {
        $reflection = new \ReflectionClass($this);

        foreach ($state as $key => $value) {
            if (!$reflection->hasProperty($key)) {
                continue;
            }
            $property = $reflection->getProperty($key);
            if (!$property->isPublic() || $property->isStatic()) {
                continue;
            }
            $property->setValue($this, $value);
        }

        return $this;
    }

    /**
     * Enveloppe le HTML de render() dans le conteneur data-component
     * attendu par le runtime JS, avec l'état signé attaché.
     */
    final public function toHtml(StateSigner $signer): string
    {
        $token = $signer->sign(static::class, $this->extractState());
        $inner = $this->render();

        $tokenAttr = htmlspecialchars($token, ENT_QUOTES);
        $classAttr = htmlspecialchars(static::class, ENT_QUOTES);

        return sprintf(
            '<div data-component="%s" data-state="%s">%s</div>',
            $classAttr,
            $tokenAttr,
            $inner
        );
    }
}
