<?php
/**
 * Souvera Central - Alias API Controller
 *
 * API-Endpunkte für Email-Alias- und Postfach-Verwaltung via Stalwart (JMAP).
 * Identität eines Postfachs ist die Mailadresse (in Souvera gilt UID == E-Mail).
 */

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use OCA\SouveraCentral\Service\StalwartService;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\MailGroupService;

class AliasApiController extends OCSController {
    private IUserManager $userManager;
    private StalwartService $stalwartService;
    private ConfigService $configService;
    private MailGroupService $mailGroupService;
    private LoggerInterface $logger;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserManager $userManager,
        StalwartService $stalwartService,
        ConfigService $configService,
        MailGroupService $mailGroupService,
        LoggerInterface $logger
    ) {
        parent::__construct($appName, $request);
        $this->userManager = $userManager;
        $this->stalwartService = $stalwartService;
        $this->configService = $configService;
        $this->mailGroupService = $mailGroupService;
        $this->logger = $logger;
    }

    /**
     * Stalwart-Status abrufen
     */
    #[NoAdminRequired]
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
     */
    #[NoAdminRequired]
    public function list(string $userId): DataResponse {
        try {
            $user = $this->userManager->get($userId);
            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Stalwart Mail-Server nicht erreichbar', 'configured' => $this->configService->isStalwartConfigured()],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            $mail = $this->stalwartService->mailFor($user) ?? $userId;
            $aliases = $this->stalwartService->getAliases($mail);
            $allEmails = $this->stalwartService->getEmails($mail);
            $maxAliases = $this->configService->getMaxAliasesPerUser();

            return new DataResponse([
                'userId' => $userId,
                'primaryEmail' => $mail,
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
     */
    #[NoAdminRequired]
    public function add(string $userId, string $alias = ''): DataResponse {
        try {
            if (empty($alias)) {
                return new DataResponse(
                    ['error' => 'Alias-Adresse ist erforderlich'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $user = $this->userManager->get($userId);
            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            if (!filter_var($alias, FILTER_VALIDATE_EMAIL)) {
                return new DataResponse(
                    ['error' => 'Ungültiges Email-Format'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            if (!$this->configService->isEmailDomainAllowed($alias)) {
                $allowedDomains = $this->configService->getAllowedDomains();
                return new DataResponse(
                    ['error' => 'Email-Domain nicht erlaubt. Erlaubte Domains: ' . implode(', ', $allowedDomains)],
                    Http::STATUS_BAD_REQUEST
                );
            }

            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Stalwart Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            $mail = $this->stalwartService->mailFor($user) ?? $userId;

            // Alias-Limit prüfen
            $currentAliases = $this->stalwartService->getAliases($mail);
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

            $success = $this->stalwartService->addAlias($mail, $alias);

            if (!$success) {
                return new DataResponse(
                    ['error' => 'Alias konnte nicht hinzugefügt werden'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            $aliases = $this->stalwartService->getAliases($mail);

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
     */
    #[NoAdminRequired]
    public function remove(string $userId, string $alias): DataResponse {
        try {
            $user = $this->userManager->get($userId);
            if ($user === null) {
                return new DataResponse(
                    ['error' => 'Benutzer nicht gefunden'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $mail = $this->stalwartService->mailFor($user) ?? $userId;

            // Verhindere Entfernung der Haupt-Email
            if (strtolower($alias) === strtolower($mail)) {
                return new DataResponse(
                    ['error' => 'Die Haupt-Email-Adresse kann nicht entfernt werden'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            if (!$this->stalwartService->isAvailable()) {
                return new DataResponse(
                    ['error' => 'Stalwart Mail-Server nicht erreichbar'],
                    Http::STATUS_SERVICE_UNAVAILABLE
                );
            }

            $success = $this->stalwartService->removeAlias($mail, $alias);

            if (!$success) {
                return new DataResponse(
                    ['error' => 'Alias konnte nicht entfernt werden'],
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            $aliases = $this->stalwartService->getAliases($mail);

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
     */
    #[NoAdminRequired]
    public function checkAvailability(string $email = ''): DataResponse {
        try {
            if (empty($email)) {
                return new DataResponse(
                    ['error' => 'Email-Adresse ist erforderlich'],
                    Http::STATUS_BAD_REQUEST
                );
            }

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
    // Postfach-Verwaltung (Admin-only)
    // ========================================================================

    /**
     * Liste aller bestehenden Stalwart-Postfächer (Mailadressen) für
     * Tabellen-Badges in der Benutzerverwaltung.
     */
    #[NoAdminRequired]
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
    #[NoAdminRequired]
    public function getMailbox(string $userId): DataResponse {
        try {
            $user = $this->userManager->get($userId);
            if ($user === null) {
                return new DataResponse(['error' => 'Benutzer nicht gefunden'], Http::STATUS_NOT_FOUND);
            }
            $mail = $this->stalwartService->mailFor($user) ?? $userId;
            return new DataResponse($this->stalwartService->getMailboxStatus($mail));
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Postfach für einen Benutzer anlegen/sicherstellen.
     */
    #[NoAdminRequired]
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

            $mail = $this->stalwartService->mailFor($user);
            if ($mail === null) {
                return new DataResponse(
                    ['error' => 'Keine gültige Mail-Adresse/Domain für diesen Benutzer'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            if ($this->stalwartService->principalExists($mail)) {
                // Bestehendes Postfach: Mail-Gruppen-Mitgliedschaft sicherstellen
                $this->mailGroupService->addUser($user);
                return new DataResponse([
                    'success' => true,
                    'created' => false,
                    'message' => 'Postfach existiert bereits',
                    'status' => $this->stalwartService->getMailboxStatus($mail),
                ]);
            }

            $ok = $this->stalwartService->createPrincipal($mail, bin2hex(random_bytes(24)), $user->getDisplayName());
            if (!$ok) {
                return new DataResponse(['error' => 'Postfach konnte nicht angelegt werden'], Http::STATUS_INTERNAL_SERVER_ERROR);
            }

            // Benutzer mit Postfach in die Mail-Gruppe aufnehmen (smail-Sichtbarkeit)
            $this->mailGroupService->addUser($user);

            return new DataResponse([
                'success' => true,
                'created' => true,
                'email' => $mail,
                'status' => $this->stalwartService->getMailboxStatus($mail),
            ], Http::STATUS_CREATED);
        } catch (\Exception $e) {
            $this->logger->error('AliasApiController: createMailbox fehlgeschlagen', ['userId' => $userId, 'error' => $e->getMessage()]);
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Backfill: legt fehlende Postfächer für alle Nextcloud-Benutzer an.
     */
    #[NoAdminRequired]
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
            $grouped = 0;

            // Mail-Gruppe sicherstellen (für smail-Sichtbarkeit)
            $this->mailGroupService->ensureGroup();

            // Alle Benutzer paginiert durchlaufen (NC34-konform, kein callForAllUsers)
            $limit = 500;
            $offset = 0;
            do {
                $users = $this->userManager->search('', $limit, $offset);
                foreach ($users as $user) {
                    $uid = $user->getUID();
                    if ($this->configService->isHiddenUser($uid) || $this->configService->isAdminUser($uid)) {
                        $skipped++;
                        continue;
                    }
                    // Nur lizenzierte "Souvera User" (Mitglieder der souvera-users-Gruppe)
                    // erhalten Postfächer. "Nextcloud User" werden übersprungen.
                    if (!$this->mailGroupService->isMember($user)) {
                        $skipped++;
                        continue;
                    }
                    $mail = $this->stalwartService->mailFor($user);
                    if ($mail === null) {
                        $noMail++;
                        continue;
                    }
                    if (isset($existing[$mail]) || $this->stalwartService->principalExists($mail)) {
                        // Bestandspostfach: Mail-Gruppen-Mitgliedschaft nachziehen
                        if ($this->mailGroupService->addUser($user)) {
                            $grouped++;
                        }
                        $skipped++;
                        continue;
                    }
                    try {
                        $ok = $this->stalwartService->createPrincipal($mail, bin2hex(random_bytes(24)), $user->getDisplayName());
                        if ($ok) {
                            $created++;
                            if ($this->mailGroupService->addUser($user)) {
                                $grouped++;
                            }
                        } else {
                            $errors++;
                        }
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
                'grouped' => $grouped,
                'mailGroup' => $this->mailGroupService->getInfo(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('AliasApiController: syncMailboxes fehlgeschlagen', ['error' => $e->getMessage()]);
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Info zur Mail-Gruppe (Name, Mitgliederzahl, Status) für das Dashboard.
     */
    #[NoAdminRequired]
    public function getMailGroup(): DataResponse {
        try {
            return new DataResponse($this->mailGroupService->getInfo());
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Postfach-Quota (Speicherlimit) eines Benutzers setzen.
     */
    #[NoAdminRequired]
    public function setMailboxQuota(string $userId, int $quota = 0): DataResponse {
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

            $mail = $this->stalwartService->mailFor($user) ?? $userId;
            if (!$this->stalwartService->principalExists($mail)) {
                return new DataResponse(['error' => 'Kein Postfach für diesen Benutzer vorhanden'], Http::STATUS_BAD_REQUEST);
            }

            $quota = max(0, (int) $quota);
            $ok = $this->stalwartService->setMailboxQuota($mail, $quota);
            if (!$ok) {
                return new DataResponse(['error' => 'Quota konnte nicht gesetzt werden'], Http::STATUS_INTERNAL_SERVER_ERROR);
            }

            return new DataResponse([
                'success' => true,
                'quota' => $quota,
                'status' => $this->stalwartService->getMailboxStatus($mail),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('AliasApiController: setMailboxQuota fehlgeschlagen', ['userId' => $userId, 'error' => $e->getMessage()]);
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
