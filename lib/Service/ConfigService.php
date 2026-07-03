<?php
/**
 * Souvera User Management - Config Service
 *
 * Liest Read-Only Konfiguration aus config.php (System Config)
 */

namespace OCA\SouveraCentral\Service;

use OCP\IConfig;

class ConfigService {
    private $config;

    public function __construct(IConfig $config) {
        $this->config = $config;
    }

    /**
     * Maximale Anzahl an Lizenzen
     *
     * @return int
     */
    public function getMaxLicenses(): int {
        return (int) $this->config->getSystemValue('souvera_central.max_licenses', 10);
    }

    /**
     * Maximale Anzahl an Lizenzen setzen (config.php, systemValue).
     * Wird ausschließlich vom Hoster per occ gesetzt.
     */
    public function setMaxLicenses(int $count): void {
        $this->config->setSystemValue('souvera_central.max_licenses', max(0, $count));
    }

    /**
     * Liste der erlaubten E-Mail-Domains
     *
     * @return array
     */
    public function getAllowedDomains(): array {
        $domains = $this->config->getSystemValue('souvera_central.allowed_domains', []);

        // Falls als String kommasepariert in config.php
        if (is_string($domains)) {
            $domains = array_filter(array_map('trim', explode(',', $domains)));
        }

        return is_array($domains) ? $domains : [];
    }

