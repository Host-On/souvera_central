<?php

declare(strict_types=1);

/**
 * Souvera Central - Page Controller
 *
 * Verwaltet das Rendern der Haupt-Seite.
 * NC34: Security-Annotationen als PHP-8-Attribute (Docblock-Annotationen
 * wie @NoCSRFRequired werden ab Nextcloud 34 nicht mehr unterstützt).
 */

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request
    ) {
        parent::__construct($appName, $request);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function index(): TemplateResponse {
        return $this->renderPage('dashboard');
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function dashboard(): TemplateResponse {
        return $this->renderPage('dashboard');
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function users(): TemplateResponse {
        return $this->renderPage('users');
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function usersNew(): TemplateResponse {
        return $this->renderPage('users', ['action' => 'new']);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function usersEdit(string $id): TemplateResponse {
        return $this->renderPage('users', ['action' => 'edit', 'userId' => $id]);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function groups(): TemplateResponse {
        return $this->renderPage('groups');
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function sharedMailboxes(): TemplateResponse {
        return $this->renderPage('shared-mailboxes');
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function settings(): TemplateResponse {
        return $this->renderPage('settings');
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function changelogs(): TemplateResponse {
        return $this->renderPage('changelogs');
    }

    /**
     * Helper: Rendert die Seite mit Initial-Route und optionalen Daten
     */
    private function renderPage(string $initialRoute, array $additionalData = []): TemplateResponse {
        Util::addScript($this->appName, 'souvera_central-main');
        Util::addStyle($this->appName, 'main');

        $templateData = array_merge([
            'initialRoute' => $initialRoute
        ], $additionalData);

        return new TemplateResponse($this->appName, 'main', $templateData);
    }
}
