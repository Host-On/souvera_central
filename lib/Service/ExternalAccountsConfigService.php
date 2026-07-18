<?php

declare(strict_types=1);

/**
 * Souvera Central - External Mail Account Settings (Shared Contract v1.0)
 *
 * Central ist die EINZIGE Quelle der Wahrheit für die Policy der Funktion
 * „Externe Mail-Konten" (web.de, GMX, Gmail, …), die souvera_mail seinen
 * Nutzern anbietet. souvera_mail (und optional souvera_shield) LESEN diese
 * Policy lazy via:
 *
 *   $svc = \OCP\Server::get(\OCA\SouveraCentral\Service\ExternalAccountsConfigService::class);
 *   if ($svc->isAllowedForUser($uid)) { ... }
 *
 * Schreiben erfolgt AUSSCHLIESSLICH über die occ-Befehle
 * (souvera_central:external:enable|disable|configure|status) bzw. eine
 * Admin-Seite – niemals durch die Consumer-Apps. Es gibt bewusst KEINEN
 * REST-Endpoint für die Setter (spiegelt das ProviderTokenService-Muster).
 *
 * Non-Goal: Die pro-Nutzer-Zugangsdaten der externen Konten liegen in
 * souvera_mail (verschlüsselt). Central besitzt nur die POLICY.
 *
 * Siehe docs/SHARED_EXTERNAL_ACCOUNTS.md.
 */

namespace OCA\SouveraCentral\Service;

use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IGroupManager;

class ExternalAccountsConfigService {
    public const APP_ID = 'souvera_central';

    private const KEY_ENABLED = 'external_accounts.enabled';
    private const KEY_GROUPS = 'external_accounts.groups';
    private const KEY_MAX = 'external_accounts.max_per_user';
    private const KEY_MIGRATION = 'external_accounts.migration_handoff';
    private const KEY_SMTP_GUARD = 'external_accounts.smtp_fail_guard';
    private const KEY_CONSENT = 'external_accounts.consent_required';
    private const KEY_PURGE_AT = 'external_accounts.purge_requested_at';

    public const DEFAULT_MAX = 3;
    public const MIN_MAX = 1;
    public const MAX_MAX = 20;

    public function __construct(
        private IConfig $config,
        private IGroupManager $groupManager,
        private IAppManager $appManager,
    ) {
    }

    // ================================================================
    // READ API (seiteneffektfrei, günstig – darf auf jedem Request laufen)
    // ================================================================

    public function isEnabled(): bool {
        return $this->config->getAppValue(self::APP_ID, self::KEY_ENABLED, '0') === '1';
    }

