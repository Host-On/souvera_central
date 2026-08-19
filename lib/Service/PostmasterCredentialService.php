<?php

declare(strict_types=1);

/**
 * Souvera Central - Zentraler Speicher für das Postmaster-App-Passwort (Souvera-Hub)
 *
 * Central ist die Zentrale von Souvera und hält gemeinsame Zugangsdaten EINMAL
 * zentral vor, statt sie in jeder App zu duplizieren. Das beim Deploy erzeugte
 * App-Passwort des "postmaster@"-Postfachs wird hier VERSCHLÜSSELT
 * (OCP\Security\ICrypto, gebunden an das Instanz-Secret aus config.php) in der
 * App-Konfiguration abgelegt und per occ gesetzt.
 *
 * Andere Souvera-Apps rufen das entschlüsselte Passwort so ab:
 *   $svc = \OCP\Server::get(\OCA\SouveraCentral\Service\PostmasterCredentialService::class);
 *   $password = $svc->getPassword(); // string|null
 * (Voraussetzung: souvera_central ist installiert + aktiviert. Siehe
 *  docs/SHARED_PROVIDER_TOKEN.md, Abschnitt Postmaster.)
 */

namespace OCA\SouveraCentral\Service;

use OCP\IConfig;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class PostmasterCredentialService {
    public const APP_ID = 'souvera_central';
    public const CREDENTIAL = 'postmaster';

    private const KEY_PASSWORD = 'postmaster.app_password';        // verschlüsselter Blob
    private const KEY_SET_AT = 'postmaster.app_password_set_at';

    public function __construct(
        private IConfig $config,
        private ICrypto $crypto,
        private LoggerInterface $logger,
    ) {
    }

    public function hasPassword(): bool {
        return $this->config->getAppValue(self::APP_ID, self::KEY_PASSWORD, '') !== '';
    }

    /**
     * Speichert das Postmaster-App-Passwort verschlüsselt (ICrypto). Wirft bei leer.
     */
    public function setPassword(string $password): void {
        $password = trim($password);
        if ($password === '') {
            throw new \InvalidArgumentException('Das Passwort darf nicht leer sein.');
        }
        $this->config->setAppValue(self::APP_ID, self::KEY_PASSWORD, $this->crypto->encrypt($password));
        $this->config->setAppValue(
            self::APP_ID,
            self::KEY_SET_AT,
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c')
        );
    }

    /**
     * Liefert das entschlüsselte Passwort oder null (nicht gesetzt / Entschlüsselung
     * fehlgeschlagen, z. B. nach Wechsel des Instanz-Secrets).
     */
    public function getPassword(): ?string {
        $enc = $this->config->getAppValue(self::APP_ID, self::KEY_PASSWORD, '');
        if ($enc === '') {
            return null;
        }
        try {
            $plain = $this->crypto->decrypt($enc);
            return $plain !== '' ? $plain : null;
        } catch (\Throwable $e) {
            $this->logger->error('[souvera_central] Postmaster-App-Passwort konnte nicht entschlüsselt werden: ' . $e->getMessage());
            return null;
        }
    }

    public function clearPassword(): void {
        $this->config->deleteAppValue(self::APP_ID, self::KEY_PASSWORD);
        $this->config->deleteAppValue(self::APP_ID, self::KEY_SET_AT);
    }

    /**
     * Zeitpunkt (ISO-8601), zu dem das Passwort zuletzt gesetzt wurde.
     */
    public function getSetAt(): ?string {
        $v = $this->config->getAppValue(self::APP_ID, self::KEY_SET_AT, '');
        return $v !== '' ? $v : null;
    }

    /**
     * Maskierte Vorschau (nur die letzten 4 Zeichen sichtbar), nie der Klartext.
     */
    public function getMaskedPassword(): ?string {
        $p = $this->getPassword();
        if ($p === null) {
            return null;
        }
        $len = strlen($p);
        if ($len <= 4) {
            return str_repeat('•', $len);
        }
        return str_repeat('•', min($len - 4, 24)) . substr($p, -4);
    }
}
