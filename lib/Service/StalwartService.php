<?php
/**
 * Souvera Central - Stalwart Mail-Server Service
 *
 * Kommunikation mit der Stalwart REST API für:
 * - Email-Aliase verwalten
 * - Shared Mailboxes (zukünftig)
 * - Principal-Management
 */

namespace OCA\SouveraCentral\Service;

use Psr\Log\LoggerInterface;

class StalwartService {
    private ConfigService $configService;
    private LoggerInterface $logger;

    public function __construct(
        ConfigService $configService,
        LoggerInterface $logger
    ) {
        $this->configService = $configService;
        $this->logger = $logger;
    }

    // ============================================================================
    // Principal-Verwaltung (Benutzer im Stalwart)
    // ============================================================================

    /**
     * Principal (Benutzer) aus Stalwart abrufen
     *
     * @param string $principalId - Username/Email des Benutzers
     * @return array|null - Principal-Daten oder null bei Fehler
     */
    public function getPrincipal(string $principalId): ?array {
        $response = $this->apiRequest('GET', '/api/principal/' . urlencode($principalId));

        if ($response === null) {
            return null;
        }

        // Stalwart API gibt {"data": {...}} zurück
        return $response['data'] ?? $response;
    }

    /**
     * Alle Email-Adressen eines Benutzers abrufen
     *
     * @param string $principalId - Username/Email des Benutzers
     * @return array - Liste der Email-Adressen (Haupt-Email + Aliase)
     */
    public function getEmails(string $principalId): array {
        $principal = $this->getPrincipal($principalId);

        if ($principal === null) {
            return [];
        }

        return $principal['emails'] ?? [$principalId];
    }

