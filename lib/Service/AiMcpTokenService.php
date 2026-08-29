<?php

declare(strict_types=1);

/**
 * Souvera Central - MCP-Token für den internen AI-Agenten (Hub-Muster)
 *
 * Bei Aktivierung der KI-Funktion erzeugt Central automatisch einen
 * Access-Token für den MCP-Endpoint und legt ihn VERSCHLÜSSELT ab
 * (OCP\Security\ICrypto, gebunden an das Instanz-Secret). Klartext liegt
 * nie in der DB.
 *
 * Der Nextcloud-interne Agent liest den Token INTERN über die Shared API:
 *
 *   $svc = \OCP\Server::get(\OCA\SouveraCentral\Service\AiMcpTokenService::class);
 *   $token = $svc->getToken(); // string|null
 *
 * Er ist ausschließlich für den lokalen MCP-Endpoint gültig (read-only),
 * läuft nie über das Internet zu Dritten und kann jederzeit rotiert werden.
 * Siehe docs/SHARED_AI_MCP.md.
 */

namespace OCA\SouveraCentral\Service;

use OCP\IAppConfig;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class AiMcpTokenService
{
    public const APP_ID = 'souvera_central';

    private const KEY_TOKEN = 'ai.mcp_token';       // verschlüsselter Blob
    private const KEY_CREATED_AT = 'ai.mcp_token_created_at';

    public function __construct(
        private IAppConfig $appConfig,
        private ICrypto $crypto,
        private LoggerInterface $logger,
    ) {
    }

    public function hasToken(): bool
    {
        return $this->appConfig->getValueString(self::APP_ID, self::KEY_TOKEN, '') !== '';
    }

    public function getCreatedAt(): ?string
    {
        $v = $this->appConfig->getValueString(self::APP_ID, self::KEY_CREATED_AT, '');
        return $v !== '' ? $v : null;
    }

    /**
     * Erzeugt den Token, falls keiner existiert. Bei Aktivierung der
     * KI-Funktion automatisch aufgerufen.
     */
    public function ensureToken(): void
    {
        if ($this->hasToken()) {
            return;
        }
        $this->rotateToken();
    }

    /**
     * Erzeugt (bzw. rotiert) den Token und legt ihn verschlüsselt ab.
     */
    public function rotateToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->appConfig->setValueString(self::APP_ID, self::KEY_TOKEN, $this->crypto->encrypt($token));
        $this->appConfig->setValueString(
            self::APP_ID,
            self::KEY_CREATED_AT,
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c')
        );
        $this->logger->info('Souvera AI: MCP access token rotated', ['app' => self::APP_ID]);
    }

    /**
     * Entschlüsselter Token — NUR für interne Consumer (Shared API) gedacht.
     */
    public function getToken(): ?string
    {
        $blob = $this->appConfig->getValueString(self::APP_ID, self::KEY_TOKEN, '');
        if ($blob === '') {
            return null;
        }
        try {
            return $this->crypto->decrypt($blob);
        } catch (\Throwable $e) {
            $this->logger->error('Souvera AI: MCP token decrypt failed — rotate required', ['app' => self::APP_ID]);
            return null;
        }
    }

    /**
     * Constant-time-Prüfung eines übergebenen Bearer-Tokens.
     */
    public function isValidToken(string $provided): bool
    {
        $expected = $this->getToken();
        if ($expected === null || $provided === '') {
            return false;
        }
        return hash_equals($expected, $provided);
    }
}