    /**
     * Fügt eine Domain zur Central-Erlaubnisliste hinzu (config.php, systemValue).
     */
    public function addAllowedDomain(string $domain): void {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return;
        }
        $domains = $this->getAllowedDomains();
        if (!in_array($domain, array_map('strtolower', $domains), true)) {
            $domains[] = $domain;
            $this->config->setSystemValue('souvera_central.allowed_domains', array_values($domains));
        }
    }

    /**
     * Prüft ob eine E-Mail-Domain erlaubt ist
     *
     * @param string $email
     * @return bool
     */
    public function isEmailDomainAllowed(string $email): bool {
        $allowedDomains = $this->getAllowedDomains();

        // Wenn keine Domains konfiguriert sind, alle erlauben
        if (empty($allowedDomains)) {
            return true;
        }

        // Domain aus E-Mail extrahieren
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }

        $emailDomain = strtolower(trim($parts[1]));

        return in_array($emailDomain, array_map('strtolower', $allowedDomains));
    }

    /**
     * License Key (optional, falls benötigt)
     *
     * @return string|null
     */
    public function getLicenseKey(): ?string {
        return $this->config->getSystemValue('souvera_central.license_key', null);
    }

    /**
     * Externe Domain-Validierungs-API URL (optional)
     *
     * @return string|null
     */
    public function getDomainValidationApiUrl(): ?string {
        return $this->config->getSystemValue('souvera_central.domain_validation_api', null);
    }

    /**
     * Cloud UUID für Reseller-API Calls
     *
     * @return string|null
     */
    public function getCloudUUID(): ?string {
        return $this->config->getSystemValue('souvera_central.cloud_uuid', null);
    }

    // ============================================================================
    // Stalwart Mail-Server Konfiguration (System Config - Read-Only)
    // ============================================================================

    /**
     * Stalwart API URL
     * Lokal: http://stalwart:443 (Docker-intern)
     * Produktion: Kubernetes-interne URL
     *
     * @return string|null
     */
    public function getStalwartApiUrl(): ?string {
        return $this->config->getSystemValue('souvera_central.stalwart_api_url', null);
    }

    /**
     * Stalwart Admin Benutzername
     *
     * @return string|null
     */
    public function getStalwartAdminUser(): ?string {
        return $this->config->getSystemValue('souvera_central.stalwart_admin_user', null);
    }

    /**
     * Stalwart Admin Passwort
     *
     * @return string|null
     */
    public function getStalwartAdminPassword(): ?string {
        return $this->config->getSystemValue('souvera_central.stalwart_admin_password', null);
    }

    /**
     * Prüft ob Stalwart-Integration konfiguriert ist
     *
     * @return bool
     */
    public function isStalwartConfigured(): bool {
        return !empty($this->getStalwartApiUrl())
            && !empty($this->getStalwartAdminUser())
            && !empty($this->getStalwartAdminPassword());
    }

    /**
     * Gibt alle Stalwart-Konfigurationswerte zurück (für Service-Initialisierung)
     *
     * @return array{url: string|null, user: string|null, password: string|null}
     */
    public function getStalwartConfig(): array {
        return [
            'url' => $this->getStalwartApiUrl(),
            'user' => $this->getStalwartAdminUser(),
            'password' => $this->getStalwartAdminPassword(),
        ];
    }

    /**
     * Maximale Anzahl an Email-Aliasen pro Benutzer
     *
     * @return int
     */
    public function getMaxAliasesPerUser(): int {
        return (int) $this->config->getSystemValue('souvera_central.max_aliases_per_user', 10);
    }

    /**
     * Maximale Anzahl an Shared Mailboxes
     *
     * @return int
     */
    public function getMaxSharedMailboxes(): int {
        return (int) $this->config->getSystemValue('souvera_central.max_shared_mailboxes', 10);
    }

    /**
     * Maximale Anzahl an Gruppen
     *
     * @return int
     */
    public function getMaxGroups(): int {
        return (int) $this->config->getSystemValue('souvera_central.max_groups', 20);
    }

    /**
     * Schwellenwert für Warnungen (0.0 - 1.0)
     * Bei 0.8 (80%) wird eine Warnung angezeigt
     *
     * @return float
     */
    public function getWarningThreshold(): float {
        return (float) $this->config->getSystemValue('souvera_central.warning_threshold', 0.8);
    }

    // ============================================================================
    // Admin-User Erkennung
    // ============================================================================

    /**
     * Prüft ob ein Benutzer der Admin-User ist
     *
     * In Souvera ist der Admin-User immer admin@<mail-domain>.
     * Für Kompatibilität mit Dev-Umgebungen wird auch "admin" akzeptiert.
     *
     * @param string $userId - Die Benutzer-ID (z.B. "admin@company.com" oder "admin")
     * @return bool
     */
    public function isAdminUser(string $userId): bool {
        $lowerUserId = strtolower($userId);
        return $lowerUserId === 'admin' || str_starts_with($lowerUserId, 'admin@');
    }

    // ============================================================================
    // App-Einstellungen (App Config - in Nextcloud DB gespeichert)
    // ============================================================================

    /**
     * Sichtbarkeits-Einstellung für ein Feld abrufen
     *
     * @param string $field - z.B. 'manager', 'groups', 'email', etc.
     * @return bool
     */
    public function getVisibilitySetting(string $field): bool {
        return (bool) $this->config->getAppValue('souvera_central', 'settings.visibility.' . $field, '1');
    }

    /**
     * Alle Sichtbarkeits-Einstellungen abrufen
     *
     * @return array
     */
    public function getAllVisibilitySettings(): array {
        return [
            'manager' => $this->getVisibilitySetting('manager'),
            'groups' => $this->getVisibilitySetting('groups'),
            'storage_location' => $this->getVisibilitySetting('storage_location'),
            'last_login' => $this->getVisibilitySetting('last_login'),
            'email' => $this->getVisibilitySetting('email'),
            'backend' => $this->getVisibilitySetting('backend'),
        ];
    }

    /**
     * Gruppen-Sortierung abrufen
     *
     * @return string - 'id', 'displayName', oder 'userCount'
     */
    public function getGroupSorting(): string {
        return $this->config->getAppValue('souvera_central', 'settings.sorting.groups', 'displayName');
    }

    /**
     * Prüfen ob E-Mails an neue Benutzer gesendet werden sollen
     *
     * @return bool
     */
    public function getSendEmailToNewUsers(): bool {
        return (bool) $this->config->getAppValue('souvera_central', 'settings.email.send_to_new_users', '0');
    }

    /**
     * Standard-Quota für neue Benutzer abrufen
     *
     * @return string - z.B. 'default', '5 GB', 'none'
     */
    public function getDefaultQuota(): string {
        return $this->config->getAppValue('souvera_central', 'settings.defaults.quota', 'default');
    }

    /**
     * Standard-Postfach-Speicherlimit (Stalwart maxDiskQuota) in Bytes.
     * 0 = unbegrenzt. Wird bei der Postfach-Anlage angewendet und dient dem
     * occ-Befehl souvera_central:quota:set --all als neuer globaler Standard.
     */
    public function getDefaultMailboxQuota(): int {
        return (int) $this->config->getAppValue(
            'souvera_central',
            'settings.defaults.mailbox_quota',
            (string) QuotaParser::DEFAULT_BYTES
        );
    }

    /**
     * Globalen Standard für das Postfach-Speicherlimit setzen (Bytes, 0 = unbegrenzt).
     */
    public function setDefaultMailboxQuota(int $bytes): void {
        $this->config->setAppValue(
            'souvera_central',
            'settings.defaults.mailbox_quota',
            (string) max(0, $bytes)
        );
    }

    // ============================================================================
    // Mail-Gruppe (Sichtbarkeit der Mail-App / smail steuern)
    // ============================================================================

    /**
     * ID der Nextcloud-Gruppe, in die alle Benutzer mit Stalwart-Postfach
     * automatisch aufgenommen werden. Über diese Gruppe lässt sich die
     * smail-App in den Nextcloud-App-Einstellungen beschränken, sodass
     * Benutzer ohne Postfach die App nicht sehen.
     *
     * @return string
     */
    public function getMailGroupId(): string {
        $gid = (string) $this->config->getSystemValue('souvera_central.mail_group', 'souvera-users');
        $gid = trim($gid);
        return $gid !== '' ? $gid : 'souvera-users';
    }

    /**
     * Anzeigename der Mail-Gruppe (in der NC-Oberfläche sichtbar).
     * Standard: "Souvera Users".
     *
     * @return string
     */
    public function getMailGroupDisplayName(): string {
        $name = (string) $this->config->getSystemValue('souvera_central.mail_group_name', 'Souvera Users');
        $name = trim($name);
        return $name !== '' ? $name : 'Souvera Users';
    }

    /**
     * Steuert, ob die automatische Pflege der Mail-Gruppe aktiv ist.
     * Standard: aktiviert.
     *
     * @return bool
     */
    public function isMailGroupSyncEnabled(): bool {
        return (bool) $this->config->getSystemValue('souvera_central.mail_group_sync', true);
    }

    // ============================================================================
    // Souvera-Administrator-Gruppe (delegierte Verwaltung ohne NC-Superadmin)
    // ============================================================================

    /**
     * GID der Nextcloud-Gruppe, deren Mitglieder als "Souvera-Administrator"
     * gelten und Souvera Central sehen + bedienen dürfen, ohne echte
     * Nextcloud-Superadmins zu sein. Standard-GID: "souvera-admins".
     *
     * Hinweis zur Benennung: "scadmin" ist der technische Admin-BENUTZER,
     * NICHT die Gruppe. Die Admin-GRUPPE heißt "souvera-admins".
     * Konfigurierbar via `souvera_central.admin_group` (bevorzugt) bzw. dem
     * Legacy-Schlüssel `souvera_central.scadmin_group`.
     *
     * @return string
     */
    public function getScadminGroupId(): string {
        $gid = (string) $this->config->getSystemValue(
            'souvera_central.admin_group',
            $this->config->getSystemValue('souvera_central.scadmin_group', 'souvera-admins')
        );
        $gid = trim($gid);
        return $gid !== '' ? $gid : 'souvera-admins';
    }

    /**
     * Anzeigename der Souvera-Administrator-Gruppe. Standard: "Souvera Admins".
     *
     * @return string
     */
    public function getScadminGroupName(): string {
        $name = (string) $this->config->getSystemValue(
            'souvera_central.admin_group_name',
            $this->config->getSystemValue('souvera_central.scadmin_group_name', 'Souvera Admins')
        );
        $name = trim($name);
        return $name !== '' ? $name : 'Souvera Admins';
    }

    /**
     * Benutzer-IDs, die in Souvera Central vollständig ausgeblendet werden
     * (z. B. der technische "ncadmin"). Sie tauchen weder in Listen noch in
     * Zählungen/Lizenzen auf. Konfigurierbar via config.php (Array oder CSV).
     *
     * @return string[]
     */
    public function getHiddenUserIds(): array {
        $raw = $this->config->getSystemValue('souvera_central.hidden_users', ['ncadmin']);
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }
        $list = is_array($raw) ? array_values(array_filter(array_map('strval', $raw))) : ['ncadmin'];
        return array_map('strtolower', $list);
    }

    /**
     * Prüft, ob ein Benutzer in Souvera Central ausgeblendet werden soll.
     *
     * @param string $userId
     * @return bool
     */
    public function isHiddenUser(string $userId): bool {
        return in_array(strtolower($userId), $this->getHiddenUserIds(), true);
    }

    // ============================================================================
    // Ausgeblendete Gruppen (z. B. NC-Systemgruppe "admin")
    // ============================================================================

    /**
     * Gruppen-IDs, die in Souvera Central vollständig ausgeblendet werden
     * (Standard: NC-Systemgruppe "admin"). Sie erscheinen weder in der
     * Gruppenliste noch im Gruppen-Selektor. Konfigurierbar via
     * `souvera_central.hidden_groups` (Array oder CSV).
     *
     * @return string[]
     */
    public function getHiddenGroupIds(): array {
        $raw = $this->config->getSystemValue('souvera_central.hidden_groups', ['admin']);
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }
        $list = is_array($raw) ? array_values(array_filter(array_map('strval', $raw))) : ['admin'];
        return array_map('strtolower', $list);
    }

    /**
     * Prüft, ob eine Gruppe in Souvera Central ausgeblendet werden soll.
     *
     * @param string $groupId
     * @return bool
     */
    public function isHiddenGroup(string $groupId): bool {
        return in_array(strtolower($groupId), $this->getHiddenGroupIds(), true);
    }

    // ============================================================================
    // Technischer Souvera-Admin-BENUTZER (z. B. "scadmin")
    // ============================================================================

    /**
     * Benutzer-Kennung(en) des technischen Souvera-Administrator-BENUTZERS
     * (Standard "scadmin"). Dieser Account erhält zwar ein Postfach + volle
     * Central-Rechte, zählt aber NICHT als Souvera User / Lizenz.
     *
     * WICHTIG: Dies ist der einzelne Admin-BENUTZER – NICHT die Admin-GRUPPE
     * "souvera-admins". Ein regulärer Souvera User, der zusätzlich
     * Souvera-Admin-Rechte (Gruppe) erhält, wird weiterhin mitgezählt.
     * Konfigurierbar via `souvera_central.admin_user` (Array oder CSV).
     *
     * @return string[] lowercase
     */
    public function getAdminUserIds(): array {
        $raw = $this->config->getSystemValue('souvera_central.admin_user', ['scadmin']);
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }
        $list = is_array($raw) ? array_values(array_filter(array_map('strval', $raw))) : ['scadmin'];
        return array_map('strtolower', $list);
    }

    /**
     * Prüft, ob der Benutzer der technische Souvera-Admin-BENUTZER ist (z. B.
     * scadmin). Matcht UID und E-Mail sowie deren Localpart, damit sowohl
     * "scadmin" als auch "scadmin@domain" erkannt werden.
     *
     * @param string $userId
     * @param string|null $email optionale E-Mail-Adresse des Benutzers
     * @return bool
     */
    public function isAdminAccount(string $userId, ?string $email = null): bool {
        $candidates = [strtolower($userId)];
        if (str_contains($userId, '@')) {
            $candidates[] = strtolower(substr($userId, 0, strpos($userId, '@')));
        }
        if ($email !== null && $email !== '') {
            $email = strtolower($email);
            $candidates[] = $email;
            if (str_contains($email, '@')) {
                $candidates[] = substr($email, 0, strpos($email, '@'));
            }
        }
        foreach ($this->getAdminUserIds() as $admin) {
            if (in_array($admin, $candidates, true)) {
                return true;
            }
        }
        return false;
    }
}
