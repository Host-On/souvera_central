<?php

declare(strict_types=1);

/**
 * Souvera Central - AI Feature Flag Service
 *
 * Einfacher Schalter für die KI-Funktion („Souvera AI") in der Central.
 *
 * Aktiviert/deaktiviert wird ausschließlich über die occ-Befehle
 * `souvera_central:ai:enable` / `ai:disable` bzw. die AI-Admin-Sektion in
 * der Central-UI. Ob eine Instanz das Add-on Souvera AI überhaupt gebucht
 * hat, entscheidet der Hoster / CloudManager in seinem eigenen System —
 * er ruft `ai:enable` nur auf gebuchten Instanzen auf.
 *
 * Consumer-Apps (z. B. souvera_documents) lesen den Zustand lazy:
 *
 *   $svc = \OCP\Server::get(\OCA\SouveraCentral\Service\AiConfigService::class);
 *   if ($svc->isEnabled()) { ... }
 *
 * Schreiben erfolgt ausschließlich über occ bzw. die Admin-UI. Es gibt
 * bewusst keinen offenen REST-Endpoint für die Setter (spiegelt das Muster
 * des ExternalAccountsConfigService).
 */

namespace OCA\SouveraCentral\Service;

use OCP\App\IAppManager;
use OCP\IConfig;

class AiConfigService
{
    public const APP_ID = 'souvera_central';

    /** App-Wert in der DB: ai.enabled */
    private const KEY_ENABLED = 'ai.enabled';

    public function __construct(
        private IConfig $config,
        private IAppManager $appManager,
    ) {
    }

    /** Ob die KI-Funktion aktuell aktiviert ist. */
    public function isEnabled(): bool
    {
        return $this->config->getAppValue(self::APP_ID, self::KEY_ENABLED, '0') === '1';
    }

    public function setEnabled(bool $enabled): void
    {
        $this->config->setAppValue(self::APP_ID, self::KEY_ENABLED, $enabled ? '1' : '0');
    }

    /**
     * Serialisierter Snapshot für `occ …:ai:status --json` und die Admin-UI.
     * Enthält KEINE Secrets.
     *
     * @return array{enabled:bool, central_version:string}
     */
    public function snapshot(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'central_version' => $this->centralVersion(),
        ];
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
