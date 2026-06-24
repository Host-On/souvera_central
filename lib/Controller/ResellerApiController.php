<?php
/**
 * Souvera Central - Reseller API Controller
 *
 * API-Endpunkt für Reseller-Informationen (Support-URL, etc.)
 */

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\Http\Client\IClientService;
use OCA\SouveraCentral\Service\ConfigService;
use Psr\Log\LoggerInterface;

class ResellerApiController extends OCSController {
    private $configService;
    private $clientService;
    private $logger;

    public function __construct(
        string $appName,
        IRequest $request,
        ConfigService $configService,
        IClientService $clientService,
        LoggerInterface $logger
    ) {
        parent::__construct($appName, $request);
        $this->configService = $configService;
        $this->clientService = $clientService;
        $this->logger = $logger;
    }

    /**
     * Reseller-Informationen abrufen
     *
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getResellerInfo(): DataResponse {
        try {
            // Cloud UUID aus Config lesen
            $cloudUUID = $this->configService->getCloudUUID();

            if (!$cloudUUID) {
                $this->logger->warning('Cloud UUID not configured in config.php (souvera_central.cloud_uuid)');
                return new DataResponse([
                    'support_url' => null,
                    'url' => null,
                    'name' => null,
                    'error' => 'Cloud UUID not configured'
                ], Http::STATUS_OK);
            }

            // HTTP Client erstellen
            $client = $this->clientService->newClient();

            // POST Request an Philip's API
            $response = $client->post('https://manage.souvera.eu/api/public/workspace/reseller', [
                'json' => [
                    'uuid' => $cloudUUID
                ],
                'timeout' => 5,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody();

            if ($statusCode !== 200) {
                $this->logger->error('Reseller API returned non-200 status: ' . $statusCode);
                return new DataResponse([
                    'support_url' => null,
                    'url' => null,
                    'name' => null,
                    'error' => 'API request failed'
                ], Http::STATUS_OK);
            }

            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Failed to parse Reseller API response: ' . json_last_error_msg());
                return new DataResponse([
                    'support_url' => null,
                    'url' => null,
                    'name' => null,
                    'error' => 'Invalid JSON response'
                ], Http::STATUS_OK);
            }

            // Prüfe auf success Flag
            if (!isset($data['success']) || $data['success'] !== true) {
                $errorMsg = $data['message'] ?? 'Unknown error';
                $this->logger->warning('Reseller API returned error: ' . $errorMsg);
                return new DataResponse([
                    'support_url' => null,
                    'url' => null,
                    'name' => null,
                    'error' => $errorMsg
                ], Http::STATUS_OK);
            }

            // Daten sind in data.data verschachtelt
            $resellerData = $data['data'] ?? [];

            return new DataResponse([
                'support_url' => $resellerData['support_url'] ?? null,
                'url' => $resellerData['url'] ?? null,
                'name' => $resellerData['name'] ?? null,
            ], Http::STATUS_OK);

        } catch (\Exception $e) {
            $this->logger->error('Error fetching reseller info: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return new DataResponse([
                'support_url' => null,
                'url' => null,
                'name' => null,
                'error' => $e->getMessage()
            ], Http::STATUS_OK);
        }
    }
}
