<?php
/**
 * Souvera Central - User Management Module - API Controller
 *
 * API-Endpunkte für Benutzerverwaltung
 */

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use OCA\SouveraCentral\Service\ConfigService;
use OCP\IUserSession;

class UserApiController extends OCSController {
    private $userManager;
    private $groupManager;
    private $config;
    private $logger;
    private $configService;
    private $userSession;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserManager $userManager,
        IGroupManager $groupManager,
        IConfig $config,
        LoggerInterface $logger,
        ConfigService $configService,
        IUserSession $userSession
    ) {
        parent::__construct($appName, $request);
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->config = $config;
        $this->logger = $logger;
        $this->configService = $configService;
        $this->userSession = $userSession;
    }

    /**
     * Liste aller Benutzer abrufen mit Suche und Pagination
     *
     * @param string $search Suchbegriff für Username, Displayname oder E-Mail
     * @param int $limit Anzahl der Ergebnisse pro Seite (Standard: 20)
     * @param int $offset Start-Offset für Pagination (Standard: 0)
     */
    public function list(string $search = '', int $limit = 20, int $offset = 0): DataResponse {
        try {
            // Alle Benutzer durchsuchen (Nextcloud UserManager hat keine native Pagination)
            $searchTerm = trim($search);
            $allUsers = $this->userManager->search($searchTerm);

            $allUsersData = [];
            foreach ($allUsers as $user) {
                $userId = $user->getUID();
                $displayName = $user->getDisplayName();
                $email = $user->getEMailAddress() ?? '';

                // Zusätzlicher Filter: Suche in Username, Displayname und E-Mail
                if (!empty($searchTerm)) {
                    $searchLower = mb_strtolower($searchTerm);
                    $userIdLower = mb_strtolower($userId);
                    $displayNameLower = mb_strtolower($displayName);
                    $emailLower = mb_strtolower($email);

                    // Überspringe User, die nicht matchen
                    if (
                        strpos($userIdLower, $searchLower) === false &&
                        strpos($displayNameLower, $searchLower) === false &&
                        strpos($emailLower, $searchLower) === false
                    ) {
                        continue;
                    }
                }

                $userData = [
                    'id' => $userId,
                    'displayName' => $displayName,
                    'email' => $email,
                    'enabled' => $user->isEnabled(),
                    'lastLogin' => $user->getLastLogin(),
                    'quota' => $this->getUserQuota($userId),
                    'groups' => $this->getUserGroups($userId),
                ];
                $allUsersData[] = $userData;
            }

            // Total count ALLER Benutzer (tatsächliche Anzahl)
            $totalCount = $this->getTotalUserCount();

            // Pagination anwenden
            $paginatedUsers = array_slice($allUsersData, $offset, $limit);

            return new DataResponse([
                'users' => $paginatedUsers,
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => ($offset + $limit) < $totalCount
            ]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Einzelnen Benutzer abrufen
     */
    public function get(string $id): DataResponse {
        try {
            $user = $this->userManager->get($id);

            if ($user === null) {
                return new DataResponse(
                    ['error' => 'User not found'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $userData = [
                'id' => $user->getUID(),
                'displayName' => $user->getDisplayName(),
                'email' => $user->getEMailAddress() ?? '',
                'enabled' => $user->isEnabled(),
                'lastLogin' => $user->getLastLogin(),
                'quota' => $this->getUserQuota($id),
                'groups' => $this->getUserGroups($id),
                'manager' => $this->config->getUserValue($id, 'souvera_central', 'manager', ''),
            ];

            return new DataResponse($userData);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Benutzer suchen (für Autocomplete)
     */
    public function search(string $query = '', int $limit = 10): DataResponse {
        try {
            if (empty($query) || strlen($query) < 2) {
                return new DataResponse([
                    'users' => []
                ]);
            }

            // Benutzer durchsuchen
            $users = $this->userManager->search($query, $limit);
            $results = [];

            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->getUID(),
                    'displayName' => $user->getDisplayName(),
                    'email' => $user->getEMailAddress() ?? ''
                ];
            }

            return new DataResponse([
                'users' => $results
            ]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Neuen Benutzer erstellen
     */
    public function create(string $username = '', string $displayName = '', string $email = '', string $password = '', array $groups = [], string $quota = 'default', bool $enabled = true, string $manager = ''): DataResponse {
        try {
            // Debug: Alle POST-Daten loggen
            $postData = file_get_contents('php://input');

            // USERNAME/EMAIL-SYNC: Username automatisch aus Email setzen
            // Grund: Stalwart Mail-Server benötigt Username = Email für IMAP-Login
            if (!empty($email)) {
                $username = $email;
            }

            // Validierung
            if (empty($username) || empty($displayName) || empty($email) || empty($password)) {
                return new DataResponse(
                    ['error' => 'Pflichtfelder fehlen', 'debug' => [
                        'username_empty' => empty($username),
                        'displayName_empty' => empty($displayName),
                        'email_empty' => empty($email),
                        'password_empty' => empty($password)
                    ]],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Validierung: Username muss Email entsprechen (doppelte Absicherung)
            if ($username !== $email) {
                return new DataResponse(
                    ['error' => 'Username und Email müssen identisch sein (erforderlich für Mail-Server Integration)'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Lizenz-Limit prüfen
            // Geschäftslogik: max_licenses + 1 Gratis-Lizenz = Maximale Benutzeranzahl
            $maxLicenses = $this->configService->getMaxLicenses();
            $currentUserCount = $this->getTotalUserCount();
            $maxAllowedUsers = $maxLicenses + 1;

            if ($currentUserCount >= $maxAllowedUsers) {
                return new DataResponse(
                    ['error' => 'Lizenzlimit erreicht. Es können keine weiteren Benutzer erstellt werden.'],
                    Http::STATUS_CONFLICT
                );
            }

            // E-Mail-Domain validieren
            if (!$this->configService->isEmailDomainAllowed($email)) {
                $allowedDomains = $this->configService->getAllowedDomains();
                return new DataResponse(
                    ['error' => 'E-Mail-Domain nicht erlaubt. Erlaubte Domains: ' . implode(', ', $allowedDomains)],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Prüfen ob User mit diesem Username bereits existiert
            if ($this->userManager->get($username) !== null) {
                return new DataResponse(
                    ['error' => 'Ein Benutzer mit dieser E-Mail-Adresse existiert bereits.'],
                    Http::STATUS_CONFLICT
                );
            }

            // Zusätzliche Prüfung: Suche nach Usern mit dieser Email (falls Username != Email)
            $existingUsers = $this->userManager->search('');
            foreach ($existingUsers as $existingUser) {
                if ($existingUser->getEMailAddress() === $email) {
                    return new DataResponse(
                        ['error' => 'Ein Benutzer mit dieser E-Mail-Adresse existiert bereits.'],
                        Http::STATUS_CONFLICT
                    );
                }
            }

            // User erstellen
            $user = $this->userManager->createUser($username, $password);

            if ($user === false || $user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer konnte nicht erstellt werden - UserManager failed'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            // Anzeigename setzen
            $user->setDisplayName($displayName);

            // E-Mail setzen
            $user->setEMailAddress($email);

            // Quota setzen
            $this->config->setUserValue($username, 'files', 'quota', $quota);

            // Benutzer aktivieren/deaktivieren
            $user->setEnabled($enabled);

            // Gruppen zuweisen
            foreach ($groups as $groupId) {
                $group = $this->groupManager->get($groupId);
                if ($group !== null) {
                    $group->addUser($user);
                }
            }

            // Manager setzen
            if (!empty($manager)) {
                $this->config->setUserValue($username, 'souvera_central', 'manager', $manager);
            }

            return new DataResponse([
                'success' => true,
                'user' => [
                    'id' => $user->getUID(),
                    'displayName' => $user->getDisplayName(),
                    'email' => $user->getEMailAddress(),
                    'enabled' => $user->isEnabled(),
                    'quota' => $this->getUserQuota($username),
                    'groups' => $this->getUserGroups($username),
                ]
            ], Http::STATUS_CREATED);

        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Benutzer aktualisieren
     */
    public function update(string $id, ?string $displayName = null, ?string $email = null, ?array $groups = null, ?string $quota = null, ?bool $enabled = null, ?string $manager = null, ?string $password = null): DataResponse {
        try {
            $user = $this->userManager->get($id);

            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Anzeigename aktualisieren
            if ($displayName !== null) {
                $user->setDisplayName($displayName);
            }

            // E-Mail aktualisieren
            // WICHTIG: E-Mail-Adresse kann nach Erstellung NICHT geändert werden
            if ($email !== null) {
                // USERNAME/EMAIL-SYNC: Email-Änderung blockieren
                // Grund: Username kann in Nextcloud nicht geändert werden, daher muss Email locked sein
                $currentEmail = $user->getEMailAddress();
                if ($email !== $currentEmail) {
                    return new DataResponse(
                        ['error' => 'E-Mail-Adresse kann nach der Erstellung nicht geändert werden'],
                        Http::STATUS_BAD_REQUEST
                    );
                }
                // Falls Email identisch ist, ignorieren (no-op)
                // Setzen wird übersprungen um unnötige Operationen zu vermeiden
            }

            // Quota aktualisieren
            if ($quota !== null) {
                $this->config->setUserValue($id, 'files', 'quota', $quota);
            }

            // Status aktualisieren
            if ($enabled !== null) {
                $user->setEnabled($enabled);
            }

            // Gruppen aktualisieren
            if ($groups !== null) {
                // Entferne User aus allen aktuellen Gruppen
                $currentGroups = $this->groupManager->getUserGroups($user);
                foreach ($currentGroups as $group) {
                    // Verhindere Entfernung von Admin-User aus "admin" Gruppe
                    if ($this->configService->isAdminUser($id) && $group->getGID() === 'admin') {
                        continue;
                    }
                    $group->removeUser($user);
                }

                // Füge User zu neuen Gruppen hinzu
                foreach ($groups as $groupId) {
                    $group = $this->groupManager->get($groupId);
                    if ($group !== null) {
                        $group->addUser($user);
                    }
                }

                // Stelle sicher, dass Admin-User immer in "admin" Gruppe ist
                if ($this->configService->isAdminUser($id)) {
                    $adminGroup = $this->groupManager->get('admin');
                    if ($adminGroup !== null && !$adminGroup->inGroup($user)) {
                        $adminGroup->addUser($user);
                    }
                }
            }

            // Manager aktualisieren
            if ($manager !== null) {
                if (empty($manager)) {
                    $this->config->deleteUserValue($id, 'souvera_central', 'manager');
                } else {
                    $this->config->setUserValue($id, 'souvera_central', 'manager', $manager);
                }
            }

            // Passwort aktualisieren (optional)
            if ($password !== null && !empty($password)) {
                // Passwort-Validierung: Mindestens 10 Zeichen
                if (strlen($password) < 10) {
                    return new DataResponse(
                        ['error' => 'Passwort muss mindestens 10 Zeichen lang sein'],
                        Http::STATUS_BAD_REQUEST
                    );
                }

                // Passwort setzen
                $user->setPassword($password);
            }

            return new DataResponse([
                'success' => true,
                'user' => [
                    'id' => $user->getUID(),
                    'displayName' => $user->getDisplayName(),
                    'email' => $user->getEMailAddress(),
                    'enabled' => $user->isEnabled(),
                    'quota' => $this->getUserQuota($id),
                    'groups' => $this->getUserGroups($id),
                ]
            ]);

        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Benutzer löschen
     */
    public function delete(string $id): DataResponse {
        try {
            // Prüfe ob versucht wird den Admin-User zu löschen
            if ($this->configService->isAdminUser($id)) {
                return new DataResponse(
                    ['error' => 'Der Administrator-Account kann nicht gelöscht werden.'],
                    Http::STATUS_FORBIDDEN
                );
            }

            // Prüfe ob User versucht sich selbst zu löschen
            $currentUser = $this->userSession->getUser();
            if ($currentUser !== null && $currentUser->getUID() === $id) {
                return new DataResponse(
                    ['error' => 'Sie können Ihr eigenes Konto nicht löschen. Bitte wenden Sie sich an einen anderen Administrator.'],
                    Http::STATUS_FORBIDDEN
                );
            }

            $user = $this->userManager->get($id);

            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $success = $user->delete();

            if (!$success) {
                return new DataResponse(
                    ['error' => 'Benutzer konnte nicht gelöscht werden'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            return new DataResponse(['success' => true]);

        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Benutzer aktivieren
     */
    public function enable(string $id): DataResponse {
        try {
            $user = $this->userManager->get($id);

            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $user->setEnabled(true);

            return new DataResponse([
                'success' => true,
                'enabled' => true
            ]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Benutzer deaktivieren
     */
    public function disable(string $id): DataResponse {
        try {
            // Prüfe ob versucht wird den Admin-User zu deaktivieren
            if ($this->configService->isAdminUser($id)) {
                return new DataResponse(
                    ['error' => 'Der Administrator-Account kann nicht deaktiviert werden.'],
                    Http::STATUS_FORBIDDEN
                );
            }

            // Prüfe ob User versucht sich selbst zu deaktivieren
            $currentUser = $this->userSession->getUser();
            if ($currentUser !== null && $currentUser->getUID() === $id) {
                return new DataResponse(
                    ['error' => 'Sie können sich nicht selbst deaktivieren. Bitte wenden Sie sich an einen anderen Administrator.'],
                    Http::STATUS_FORBIDDEN
                );
            }

            $user = $this->userManager->get($id);

            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $user->setEnabled(false);

            return new DataResponse([
                'success' => true,
                'enabled' => false
            ]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Alle Geräte trennen und lokale Daten löschen (Wipe Devices)
     */
    public function wipeDevices(string $id): DataResponse {
        try {
            $user = $this->userManager->get($id);

            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Alle Auth-Tokens des Benutzers löschen (Sessions, App-Passwörter, etc.)
            try {
                // Versuche Token Provider (benötigt IUser-Objekt, nicht nur UID)
                $tokenProvider = \OC::$server->get(\OCP\Authentication\Token\IProvider::class);
                $tokenProvider->invalidateTokensOfUser($user->getUID(), $user->getUID());
            } catch (\Exception $e) {
                // Fallback: Versuche direkt über DB alle Sessions zu löschen
                try {
                    $connection = \OC::$server->getDatabaseConnection();
                    $qb = $connection->getQueryBuilder();
                    $qb->delete('authtoken')
                        ->where($qb->expr()->eq('uid', $qb->createNamedParameter($user->getUID())))
                        ->executeStatement();
                } catch (\Exception $e2) {
                    // Nicht werfen, da wir trotzdem als "erfolgreich" behandeln
                }
            }

            return new DataResponse([
                'success' => true,
                'message' => 'Alle Geräte wurden getrennt und lokale Daten gelöscht'
            ]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => 'Fehler beim Trennen der Geräte: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Willkommens-Email erneut versenden
     */
    public function resendWelcomeEmail(string $id): DataResponse {
        try {
            $user = $this->userManager->get($id);

            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $email = $user->getEMailAddress();
            if (empty($email)) {
                return new DataResponse(
                    ['error' => 'Benutzer hat keine E-Mail-Adresse'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Mailer und Defaults-Service laden
            $mailer = \OC::$server->getMailer();
            $defaults = \OC::$server->query(\OCP\Defaults::class);

            // E-Mail-Template erstellen
            $message = $mailer->createMessage();
            $message->setTo([$email => $user->getDisplayName()]);
            $message->setSubject('Willkommen bei ' . $defaults->getName());

            $emailText = "Hallo " . $user->getDisplayName() . ",\n\n";
            $emailText .= "Willkommen bei " . $defaults->getName() . "!\n\n";
            $emailText .= "Ihr Benutzername: " . $user->getUID() . "\n";
            $emailText .= "Login-URL: " . $defaults->getBaseUrl() . "\n\n";
            $emailText .= "Viel Erfolg!\n";
            $emailText .= "Ihr " . $defaults->getName() . " Team";

            $message->setBody($emailText, 'text/plain');

            // E-Mail versenden
            $mailer->send($message);

            return new DataResponse([
                'success' => true,
                'message' => 'Willkommens-Email wurde erneut versendet'
            ]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => 'E-Mail konnte nicht versendet werden: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Aktuellen Benutzer abrufen
     */
    public function getCurrentUser(): DataResponse {
        try {
            $currentUser = $this->userSession->getUser();

            if ($currentUser === null) {
                return new DataResponse(
                    ['error' => 'Kein Benutzer angemeldet'],
                    Http::STATUS_UNAUTHORIZED
                );
            }

            return new DataResponse([
                'id' => $currentUser->getUID(),
                'displayName' => $currentUser->getDisplayName(),
                'email' => $currentUser->getEMailAddress() ?? ''
            ]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Config-Informationen abrufen
     */
    public function getConfig(): DataResponse {
        try {
            return new DataResponse([
                'total_users' => $this->getTotalUserCount(),
                'used_licenses' => $this->getUsedLicenseCount(),
                'max_licenses' => $this->configService->getMaxLicenses(),
                'allowed_domains' => $this->configService->getAllowedDomains(),
                // Neue Limit-Felder
                'max_shared_mailboxes' => $this->configService->getMaxSharedMailboxes(),
                'max_groups' => $this->configService->getMaxGroups(),
                'max_aliases_per_user' => $this->configService->getMaxAliasesPerUser(),
                'warning_threshold' => $this->configService->getWarningThreshold(),
            ]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Debug-Endpoint für Troubleshooting
     */
    public function debug(): DataResponse {
        $debugInfo = [
            'endpoint_reached' => true,
            'php_version' => phpversion(),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'post_data' => file_get_contents('php://input'),
            'get_params' => $_GET,
            'post_params' => $_POST,
            'user_manager_exists' => $this->userManager !== null,
            'group_manager_exists' => $this->groupManager !== null,
            'logger_exists' => $this->logger !== null,
            'config' => [
                'max_licenses' => $this->configService->getMaxLicenses(),
                'allowed_domains' => $this->configService->getAllowedDomains(),
            ],
        ];

        return new DataResponse($debugInfo);
    }

    /**
     * Liste aller Gruppen abrufen
     */
    public function listGroups(): DataResponse {
        try {
            $allGroups = $this->groupManager->search('');
            $groups = [];

            foreach ($allGroups as $group) {
                $groups[] = [
                    'id' => $group->getGID(),
                    'displayName' => $group->getDisplayName(),
                    'userCount' => $group->count()
                ];
            }

            return new DataResponse([
                'groups' => $groups,
                'total' => count($groups)
            ]);

        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Benutzer-Quota ermitteln
     */
    private function getUserQuota(string $userId): array {
        $quota = $this->config->getUserValue($userId, 'files', 'quota', 'default');

        return [
            'quota' => $quota,
            'used' => 0, // TODO: Tatsächlichen Speicher berechnen
            'relative' => 0
        ];
    }

    /**
     * Benutzergruppen ermitteln
     */
    private function getUserGroups(string $userId): array {
        $groups = $this->groupManager->getUserGroups(
            $this->userManager->get($userId)
        );

        return array_map(function($group) {
            return [
                'id' => $group->getGID(),
                'displayName' => $group->getDisplayName()
            ];
        }, $groups);
    }

    /**
     * Tatsächliche Benutzeranzahl ermitteln (alle Benutzer inkl. Admin)
     *
     * @return int Anzahl aller Benutzer
     */
    private function getTotalUserCount(): int {
        $allUsers = $this->userManager->search('');
        return count($allUsers);
    }

    /**
     * Genutzte Lizenzen ermitteln
     *
     * Geschäftslogik: 1 Lizenz ist immer kostenlos inkludiert (für Admin).
     * Genutzte Lizenzen = Gesamtanzahl Benutzer - 1
     *
     * @return int Anzahl der genutzten Lizenzen
     */
    private function getUsedLicenseCount(): int {
        return max(0, $this->getTotalUserCount() - 1);
    }
}
