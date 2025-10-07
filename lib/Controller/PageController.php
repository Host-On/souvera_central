<?php
/**
 * Souvera User Management - Page Controller
 *
 * Verwaltet das Rendern der Haupt-Seite
 */

namespace OCA\SouveraUserManagement\Controller;

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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        // Lade Vue.js JavaScript
        Util::addScript($this->appName, 'souvera_user_management-main');

        // Lade CSS
        Util::addStyle($this->appName, 'main');

        // Render Template mit App-Navigation
        return new TemplateResponse($this->appName, 'main');
    }
}
