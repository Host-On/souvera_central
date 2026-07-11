<?php

declare(strict_types=1);

/**
 * Souvera Central - BIMI API Controller (Souvera-Admin)
 *
 * Verwaltet BIMI pro Domain: DMARC prüfen, Logo (SVG) hochladen/validieren,
 * VMC setzen, DNS-Record ausgeben. Autorisierung über SouveraAdminMiddleware
 * (daher #[NoAdminRequired]).
 */

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\Service\BimiService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class BimiApiController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private BimiService $bimi,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function list(): DataResponse {
        $configs = $this->bimi->listConfigs();
        $domains = [];
        foreach (array_keys($configs) as $domain) {
            $domains[] = $this->bimi->getPayload($domain);
        }
        return new DataResponse(['domains' => $domains]);
    }

    #[NoAdminRequired]
    public function get(string $domain): DataResponse {
        if (!$this->bimi->isValidDomain($domain)) {
            return new DataResponse(['error' => 'Ungültige Domain'], Http::STATUS_BAD_REQUEST);
        }
        $payload = $this->bimi->getPayload($domain);
        if ($payload === null) {
            // Noch nicht konfiguriert: leeres Grundgerüst zurückgeben
            $payload = [
                'domain' => $this->bimi->normalizeDomain($domain),
                'selector' => BimiService::SELECTOR,
                'host' => BimiService::SELECTOR . '._bimi.' . $this->bimi->normalizeDomain($domain),
                'type' => 'TXT',
                'record' => null,
                'logoUrl' => $this->bimi->logoUrl($domain),
                'vmcUrl' => null,
                'vmcMode' => 'none',
                'hasLogo' => false,
                'svgSize' => 0,
                'dmarc' => null,
                'dmarcEnforced' => false,
                'ready' => false,
                'status' => 'new',
                'updatedAt' => null,
            ];
        }
        return new DataResponse($payload);
    }

    #[NoAdminRequired]
    public function checkDmarc(string $domain): DataResponse {
        if (!$this->bimi->isValidDomain($domain)) {
            return new DataResponse(['error' => 'Ungültige Domain'], Http::STATUS_BAD_REQUEST);
        }
        return new DataResponse($this->bimi->checkDmarc($domain));
    }

    #[NoAdminRequired]
    public function uploadLogo(string $domain, string $svg = ''): DataResponse {
        if (!$this->bimi->isValidDomain($domain)) {
            return new DataResponse(['error' => 'Ungültige Domain'], Http::STATUS_BAD_REQUEST);
        }
        $result = $this->bimi->saveLogo($domain, $svg);
        if (!$result['ok']) {
            return new DataResponse(
                ['error' => 'SVG nicht BIMI-konform', 'errors' => $result['errors'], 'warnings' => $result['warnings']],
                Http::STATUS_UNPROCESSABLE_ENTITY
            );
        }
        return new DataResponse([
            'ok' => true,
            'warnings' => $result['warnings'],
            'size' => $result['size'],
            'payload' => $this->bimi->getPayload($domain),
        ]);
    }

    #[NoAdminRequired]
    public function setVmc(string $domain, string $mode = 'none', string $url = '', string $pem = ''): DataResponse {
        if (!$this->bimi->isValidDomain($domain)) {
            return new DataResponse(['error' => 'Ungültige Domain'], Http::STATUS_BAD_REQUEST);
        }
        $result = $this->bimi->setVmc($domain, $mode, $url, $pem);
        if (!($result['ok'] ?? false)) {
            return new DataResponse(['error' => $result['error'] ?? 'Fehler'], Http::STATUS_BAD_REQUEST);
        }
        return new DataResponse(['ok' => true, 'payload' => $this->bimi->getPayload($domain)]);
    }

    #[NoAdminRequired]
    public function delete(string $domain): DataResponse {
        $this->bimi->deleteConfig($domain);
        return new DataResponse(['ok' => true]);
    }
}
