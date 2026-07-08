<?php

declare(strict_types=1);

/**
 * Souvera Central - Hilfe-Seite (Page Controller)
 *
 * Rendert die Hilfe-Seite im Central-Layout. Anders als die Verwaltung ist die
 * Hilfe für Souvera-User UND Souvera-Admins zugänglich (nicht für reine
 * Nextcloud-User). Diese Controller-Klasse ist von der SouveraAdminMiddleware
 * ausgenommen und prüft die Berechtigung selbst (canSeeHelp).
 */

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class HelpController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private PermissionService $permission,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function index(): TemplateResponse {
        if (!$this->permission->canSeeHelp()) {
            $response = new TemplateResponse(
                'core',
                '403',
                ['message' => 'Kein Zugriff auf die Souvera-Hilfe.'],
                TemplateResponse::RENDER_AS_GUEST
            );
            $response->setStatus(Http::STATUS_FORBIDDEN);
            return $response;
        }

        Util::addScript($this->appName, $this->appName . '-help');
        Util::addStyle($this->appName, 'main');

        return new TemplateResponse($this->appName, 'help', []);
    }
}
