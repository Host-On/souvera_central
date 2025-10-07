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

class UserApiController extends OCSController {
    private $userManager;
    private $groupManager;
    private $config;
    private $logger;
    private $configService;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserManager $userManager,
        IGroupManager $groupManager,
        IConfig $config,
        LoggerInterface $logger,
        ConfigService $configService
    ) {
        parent::__construct($appName, $request);
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->config = $config;
        $this->logger = $logger;
        $this->configService = $configService;
    }

    /**
     * Liste aller Benutzer abrufen
     *
     * @NoAdminRequired
     */
    public function list(): DataResponse {
        $this->logger->info('UserApiController::list() aufgerufen');

        try {
            $users = [];
            $this->logger->info('Suche nach Benutzern...');
            $allUsers = $this->userManager->search('');
            $this->logger->info('Gefundene Benutzer: ' . count($allUsers));

            foreach ($allUsers as $user) {
                $userId = $user->getUID();
                $this->logger->debug('Verarbeite Benutzer: ' . $userId);

                $userData = [
                    'id' => $userId,
                    'displayName' => $user->getDisplayName(),
                    'email' => $user->getEMailAddress() ?? '',
                    'enabled' => $user->isEnabled(),
                    'lastLogin' => $user->getLastLogin(),
                    'quota' => $this->getUserQuota($userId),
                    'groups' => $this->getUserGroups($userId),
                ];
                $users[] = $userData;
            }

            $this->logger->info('Rückgabe von ' . count($users) . ' Benutzern');

            return new DataResponse([
                'users' => $users,
                'total' => count($users)
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
     *
     * @NoAdminRequired
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
     * Neuen Benutzer erstellen
     *
     * @NoAdminRequired
     */
    public function create(string $username = '', string $displayName = '', string $email = '', string $password = '', array $groups = [], string $quota = 'default', bool $enabled = true): DataResponse {
        error_log('=== UserApiController::create() START ===');
        error_log('Empfangene Parameter: username=' . $username . ', displayName=' . $displayName . ', email=' . $email . ', groups=' . json_encode($groups));

        $this->logger->info('UserApiController::create() aufgerufen für User: ' . $username);

        try {
            // Debug: Alle POST-Daten loggen
            $postData = file_get_contents('php://input');
            error_log('POST body: ' . $postData);

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
     *
     * @NoAdminRequired
     */
    public function update(string $id, ?string $displayName = null, ?string $email = null, ?array $groups = null, ?string $quota = null, ?bool $enabled = null): DataResponse {
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
                $user->setEMailAddress($email);
                $this->logger->debug('E-Mail aktualisiert: ' . $email);
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
     *
     * @NoAdminRequired
     */
    public function delete(string $id): DataResponse {
        $this->logger->info('UserApiController::delete() aufgerufen für User: ' . $id);

        try {
            $user = $this->userManager->get($id);

            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Verhindere Löschen des eigenen Accounts
            // TODO: Aktuellen User prüfen

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
     * Config-Informationen abrufen
     *
     * @NoAdminRequired
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
     *
     * @NoAdminRequired
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
     *
     * @NoAdminRequired
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
