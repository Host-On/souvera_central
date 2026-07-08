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

use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class HelpController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private PermissionService $permission,
        private ConfigService $config,
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

        $response = new TemplateResponse($this->appName, 'help', []);

        // Screenshots/Bilder der BookStack-Doku stammen von einer externen Domain
        // (z. B. https://doku.souvera.eu). Ohne CSP-Freigabe blockt Nextcloud diese
        // Bilder. Bilder von der BookStack-Domain daher explizit erlauben.
        $policy = new ContentSecurityPolicy();
        $bookStackUrl = $this->config->getBookStackUrl();
        if ($bookStackUrl !== '') {
            $policy->addAllowedImageDomain($bookStackUrl);
        }
        $response->setContentSecurityPolicy($policy);

        return $response;
    }
}
