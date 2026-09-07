<?php

declare(strict_types=1);

namespace HyperPHP\Core;

final class VerifiedState
{
    public function __construct(
        public readonly string $componentClass,
        public readonly array $state,
    ) {
    }
}