    /**
     * Email-Alias hinzufügen
     *
     * @param string $principalId - Username/Email des Benutzers
     * @param string $alias - Neue Email-Adresse
     * @return bool - Erfolg
     */
    public function addAlias(string $principalId, string $alias): bool {
        // Validierung: Email-Format prüfen
        if (!filter_var($alias, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('StalwartService: Ungültiges Email-Format für Alias', [
                'principalId' => $principalId,
                'alias' => $alias
            ]);
            return false;
        }

        // Domain-Validierung
        if (!$this->configService->isEmailDomainAllowed($alias)) {
            $this->logger->warning('StalwartService: Domain nicht erlaubt für Alias', [
                'principalId' => $principalId,
                'alias' => $alias
            ]);
            return false;
        }

        // PATCH-Request mit addItem Operation
        $payload = [
            [
                'action' => 'addItem',
                'field' => 'emails',
                'value' => $alias
            ]
        ];

        $response = $this->apiRequest('PATCH', '/api/principal/' . urlencode($principalId), $payload);

        if ($response === null) {
            return false;
        }

        $this->logger->info('StalwartService: Alias hinzugefügt', [
            'principalId' => $principalId,
            'alias' => $alias
        ]);

        return true;
    }

    /**
     * Email-Alias entfernen
     *
     * @param string $principalId - Username/Email des Benutzers
     * @param string $alias - Zu entfernende Email-Adresse
     * @return bool - Erfolg
     */
    public function removeAlias(string $principalId, string $alias): bool {
        // Verhindere Entfernung der Haupt-Email
        if ($alias === $principalId) {
            $this->logger->warning('StalwartService: Haupt-Email kann nicht entfernt werden', [
                'principalId' => $principalId,
                'alias' => $alias
            ]);
            return false;
        }

        // PATCH-Request mit removeItem Operation
        $payload = [
            [
                'action' => 'removeItem',
                'field' => 'emails',
                'value' => $alias
            ]
        ];

        $response = $this->apiRequest('PATCH', '/api/principal/' . urlencode($principalId), $payload);

        if ($response === null) {
            return false;
        }

        $this->logger->info('StalwartService: Alias entfernt', [
            'principalId' => $principalId,
            'alias' => $alias
        ]);

        return true;
    }

    /**
     * Alle Aliase eines Benutzers abrufen (ohne Haupt-Email)
     *
     * @param string $principalId - Username/Email des Benutzers
     * @return array - Liste der Aliase
     */
    public function getAliases(string $principalId): array {
        $emails = $this->getEmails($principalId);

        // Filtere die Haupt-Email heraus
        return array_values(array_filter($emails, function($email) use ($principalId) {
            return $email !== $principalId;
        }));
    }

    /**
     * Prüft ob ein Email-Alias bereits existiert (global)
     *
     * @param string $email - Zu prüfende Email-Adresse
     * @return bool - true wenn bereits vergeben
     */
    public function isEmailTaken(string $email): bool {
        // Suche nach Principal mit dieser Email
        $response = $this->apiRequest('GET', '/api/principal?filter=' . urlencode($email));

        if ($response === null) {
            return false; // Im Zweifel erlauben
        }

        // Prüfe ob Email in einem der Ergebnisse vorkommt
        foreach ($response as $principal) {
            $emails = $principal['emails'] ?? [];
            if (in_array($email, $emails)) {
                return true;
            }
        }

        return false;
    }

    // ============================================================================
    // API-Kommunikation
    // ============================================================================

    /**
     * API-Request an Stalwart senden
     *
     * @param string $method - HTTP-Methode (GET, POST, PATCH, DELETE)
     * @param string $endpoint - API-Endpunkt (z.B. /api/principal/user@domain.de)
     * @param array|null $data - Request-Body (für POST/PATCH)
     * @return array|null - Response-Daten oder null bei Fehler
     */
    private function apiRequest(string $method, string $endpoint, ?array $data = null): ?array {
        // Config prüfen
        if (!$this->configService->isStalwartConfigured()) {
            $this->logger->error('StalwartService: Stalwart nicht konfiguriert');
            return null;
        }

        $config = $this->configService->getStalwartConfig();
        $url = rtrim($config['url'], '/') . $endpoint;

        // cURL initialisieren
        $ch = curl_init();

        // Basis-Optionen
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            // Basic Auth
            CURLOPT_USERPWD => $config['user'] . ':' . $config['password'],
            // SSL-Optionen (für lokale Entwicklung ggf. deaktivieren)
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        // Methode und Body setzen
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;

            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;

            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;

            case 'GET':
            default:
                // GET ist Standard
                break;
        }

        // Request ausführen
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Fehlerbehandlung
        if ($error) {
            $this->logger->error('StalwartService: cURL-Fehler', [
                'url' => $url,
                'method' => $method,
                'error' => $error
            ]);
            return null;
        }

        // HTTP-Status prüfen
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->logger->warning('StalwartService: HTTP-Fehler', [
                'url' => $url,
                'method' => $method,
                'httpCode' => $httpCode,
                'response' => $response
            ]);
            return null;
        }

        // Response parsen
        $decoded = json_decode($response, true);

        if ($decoded === null && !empty($response)) {
            $this->logger->warning('StalwartService: JSON-Parsing fehlgeschlagen', [
                'url' => $url,
                'response' => substr($response, 0, 500)
            ]);
            return null;
        }

        return $decoded ?? [];
    }

    // ============================================================================
    // Status & Health
    // ============================================================================

    /**
     * Prüft ob Stalwart erreichbar ist
     *
     * @return bool
     */
    public function isAvailable(): bool {
        if (!$this->configService->isStalwartConfigured()) {
            return false;
        }

        // Versuche API-Root aufzurufen
        $config = $this->configService->getStalwartConfig();
        $url = rtrim($config['url'], '/') . '/api/principal';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_USERPWD => $config['user'] . ':' . $config['password'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * Gibt Status-Informationen zurück
     *
     * @return array
     */
    public function getStatus(): array {
        return [
            'configured' => $this->configService->isStalwartConfigured(),
            'available' => $this->isAvailable(),
            'url' => $this->configService->getStalwartApiUrl() ?
                preg_replace('/\/\/[^:]+:[^@]+@/', '//***:***@', $this->configService->getStalwartApiUrl()) :
                null
        ];
    }
}
