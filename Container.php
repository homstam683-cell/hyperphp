<?php

declare(strict_types=1);

namespace HyperPHP\Core;

/**
 * Container
 *
 * DI minimaliste v0.1 : suffisant pour partager des instances (ex: le
 * StateSigner, une connexion PDO) entre le bootstrap et le Kernel, sans
 * imposer un état global mutable — point important en worker mode, où
 * un singleton mal scaffoldé fuite d'une requête/utilisateur à l'autre.
 *
 * v0.1 ne fait PAS d'auto-wiring par réflexion (roadmap v0.2) : chaque
 * binding est un closure explicite, plus prévisible en mode worker.
 */
final class Container
{
    /** @var array<string, \Closure> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function bind(string $id, \Closure $factory): self
    {
        $this->bindings[$id] = $factory;
        unset($this->instances[$id]);
        return $this;
    }

    /** Lie un singleton : la factory n'est appelée qu'une fois par requête. */
    public function singleton(string $id, \Closure $factory): self
    {
        return $this->bind($id, $factory);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new \RuntimeException("HyperPHP: aucun binding pour '{$id}'.");
        }

        $value = ($this->bindings[$id])($this);
        $this->instances[$id] = $value;

        return $value;
    }

    /**
     * À appeler entre chaque requête en worker mode (FrankenPHP/Octane) pour
     * purger les instances résolues et éviter qu'un état de requête N
     * fuite vers la requête N+1 sur le même worker.
     */
    public function resetRequestScope(): void
    {
        $this->instances = [];
    }
}
