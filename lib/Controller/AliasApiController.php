<?php
/**
 * Souvera Central - Alias API Controller
 *
 * API-Endpunkte für Email-Alias-Verwaltung via Stalwart
 */

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use OCA\SouveraCentral\Service\StalwartService;
use OCA\SouveraCentral\Service\ConfigService;

class AliasApiController extends OCSController {
    private IUserManager $userManager;
    private StalwartService $stalwartService;
    private ConfigService $configService;
    private LoggerInterface $logger;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserManager $userManager,
        StalwartService $stalwartService,
        ConfigService $configService,
        LoggerInterface $logger
    ) {
        parent::__construct($appName, $request);
        $this->userManager = $userManager;
        $this->stalwartService = $stalwartService;
        $this->configService = $configService;
        $this->logger = $logger;
    }

    /**
     * Stalwart-Status abrufen
     *
     * @return DataResponse
     */
    public function getStatus(): DataResponse {
        try {
            return new DataResponse($this->stalwartService->getStatus());
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Alle Aliase eines Benutzers abrufen
     *
     * @param string $userId - Benutzer-ID (= Email)
     * @return DataResponse
     */
    public function list(string $userId): DataResponse {
        try {
            // Prüfe ob User existiert
            $user = $this->userManager->get($userId);
            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Prüfe Stalwart-Verbindung
            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Stalwart Mail-Server nicht erreichbar', 'configured' => $this->configService->isStalwartConfigured()],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            // Aliase abrufen
            $aliases = $this->stalwartService->getAliases($userId);
            $allEmails = $this->stalwartService->getEmails($userId);
            $maxAliases = $this->configService->getMaxAliasesPerUser();

            return new DataResponse([
                'userId' => $userId,
                'primaryEmail' => $userId,
                'aliases' => $aliases,
                'allEmails' => $allEmails,
                'total' => count($aliases),
                'maxAliases' => $maxAliases
            ]);

        } catch (\Exception $e) {
            $this->logger->error('AliasApiController: Fehler beim Abrufen der Aliase', [
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
     * Neuen Alias hinzufügen
     *
     * @param string $userId - Benutzer-ID (= Email)
     * @param string $alias - Neue Email-Adresse
     * @return DataResponse
     */
    public function add(string $userId, string $alias = ''): DataResponse {
        try {
            // Validierung: Alias-Parameter
            if (empty($alias)) {
                return new DataResponse(
                    ['error' => 'Alias-Adresse ist erforderlich'],
                    Http::STATUS_BAD_REQUEST
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

            // Email-Format validieren
            if (!filter_var($alias, FILTER_VALIDATE_EMAIL)) {
                return new DataResponse(
                    ['error' => 'Ungültiges Email-Format'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Domain validieren
            if (!$this->configService->isEmailDomainAllowed($alias)) {
                $allowedDomains = $this->configService->getAllowedDomains();
                return new DataResponse(
                    ['error' => 'Email-Domain nicht erlaubt. Erlaubte Domains: ' . implode(', ', $allowedDomains)],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Prüfe Stalwart-Verbindung
            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Stalwart Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            // Alias-Limit prüfen
            $currentAliases = $this->stalwartService->getAliases($userId);
            $maxAliases = $this->configService->getMaxAliasesPerUser();
            if (count($currentAliases) >= $maxAliases) {
                return new DataResponse(
                    ['error' => 'Maximale Anzahl an Aliasen erreicht (' . $maxAliases . ')'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Prüfe ob Alias bereits vergeben ist
            if ($this->stalwartService->isEmailTaken($alias)) {
                return new DataResponse(
                    ['error' => 'Diese Email-Adresse ist bereits vergeben'],
                    Http::STATUS_CONFLICT
                );
            }

            // Alias hinzufügen
            $success = $this->stalwartService->addAlias($userId, $alias);

            if (!$success) {
                return new DataResponse(
                    ['error' => 'Alias konnte nicht hinzugefügt werden'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            // Aktuelle Aliase zurückgeben
            $aliases = $this->stalwartService->getAliases($userId);

            return new DataResponse([
                'success' => true,
                'message' => 'Alias erfolgreich hinzugefügt',
                'alias' => $alias,
                'aliases' => $aliases,
                'total' => count($aliases)
            ], Http::STATUS_CREATED);

        } catch (\Exception $e) {
            $this->logger->error('AliasApiController: Fehler beim Hinzufügen des Alias', [
                'userId' => $userId,
                'alias' => $alias,
                'error' => $e->getMessage()
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Alias entfernen
     *
     * @param string $userId - Benutzer-ID (= Email)
     * @param string $alias - Zu entfernende Email-Adresse
     * @return DataResponse
     */
    public function remove(string $userId, string $alias): DataResponse {
        try {
            // Prüfe ob User existiert
            $user = $this->userManager->get($userId);
            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            // Verhindere Entfernung der Haupt-Email
            if ($alias === $userId) {
                return new DataResponse(
                    ['error' => 'Die Haupt-Email-Adresse kann nicht entfernt werden'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Prüfe Stalwart-Verbindung
            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Stalwart Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            // Alias entfernen
            $success = $this->stalwartService->removeAlias($userId, $alias);

            if (!$success) {
                return new DataResponse(
                    ['error' => 'Alias konnte nicht entfernt werden'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            // Aktuelle Aliase zurückgeben
            $aliases = $this->stalwartService->getAliases($userId);

            return new DataResponse([
                'success' => true,
                'message' => 'Alias erfolgreich entfernt',
                'aliases' => $aliases,
                'total' => count($aliases)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('AliasApiController: Fehler beim Entfernen des Alias', [
                'userId' => $userId,
                'alias' => $alias,
                'error' => $e->getMessage()
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Prüfen ob Email-Adresse verfügbar ist
     *
     * @param string $email - Zu prüfende Email-Adresse
     * @return DataResponse
     */
    public function checkAvailability(string $email = ''): DataResponse {
        try {
            if (empty($email)) {
                return new DataResponse(
                    ['error' => 'Email-Adresse ist erforderlich'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Prüfe Stalwart-Verbindung
            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Stalwart Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            $isTaken = $this->stalwartService->isEmailTaken($email);
            $isDomainAllowed = $this->configService->isEmailDomainAllowed($email);

            return new DataResponse([
                'email' => $email,
                'available' => !$isTaken && $isDomainAllowed,
                'taken' => $isTaken,
                'domainAllowed' => $isDomainAllowed
            ]);

        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // Postfach-Verwaltung (Admin-only: Controller verlangt standardmäßig Admin)
    // ========================================================================

    /**
     * Liste aller bestehenden Stalwart-Postfächer (Principal-Namen) für
     * Tabellen-Badges in der Benutzerverwaltung.
     */
    public function listMailboxes(): DataResponse {
        try {
            if (!$this->configService->isStalwartConfigured()) {
                return new DataResponse(['configured' => false, 'available' => false, 'mailboxes' => [], 'total' => 0]);
            }

            $names = $this->stalwartService->listPrincipalNames();
            return new DataResponse([
                'configured' => true,
                'available' => $this->stalwartService->isAvailable(),
                'mailboxes' => $names,
                'total' => count($names),
            ]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Postfach-Status eines einzelnen Benutzers abrufen.
     */
    public function getMailbox(string $userId): DataResponse {
        try {
            $user = $this->userManager->get($userId);
            if ($user === null) {
                return new DataResponse(['error' => 'Benutzer nicht gefunden'], Http::STATUS_NOT_FOUND);
            }
            return new DataResponse($this->stalwartService->getMailboxStatus($userId));
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Postfach für einen Benutzer anlegen/sicherstellen.
     * Nutzt ein Zufalls-Secret; der Nutzer setzt sein Mail-Passwort danach per
     * NC-Passwortänderung neu (PasswordSyncListener spiegelt es nach Stalwart).
     */
    public function createMailbox(string $userId): DataResponse {
        try {
            $user = $this->userManager->get($userId);
            if ($user === null) {
                return new DataResponse(['error' => 'Benutzer nicht gefunden'], Http::STATUS_NOT_FOUND);
            }
            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Stalwart Mail-Server nicht erreichbar', 'configured' => $this->configService->isStalwartConfigured()],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }
            if ($this->stalwartService->principalExists($userId)) {
                return new DataResponse([
                    'success' => true,
                    'created' => false,
                    'message' => 'Postfach existiert bereits',
                    'status' => $this->stalwartService->getMailboxStatus($userId),
                ]);
            }

            $mail = $this->stalwartService->mailFor($user);
            if ($mail === null) {
                return new DataResponse(
                    ['error' => 'Keine gültige Mail-Adresse/Domain für diesen Benutzer'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $ok = $this->stalwartService->createPrincipal($userId, bin2hex(random_bytes(24)), $mail, $user->getDisplayName());
            if (!$ok) {
                return new DataResponse(['error' => 'Postfach konnte nicht angelegt werden'], Http::STATUS_INTERNAL_SERVER_ERROR);
            }

            return new DataResponse([
                'success' => true,
                'created' => true,
                'email' => $mail,
                'status' => $this->stalwartService->getMailboxStatus($userId),
            ], Http::STATUS_CREATED);
        } catch (\Exception $e) {
            $this->logger->error('AliasApiController: createMailbox fehlgeschlagen', ['userId' => $userId, 'error' => $e->getMessage()]);
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Backfill: legt fehlende Postfächer für alle Nextcloud-Benutzer an.
     */
    public function syncMailboxes(): DataResponse {
        try {
            if (!$this->configService->isStalwartConfigured()) {
                return new DataResponse(['error' => 'Stalwart nicht konfiguriert', 'configured' => false], Http::STATUS_BAD_REQUEST);
            }
            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(['error' => 'Stalwart Mail-Server nicht erreichbar', 'configured' => true], Http::STATUS_SERVICE_UNAVAILABLE);
            }

            $existing = array_flip($this->stalwartService->listPrincipalNames());
            $created = 0;
            $skipped = 0;
            $noMail = 0;
            $errors = 0;

            // Alle Benutzer paginiert durchlaufen (NC34-konform, kein callForAllUsers)
            $limit = 500;
            $offset = 0;
            do {
                $users = $this->userManager->search('', $limit, $offset);
                foreach ($users as $user) {
                    $uid = $user->getUID();
                    if ($this->configService->isAdminUser($uid)) {
                        $skipped++;
                        continue;
                    }
                    if (isset($existing[$uid]) || $this->stalwartService->principalExists($uid)) {
                        $skipped++;
                        continue;
                    }
                    $mail = $this->stalwartService->mailFor($user);
                    if ($mail === null) {
                        $noMail++;
                        continue;
                    }
                    try {
                        $ok = $this->stalwartService->createPrincipal($uid, bin2hex(random_bytes(24)), $mail, $user->getDisplayName());
                        $ok ? $created++ : $errors++;
                    } catch (\Throwable $e) {
                        $errors++;
                    }
                }
                $offset += $limit;
            } while (count($users) === $limit);

            return new DataResponse([
                'success' => true,
                'created' => $created,
                'skipped' => $skipped,
                'noMail' => $noMail,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('AliasApiController: syncMailboxes fehlgeschlagen', ['error' => $e->getMessage()]);
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
