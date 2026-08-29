<?php

declare(strict_types=1);

/**
 * Souvera Central - AI Feature Flag Service
 *
 * Zentrale Steuerung der KI-Funktion („Souvera AI") in der Central. Es gibt
 * zwei klar getrennte Zustände:
 *
 *   - „gebucht" (booked): Ob die Instanz das Add-on Souvera AI überhaupt
 *     gebucht hat. Das entscheidet der Hoster / CloudManager und wird als
 *     System-Wert in config.php abgelegt (souvera_central.ai_booked) bzw.
 *     über `occ souvera_central:ai:book` gesetzt.
 *
 *   - „aktiviert" (enabled): Der Laufzeit-Schalter, den der Hoster per occ
 *     (`ai:enable` / `ai:disable`) umlegt. Aktivieren ist nur erlaubt, wenn
 *     die Instanz AI gebucht hat.
 *
 * Consumer-Apps (z. B. souvera_documents) lesen den Zustand lazy:
 *
 *   $svc = \OCP\Server::get(\OCA\SouveraCentral\Service\AiConfigService::class);
 *   if ($svc->isEnabled()) { ... }
 *
 * Schreiben erfolgt ausschließlich über die occ-Befehle. Es gibt bewusst
 * keinen REST-Endpoint für die Setter (spiegelt das Muster des
 * ExternalAccountsConfigService).
 */

namespace OCA\SouveraCentral\Service;

use OCP\App\IAppManager;
use OCP\IConfig;

class AiConfigService
{
    public const APP_ID = 'souvera_central';

    /** System-Wert in config.php: souvera_central.ai_booked */
    private const SYSTEM_KEY_BOOKED = 'ai_booked';

    /** App-Wert in der DB: ai.enabled */
    private const KEY_ENABLED = 'ai.enabled';

    public function __construct(
        private IConfig $config,
        private IAppManager $appManager,
    ) {
    }

    // ================================================================
    // READ API (seiteneffektfrei – darf auf jedem Request laufen)
    // ================================================================

    /** Ob die Instanz das Add-on Souvera AI gebucht hat. */
    public function isBooked(): bool
    {
        return (bool) $this->config->getSystemValue(self::APP_ID . '.' . self::SYSTEM_KEY_BOOKED, false);
    }

    /** Ob die KI-Funktion aktuell aktiviert ist. */
    public function isEnabled(): bool
    {
        return $this->config->getAppValue(self::APP_ID, self::KEY_ENABLED, '0') === '1';
    }

    /**
     * Serialisierter Snapshot für `occ …:ai:status --json`. Enthält KEINE
     * Secrets.
     *
     * @return array{booked:bool, enabled:bool, central_version:string}
     */
    public function snapshot(): array
    {
        return [
            'booked' => $this->isBooked(),
            'enabled' => $this->isEnabled(),
            'central_version' => $this->centralVersion(),
        ];
    }

    // ================================================================
    // WRITE API (nur occ / Hoster – niemals Consumer-Apps)
    // ================================================================

    /**
     * Aktiviert die KI-Funktion.
     *
     * @throws \RuntimeException wenn die Instanz AI nicht gebucht hat.
     */
    public function setEnabled(bool $enabled): void
    {
        if ($enabled && !$this->isBooked()) {
            throw new \RuntimeException('Souvera AI ist für diese Instanz nicht gebucht.');
        }
        $this->config->setAppValue(self::APP_ID, self::KEY_ENABLED, $enabled ? '1' : '0');
    }

    /** Setzt den Buchungsstatus (nur Hoster / CloudManager). */
    public function setBooked(bool $booked): void
    {
        $this->config->setSystemValue(self::APP_ID . '.' . self::SYSTEM_KEY_BOOKED, $booked);
        if (!$booked) {
            // Beim Entbuchen die Funktion auch gleich deaktivieren.
            $this->config->setAppValue(self::APP_ID, self::KEY_ENABLED, '0');
        }
    }

    private function centralVersion(): string
    {
        try {
            return $this->appManager->getAppVersion(self::APP_ID);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
