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
}
