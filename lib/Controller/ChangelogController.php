<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\Service\ChangelogService;
use OCA\SouveraCentral\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Internal JSON feed for the changelog viewer.
 *
 * The viewer PAGE lives in the main Central app (PageController /
 * `page#changelogs`); this controller only serves the normalized data
 * to the frontend. The data itself is fetched from the PUBLIC
 * CloudManager endpoints (https://cm.host-on.network/api/v1/changelogs/
 * {app}) — see ChangelogService. Accessible to Souvera users AND
 * Souvera admins (same gate as the help page).
 */
class ChangelogController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private ChangelogService $changelogService,
        private PermissionService $permission,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function all(): JSONResponse
    {
        if (!$this->permission->canSeeHelp()) {
            return new JSONResponse(
                ['message' => 'Kein Zugriff auf die Souvera-Changelogs.'],
                Http::STATUS_FORBIDDEN
            );
        }
        return new JSONResponse($this->changelogService->getAll());
    }
}
