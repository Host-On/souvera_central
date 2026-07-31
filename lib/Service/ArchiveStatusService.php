<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Service;

use OCA\SouveraArchive\Service\CmApiClient;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Liefert den Live-Status des E-Mail-Archivs vom CloudManager.
 * Ergebnisse werden 60 Sekunden gecached.
 *
 * @see ARCHIVE_PLAN §2.2c
 */
class ArchiveStatusService
{
	public function __construct(
		private IConfig $config,
		private ICacheFactory $cacheFactory,
		private LoggerInterface $logger,
	) {}

	public function getStatus(): array
	{
		$enabled = $this->config->getAppValue('souvera_central', 'archive.enabled', '0') === '1';
		if (!$enabled) {
			return ['enabled' => false];
		}

		$cache = $this->cacheFactory->createDistributed('souvera_archive');
		$tenantId = $this->config->getSystemValue('souvera_central.tenant_id', 'default');
		$cached = $cache->get("archive_status_{$tenantId}");
		if ($cached !== null) {
			return json_decode($cached, true);
		}

		$cmApiUrl = $this->config->getSystemValue('souvera_central.cm_api_url', '');
		$cmApiKey = $this->config->getSystemValue('souvera_central.cm_api_key', '');
		if (!$cmApiUrl || !$cmApiKey) {
			return ['enabled' => true, 'error' => 'CM API not configured'];
		}

		try {
			$client = \OCP\Server::get(\OCP\Http\Client\IClientService::class)->newClient();
			$response = $client->get("{$cmApiUrl}/clouds/{$tenantId}/archive/status", [
				'headers' => [
					'Authorization' => 'Bearer ' . $cmApiKey,
					'Accept' => 'application/json',
				],
				'timeout' => 10,
				'verify' => false,
			]);
			$status = json_decode($response->getBody(), true) ?? [];
		} catch (\Throwable $e) {
			$this->logger->warning('ArchiveStatusService: CM API call failed', [
				'exception' => $e->getMessage(),
			]);
			$status = ['chain_status' => 'unreachable'];
		}

		$status['enabled'] = true;
		$cache->set("archive_status_{$tenantId}", json_encode($status), 60);
		return $status;
	}
}
