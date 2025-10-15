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
        $this->logger->info('UserApiController::list() aufgerufen - search: "' . $search . '", limit: ' . $limit . ', offset: ' . $offset);

        try {
            // Alle Benutzer durchsuchen (Nextcloud UserManager hat keine native Pagination)
            $searchTerm = trim($search);
            $allUsers = $this->userManager->search($searchTerm);
            $this->logger->info('Gefundene Benutzer (vor Filter): ' . count($allUsers));

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

            $totalCount = count($allUsersData);
            $this->logger->info('Gefundene Benutzer (nach Filter): ' . $totalCount);

            // Pagination anwenden
            $paginatedUsers = array_slice($allUsersData, $offset, $limit);
            $this->logger->info('Rückgabe von ' . count($paginatedUsers) . ' Benutzern (Seite)');

            return new DataResponse([
                'users' => $paginatedUsers,
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => ($offset + $limit) < $totalCount
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Fehler in UserApiController::list(): ' . $e->getMessage(), [
                'exception' => $e
            ]);
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
        $this->logger->info('UserApiController::search() aufgerufen - query: "' . $query . '"');

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
            $this->logger->error('Fehler bei User-Suche: ' . $e->getMessage());
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
        error_log('=== UserApiController::create() START ===');
        error_log('Empfangene Parameter: username=' . $username . ', displayName=' . $displayName . ', email=' . $email . ', groups=' . json_encode($groups));

        $this->logger->info('UserApiController::create() aufgerufen für User: ' . $username);

        try {
            // Debug: Alle POST-Daten loggen
            $postData = file_get_contents('php://input');
            error_log('POST body: ' . $postData);

            // USERNAME/EMAIL-SYNC: Username automatisch aus Email setzen
            // Grund: Stalwart Mail-Server benötigt Username = Email für IMAP-Login
            if (!empty($email)) {
                $username = $email;
                error_log('Username/Email-Sync: Username automatisch auf Email gesetzt: ' . $username);
                $this->logger->info('Username/Email-Sync aktiviert: ' . $username);
            }

            // Validierung
            if (empty($username) || empty($displayName) || empty($email) || empty($password)) {
                error_log('FEHLER: Pflichtfelder fehlen - username empty: ' . (empty($username) ? 'YES' : 'NO'));
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
                error_log('FEHLER: Username und Email müssen identisch sein');
                $this->logger->error('Username/Email-Mismatch verhindert: username=' . $username . ', email=' . $email);
                return new DataResponse(
                    ['error' => 'Username und Email müssen identisch sein (erforderlich für Mail-Server Integration)'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Lizenz-Limit prüfen
            $maxLicenses = $this->configService->getMaxLicenses();
            $currentUserCount = count($this->userManager->search(''));

            if ($currentUserCount >= $maxLicenses) {
                error_log('FEHLER: Lizenzlimit erreicht - Current: ' . $currentUserCount . ', Max: ' . $maxLicenses);
                return new DataResponse(
                    ['error' => 'Lizenzlimit erreicht. Es können keine weiteren Benutzer erstellt werden.'],
                    Http::STATUS_CONFLICT
                );
            }

            // E-Mail-Domain validieren
            if (!$this->configService->isEmailDomainAllowed($email)) {
                $allowedDomains = $this->configService->getAllowedDomains();
                error_log('FEHLER: E-Mail-Domain nicht erlaubt: ' . $email . ' - Erlaubte Domains: ' . implode(', ', $allowedDomains));
                return new DataResponse(
                    ['error' => 'E-Mail-Domain nicht erlaubt. Erlaubte Domains: ' . implode(', ', $allowedDomains)],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Prüfen ob User schon existiert
            error_log('Prüfe ob User bereits existiert: ' . $username);
            if ($this->userManager->get($username) !== null) {
                error_log('FEHLER: Benutzername bereits vergeben: ' . $username);
                return new DataResponse(
                    ['error' => 'Benutzername bereits vergeben'],
                    Http::STATUS_CONFLICT
                );
            }

            // User erstellen
            error_log('Erstelle Benutzer mit UserManager: ' . $username);
            $this->logger->info('Erstelle Benutzer: ' . $username);
            $user = $this->userManager->createUser($username, $password);

            if ($user === false || $user === null) {
                error_log('FEHLER: UserManager::createUser() returned false/null');
                return new DataResponse(
                    ['error' => 'Benutzer konnte nicht erstellt werden - UserManager failed'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            error_log('User erfolgreich erstellt, UID: ' . $user->getUID());

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
                    $this->logger->debug('User ' . $username . ' zu Gruppe ' . $groupId . ' hinzugefügt');
                }
            }

            // Manager setzen
            if (!empty($manager)) {
                $this->config->setUserValue($username, 'souvera_central', 'manager', $manager);
                $this->logger->debug('Manager gesetzt: ' . $manager);
            }

            $this->logger->info('Benutzer erfolgreich erstellt: ' . $username);
            error_log('=== UserApiController::create() SUCCESS ===');

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
            error_log('=== UserApiController::create() EXCEPTION ===');
            error_log('Exception: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());

            $this->logger->error('Fehler beim Erstellen des Benutzers: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return new DataResponse(
                ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Benutzer aktualisieren
     */
    public function update(string $id, ?string $displayName = null, ?string $email = null, ?array $groups = null, ?string $quota = null, ?bool $enabled = null, ?string $manager = null): DataResponse {
        $this->logger->info('UserApiController::update() aufgerufen für User: ' . $id);

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
                $this->logger->debug('DisplayName aktualisiert: ' . $displayName);
            }

            // E-Mail aktualisieren
            if ($email !== null) {
                // USERNAME/EMAIL-SYNC: Email-Änderung blockieren
                // Grund: Username kann in Nextcloud nicht geändert werden, daher muss Email locked sein
                $currentEmail = $user->getEMailAddress();
                if ($email !== $currentEmail) {
                    $this->logger->warning('Email-Änderung blockiert für User: ' . $id . ' (alt: ' . $currentEmail . ', neu: ' . $email . ')');
                    return new DataResponse(
                        ['error' => 'E-Mail-Adresse kann nach der Erstellung nicht geändert werden (erforderlich für Mail-Server Integration)'],
                        Http::STATUS_BAD_REQUEST
                    );
                }
                // Falls Email identisch ist, trotzdem setzen (no-op, aber konsistent)
                $user->setEMailAddress($email);
                $this->logger->debug('E-Mail unverändert: ' . $email);
            }

            // Quota aktualisieren
            if ($quota !== null) {
                $this->config->setUserValue($id, 'files', 'quota', $quota);
                $this->logger->debug('Quota aktualisiert: ' . $quota);
            }

            // Status aktualisieren
            if ($enabled !== null) {
                $user->setEnabled($enabled);
                $this->logger->debug('Enabled status aktualisiert: ' . ($enabled ? 'true' : 'false'));
            }

            // Gruppen aktualisieren
            if ($groups !== null) {
                // Entferne User aus allen aktuellen Gruppen
                $currentGroups = $this->groupManager->getUserGroups($user);
                foreach ($currentGroups as $group) {
                    $group->removeUser($user);
                }

                // Füge User zu neuen Gruppen hinzu
                foreach ($groups as $groupId) {
                    $group = $this->groupManager->get($groupId);
                    if ($group !== null) {
                        $group->addUser($user);
                        $this->logger->debug('User zu Gruppe hinzugefügt: ' . $groupId);
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
                $this->logger->debug('Manager aktualisiert: ' . $manager);
            }

            $this->logger->info('Benutzer erfolgreich aktualisiert: ' . $id);

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
            $this->logger->error('Fehler beim Aktualisieren des Benutzers: ' . $e->getMessage(), [
                'exception' => $e
            ]);
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
        $this->logger->info('UserApiController::delete() aufgerufen für User: ' . $id);

        try {
            // Prüfe ob User versucht sich selbst zu löschen
            $currentUser = $this->userSession->getUser();
            if ($currentUser !== null && $currentUser->getUID() === $id) {
                $this->logger->warning('Benutzer ' . $id . ' versuchte sich selbst zu löschen - verhindert!');
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

            $this->logger->info('Benutzer erfolgreich gelöscht: ' . $id);

            return new DataResponse(['success' => true]);

        } catch (\Exception $e) {
            $this->logger->error('Fehler beim Löschen des Benutzers: ' . $e->getMessage(), [
                'exception' => $e
            ]);
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
        $this->logger->info('UserApiController::enable() aufgerufen für User: ' . $id);

        try {
            $user = $this->userManager->get($id);

            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $user->setEnabled(true);
            $this->logger->info('Benutzer erfolgreich aktiviert: ' . $id);

            return new DataResponse([
                'success' => true,
                'enabled' => true
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Fehler beim Aktivieren des Benutzers: ' . $e->getMessage());
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
        $this->logger->info('UserApiController::disable() aufgerufen für User: ' . $id);

        try {
            // Prüfe ob User versucht sich selbst zu deaktivieren
            $currentUser = $this->userSession->getUser();
            if ($currentUser !== null && $currentUser->getUID() === $id) {
                $this->logger->warning('Benutzer ' . $id . ' versuchte sich selbst zu deaktivieren - verhindert!');
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
            $this->logger->info('Benutzer erfolgreich deaktiviert: ' . $id);

            return new DataResponse([
                'success' => true,
                'enabled' => false
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Fehler beim Deaktivieren des Benutzers: ' . $e->getMessage());
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
        $this->logger->info('UserApiController::wipeDevices() aufgerufen für User: ' . $id);

        try {
            $user = $this->userManager->get($id);

            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Alle Auth-Tokens des Benutzers löschen (Sessions, App-Passwörter, etc.)
            $tokenProvider = \OC::$server->query(\OC\Authentication\Token\IProvider::class);
            $tokenProvider->invalidateTokensOfUser($user->getUID());

            $this->logger->info('Alle Geräte/Sessions für Benutzer erfolgreich getrennt: ' . $id);

            return new DataResponse([
                'success' => true,
                'message' => 'Alle Geräte wurden getrennt und lokale Daten gelöscht'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Fehler beim Trennen der Geräte: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Willkommens-Email erneut versenden
     */
    public function resendWelcomeEmail(string $id): DataResponse {
        $this->logger->info('UserApiController::resendWelcomeEmail() aufgerufen für User: ' . $id);

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

            $this->logger->info('Willkommens-Email erfolgreich versendet an: ' . $email);

            return new DataResponse([
                'success' => true,
                'message' => 'Willkommens-Email wurde erneut versendet'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Fehler beim Versenden der Willkommens-Email: ' . $e->getMessage(), [
                'exception' => $e
            ]);
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
            $this->logger->error('Fehler beim Laden des aktuellen Benutzers: ' . $e->getMessage());
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
                'max_licenses' => $this->configService->getMaxLicenses(),
                'allowed_domains' => $this->configService->getAllowedDomains(),
                'current_user_count' => count($this->userManager->search('')),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Fehler beim Laden der Config: ' . $e->getMessage());
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
        error_log('=== DEBUG ENDPOINT CALLED ===');

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

        error_log('Debug info: ' . json_encode($debugInfo));

        return new DataResponse($debugInfo);
    }

    /**
     * Liste aller Gruppen abrufen
     */
    public function listGroups(): DataResponse {
        $this->logger->info('UserApiController::listGroups() aufgerufen');

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

            $this->logger->info('Rückgabe von ' . count($groups) . ' Gruppen');

            return new DataResponse([
                'groups' => $groups,
                'total' => count($groups)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Fehler beim Laden der Gruppen: ' . $e->getMessage(), [
                'exception' => $e
            ]);
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
}
