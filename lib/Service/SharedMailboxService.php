<?php
/**
 * Souvera Central - Shared Mailbox Service
 *
 * Verwaltung von geteilten Postfächern via Stalwart API
 * Shared Mailboxes sind Stalwart Principals vom Typ "group"
 */

namespace OCA\SouveraCentral\Service;

use Psr\Log\LoggerInterface;

class SharedMailboxService {
    private ConfigService $configService;
    private StalwartService $stalwartService;
    private LoggerInterface $logger;

    public function __construct(
        ConfigService $configService,
        StalwartService $stalwartService,
        LoggerInterface $logger
    ) {
        $this->configService = $configService;
        $this->stalwartService = $stalwartService;
        $this->logger = $logger;
    }

    // ============================================================================
    // CRUD-Operationen für Shared Mailboxes
    // ============================================================================

    /**
     * Alle Shared Mailboxes abrufen
     *
     * @return array
     */
    public function list(): array {
        $response = $this->apiRequest('GET', '/api/principal?types=group');

        if ($response === null) {
            return [];
        }

        $items = $response['data']['items'] ?? [];

        // Filtere nur echte Shared Mailboxes (haben emails)
        return array_values(array_filter($items, function($item) {
            return !empty($item['emails']);
        }));
    }

    /**
     * Shared Mailbox erstellen
     *
     * @param string $name - Interner Name (z.B. "support-mailbox")
     * @param string $email - Email-Adresse (z.B. "support@company.org")
     * @param string $description - Beschreibung
     * @return array|null - Erstellte Mailbox oder null bei Fehler
     */
    public function create(string $name, string $email, string $description = ''): ?array {
        // Validierung
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('SharedMailboxService: Ungültiges Email-Format', [
                'email' => $email
            ]);
            return null;
        }

        // Domain-Validierung
        if (!$this->configService->isEmailDomainAllowed($email)) {
            $this->logger->warning('SharedMailboxService: Domain nicht erlaubt', [
                'email' => $email
            ]);
            return null;
        }

        // Name normalisieren (lowercase, keine Sonderzeichen)
        $normalizedName = $this->normalizeName($name);

        $payload = [
            'type' => 'group',
            'name' => $normalizedName,
            'description' => $description,
            'emails' => [$email]
        ];

        $response = $this->apiRequest('POST', '/api/principal', $payload);

        if ($response === null) {
            return null;
        }

        $this->logger->info('SharedMailboxService: Shared Mailbox erstellt', [
            'name' => $normalizedName,
            'email' => $email
        ]);

