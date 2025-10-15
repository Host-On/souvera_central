<?php
/**
 * Souvera Central - Group Management Module - API Controller
 *
 * API-Endpunkte für Gruppenverwaltung
 */

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

class GroupApiController extends OCSController {
    private $userManager;
    private $groupManager;
    private $logger;

    /** @var array Geschützte Systemgruppen, die nicht gelöscht werden dürfen */
    private const PROTECTED_GROUPS = ['admin', 'users'];

    public function __construct(
        string $appName,
        IRequest $request,
        IUserManager $userManager,
        IGroupManager $groupManager,
        LoggerInterface $logger
    ) {
        parent::__construct($appName, $request);
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->logger = $logger;
    }

    /**
     * Liste aller Gruppen abrufen mit Suche und Pagination
     *
     * @param string $search Suchbegriff für Gruppenname
     * @param int $limit Anzahl der Ergebnisse pro Seite (Standard: 20)
     * @param int $offset Start-Offset für Pagination (Standard: 0)
     */
    public function list(string $search = '', int $limit = 20, int $offset = 0): DataResponse {
        $this->logger->info('GroupApiController::list() aufgerufen - search: "' . $search . '", limit: ' . $limit . ', offset: ' . $offset);

        try {
            // Alle Gruppen durchsuchen
            $searchTerm = trim($search);
            $allGroups = $this->groupManager->search($searchTerm);
            $this->logger->info('Gefundene Gruppen (vor Filter): ' . count($allGroups));

            $allGroupsData = [];
            foreach ($allGroups as $group) {
                $groupId = $group->getGID();
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
                    'isProtected' => in_array($groupId, self::PROTECTED_GROUPS)
                ];
                $allGroupsData[] = $groupData;
            }

            $totalCount = count($allGroupsData);
            $this->logger->info('Gefundene Gruppen (nach Filter): ' . $totalCount);

            // Pagination anwenden
            $paginatedGroups = array_slice($allGroupsData, $offset, $limit);
            $this->logger->info('Rückgabe von ' . count($paginatedGroups) . ' Gruppen (Seite)');

            return new DataResponse([
                'groups' => $paginatedGroups,
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => ($offset + $limit) < $totalCount
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Fehler in GroupApiController::list(): ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Einzelne Gruppe abrufen
     */
    public function get(string $id): DataResponse {
        $this->logger->info('GroupApiController::get() aufgerufen für Gruppe: ' . $id);

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
                'isProtected' => in_array($id, self::PROTECTED_GROUPS)
            ];

            return new DataResponse($groupData);
        } catch (\Exception $e) {
            $this->logger->error('Fehler in GroupApiController::get(): ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Neue Gruppe erstellen
     */
    public function create(string $groupId = '', string $displayName = ''): DataResponse {
        $this->logger->info('GroupApiController::create() aufgerufen für Gruppe: ' . $groupId);

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
                $this->logger->warning('Gruppe existiert bereits: ' . $groupId);
                return new DataResponse(
                    ['error' => 'Gruppe existiert bereits'],
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

            $this->logger->info('Gruppe erfolgreich erstellt: ' . $groupId);

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
            $this->logger->error('Fehler beim Erstellen der Gruppe: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Gruppe aktualisieren
     */
    public function update(string $id, ?string $displayName = null): DataResponse {
        $this->logger->info('GroupApiController::update() aufgerufen für Gruppe: ' . $id);

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
                $this->logger->debug('DisplayName aktualisiert: ' . $displayName);
            }

            $this->logger->info('Gruppe erfolgreich aktualisiert: ' . $id);

            return new DataResponse([
                'success' => true,
                'group' => [
                    'id' => $group->getGID(),
                    'displayName' => $group->getDisplayName(),
                    'userCount' => $group->count(),
                    'isProtected' => in_array($id, self::PROTECTED_GROUPS)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Fehler beim Aktualisieren der Gruppe: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Gruppe löschen
     */
    public function delete(string $id): DataResponse {
        $this->logger->info('GroupApiController::delete() aufgerufen für Gruppe: ' . $id);

        try {
            // Prüfen ob Gruppe geschützt ist
            if (in_array($id, self::PROTECTED_GROUPS)) {
                $this->logger->warning('Versuch geschützte Gruppe zu löschen: ' . $id);
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

            $this->logger->info('Gruppe erfolgreich gelöscht: ' . $id);

            return new DataResponse(['success' => true]);

        } catch (\Exception $e) {
            $this->logger->error('Fehler beim Löschen der Gruppe: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Mitglieder einer Gruppe abrufen
     */
    public function getMembers(string $id, string $search = '', int $limit = 100, int $offset = 0): DataResponse {
        $this->logger->info('GroupApiController::getMembers() aufgerufen für Gruppe: ' . $id);

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
            $this->logger->error('Fehler in GroupApiController::getMembers(): ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Benutzer zu Gruppe hinzufügen
     */
    public function addMember(string $id, string $userId): DataResponse {
        $this->logger->info('GroupApiController::addMember() aufgerufen - Gruppe: ' . $id . ', User: ' . $userId);

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

            // User zu Gruppe hinzufügen
            $group->addUser($user);

            $this->logger->info('Benutzer erfolgreich zu Gruppe hinzugefügt: ' . $userId . ' -> ' . $id);

            return new DataResponse([
                'success' => true,
                'message' => 'Benutzer erfolgreich hinzugefügt'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Fehler beim Hinzufügen des Benutzers zur Gruppe: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Benutzer aus Gruppe entfernen
     */
    public function removeMember(string $id, string $userId): DataResponse {
        $this->logger->info('GroupApiController::removeMember() aufgerufen - Gruppe: ' . $id . ', User: ' . $userId);

        try {
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

            $this->logger->info('Benutzer erfolgreich aus Gruppe entfernt: ' . $userId . ' <- ' . $id);

            return new DataResponse([
                'success' => true,
                'message' => 'Benutzer erfolgreich entfernt'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Fehler beim Entfernen des Benutzers aus der Gruppe: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
