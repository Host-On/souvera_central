<?php
/**
 * Souvera User Management - Page Controller
 *
 * Verwaltet das Rendern der Haupt-Seite
 */

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Controller;
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

    /**
     * Rendert die Haupt-Seite der App
     *
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        return $this->renderPage('dashboard');
    }

    /**
     * Rendert das Dashboard
     *
     * @NoCSRFRequired
     */
    public function dashboard(): TemplateResponse {
        return $this->renderPage('dashboard');
    }

    /**
     * Rendert die Benutzerverwaltung
     *
     * @NoCSRFRequired
     */
    public function users(): TemplateResponse {
        return $this->renderPage('users');
    }

    /**
     * Rendert "Neuer Benutzer"
     *
     * @NoCSRFRequired
     */
    public function usersNew(): TemplateResponse {
        return $this->renderPage('users', ['action' => 'new']);
    }

    /**
     * Rendert "Benutzer bearbeiten"
     *
     * @NoCSRFRequired
     */
    public function usersEdit(string $id): TemplateResponse {
        return $this->renderPage('users', ['action' => 'edit', 'userId' => $id]);
    }

    /**
     * Rendert die Gruppenverwaltung
     *
     * @NoCSRFRequired
     */
    public function groups(): TemplateResponse {
        return $this->renderPage('groups');
    }

    /**
     * Rendert die Shared Mailboxes Verwaltung
     *
     * @NoCSRFRequired
     */
    public function sharedMailboxes(): TemplateResponse {
        return $this->renderPage('shared-mailboxes');
    }

    /**
     * Rendert die Einstellungen
     *
     * @NoCSRFRequired
     */
    public function settings(): TemplateResponse {
        return $this->renderPage('settings');
    }

    /**
     * Helper: Rendert die Seite mit Initial-Route und optionalen Daten
     */
    private function renderPage(string $initialRoute, array $additionalData = []): TemplateResponse {
        // Lade Vue.js JavaScript
        Util::addScript($this->appName, 'souvera_central-main');

        // Lade CSS
        Util::addStyle($this->appName, 'main');

        // Merge initial route mit zusätzlichen Daten
        $templateData = array_merge([
            'initialRoute' => $initialRoute
        ], $additionalData);

        // Render Template mit Initial-Route als Parameter
        return new TemplateResponse($this->appName, 'main', $templateData);
    }
}
