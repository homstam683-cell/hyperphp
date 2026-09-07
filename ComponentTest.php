<?php

declare(strict_types=1);

namespace HyperPHP\Tests;

use HyperPHP\Core\Component;
use HyperPHP\Core\StateSigner;
use PHPUnit\Framework\TestCase;

final class DummyCounter extends Component
{
    public int $count = 0;
    protected string $notExposed = 'server-only';

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): string
    {
        return "<span>{$this->count}</span>";
    }
}

final class ComponentTest extends TestCase
{
    public function test_extract_state_only_includes_public_properties(): void
    {
        $component = new DummyCounter();
        $component->count = 5;

        $state = $component->extractState();

        self::assertSame(['count' => 5], $state);
        self::assertArrayNotHasKey('notExposed', $state);
    }

    public function test_hydrate_restores_public_properties(): void
    {
        $component = new DummyCounter();
        $component->hydrate(['count' => 42]);

        self::assertSame(42, $component->count);
    }

    public function test_hydrate_ignores_unknown_keys(): void
    {
        $component = new DummyCounter();

        // Ne doit pas planter même si le client envoie une clé qui n'existe
        // pas (tolérance aux évolutions mineures de schéma).
        $component->hydrate(['count' => 1, 'ghost_property' => 'x']);

        self::assertSame(1, $component->count);
    }

    public function test_to_html_wraps_render_with_signed_state(): void
    {
        $signer = new StateSigner(secretKey: 'test-secret');
        $component = new DummyCounter();
        $component->count = 7;

        $html = $component->toHtml($signer);

        self::assertStringContainsString('data-component=', $html);
        self::assertStringContainsString('data-state=', $html);
        self::assertStringContainsString('<span>7</span>', $html);
    }
}
