<?php

declare(strict_types=1);

/**
 * Souvera Central - Souvera-Admin Middleware
 *
 * Öffnet die Central-Controller für delegierte Administratoren: Da alle
 * Route-Methoden mit #[NoAdminRequired] annotiert sind (sonst würde die
 * NC-Core-SecurityMiddleware Nicht-Superadmins blocken), übernimmt diese
 * Middleware die eigentliche Autorisierung. Zugriff nur für Souvera-Admins
 * (NC-Superadmin ODER Mitglied der scadmin-Gruppe), sonst 403.
 */

namespace OCA\SouveraCentral\Middleware;

use OCA\SouveraCentral\Controller\BimiPublicController;
use OCA\SouveraCentral\Controller\HelpApiController;
use OCA\SouveraCentral\Controller\HelpController;
use OCA\SouveraCentral\Exception\NotSouveraAdminException;
use OCA\SouveraCentral\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCSController;

class SouveraAdminMiddleware extends Middleware {
    public function __construct(
        private PermissionService $permission,
    ) {
    }

    public function beforeController(Controller $controller, string $methodName): void {
        // Hilfe-Controller haben eine eigene, gelockerte Prüfung (canSeeHelp)
        // und sind daher von der Souvera-Admin-Pflicht ausgenommen.
        if ($controller instanceof HelpController || $controller instanceof HelpApiController) {
            return;
        }
        // Öffentlicher BIMI-Endpunkt: bewusst ohne Login (PublicPage) – der
        // DNS-Record ist öffentliche Information für den CloudManager/Mail-Provider.
        if ($controller instanceof BimiPublicController) {
            return;
        }
        if ($this->permission->isSouveraAdmin()) {
            return;
        }
        throw new NotSouveraAdminException();
    }

    public function afterException(Controller $controller, string $methodName, \Exception $exception): Response {
        if ($exception instanceof NotSouveraAdminException) {
            // API-/OCS-Controller: JSON-403 (Frontend wertet error.response.status aus)
            if ($controller instanceof OCSController) {
                return new DataResponse(
                    ['message' => $exception->getMessage(), 'error' => $exception->getMessage()],
                    Http::STATUS_FORBIDDEN
                );
            }
            // Seiten-Controller: 403-Seite anzeigen.
            $response = new TemplateResponse(
                'core',
                '403',
                ['message' => $exception->getMessage()],
                TemplateResponse::RENDER_AS_GUEST
            );
            $response->setStatus(Http::STATUS_FORBIDDEN);
            return $response;
        }
        throw $exception;
    }
}
