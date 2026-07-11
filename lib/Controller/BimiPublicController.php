<?php

declare(strict_types=1);

/**
 * Souvera Central - Öffentlicher BIMI-Endpunkt (ohne Login)
 *
 * Stellt den fertigen BIMI-DNS-Record als JSON bereit, damit der Souvera
 * CloudManager ihn ohne Authentifizierung abfragen und an den Kunden
 * durchreichen kann. Liefert zusätzlich das gehostete Logo-SVG (l=) und ggf.
 * das VMC-PEM (a=) unter stabilen, öffentlichen URLs aus.
 *
 * Sicherheit: Der DNS-Record ist per Definition öffentliche Information; daher
 * bewusst ohne Auth (#[PublicPage]). Von der SouveraAdminMiddleware ausgenommen.
 */

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\Service\BimiService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class BimiPublicController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private BimiService $bimi,
    ) {
        parent::__construct($appName, $request);
    }

    #[PublicPage]
    #[NoCSRFRequired]
    public function record(string $domain): JSONResponse {
        if (!$this->bimi->isValidDomain($domain)) {
            return new JSONResponse(['error' => 'invalid_domain'], Http::STATUS_BAD_REQUEST);
        }
        $payload = $this->bimi->getPayload($domain);
        if ($payload === null) {
            return new JSONResponse(['error' => 'not_configured', 'domain' => $this->bimi->normalizeDomain($domain)], Http::STATUS_NOT_FOUND);
        }
        $response = new JSONResponse($payload);
        $response->addHeader('Access-Control-Allow-Origin', '*');
        $response->cacheFor(300, false, true);
        return $response;
    }

    #[PublicPage]
    #[NoCSRFRequired]
    public function logo(string $domain): DataDisplayResponse|JSONResponse {
        $svg = $this->bimi->getSvg($domain);
        if ($svg === null) {
            return new JSONResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
        }
        $response = new DataDisplayResponse($svg, Http::STATUS_OK, ['Content-Type' => 'image/svg+xml']);
        $response->addHeader('Access-Control-Allow-Origin', '*');
        $response->cacheFor(3600, false, true);
        return $response;
    }

    #[PublicPage]
    #[NoCSRFRequired]
    public function vmc(string $domain): DataDisplayResponse|JSONResponse {
        $pem = $this->bimi->getVmcPem($domain);
        if ($pem === null) {
            return new JSONResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
        }
        $response = new DataDisplayResponse($pem, Http::STATUS_OK, ['Content-Type' => 'application/pem-certificate-chain']);
        $response->addHeader('Access-Control-Allow-Origin', '*');
        $response->cacheFor(3600, false, true);
        return $response;
    }
}
