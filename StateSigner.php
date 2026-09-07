<?php

declare(strict_types=1);

namespace HyperPHP\Core;

/**
 * StateSigner
 *
 * Pilier n°2 du framework : l'état d'un composant qui transite par le client
 * (dans un attribut data-state) n'est jamais fait confiance tel quel.
 * Il est toujours signé HMAC-SHA256 côté serveur avant d'être envoyé,
 * et systématiquement re-vérifié à la réception. Un état modifié dans
 * l'inspecteur du navigateur est rejeté, pas "silencieusement accepté".
 *
 * Ce n'est pas une option de sécurité tardive : c'est imposé par le Kernel
 * à chaque cycle patch, donc un développeur ne peut pas l'oublier.
 */
final class StateSigner
{
    private const ALGO = 'sha256';

    public function __construct(
        private readonly string $secretKey,
        /** Incrémenté quand la forme d'un composant change de manière incompatible. */
        private readonly int $schemaVersion = 1,
    ) {
        if ($secretKey === '' || $secretKey === 'change-me') {
            throw new \RuntimeException(
                'HyperPHP: une clé secrète de production doit être fournie (HYPERPHP_KEY). '
                . 'Ne jamais démarrer en production avec la clé de développement par défaut.'
            );
        }
    }

    /**
     * Signe un état de composant. Le token contient : la classe du composant,
     * la version de schéma, l'état sérialisé, et une signature HMAC du tout.
     */
    public function sign(string $componentClass, array $state): string
    {
        $payload = [
            'c' => $componentClass,
            'v' => $this->schemaVersion,
            's' => $state,
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $encoded = base64_encode($json);
        $signature = $this->hmac($encoded);

        return $encoded . '.' . $signature;
    }

    /**
     * Vérifie et décode un token. Retourne null si :
     * - la signature ne correspond pas (état trafiqué côté client)
     * - le schema_version ne correspond plus (déploiement plus récent : on
     *   force un état neuf plutôt que de risquer une hydration corrompue)
     */
    public function verify(string $token): ?VerifiedState
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;

        if (!hash_equals($this->hmac($encoded), $signature)) {
            return null; // état trafiqué : on ne fait JAMAIS confiance à un fallback silencieux
        }

        $json = base64_decode($encoded, strict: true);
        if ($json === false) {
            return null;
        }

        try {
            $payload = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!isset($payload['c'], $payload['v'], $payload['s'])) {
            return null;
        }

        if ((int) $payload['v'] !== $this->schemaVersion) {
            // Version de schéma obsolète : le composant a changé de forme
            // depuis un déploiement. On refuse l'hydration plutôt que de
            // risquer un état incohérent -> le client refera un GET propre.
            return null;
        }

        return new VerifiedState(
            componentClass: (string) $payload['c'],
            state: (array) $payload['s'],
        );
    }

    private function hmac(string $data): string
    {
        return hash_hmac(self::ALGO, $data, $this->secretKey);
    }
}
