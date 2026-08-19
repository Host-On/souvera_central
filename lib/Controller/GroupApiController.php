<?php
/**
 * Souvera Central - Group Management Module - API Controller
 *
 * API-Endpunkte für Gruppenverwaltung
 */

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\LicenseService;
use OCA\SouveraCentral\Service\MailGroupService;

class GroupApiController extends OCSController {
    private $userManager;
    private $groupManager;
    private $logger;
    private $configService;
    private LicenseService $licenseService;
    private MailGroupService $mailGroupService;

    /** @var array NC-Systemgruppen, die nicht gelöscht werden dürfen */
    private const SYSTEM_GROUPS = ['admin', 'users'];

    public function __construct(
        string $appName,
        IRequest $request,
        IUserManager $userManager,
        IGroupManager $groupManager,
        LoggerInterface $logger,
        ConfigService $configService,
        LicenseService $licenseService,
        MailGroupService $mailGroupService
    ) {
        parent::__construct($appName, $request);
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->logger = $logger;
        $this->configService = $configService;
        $this->licenseService = $licenseService;
        $this->mailGroupService = $mailGroupService;
    }

    /**
     * Geschützte Gruppen: NC-Systemgruppen + die von Souvera verwalteten Gruppen
     * (souvera-users / scadmin). Diese dürfen nicht gelöscht werden.
     *
     * @return string[]
     */
    private function getProtectedGroups(): array {
        return array_merge(self::SYSTEM_GROUPS, [
            $this->configService->getMailGroupId(),
            $this->configService->getScadminGroupId(),
        ]);
    }

