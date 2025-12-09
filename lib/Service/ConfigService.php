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
}