    /**
     * @return list<string> Leeres Array => alle Nutzer erlaubt.
     */
    public function getAllowedGroups(): array {
        $raw = $this->config->getAppValue(self::APP_ID, self::KEY_GROUPS, '[]');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $g) {
            $g = trim((string) $g);
            if ($g !== '') {
                $out[] = $g;
            }
        }
        return array_values(array_unique($out));
    }

    /** MUSS >= 1 sein, solange isEnabled() true ist. Default 3. */
    public function getMaxAccountsPerUser(): int {
        $v = (int) $this->config->getAppValue(self::APP_ID, self::KEY_MAX, (string) self::DEFAULT_MAX);
        return $v < self::MIN_MAX ? self::MIN_MAX : $v;
    }

    public function isMigrationHandoffEnabled(): bool {
        return $this->config->getAppValue(self::APP_ID, self::KEY_MIGRATION, '1') === '1';
    }

    public function isSmtpFailGuardEnabled(): bool {
        return $this->config->getAppValue(self::APP_ID, self::KEY_SMTP_GUARD, '1') === '1';
    }

    public function isConsentRequired(): bool {
        return $this->config->getAppValue(self::APP_ID, self::KEY_CONSENT, '1') === '1';
    }

    /**
     * One-stop-Prüfung: isEnabled() + Gruppenzugehörigkeit. Gruppen, die es
     * nicht (mehr) gibt, werden ignoriert (isInGroup liefert dann false).
     */
    public function isAllowedForUser(string $uid): bool {
        if ($uid === '' || !$this->isEnabled()) {
            return false;
        }
        $groups = $this->getAllowedGroups();
        if ($groups === []) {
            return true;
        }
        foreach ($groups as $gid) {
            if ($this->groupManager->isInGroup($uid, $gid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Serialisierter Snapshot für `occ …:status --json`. Enthält KEINE Secrets.
     *
     * @return array{enabled:bool, allowed_groups:list<string>, max_per_user:int,
     *               migration_handoff:bool, smtp_fail_guard:bool, consent_required:bool,
     *               central_version:string}
     */
    public function snapshot(): array {
        return [
            'enabled' => $this->isEnabled(),
            'allowed_groups' => $this->getAllowedGroups(),
            'max_per_user' => $this->getMaxAccountsPerUser(),
            'migration_handoff' => $this->isMigrationHandoffEnabled(),
            'smtp_fail_guard' => $this->isSmtpFailGuardEnabled(),
            'consent_required' => $this->isConsentRequired(),
            'central_version' => $this->centralVersion(),
        ];
    }

    // ================================================================
    // WRITE API (nur occ / Admin – niemals Consumer-Apps)
    // ================================================================

    public function setEnabled(bool $enabled): void {
        $this->config->setAppValue(self::APP_ID, self::KEY_ENABLED, $enabled ? '1' : '0');
    }

    /**
     * @param array<int|string,mixed> $groupIds
     */
    public function setAllowedGroups(array $groupIds): void {
        $clean = [];
        foreach ($groupIds as $g) {
            $g = trim((string) $g);
            if ($g !== '') {
                $clean[] = $g;
            }
        }
        $this->config->setAppValue(
            self::APP_ID,
            self::KEY_GROUPS,
            json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function setMaxAccountsPerUser(int $max): void {
        if ($max < self::MIN_MAX) {
            throw new \InvalidArgumentException('max_per_user must be >= ' . self::MIN_MAX);
        }
        $this->config->setAppValue(self::APP_ID, self::KEY_MAX, (string) $max);
    }

    public function setMigrationHandoffEnabled(bool $enabled): void {
        $this->config->setAppValue(self::APP_ID, self::KEY_MIGRATION, $enabled ? '1' : '0');
    }

    public function setSmtpFailGuardEnabled(bool $enabled): void {
        $this->config->setAppValue(self::APP_ID, self::KEY_SMTP_GUARD, $enabled ? '1' : '0');
    }

    public function setConsentRequired(bool $required): void {
        $this->config->setAppValue(self::APP_ID, self::KEY_CONSENT, $required ? '1' : '0');
    }

    // ================================================================
    // Purge-Marker (Cross-App): Central kann die pro-Nutzer-Konten NICHT
    // selbst löschen (die liegen in souvera_mail). `disable --purge` setzt
    // daher einen einmaligen Marker, den souvera_mail beim nächsten Boot
    // abarbeitet und danach quittiert.
    // ================================================================

    public function requestPurge(): void {
        $this->config->setAppValue(
            self::APP_ID,
            self::KEY_PURGE_AT,
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c')
        );
    }

    public function getPurgeRequestedAt(): ?string {
        $v = $this->config->getAppValue(self::APP_ID, self::KEY_PURGE_AT, '');
        return $v !== '' ? $v : null;
    }

    public function acknowledgePurge(): void {
        $this->config->deleteAppValue(self::APP_ID, self::KEY_PURGE_AT);
    }

    /** Test-Hook / `configure --reset`: alle Keys auf Default (löschen). */
    public function resetToDefaults(): void {
        foreach ([
            self::KEY_ENABLED, self::KEY_GROUPS, self::KEY_MAX, self::KEY_MIGRATION,
            self::KEY_SMTP_GUARD, self::KEY_CONSENT, self::KEY_PURGE_AT,
        ] as $k) {
            $this->config->deleteAppValue(self::APP_ID, $k);
        }
    }

    /** y/n-CLI-Flag parsen. null wenn nicht gesetzt; wirft bei ungültig. */
    public static function parseYesNo(?string $v): ?bool {
        if ($v === null) {
            return null;
        }
        $v = strtolower(trim($v));
        if ($v === '') {
            return null;
        }
        if (in_array($v, ['y', 'yes', '1', 'true', 'on', 'j', 'ja'], true)) {
            return true;
        }
        if (in_array($v, ['n', 'no', '0', 'false', 'off', 'nein'], true)) {
            return false;
        }
        throw new \InvalidArgumentException('Ungültiger y/n-Wert: ' . $v);
    }

    private function centralVersion(): string {
        try {
            return $this->appManager->getAppVersion(self::APP_ID);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
