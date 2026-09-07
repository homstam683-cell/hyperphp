<?php

declare(strict_types=1);

namespace HyperPHP\Examples\Counter;

use HyperPHP\Core\Component;

final class Counter extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function decrement(): void
    {
        $this->count = max(0, $this->count - 1);
    }

    public function reset(): void
    {
        $this->count = 0;
    }

    public function render(): string
    {
        return <<<HTML
            <p>Compteur : <strong>{$this->count}</strong></p>
            <button data-action="decrement">-1</button>
            <button data-action="increment">+1</button>
            <button data-action="reset">Réinitialiser</button>
            HTML;
    }
}
