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
}
