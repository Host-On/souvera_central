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
use OCA\SouveraCentral\Service\SignatureInjectionService;
use OCA\SouveraCentral\Service\SignatureResolverService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;

class MailSettingsApiController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private ConfigService $config,
        private IUserSession $userSession,
        private SignatureResolverService $resolver,
        private IDBConnection $db,
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

    /**
     * Finale, gerenderte Signatur für den ANGEMELDETEN User — die Quelle
     * für die Compose-Injection in souvera_mail (Webmail sieht die
     * Signatur beim Schreiben, nicht erst beim Empfang).
     */
    #[NoAdminRequired]
    public function getResolvedSignature(): DataResponse {
        $user = $this->userSession->getUser();
        $email = $user?->getEMailAddress() ?? '';
        if ($email === '') {
            return new DataResponse(['found' => false, 'error' => 'Keine E-Mail-Adresse im Profil']);
        }
        $sig = $this->resolver->resolveForEmail($email);
        if ($sig === null) {
            return new DataResponse(['found' => false]);
        }
        return new DataResponse([
            'found' => true,
            'html' => $sig['html'],
            'text' => $sig['text'],
            'source' => $sig['source'],
            'contentId' => SignatureInjectionService::LOGO_CONTENT_ID,
        ]);
    }

    /**
     * Logo-Bytes für die WYSIWYG-Vorschau im Webmail-Composer
     * (angemeldete Souvera-User; das HOOK-Logo wird serverseitig als
     * Inline-CID eingebettet, hier geht es nur um die Vorschau).
     */
    #[NoAdminRequired]
    public function getSignatureLogo(): DataDownloadResponse {
        try {
            $row = $this->db->fetchAssociative('SELECT name, mime, data FROM *PREFIX*souvera_central_sig_assets LIMIT 1');
            if (!\is_array($row) || !isset($row['data'])) {
                return new DataDownloadResponse('', 'logo.png', 'image/png');
            }
            return new DataDownloadResponse(
                (string) $row['data'],
                (string) ($row['name'] ?? 'logo.png'),
                (string) ($row['mime'] ?? 'image/png')
            );
        } catch (\Throwable $e) {
            return new DataDownloadResponse('', 'logo.png', 'image/png');
        }
    }
}
