<?php

declare(strict_types=1);

/**
 * Souvera Central - Mail Settings API (read-only, for other Souvera apps)
 *
 * Liefert die zentral in Central verwalteten Mail-Einstellungen (aktuell die
 * globale Signatur) an andere Apps – insbesondere souvera_mail. Dieser Controller
 * ist bewusst von der Souvera-Admin-Pflicht AUSGENOMMEN (siehe
 * SouveraAdminMiddleware), damit ihn JEDER angemeldete Souvera-User beim Verfassen
 * einer Mail abfragen kann. Er ist strikt lesend.
 */

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\Service\ConfigService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class MailSettingsApiController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private ConfigService $config,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Zentrale Mail-Einstellungen (globale Signatur) für souvera_mail.
     *
     * @return DataResponse mit { signature_enabled, signature_template,
     *         signature_format, server_side, variables }
     */
    #[NoAdminRequired]
    public function getMailSettings(): DataResponse {
        try {
            return new DataResponse($this->config->getMailSignatureSettings());
        } catch (\Throwable $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
