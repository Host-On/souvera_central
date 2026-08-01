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
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

/**
 * Changelog viewer for the Souvera apps.
 *
 * The DATA is fetched from the public CloudManager endpoints
 * (https://cm.host-on.network/api/v1/changelogs/{app}) — this controller
 * only renders the viewer page and serves the normalized payload to its
 * own frontend. Accessible to Souvera users AND Souvera admins (same
 * gate as the help page).
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
    public function index(): TemplateResponse
    {
        if (!$this->permission->canSeeHelp()) {
            $response = new TemplateResponse(
                'core',
                '403',
                ['message' => 'Kein Zugriff auf die Souvera-Changelogs.'],
                TemplateResponse::RENDER_AS_GUEST
            );
            $response->setStatus(Http::STATUS_FORBIDDEN);
            return $response;
        }

        Util::addScript($this->appName, $this->appName . '-changelog');
        Util::addStyle($this->appName, 'main');

        return new TemplateResponse($this->appName, 'changelog', []);
    }

    /**
     * Internal JSON feed for the viewer frontend.
     */
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
