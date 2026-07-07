<?php
/**
 * Souvera Central - Shared Mailbox API Controller
 *
 * REST-Endpoints für Shared Mailbox Verwaltung
 */

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use OCA\SouveraCentral\Service\SharedMailboxService;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;

class SharedMailboxApiController extends OCSController {
    private IUserManager $userManager;
    private SharedMailboxService $sharedMailboxService;
    private StalwartService $stalwartService;
    private ConfigService $configService;
    private LoggerInterface $logger;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserManager $userManager,
        SharedMailboxService $sharedMailboxService,
        StalwartService $stalwartService,
        ConfigService $configService,
        LoggerInterface $logger
    ) {
        parent::__construct($appName, $request);
        $this->userManager = $userManager;
        $this->sharedMailboxService = $sharedMailboxService;
        $this->stalwartService = $stalwartService;
        $this->configService = $configService;
        $this->logger = $logger;
    }

    /**
     * Alle Shared Mailboxes abrufen
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function list(): DataResponse {
        try {
            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            $mailboxes = $this->sharedMailboxService->list();

            return new DataResponse([
                'mailboxes' => $mailboxes,
                'total' => count($mailboxes),
                'maxMailboxes' => $this->configService->getMaxSharedMailboxes(),
                'warningThreshold' => $this->configService->getWarningThreshold()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('SharedMailboxApiController: Fehler beim Abrufen', [
                'error' => $e->getMessage()
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Shared Mailbox erstellen
     *
     * @param string $name - Anzeigename
     * @param string $email - Email-Adresse
     * @param string $description - Beschreibung
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function create(string $name = '', string $email = '', string $description = ''): DataResponse {
        try {
            // Validierung
            if (empty($name)) {
                return new DataResponse(
                    ['error' => 'Name ist erforderlich'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            if (empty($email)) {
                return new DataResponse(
                    ['error' => 'Email-Adresse ist erforderlich'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new DataResponse(
                    ['error' => 'Ungültiges Email-Format'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Domain-Validierung
            if (!$this->configService->isEmailDomainAllowed($email)) {
                $allowedDomains = $this->configService->getAllowedDomains();
                return new DataResponse(
                    ['error' => 'Email-Domain nicht erlaubt. Erlaubte Domains: ' . implode(', ', $allowedDomains)],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Limit prüfen
            $currentMailboxes = $this->sharedMailboxService->list();
            $currentCount = is_array($currentMailboxes) ? count($currentMailboxes) : 0;
            $maxMailboxes = $this->configService->getMaxSharedMailboxes();
            if ($currentCount >= $maxMailboxes) {
                return new DataResponse(
                    ['error' => 'Limit für geteilte Postfächer erreicht (' . $maxMailboxes . ')'],
                    Http::STATUS_CONFLICT
                );
            }

            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            // Prüfe ob Email bereits vergeben
            if ($this->sharedMailboxService->isEmailTaken($email)) {
                return new DataResponse(
                    ['error' => 'Diese Email-Adresse ist bereits vergeben'],
                    Http::STATUS_CONFLICT
                );
            }

            // Erstellen
            $mailbox = $this->sharedMailboxService->create($name, $email, $description);

            if ($mailbox === null) {
                return new DataResponse(
                    ['error' => 'Shared Mailbox konnte nicht erstellt werden'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            return new DataResponse([
                'success' => true,
                'message' => 'Shared Mailbox erfolgreich erstellt',
                'mailbox' => $mailbox
            ], Http::STATUS_CREATED);

        } catch (\Exception $e) {
            $this->logger->error('SharedMailboxApiController: Fehler beim Erstellen', [
                'name' => $name,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Shared Mailbox Details abrufen
     *
     * @param string $id - Interner Name
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function get(string $id): DataResponse {
        try {
            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            $mailbox = $this->sharedMailboxService->get($id);

            if ($mailbox === null) {
                return new DataResponse(
                    ['error' => 'Shared Mailbox nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            return new DataResponse([
                'mailbox' => $mailbox
            ]);

        } catch (\Exception $e) {
            $this->logger->error('SharedMailboxApiController: Fehler beim Abrufen', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Shared Mailbox aktualisieren
     *
     * @param string $id - Interner Name
     * @param string $description - Neue Beschreibung
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function update(string $id, string $description = ''): DataResponse {
        try {
            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            // Prüfe ob Mailbox existiert
            $mailbox = $this->sharedMailboxService->get($id);
            if ($mailbox === null) {
                return new DataResponse(
                    ['error' => 'Shared Mailbox nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $updates = [];
            if ($description !== '') {
                $updates['description'] = $description;
            }

            $success = $this->sharedMailboxService->update($id, $updates);

            if (!$success) {
                return new DataResponse(
                    ['error' => 'Aktualisierung fehlgeschlagen'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            // Aktualisierte Mailbox abrufen
            $mailbox = $this->sharedMailboxService->get($id);

            return new DataResponse([
                'success' => true,
                'message' => 'Shared Mailbox aktualisiert',
                'mailbox' => $mailbox
            ]);

        } catch (\Exception $e) {
            $this->logger->error('SharedMailboxApiController: Fehler beim Aktualisieren', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Shared Mailbox löschen
     *
     * @param string $id - Interner Name
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function delete(string $id): DataResponse {
        try {
            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            // Prüfe ob Mailbox existiert
            $mailbox = $this->sharedMailboxService->get($id);
            if ($mailbox === null) {
                return new DataResponse(
                    ['error' => 'Shared Mailbox nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $success = $this->sharedMailboxService->delete($id);

            if (!$success) {
                $reason = $this->sharedMailboxService->getLastError();
                return new DataResponse(
                    ['error' => $reason !== null ? ('Löschen fehlgeschlagen: ' . $reason) : 'Löschen fehlgeschlagen'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            return new DataResponse([
                'success' => true,
                'message' => 'Shared Mailbox gelöscht'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('SharedMailboxApiController: Fehler beim Löschen', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ============================================================================
    // Mitglieder-Verwaltung
    // ============================================================================

    /**
     * Mitglieder einer Shared Mailbox abrufen
     *
     * @param string $id - Interner Name
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function getMembers(string $id): DataResponse {
        try {
            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            $mailbox = $this->sharedMailboxService->get($id);
            if ($mailbox === null) {
                return new DataResponse(
                    ['error' => 'Shared Mailbox nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $members = $this->sharedMailboxService->getMembers($id);

            return new DataResponse([
                'mailboxId' => $id,
                'members' => $members,
                'total' => count($members)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('SharedMailboxApiController: Fehler beim Abrufen der Mitglieder', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Mitglied zu Shared Mailbox hinzufügen
     *
     * @param string $id - Interner Name der Mailbox
     * @param string $userId - Benutzer-ID (Email)
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function addMember(string $id, string $userId = ''): DataResponse {
        try {
            if (empty($userId)) {
                return new DataResponse(
                    ['error' => 'Benutzer-ID ist erforderlich'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            // Prüfe ob Mailbox existiert
            $mailbox = $this->sharedMailboxService->get($id);
            if ($mailbox === null) {
                return new DataResponse(
                    ['error' => 'Shared Mailbox nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Prüfe ob User existiert
            $user = $this->userManager->get($userId);
            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Prüfe ob User bereits Mitglied ist (Mitglieder werden als Mailadresse geführt)
            $memberMail = $this->stalwartService->mailFor($user) ?? $userId;
            $currentMembers = $this->sharedMailboxService->getMembers($id);
            if (in_array(strtolower($memberMail), array_map('strtolower', $currentMembers), true)) {
                return new DataResponse(
                    ['error' => 'Benutzer ist bereits Mitglied'],
                    Http::STATUS_CONFLICT
                );
            }

            $success = $this->sharedMailboxService->addMember($id, $userId);

            if (!$success) {
                return new DataResponse(
                    ['error' => 'Mitglied konnte nicht hinzugefügt werden'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            $members = $this->sharedMailboxService->getMembers($id);

            return new DataResponse([
                'success' => true,
                'message' => 'Mitglied hinzugefügt',
                'members' => $members,
                'total' => count($members)
            ], Http::STATUS_CREATED);

        } catch (\Exception $e) {
            $this->logger->error('SharedMailboxApiController: Fehler beim Hinzufügen des Mitglieds', [
                'id' => $id,
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Mitglied aus Shared Mailbox entfernen
     *
     * @param string $id - Interner Name der Mailbox
     * @param string $userId - Benutzer-ID (Email)
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function removeMember(string $id, string $userId): DataResponse {
        try {
            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            // Prüfe ob Mailbox existiert
            $mailbox = $this->sharedMailboxService->get($id);
            if ($mailbox === null) {
                return new DataResponse(
                    ['error' => 'Shared Mailbox nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $success = $this->sharedMailboxService->removeMember($id, $userId);

            if (!$success) {
                return new DataResponse(
                    ['error' => 'Mitglied konnte nicht entfernt werden'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            $members = $this->sharedMailboxService->getMembers($id);

            return new DataResponse([
                'success' => true,
                'message' => 'Mitglied entfernt',
                'members' => $members,
                'total' => count($members)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('SharedMailboxApiController: Fehler beim Entfernen des Mitglieds', [
                'id' => $id,
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
