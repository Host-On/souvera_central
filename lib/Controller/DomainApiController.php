<?php

declare(strict_types=1);

/**
 * Souvera Central - Domain Admin API
 *
 * Verwaltung der Mail-Domains (Liste, Anlegen, Entfernen). Alle Endpoints
 * sind über die SouveraAdminMiddleware für Souvera-Admins geschützt.
 * Der CloudManager nutzt dieselben Endpoints bzw. die occ-Befehle —
 * siehe docs/MULTI_DOMAIN.md.
 */

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\Service\DomainManagementService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSConflictException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class DomainApiController extends OCSController
{
    public function __construct(
        string $appName,
        IRequest $request,
        private DomainManagementService $domains,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function list(): DataResponse
    {
        return new DataResponse($this->domains->listDomains());
    }

    #[NoAdminRequired]
    public function create(string $domain = ''): DataResponse
    {
        try {
            $result = $this->domains->addDomain($domain);
        } catch (\InvalidArgumentException $e) {
            throw new OCSBadRequestException($e->getMessage());
        } catch (\RuntimeException $e) {
            throw new OCSBadRequestException($e->getMessage());
        }

        return new DataResponse($result, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function destroy(string $domain): DataResponse
    {
        try {
            $result = $this->domains->removeDomain($domain);
        } catch (\RuntimeException $e) {
            // Domain noch in Benutzung → Conflict mit Begründung.
            throw new OCSConflictException($e->getMessage());
        }

        return new DataResponse($result);
    }
}
