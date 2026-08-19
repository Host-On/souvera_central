<?php

declare(strict_types=1);

/**
 * Souvera Central - Zentraler BookStack-Token-Speicher (Souvera-Hub)
 *
 * Analog zum provider.tools-Token (ProviderTokenService): Der BookStack-API-Token
 * wird hier VERSCHLÜSSELT (OCP\Security\ICrypto, gebunden an das Instanz-Secret aus
 * config.php) in der App-Konfiguration abgelegt und ausschließlich per OCC gesetzt.
 * Klartext liegt nie in der DB oder in config.php.
 *
 * Die BookStack-URL ist NICHT konfigurierbar (fester Default via
 * ConfigService::getBookStackUrl()); zentral verwaltet wird nur der Token.
 *
 * Andere Souvera-Apps (Shield, Mail) rufen den entschlüsselten Token so ab:
 *   $svc = \OCP\Server::get(\OCA\SouveraCentral\Service\BookStackTokenService::class);
 *   $token = $svc->getToken(); // string|null ("<TOKEN_ID>:<TOKEN_SECRET>")
 * (Voraussetzung: souvera_central ist installiert + aktiviert. Siehe
 *  docs/SHARED_PROVIDER_TOKEN.md.)
 */

namespace OCA\SouveraCentral\Service;

use OCP\IConfig;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class BookStackTokenService {
    public const APP_ID = 'souvera_central';
    public const PROVIDER = 'bookstack';

    private const KEY_TOKEN = 'bookstack.token';          // verschlüsselter Blob
    private const KEY_SET_AT = 'bookstack.token_set_at';

    public function __construct(
        private IConfig $config,
        private ICrypto $crypto,
        private LoggerInterface $logger,
    ) {
    }

    public function hasToken(): bool {
        return $this->config->getAppValue(self::APP_ID, self::KEY_TOKEN, '') !== '';
    }

    /**
     * Speichert den Token verschlüsselt (ICrypto). Wirft bei leerem Token.
     */
    public function setToken(string $token): void {
        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('Der Token darf nicht leer sein.');
        }
        $this->config->setAppValue(self::APP_ID, self::KEY_TOKEN, $this->crypto->encrypt($token));
        $this->config->setAppValue(
            self::APP_ID,
            self::KEY_SET_AT,
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c')
        );
    }

    /**
     * Liefert den entschlüsselten Token oder null (nicht gesetzt / Entschlüsselung
     * fehlgeschlagen, z. B. nach Wechsel des Instanz-Secrets).
     */
    public function getToken(): ?string {
        $enc = $this->config->getAppValue(self::APP_ID, self::KEY_TOKEN, '');
        if ($enc === '') {
            return null;
        }
        try {
            $plain = $this->crypto->decrypt($enc);
            return $plain !== '' ? $plain : null;
        } catch (\Throwable $e) {
            $this->logger->error('[souvera_central] BookStack-Token konnte nicht entschlüsselt werden: ' . $e->getMessage());
            return null;
        }
    }

    public function clearToken(): void {
        $this->config->deleteAppValue(self::APP_ID, self::KEY_TOKEN);
        $this->config->deleteAppValue(self::APP_ID, self::KEY_SET_AT);
    }

    /**
     * Zeitpunkt (ISO-8601), zu dem der Token zuletzt gesetzt wurde.
     */
    public function getSetAt(): ?string {
        $v = $this->config->getAppValue(self::APP_ID, self::KEY_SET_AT, '');
        return $v !== '' ? $v : null;
    }

    /**
     * Maskierte Vorschau (nur die letzten 4 Zeichen sichtbar), nie der Klartext.
     */
    public function getMaskedToken(): ?string {
        $t = $this->getToken();
        if ($t === null) {
            return null;
        }
        $len = strlen($t);
        if ($len <= 4) {
            return str_repeat('•', $len);
        }
        return str_repeat('•', min($len - 4, 24)) . substr($t, -4);
    }
}
