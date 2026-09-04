<?php
/**
 * Souvera User Management - Config Service
 *
 * Liest Read-Only Konfiguration aus config.php (System Config)
 */

namespace OCA\SouveraCentral\Service;

use OCA\SouveraCentral\AppInfo\Application;

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
    /** App-eigener AppValue-Reader (Bequemlichkeits-Wrapper) */
    public function getSelfAppValue(string $key, string $default = ''): string {
        return $this->config->getAppValue(Application::APP_ID, $key, $default);
    }

    /** App-eigener AppValue-Writer (Bequemlichkeits-Wrapper) */
    public function setSelfAppValue(string $key, string $value): void {
        $this->config->setAppValue(Application::APP_ID, $key, $value);
    }

    /**
     * Schreibt die Souvera-L10n-Theme-Dateien nach
     * <serverroot>/themes/souvera/l10n/apps/<app>/l10n/<lang>.json.
     * NC merged Theme-Übersetzungen bei aktivem `theme`-Config (L10N/Factory).
     * Gibt true zurück, wenn ALLE Dateien erfolgreich geschrieben wurden.
     */
    public function writeThemeL10nFiles(): bool {
        $serverRoot = \OC::$SERVERROOT;
        if (!is_string($serverRoot) || $serverRoot === '' || !is_dir($serverRoot)) {
            return false;
        }

        $link = $this->getBrandingTalkName();
        $desk = $this->getBrandingOfficeName();
        $plural = 'nplurals=2; plural=(n != 1);';
        $targets = [
            'spreed' => ['Talk' => $link],
            'richdocuments' => ['Nextcloud Office' => $desk],
        ];

        foreach ($targets as $appId => $map) {
            $dir = $serverRoot . '/themes/souvera/l10n/apps/' . $appId . '/l10n';
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return false;
            }
            foreach (['de', 'en'] as $lang) {
                $json = json_encode(
                    ['translations' => $map, 'pluralForm' => $plural],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                if (@file_put_contents($dir . '/' . $lang . '.json', $json . "\n") === false) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Login-Branding (Split-Screen) aktiv? Notbremse via
     * occ config:system:set souvera_login.enabled --value 0.
     */
    public function isLoginBrandingEnabled(): bool {
        return $this->config->getSystemValueString('souvera_login.enabled', '1') !== '0';
    }

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
     * Gesamter Mail-Speicher-Pool in Bytes (config.php: souvera_central.max_mail_storage).
     * 0 = kein Pool gesetzt (unbegrenzt, abwärtskompatibel). Wird ausschließlich
     * vom Hoster per occ gesetzt (nicht in der UI) und in der Central verteilt.
     */
    public function getMaxMailStorage(): int {
        return (int) $this->config->getSystemValue('souvera_central.max_mail_storage', 0);
    }

    public function setMaxMailStorage(int $bytes): void {
        $this->config->setSystemValue('souvera_central.max_mail_storage', max(0, $bytes));
    }

    /**
     * Standard-Postfach-Speicherlimit (Bytes), das bei AKTIVEM Mail-Speicher-Pool
     * für neue Postfächer verwendet wird, wenn kein explizites Limit angegeben ist.
     * Bewusst klein (Standard 1 GiB), damit nicht einige wenige neue Postfächer den
     * ganzen Pool aufbrauchen. Wird vom Hoster per occ gesetzt
     * (souvera_central:mailstorage:set … --mailbox-default 1G).
     *
     * Hinweis: getDefaultMailboxQuota() (50 GiB) gilt weiterhin, wenn KEIN Pool
     * gesetzt ist (abwärtskompatibel).
     */
    public function getPoolDefaultMailboxQuota(): int {
        $v = (int) $this->config->getSystemValue('souvera_central.mail_storage_default_quota', StorageService::GIB);
        return $v > 0 ? $v : StorageService::GIB;
    }

    public function setPoolDefaultMailboxQuota(int $bytes): void {
        $this->config->setSystemValue('souvera_central.mail_storage_default_quota', max(1, $bytes));
    }

    /**
     * Lokale Teile (vor dem @) interner System-/Dienst-Postfächer. Diese werden
     * dem Kunden NICHT angezeigt und zählen NICHT in den Mail-Speicher-Pool.
     * Hoster-konfigurierbar via systemValue souvera_central.system_mailboxes
     * (kommagetrennt). Default: postmaster, mailer-daemon.
     *
     * @return list<string> lowercase
     */
    public function getSystemMailboxLocalParts(): array {
        $raw = (string) $this->config->getSystemValue('souvera_central.system_mailboxes', 'postmaster,mailer-daemon');
        $parts = array_values(array_filter(array_map(static function ($p) {
            return strtolower(trim($p));
        }, explode(',', $raw)), static function ($p) {
            return $p !== '';
        }));
        return $parts ?: ['postmaster', 'mailer-daemon'];
    }

    /**
     * Zielgröße interner System-Postfächer (Bytes). Diese Postfächer brauchen
     * nur wenig Platz (Default 1 GiB) und sind vom Pool ausgenommen.
     */
    public function getSystemMailboxQuota(): int {
        $v = (int) $this->config->getSystemValue('souvera_central.system_mailbox_quota', StorageService::GIB);
        return $v > 0 ? $v : StorageService::GIB;
    }

    public function setSystemMailboxQuota(int $bytes): void {
        $this->config->setSystemValue('souvera_central.system_mailbox_quota', max(1, $bytes));
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
     * Entfernt eine Domain aus der Erlaubnisliste (idempotent,
     * case-insensitive).
     */
    public function removeAllowedDomain(string $domain): void {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return;
        }
        $domains = $this->getAllowedDomains();
        $filtered = array_values(array_filter(
            $domains,
            static fn ($d) => strtolower(trim((string) $d)) !== $domain
        ));
        if (count($filtered) !== count($domains)) {
            $this->config->setSystemValue('souvera_central.allowed_domains', $filtered);
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
     * Externe Authentifizierung (Föderation, z. B. Authentik/Keycloak via
     * NC `user_oidc`/`user_saml`): Wenn aktiv, bekommen beim ersten Login
     * automatisch provisionierte Benutzer (sofern Mitglied der
     * souvera-users-Gruppe) ein Stalwart-Postfach mit zufälligem internen
     * Passwort. Login selbst läuft über SSO, Mail-Auth über das
     * H2CK/oidc-JWT. Hoster-Set via occ/config.php:
     *   souvera_central.ext_idp.enabled = true
     */
    public function isExternalIdpProvisioningEnabled(): bool {
        return (bool) $this->config->getSystemValue('souvera_central.ext_idp.enabled', false);
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
    // Globale Mail-Signatur (zentral in Central verwaltet; von souvera_mail via
    // GET /api/mail-settings abgefragt, optional serverseitig via Stalwart Sieve).
    // EINE Signatur/Vorlage – gilt für Webmail UND (optional) serverseitig.
    // ============================================================================

    /** Unterstützte Platzhalter, die souvera_mail bzw. das Sieve-Script ersetzen. */
    public const SIGNATURE_VARIABLES = ['%name%', '%email%', '%first_name%', '%last_name%', '%domain%'];

    public function isMailSignatureEnabled(): bool {
        return $this->config->getAppValue('souvera_central', 'settings.mail_signature.enabled', '0') === '1';
    }

    public function setMailSignatureEnabled(bool $enabled): void {
        $this->config->setAppValue('souvera_central', 'settings.mail_signature.enabled', $enabled ? '1' : '0');
    }

    public function getMailSignatureTemplate(): string {
        return $this->config->getAppValue('souvera_central', 'settings.mail_signature.template', '');
    }

    public function setMailSignatureTemplate(string $html): void {
        $this->config->setAppValue('souvera_central', 'settings.mail_signature.template', $html);
    }

    /**
     * true  = serverseitig via Stalwart erzwingen (gilt für ALLE SMTP-Clients);
     *         souvera_mail hängt dann NICHTS an (Doppel-Signatur vermeiden).
     * false = nur Webmail (souvera_mail) rendert die Signatur personalisiert.
     */
    public function isMailSignatureServerSide(): bool {
        return $this->config->getAppValue('souvera_central', 'settings.mail_signature.server_side', '0') === '1';
    }

    public function setMailSignatureServerSide(bool $serverSide): void {
        $this->config->setAppValue('souvera_central', 'settings.mail_signature.server_side', $serverSide ? '1' : '0');
    }

    /**
     * Vollständiger Signatur-Vertrag für die API / souvera_mail.
     *
     * souvera_mail-Logik: wenn signature_enabled && !server_side -> Vorlage
     * personalisiert rendern; wenn server_side -> NICHTS anhängen (Stalwart macht es).
     *
     * @return array{signature_enabled:bool, signature_template:string, signature_format:string, server_side:bool, variables:list<string>}
     */
    public function getMailSignatureSettings(): array {
        return [
            'signature_enabled' => $this->isMailSignatureEnabled(),
            'signature_template' => $this->getMailSignatureTemplate(),
            'signature_format' => 'html',
            'server_side' => $this->isMailSignatureServerSide(),
            'variables' => self::SIGNATURE_VARIABLES,
        ];
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

    // ============================================================================
    // Hilfe / BookStack-Dokumentation (Standard: doku.souvera.eu)
    // ============================================================================

    /**
     * Basis-URL der BookStack-Instanz (ohne abschließenden Slash).
     * Fester Default; nicht als Hoster-Config vorgesehen. Der Zugang läuft
     * ausschließlich über den verschlüsselten BookStackTokenService (occ).
     */
    public function getBookStackUrl(): string {
        $url = (string) $this->config->getSystemValue('souvera_central.bookstack_url', 'https://doku.souvera.eu');
        return rtrim(trim($url), '/');
    }

    /**
     * Cache-Dauer (Sekunden) für BookStack-Antworten der Hilfe. Verkürzt
     * Ladezeiten und minimiert Traffic zur BookStack-Instanz. Standard: 86400
     * (24 Stunden). 0 = Cache deaktiviert. Config: souvera_central.help_cache_ttl.
     */
    public function getHelpCacheTtl(): int {
        $v = (int) $this->config->getSystemValue('souvera_central.help_cache_ttl', 86400);
        return $v < 0 ? 0 : $v;
    }

    /**
     * BookStack-Shelf-IDs, die normale Souvera-User (Endnutzer) in der Hilfe
     * sehen. Standard: [1] ("Benutzer"). Config: souvera_central.help_user_shelves.
     *
     * @return int[]
     */
    public function getHelpUserShelfIds(): array {
        return $this->parseShelfIds('souvera_central.help_user_shelves', [1]);
    }

    /**
     * Zusätzliche Shelf-IDs, die NUR Souvera-Admins sehen. Standard: [2]
     * ("Administratoren"). Config: souvera_central.help_admin_shelves.
     *
     * @return int[]
     */
    public function getHelpAdminShelfIds(): array {
        return $this->parseShelfIds('souvera_central.help_admin_shelves', [2]);
    }

    /**
     * @param int[] $default
     * @return int[]
     */
    private function parseShelfIds(string $key, array $default): array {
        $raw = $this->config->getSystemValue($key, $default);
        if (is_string($raw)) {
            $raw = array_map('trim', explode(',', $raw));
        }
        if (!is_array($raw)) {
            $raw = $default;
        }
        $ids = [];
        foreach ($raw as $v) {
            $n = (int) $v;
            if ($n > 0) {
                $ids[] = $n;
            }
        }
        return array_values(array_unique($ids !== [] ? $ids : $default));
    }

    // ============================================================================
    // Instanzweite App-Umbenennung (Branding): Talk -> "Link", Office -> "Desk"
    //
    // Feste Souvera-Namen (NICHT editierbar), immer aktiv.
    // ============================================================================

    public function getBrandingTalkName(): string {
        return 'Link';
    }

    public function getBrandingOfficeName(): string {
        return 'Desk';
    }

    /**
     * Branding-Konfiguration für das global eingespielte Frontend-Skript.
     * Bildet App-IDs auf die festen neuen Anzeigenamen ab.
     *
     * @return array{names: array<string,string>}
     */
    /** Default-Gepinnte Apps für den Souvera-Header (Reihenfolge). */
    public const HEADER_PINNED_DEFAULT = [
        'souvera_mail', 'calendar', 'spreed', 'office', 'deck', 'files',
    ];

    public function getBrandingConfig(): array {
        $office = $this->getBrandingOfficeName();
        return [
            'names' => [
                'spreed' => $this->getBrandingTalkName(),
                'richdocuments' => $office,
                'richdocumentscode' => $office,
                // Alias-Keys für das App-Menü: Nextcloud Office registriert seinen
                // Navigations-Eintrag unter dem Pfad /apps/office/ (nicht
                // /apps/richdocuments/). Das JS-Fallback matcht per href, daher hier
                // zusätzlich die tatsächlich verwendeten Pfad-Segmente abbilden.
                'office' => $office,
                'collabora' => $office,
            ],
            // Souvera-Header (v34-Header-Umbau): gepinnte Apps direkt im Header,
            // „Dashboard"-Breadcrumb aus, Suche rechts kompakt, „Mehr" = der
            // bestehende App-Grid-Dropdown. Global an (dev-Channels), Notbremse:
            // branding.header.enabled = 0
            'header' => [
                'enabled' => $this->isHeaderLayoutEnabled(),
                'pinned' => $this->getHeaderPinnedApps(),
                'adminOnly' => ['souvera_central'],
            ],
        ];
    }

    /**
     * Souvera-Header aktiv? Default AN — Notbremse per occ/config.php:
     *   souvera_central.branding.header.enabled = 0
     */
    public function isHeaderLayoutEnabled(): bool {
        return $this->config->getAppValue('souvera_central', 'branding.header.enabled', '1') === '1';
    }

    /**
     * Gepinnte Apps für den Souvera-Header (App-IDs in Reihenfolge).
     * Überschreibbar via occ/config.php:
     *   souvera_central.branding.header.pinned = ["dashboard","files",...]
     */
    public function getHeaderPinnedApps(): array {
        $raw = $this->config->getAppValue('souvera_central', 'branding.header.pinned', '');
        if (trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('strval', $decoded), static fn ($v) => trim($v) !== ''));
            }
        }
        return self::HEADER_PINNED_DEFAULT;
    }

    /** @see ARCHIVE_PLAN §2.2a */
    public function getArchiveEnabled(): bool
    {
        return $this->config->getAppValue('souvera_central', 'archive.enabled', '0') === '1';
    }

    public function getArchiveRetentionYears(): int
    {
        return (int) $this->config->getAppValue('souvera_central', 'archive.retention_years', '10');
    }

    public function getArchiveAutoDelete(): bool
    {
        return $this->config->getAppValue('souvera_central', 'archive.auto_delete', '0') === '1';
    }

    public function getArchiveS3Bucket(): ?string
    {
        return $this->getSystemValue('souvera_central.archive_s3_bucket', null);
    }

    public function getArchiveCmApiUrl(): ?string
    {
        return $this->getSystemValue('souvera_central.cm_api_url', null);
    }

    public function getArchiveCmApiKey(): ?string
    {
        return $this->getSystemValue('souvera_central.cm_api_key', null);
    }
}