    /**
     * Liste aller Gruppen abrufen mit Suche und Pagination
     *
     * @param string $search Suchbegriff für Gruppenname
     * @param int $limit Anzahl der Ergebnisse pro Seite (Standard: 20)
     * @param int $offset Start-Offset für Pagination (Standard: 0)
     */
    #[NoAdminRequired]
    public function list(string $search = '', int $limit = 20, int $offset = 0): DataResponse {
        try {
            // Alle Gruppen durchsuchen
            $searchTerm = trim($search);
            $allGroups = $this->groupManager->search($searchTerm);

            $allGroupsData = [];
            foreach ($allGroups as $group) {
                $groupId = $group->getGID();
                // Ausgeblendete Gruppen (z. B. NC-Systemgruppe "admin") nie anzeigen
                if ($this->configService->isHiddenGroup($groupId)) {
                    continue;
                }
                $displayName = $group->getDisplayName();

                // Zusätzlicher Filter: Suche im Gruppennamen und Display Name
                if (!empty($searchTerm)) {
                    $searchLower = mb_strtolower($searchTerm);
                    $groupIdLower = mb_strtolower($groupId);
                    $displayNameLower = mb_strtolower($displayName);

                    // Überspringe Gruppen, die nicht matchen
                    if (
                        strpos($groupIdLower, $searchLower) === false &&
                        strpos($displayNameLower, $searchLower) === false
                    ) {
                        continue;
                    }
                }

                $groupData = [
                    'id' => $groupId,
                    'displayName' => $displayName,
                    'userCount' => $group->count(),
                    'isProtected' => in_array($groupId, $this->getProtectedGroups(), true)
                ];
                $allGroupsData[] = $groupData;
            }

            $totalCount = count($allGroupsData);

            // Pagination anwenden
            $paginatedGroups = array_slice($allGroupsData, $offset, $limit);

            return new DataResponse([
                'groups' => $paginatedGroups,
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => ($offset + $limit) < $totalCount,
                'maxGroups' => $this->configService->getMaxGroups(),
                'warningThreshold' => $this->configService->getWarningThreshold()
            ]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Einzelne Gruppe abrufen
     */
    #[NoAdminRequired]
    public function get(string $id): DataResponse {
        try {
            $group = $this->groupManager->get($id);

            if ($group === null) {
                return new DataResponse(
                    ['error' => 'Gruppe nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $groupData = [
                'id' => $group->getGID(),
                'displayName' => $group->getDisplayName(),
                'userCount' => $group->count(),
                'isProtected' => in_array($id, $this->getProtectedGroups(), true)
            ];

            return new DataResponse($groupData);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Neue Gruppe erstellen
     */
    #[NoAdminRequired]
    public function create(string $groupId = '', string $displayName = ''): DataResponse {
        try {
            // Validierung
            if (empty($groupId)) {
                return new DataResponse(
                    ['error' => 'Gruppen-ID ist erforderlich'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Gruppen-ID validieren (nur alphanumerisch, _ und -)
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $groupId)) {
                return new DataResponse(
                    ['error' => 'Gruppen-ID darf nur Buchstaben, Zahlen, Unterstriche und Bindestriche enthalten'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Prüfen ob Gruppe schon existiert
            if ($this->groupManager->get($groupId) !== null) {
                return new DataResponse(
                    ['error' => 'Gruppe existiert bereits'],
                    Http::STATUS_CONFLICT
                );
            }

            // Gruppen-Limit prüfen
            $allGroups = $this->groupManager->search('');
            $currentCount = count($allGroups);
            $maxGroups = $this->configService->getMaxGroups();
            if ($currentCount >= $maxGroups) {
                return new DataResponse(
                    ['error' => 'Limit für Gruppen erreicht (' . $maxGroups . ')'],
                    Http::STATUS_CONFLICT
                );
            }

            // Gruppe erstellen
            $group = $this->groupManager->createGroup($groupId);

            if ($group === null) {
                return new DataResponse(
                    ['error' => 'Gruppe konnte nicht erstellt werden'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            // Display Name setzen, falls angegeben
            if (!empty($displayName)) {
                $group->setDisplayName($displayName);
            }

            return new DataResponse([
                'success' => true,
                'group' => [
                    'id' => $group->getGID(),
                    'displayName' => $group->getDisplayName(),
                    'userCount' => $group->count(),
                    'isProtected' => false
                ]
            ], Http::STATUS_CREATED);

        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Gruppe aktualisieren
     */
    #[NoAdminRequired]
    public function update(string $id, ?string $displayName = null): DataResponse {
        try {
            $group = $this->groupManager->get($id);

            if ($group === null) {
                return new DataResponse(
                    ['error' => 'Gruppe nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Display Name aktualisieren
            if ($displayName !== null) {
                $group->setDisplayName($displayName);
            }

            return new DataResponse([
                'success' => true,
                'group' => [
                    'id' => $group->getGID(),
                    'displayName' => $group->getDisplayName(),
                    'userCount' => $group->count(),
                    'isProtected' => in_array($id, $this->getProtectedGroups(), true)
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
     * Gruppe löschen
     */
    #[NoAdminRequired]
    public function delete(string $id): DataResponse {
        try {
            // Prüfen ob Gruppe geschützt ist
            if (in_array($id, $this->getProtectedGroups(), true)) {
                return new DataResponse(
                    ['error' => 'Systemgruppen können nicht gelöscht werden'],
                    Http::STATUS_FORBIDDEN
                );
            }

            $group = $this->groupManager->get($id);

            if ($group === null) {
                return new DataResponse(
                    ['error' => 'Gruppe nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Gruppe löschen
            $success = $group->delete();

            if (!$success) {
                return new DataResponse(
                    ['error' => 'Gruppe konnte nicht gelöscht werden'],
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
     * Mitglieder einer Gruppe abrufen
     */
    #[NoAdminRequired]
    public function getMembers(string $id, string $search = '', int $limit = 100, int $offset = 0): DataResponse {
        try {
            $group = $this->groupManager->get($id);

            if ($group === null) {
                return new DataResponse(
                    ['error' => 'Gruppe nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Alle User der Gruppe abrufen
            $allUsers = $group->getUsers();
            $searchTerm = trim($search);

            $membersData = [];
            foreach ($allUsers as $user) {
                $userId = $user->getUID();
                // Technische/ausgeblendete Benutzer (z. B. ncadmin) nie anzeigen
                if ($this->configService->isHiddenUser($userId)) {
                    continue;
                }
                $displayName = $user->getDisplayName();
                $email = $user->getEMailAddress() ?? '';

                // Filter nach Suchbegriff
                if (!empty($searchTerm)) {
                    $searchLower = mb_strtolower($searchTerm);
                    $userIdLower = mb_strtolower($userId);
                    $displayNameLower = mb_strtolower($displayName);
                    $emailLower = mb_strtolower($email);

                    if (
                        strpos($userIdLower, $searchLower) === false &&
                        strpos($displayNameLower, $searchLower) === false &&
                        strpos($emailLower, $searchLower) === false
                    ) {
                        continue;
                    }
                }

                $membersData[] = [
                    'id' => $userId,
                    'displayName' => $displayName,
                    'email' => $email
                ];
            }

            $totalCount = count($membersData);

            // Pagination anwenden
            $paginatedMembers = array_slice($membersData, $offset, $limit);

            return new DataResponse([
                'members' => $paginatedMembers,
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => ($offset + $limit) < $totalCount
            ]);

        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Benutzer zu Gruppe hinzufügen
     */
    #[NoAdminRequired]
    public function addMember(string $id, string $userId): DataResponse {
        try {
            // Validierung
            if (empty($userId)) {
                return new DataResponse(
                    ['error' => 'Benutzer-ID ist erforderlich'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $group = $this->groupManager->get($id);
            if ($group === null) {
                return new DataResponse(
                    ['error' => 'Gruppe nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $user = $this->userManager->get($userId);
            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Prüfen ob User bereits in Gruppe ist
            if ($group->inGroup($user)) {
                return new DataResponse(
                    ['error' => 'Benutzer ist bereits Mitglied der Gruppe'],
                    Http::STATUS_CONFLICT
                );
            }

            $souveraGid = $this->configService->getMailGroupId();
            $scadminGid = $this->configService->getScadminGroupId();

            // Aufnahme in souvera-users = lizenzierter "Souvera User" (+ Postfach).
            // Lizenz-Limit prüfen (scadmin-Mitglieder verbrauchen keine Lizenz).
            if ($id === $souveraGid) {
                $isScadmin = $this->groupManager->isInGroup($userId, $scadminGid);
                if (!$isScadmin && $this->licenseService->isLimitReached()) {
                    return new DataResponse(
                        ['error' => 'Lizenzlimit erreicht. Es können keine weiteren Souvera User aufgenommen werden.'],
                        Http::STATUS_CONFLICT
                    );
                }
                $this->mailGroupService->makeSouveraUser($user);
                return new DataResponse([
                    'success' => true,
                    'message' => 'Benutzer erfolgreich als Souvera User aufgenommen'
                ]);
            }

            // User zu Gruppe hinzufügen
            $group->addUser($user);

            // Souvera-Administratoren (scadmin) erhalten ebenfalls ein Postfach
            // (Souvera User), verbrauchen aber keine Lizenz.
            if ($id === $scadminGid) {
                $this->mailGroupService->makeSouveraUser($user);
            }

            return new DataResponse([
                'success' => true,
                'message' => 'Benutzer erfolgreich hinzugefügt'
            ]);

        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Benutzer aus Gruppe entfernen
     */
    #[NoAdminRequired]
    public function removeMember(string $id, string $userId): DataResponse {
        try {
            // Verhindere Entfernung von Admin-User aus "admin" Gruppe
            if ($id === 'admin' && $this->configService->isAdminUser($userId)) {
                return new DataResponse(
                    ['error' => 'Der Administrator kann nicht aus der Admin-Gruppe entfernt werden.'],
                    Http::STATUS_FORBIDDEN
                );
            }

            $group = $this->groupManager->get($id);
            if ($group === null) {
                return new DataResponse(
                    ['error' => 'Gruppe nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $user = $this->userManager->get($userId);
            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Prüfen ob User in Gruppe ist
            if (!$group->inGroup($user)) {
                return new DataResponse(
                    ['error' => 'Benutzer ist kein Mitglied der Gruppe'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // User aus Gruppe entfernen
            $group->removeUser($user);

            return new DataResponse([
                'success' => true,
                'message' => 'Benutzer erfolgreich entfernt'
            ]);

        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
