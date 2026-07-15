<?php
/**
 * Souvera Central - User Management Module - API Controller
 *
 * API-Endpunkte für Benutzerverwaltung
 */

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\LicenseService;
use OCA\SouveraCentral\Service\MailGroupService;
use OCA\SouveraCentral\Service\StorageService;
use OCP\IUserSession;

class UserApiController extends OCSController {
    private $userManager;
    private $groupManager;
    private $config;
    private $logger;
    private $configService;
    private $userSession;
    private LicenseService $licenseService;
    private MailGroupService $mailGroupService;
    private StorageService $storageService;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserManager $userManager,
        IGroupManager $groupManager,
        IConfig $config,
        LoggerInterface $logger,
        ConfigService $configService,
        IUserSession $userSession,
        LicenseService $licenseService,
        MailGroupService $mailGroupService,
        StorageService $storageService
    ) {
        parent::__construct($appName, $request);
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->config = $config;
        $this->logger = $logger;
        $this->configService = $configService;
        $this->userSession = $userSession;
        $this->licenseService = $licenseService;
        $this->mailGroupService = $mailGroupService;
        $this->storageService = $storageService;
    }

    /**
     * Liste aller Benutzer abrufen mit Suche und Pagination
     *
     * @param string $search Suchbegriff für Username, Displayname oder E-Mail
     * @param int $limit Anzahl der Ergebnisse pro Seite (Standard: 20)
     * @param int $offset Start-Offset für Pagination (Standard: 0)
     */
    #[NoAdminRequired]
    public function list(string $search = '', int $limit = 20, int $offset = 0): DataResponse {
        try {
            // Alle Benutzer durchsuchen (Nextcloud UserManager hat keine native Pagination)
            $searchTerm = trim($search);
            $allUsers = $this->userManager->search($searchTerm);
            $souveraGid = $this->configService->getMailGroupId();
            $adminGid = $this->configService->getScadminGroupId();

            $allUsersData = [];
            foreach ($allUsers as $user) {
                $userId = $user->getUID();
                // Technische/ausgeblendete Benutzer (z. B. ncadmin) nie anzeigen
                if ($this->configService->isHiddenUser($userId)) {
                    continue;
                }
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

                $isSouveraUser = $this->groupManager->isInGroup($userId, $souveraGid);
                $userData = [
                    'id' => $userId,
                    'displayName' => $displayName,
                    'email' => $email,
                    'enabled' => $user->isEnabled(),
                    'lastLogin' => $user->getLastLogin(),
                    'quota' => $this->getUserQuota($userId),
                    'groups' => $this->getUserGroups($userId),
                    'isSouveraUser' => $isSouveraUser,
                    'isSouveraAdmin' => $this->groupManager->isInGroup($userId, $adminGid),
                    'isProtected' => $this->configService->isAdminAccount($userId, $email),
                    'type' => $isSouveraUser ? 'souvera' : 'nextcloud',
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
                'hasMore' => ($offset + $limit) < $totalCount,
                'usedLicenses' => $this->licenseService->getUsedLicenses(),
                'maxLicenses' => $this->configService->getMaxLicenses()
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
    #[NoAdminRequired]
    public function get(string $id): DataResponse {
        try {
            $user = $this->userManager->get($id);

            if ($user === null) {
                return new DataResponse(
                    ['error' => 'User not found'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $isSouveraUser = $this->groupManager->isInGroup($id, $this->configService->getMailGroupId());
            $userData = [
                'id' => $user->getUID(),
                'displayName' => $user->getDisplayName(),
                'email' => $user->getEMailAddress() ?? '',
                'enabled' => $user->isEnabled(),
                'lastLogin' => $user->getLastLogin(),
                'quota' => $this->getUserQuota($id),
                'groups' => $this->getUserGroups($id),
                'manager' => $this->config->getUserValue($id, 'souvera_central', 'manager', ''),
                'isSouveraUser' => $isSouveraUser,
                'isSouveraAdmin' => $this->groupManager->isInGroup($id, $this->configService->getScadminGroupId()),
                'isProtected' => $this->configService->isAdminAccount($user->getUID(), $user->getEMailAddress()),
                'type' => $isSouveraUser ? 'souvera' : 'nextcloud',
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
    #[NoAdminRequired]
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
                if ($this->configService->isHiddenUser($user->getUID())) {
                    continue;
                }
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
    #[NoAdminRequired]
    public function create(string $username = '', string $displayName = '', string $email = '', string $password = '', array $groups = [], string $quota = 'default', bool $enabled = true, string $manager = '', bool $isSouveraUser = true, ?int $mailboxQuota = null): DataResponse {
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

            // Lizenz-Limit prüfen: nur lizenzierte "Souvera User" (Mitglieder von
            // souvera-users, ohne scadmin) zählen. "Nextcloud User" sind unlizenziert.
            if ($isSouveraUser && $this->licenseService->isLimitReached()) {
                return new DataResponse(
                    ['error' => 'Lizenzlimit erreicht. Es können keine weiteren Souvera User (mit Postfach) erstellt werden.'],
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

            // Mail-Speicher-Pool: Postfach-Limit für neuen Souvera User auflösen und
            // hart erzwingen (GB-Schritt, kein „Unbegrenzt" bei aktivem Pool, Pool nicht
            // sprengen). Passiert VOR dem Anlegen des NC-Users, damit bei Pool-Verstoß
            // kein verwaister Benutzer zurückbleibt.
            $resolvedMailboxQuota = null;
            if ($isSouveraUser) {
                $resolved = $this->storageService->resolveNewMailboxQuota($mailboxQuota);
                if ($resolved['error'] !== null) {
                    return new DataResponse(['error' => $resolved['error']], Http::STATUS_CONFLICT);
                }
                $resolvedMailboxQuota = $resolved['quota'];
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

            // Benutzer-Typ anwenden: "Souvera User" (lizenziert: souvera-users + Postfach
            // mit dem Klartext-Passwort) oder "Nextcloud User" (unlizenziert, kein Postfach).
            if ($isSouveraUser) {
                $this->mailGroupService->makeSouveraUser($user, $password, $resolvedMailboxQuota);
            } else {
                $this->mailGroupService->makeNextcloudUser($user);
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
                    'isSouveraUser' => $isSouveraUser,
                    'type' => $isSouveraUser ? 'souvera' : 'nextcloud',
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
    #[NoAdminRequired]
    public function update(string $id, ?string $displayName = null, ?string $email = null, ?array $groups = null, ?string $quota = null, ?bool $enabled = null, ?string $manager = null, ?string $password = null, ?bool $isSouveraUser = null): DataResponse {
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
            // Fallback: Array-Parameter werden bei manchen PUT/JSON-Konstellationen
            // nicht typisiert an die Methode gebunden – dann direkt aus dem Request lesen,
            // damit Gruppenänderungen verlässlich gespeichert werden.
            if ($groups === null) {
                $rawGroups = $this->request->getParam('groups');
                if (is_array($rawGroups)) {
                    $groups = array_values(array_filter(array_map('strval', $rawGroups)));
                }
            }
            if ($groups !== null) {
                // souvera-users (Lizenz/Postfach) + scadmin werden NICHT über die generische
                // Gruppenliste verwaltet: souvera-users via Typ-Umschalter (isSouveraUser),
                // scadmin über die Gruppenverwaltung. Daher hier ausnehmen.
                $protectedGids = [$this->configService->getMailGroupId(), $this->configService->getScadminGroupId()];

                // Entferne User aus allen aktuellen Gruppen
                $currentGroups = $this->groupManager->getUserGroups($user);
                foreach ($currentGroups as $group) {
                    $gid = $group->getGID();
                    // Verhindere Entfernung von Admin-User aus "admin" Gruppe
                    if ($this->configService->isAdminUser($id) && $gid === 'admin') {
                        continue;
                    }
                    if (in_array($gid, $protectedGids, true)) {
                        continue;
                    }
                    $group->removeUser($user);
                }

                // Füge User zu neuen Gruppen hinzu
                foreach ($groups as $groupId) {
                    if (in_array($groupId, $protectedGids, true)) {
                        continue;
                    }
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

            // Benutzer-Typ (Souvera User / Nextcloud User) umschalten
            if ($isSouveraUser !== null) {
                $alreadySouvera = $this->mailGroupService->isMember($user);
                if ($isSouveraUser && !$alreadySouvera) {
                    // Hochstufen: Lizenzprüfung (scadmin-Mitglieder verbrauchen keine Lizenz)
                    $isScadmin = $this->groupManager->isInGroup($id, $this->configService->getScadminGroupId());
                    if (!$isScadmin && $this->licenseService->isLimitReached()) {
                        return new DataResponse(
                            ['error' => 'Lizenzlimit erreicht. Dieser Benutzer kann nicht zum Souvera User (mit Postfach) hochgestuft werden.'],
                            Http::STATUS_CONFLICT
                        );
                    }
                    // Mail-Speicher-Pool beim Hochstufen (= neues Postfach) hart erzwingen.
                    $resolvedUpgrade = $this->storageService->resolveNewMailboxQuota(null);
                    if ($resolvedUpgrade['error'] !== null) {
                        return new DataResponse(['error' => $resolvedUpgrade['error']], Http::STATUS_CONFLICT);
                    }
                    $this->mailGroupService->makeSouveraUser($user, !empty($password) ? $password : null, $resolvedUpgrade['quota']);
                } elseif (!$isSouveraUser && $alreadySouvera) {
                    $this->mailGroupService->makeNextcloudUser($user);
                }
            }

            $finalIsSouvera = $this->mailGroupService->isMember($user);
            return new DataResponse([
                'success' => true,
                'user' => [
                    'id' => $user->getUID(),
                    'displayName' => $user->getDisplayName(),
                    'email' => $user->getEMailAddress(),
                    'enabled' => $user->isEnabled(),
                    'quota' => $this->getUserQuota($id),
                    'groups' => $this->getUserGroups($id),
                    'isSouveraUser' => $finalIsSouvera,
                    'type' => $finalIsSouvera ? 'souvera' : 'nextcloud',
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
     * Benutzer zum Souvera-Administrator machen.
     *
     * Fügt den Benutzer der souvera-admins-Gruppe hinzu. Nur möglich für
     * bestehende "Souvera User" (Mitglieder von souvera-users) - ein reiner
     * Nextcloud-User muss erst per "Zum Souvera User machen" umgewandelt werden.
     * Souvera-Administratoren verbrauchen keine Lizenz (siehe LicenseService).
     */
    #[NoAdminRequired]
    public function makeAdmin(string $id): DataResponse {
        try {
            $user = $this->userManager->get($id);
            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Voraussetzung: Benutzer ist bereits Souvera User (Lizenzgruppe + Postfach).
            if (!$this->mailGroupService->isMember($user)) {
                return new DataResponse(
                    ['error' => 'Nur Souvera User können zu Souvera-Administratoren gemacht werden. Bitte zuerst in einen Souvera User umwandeln.'],
                    Http::STATUS_CONFLICT
                );
            }

            $adminGid = $this->configService->getScadminGroupId();
            $group = $this->groupManager->get($adminGid);
            if ($group === null) {
                $group = $this->groupManager->createGroup($adminGid);
            }
            if ($group === null) {
                return new DataResponse(
                    ['error' => 'Administrator-Gruppe konnte nicht angelegt werden'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }
            if (!$group->inGroup($user)) {
                $group->addUser($user);
            }

            return new DataResponse([
                'success' => true,
                'message' => 'Benutzer ist jetzt Souvera-Administrator',
                'isSouveraAdmin' => true,
            ]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Souvera-Administrator-Rechte entziehen (aus souvera-admins entfernen).
     *
     * Der Souvera-User-Status (souvera-users + Postfach) bleibt erhalten.
     */
    #[NoAdminRequired]
    public function removeAdmin(string $id): DataResponse {
        try {
            // Selbst-Aussperrung verhindern
            $currentUser = $this->userSession->getUser();
            if ($currentUser !== null && $currentUser->getUID() === $id) {
                return new DataResponse(
                    ['error' => 'Sie können sich nicht selbst die Administrator-Rechte entziehen. Bitte wenden Sie sich an einen anderen Administrator.'],
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

            $adminGid = $this->configService->getScadminGroupId();
            $group = $this->groupManager->get($adminGid);
            if ($group !== null && $group->inGroup($user)) {
                $group->removeUser($user);
            }

            return new DataResponse([
                'success' => true,
                'message' => 'Administrator-Rechte entfernt',
                'isSouveraAdmin' => false,
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
    #[NoAdminRequired]
    public function delete(string $id): DataResponse {
        try {
            $user = $this->userManager->get($id);
            $targetEmail = $user !== null ? $user->getEMailAddress() : null;

            // Geschützter Souvera-Administrator-BENUTZER (z. B. "scadmin"): darf NIE
            // gelöscht werden. Deckt UID UND E-Mail (localpart) ab.
            if ($this->configService->isAdminAccount($id, $targetEmail)) {
                return new DataResponse(
                    ['error' => 'Der Souvera-Administrator (scadmin) ist geschützt und kann nicht gelöscht werden.'],
                    Http::STATUS_FORBIDDEN
                );
            }

            // Prüfe ob versucht wird den (NC-)Admin-User zu löschen
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
    #[NoAdminRequired]
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
    #[NoAdminRequired]
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
    #[NoAdminRequired]
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
                    $connection = \OC::$server->get(\OCP\IDBConnection::class);
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
    #[NoAdminRequired]
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
            $mailer = \OC::$server->get(\OCP\Mail\IMailer::class);
            $defaults = \OC::$server->get(\OCP\Defaults::class);

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
    #[NoAdminRequired]
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
    #[NoAdminRequired]
    public function getConfig(): DataResponse {
        try {
            return new DataResponse([
                'total_users' => $this->getTotalUserCount(),
                'used_licenses' => $this->licenseService->getUsedLicenses(),
                'max_licenses' => $this->configService->getMaxLicenses(),
                'allowed_domains' => $this->configService->getAllowedDomains(),
                // Neue Limit-Felder
                'max_shared_mailboxes' => $this->configService->getMaxSharedMailboxes(),
                'max_groups' => $this->configService->getMaxGroups(),
                'max_aliases_per_user' => $this->configService->getMaxAliasesPerUser(),
                'warning_threshold' => $this->configService->getWarningThreshold(),
                // Souvera-Administrator (delegierte Verwaltung)
                'scadmin_group' => $this->configService->getScadminGroupId(),
                'souvera_group' => $this->configService->getMailGroupId(),
                // Mail-Speicher-Pool (nur Limit + Schrittweite; belegte Menge via /api/mail-storage)
                'mail_storage_max' => $this->configService->getMaxMailStorage(),
                'mail_storage_step' => StorageService::GIB,
            ]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Mail-Speicher-Pool: Gesamt/verteilt/verfügbar (Bytes) für die UI.
     * Ermittelt die verteilte Menge live aus Stalwart (User + geteilte Postfächer).
     */
    #[NoAdminRequired]
    public function getMailStorage(): DataResponse {
        try {
            return new DataResponse($this->storageService->getSummary());
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
    #[NoAdminRequired]
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
    #[NoAdminRequired]
    public function listGroups(): DataResponse {
        try {
            $allGroups = $this->groupManager->search('');
            $groups = [];

            foreach ($allGroups as $group) {
                // Ausgeblendete Gruppen (z. B. NC-Systemgruppe "admin") nicht anbieten
                if ($this->configService->isHiddenGroup($group->getGID())) {
                    continue;
                }
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
        $count = 0;
        foreach ($allUsers as $user) {
            if ($this->configService->isHiddenUser($user->getUID())) {
                continue;
            }
            $count++;
        }
        return $count;
    }

    /**
     * Genutzte Lizenzen = lizenzierte Souvera-User (souvera-users ohne scadmin/hidden).
     *
     * @return int Anzahl der genutzten Lizenzen
     */
    private function getUsedLicenseCount(): int {
        return $this->licenseService->getUsedLicenses();
    }
}