        // Neu erstellte Mailbox abrufen
        return $this->get($normalizedName);
    }

    /**
     * Shared Mailbox abrufen
     *
     * @param string $name - Interner Name
     * @return array|null
     */
    public function get(string $name): ?array {
        $response = $this->apiRequest('GET', '/api/principal/' . urlencode($name));

        if ($response === null) {
            return null;
        }

        $data = $response['data'] ?? $response;

        // Prüfe ob es eine Shared Mailbox ist (type=group mit emails)
        if (($data['type'] ?? '') !== 'group' || empty($data['emails'])) {
            return null;
        }

        return $data;
    }

    /**
     * Shared Mailbox aktualisieren
     *
     * @param string $name - Interner Name
     * @param array $updates - Zu aktualisierende Felder
     * @return bool
     */
    public function update(string $name, array $updates): bool {
        $operations = [];

        if (isset($updates['description'])) {
            $operations[] = [
                'action' => 'set',
                'field' => 'description',
                'value' => $updates['description']
            ];
        }

        if (empty($operations)) {
            return true; // Nichts zu tun
        }

        $response = $this->apiRequest('PATCH', '/api/principal/' . urlencode($name), $operations);

        if ($response === null) {
            return false;
        }

        $this->logger->info('SharedMailboxService: Shared Mailbox aktualisiert', [
            'name' => $name,
            'updates' => array_keys($updates)
        ]);

        return true;
    }

    /**
     * Shared Mailbox löschen
     *
     * @param string $name - Interner Name
     * @return bool
     */
    public function delete(string $name): bool {
        $response = $this->apiRequest('DELETE', '/api/principal/' . urlencode($name));

        // DELETE gibt oft leere Response zurück, prüfe ob kein Fehler
        $this->logger->info('SharedMailboxService: Shared Mailbox gelöscht', [
            'name' => $name
        ]);

        return true;
    }

    // ============================================================================
    // Mitglieder-Verwaltung
    // ============================================================================

    /**
     * Mitglieder einer Shared Mailbox abrufen
     *
     * In Stalwart wird Gruppenmitgliedschaft auf dem USER gespeichert (memberOf),
     * nicht auf der Gruppe. Daher müssen wir alle User durchsuchen.
     *
     * @param string $name - Interner Name
     * @return array - Liste der Mitglieder-IDs
     */
    public function getMembers(string $name): array {
        // Erst prüfen ob die Gruppe existiert
        $mailbox = $this->get($name);
        if ($mailbox === null) {
            return [];
        }

        // Alle User abrufen und filtern nach memberOf
        $response = $this->apiRequest('GET', '/api/principal?types=individual');
        if ($response === null) {
            return [];
        }

        $users = $response['data']['items'] ?? [];
        $members = [];

        foreach ($users as $user) {
            $memberOf = $user['memberOf'] ?? [];
            if (in_array($name, $memberOf)) {
                $members[] = $user['name'];
            }
        }

        return $members;
    }

    /**
     * Mitglied hinzufügen
     *
     * Fügt die Gruppe zum memberOf-Feld des Users hinzu
     *
     * @param string $name - Interner Name der Mailbox
     * @param string $userId - Benutzer-ID (Email)
     * @return bool
     */
    public function addMember(string $name, string $userId): bool {
        // PATCH auf den USER, nicht auf die Gruppe
        $payload = [
            [
                'action' => 'addItem',
                'field' => 'memberOf',
                'value' => $name
            ]
        ];

        $response = $this->apiRequest('PATCH', '/api/principal/' . urlencode($userId), $payload);

        if ($response === null) {
            return false;
        }

        $this->logger->info('SharedMailboxService: Mitglied hinzugefügt', [
            'mailbox' => $name,
            'userId' => $userId
        ]);

        return true;
    }

    /**
     * Mitglied entfernen
     *
     * Entfernt die Gruppe vom memberOf-Feld des Users
     *
     * @param string $name - Interner Name der Mailbox
     * @param string $userId - Benutzer-ID (Email)
     * @return bool
     */
    public function removeMember(string $name, string $userId): bool {
        // PATCH auf den USER, nicht auf die Gruppe
        $payload = [
            [
                'action' => 'removeItem',
                'field' => 'memberOf',
                'value' => $name
            ]
        ];

        $response = $this->apiRequest('PATCH', '/api/principal/' . urlencode($userId), $payload);

        if ($response === null) {
            return false;
        }

        $this->logger->info('SharedMailboxService: Mitglied entfernt', [
            'mailbox' => $name,
            'userId' => $userId
        ]);

        return true;
    }

    // ============================================================================
    // Hilfsmethoden
    // ============================================================================

    /**
     * Prüft ob eine Email-Adresse bereits als Shared Mailbox existiert
     *
     * @param string $email
     * @return bool
     */
    public function isEmailTaken(string $email): bool {
        return $this->stalwartService->isEmailTaken($email);
    }

    /**
     * Name normalisieren für Stalwart
     *
     * @param string $name
     * @return string
     */
    private function normalizeName(string $name): string {
        // Lowercase, Leerzeichen durch Bindestriche ersetzen
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/\s+/', '-', $normalized);
        // Nur alphanumerische Zeichen und Bindestriche
        $normalized = preg_replace('/[^a-z0-9\-]/', '', $normalized);
        return $normalized;
    }

    /**
     * API-Request an Stalwart senden (nutzt ConfigService)
     */
    private function apiRequest(string $method, string $endpoint, ?array $data = null): ?array {
        if (!$this->configService->isStalwartConfigured()) {
            $this->logger->error('SharedMailboxService: Stalwart nicht konfiguriert');
            return null;
        }

        $config = $this->configService->getStalwartConfig();
        $url = rtrim($config['url'], '/') . $endpoint;

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_USERPWD => $config['user'] . ':' . $config['password'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

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
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error('SharedMailboxService: cURL-Fehler', [
                'url' => $url,
                'method' => $method,
                'error' => $error
            ]);
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->logger->warning('SharedMailboxService: HTTP-Fehler', [
                'url' => $url,
                'method' => $method,
                'httpCode' => $httpCode
            ]);
            return null;
        }

        $decoded = json_decode($response, true);
        return $decoded ?? [];
    }
}
