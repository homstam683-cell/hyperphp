<?php

declare(strict_types=1);

namespace HyperPHP\Tests;

use HyperPHP\Core\StateSigner;
use PHPUnit\Framework\TestCase;

final class StateSignerTest extends TestCase
{
    public function test_signs_and_verifies_a_round_trip(): void
    {
        $signer = new StateSigner(secretKey: 'test-secret');

        $token = $signer->sign('App\\Counter', ['count' => 3]);
        $verified = $signer->verify($token);

        self::assertNotNull($verified);
        self::assertSame('App\\Counter', $verified->componentClass);
        self::assertSame(['count' => 3], $verified->state);
    }

    public function test_rejects_a_tampered_state(): void
    {
        $signer = new StateSigner(secretKey: 'test-secret');

        $token = $signer->sign('App\\Counter', ['count' => 1]);

        // Simule un utilisateur qui modifie l'état dans l'inspecteur du
        // navigateur avant de le renvoyer : on décode, on modifie, on
        // ré-encode SANS connaître la clé secrète.
        [$encoded, $signature] = explode('.', $token, 2);
        $payload = json_decode(base64_decode($encoded), true);
        $payload['s']['count'] = 999999;
        $tamperedEncoded = base64_encode(json_encode($payload));
        $tamperedToken = $tamperedEncoded . '.' . $signature;

        self::assertNull($signer->verify($tamperedToken));
    }

    public function test_rejects_a_forged_signature(): void
    {
        $signer = new StateSigner(secretKey: 'test-secret');

        $token = $signer->sign('App\\Counter', ['count' => 1]);
        [$encoded] = explode('.', $token, 2);

        $forgedToken = $encoded . '.' . str_repeat('0', 64);

        self::assertNull($signer->verify($forgedToken));
    }

    public function test_rejects_a_malformed_token(): void
    {
        $signer = new StateSigner(secretKey: 'test-secret');

        self::assertNull($signer->verify('not-a-valid-token'));
        self::assertNull($signer->verify(''));
    }

    public function test_rejects_a_stale_schema_version(): void
    {
        $oldSigner = new StateSigner(secretKey: 'test-secret', schemaVersion: 1);
        $newSigner = new StateSigner(secretKey: 'test-secret', schemaVersion: 2);

        $tokenFromBeforeDeploy = $oldSigner->sign('App\\Counter', ['count' => 1]);

        // Après un déploiement qui change la forme du composant, un état
        // signé avec l'ancienne version doit être refusé plutôt que
        // risquer une hydration incohérente.
        self::assertNull($newSigner->verify($tokenFromBeforeDeploy));
    }

    public function test_throws_on_default_dev_key(): void
    {
        $this->expectException(\RuntimeException::class);
        new StateSigner(secretKey: 'change-me');
    }
}
