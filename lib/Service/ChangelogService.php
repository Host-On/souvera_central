<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Service;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Changelog viewer data source.
 *
 * Fetches the changelogs of the Souvera apps from the PUBLIC CloudManager
 * endpoints (no auth):
 *   GET {base}/souvera_mail | souvera_central | souvera_shield
 * Response shape (provided by CloudManager):
 *   {"app_id": "...", "app_label": "...", "entries": [{version, date, title, body}]}
 *
 * Results are validated, normalized and cached in appdata for CACHE_TTL
 * seconds; on fetch failure a stale cache is served as fallback, otherwise
 * an empty `entries` list. The base URL is operator-configurable:
 *   occ config:app:set souvera_central changelog_base_url --value https://...
 */
class ChangelogService
{
    private const CACHE_TTL = 600;

    private const APPS = [
        'souvera_mail' => 'Souvera Mail',
        'souvera_central' => 'Souvera Central',
        'souvera_shield' => 'Souvera Shield',
    ];

    public function __construct(
        private IClientService $clientService,
        private IConfig $config,
        private IAppData $appData,
        private LoggerInterface $logger,
    ) {
    }

    public function isKnownApp(string $appId): bool
    {
        return isset(self::APPS[$appId]);
    }

    /**
     * Changelog for one app, served from the CloudManager endpoint.
     *
     * @return array{app_id: string, app_label: string, entries: list<array{version: string, date: string, title: string, body: string}>}|null
     */
    public function getChangelog(string $appId): ?array
    {
        if (!isset(self::APPS[$appId])) {
            return null;
        }

        $cached = $this->readCache($appId);
        if ($cached !== null && $cached['ts'] > time() - self::CACHE_TTL) {
            return $this->buildResponse($appId, $cached['entries']);
        }

        $payload = $this->fetchFromCloudManager($appId);
        if ($payload === null) {
            // Stale cache is better than nothing — network failures must
            // not break the viewer.
            if ($cached !== null) {
                return $this->buildResponse($appId, $cached['entries']);
            }
            $this->logger->warning('Changelog: CloudManager fetch failed for ' . $appId);
            return $this->buildResponse($appId, []);
        }

        $entries = $this->normalizeEntries($payload);
        $this->writeCache($appId, $entries);
        return $this->buildResponse($appId, $entries);
    }

    /**
     * Changelogs of all managed apps (viewer page payload).
     *
     * @return list<array{app_id: string, app_label: string, entries: list<array{version: string, date: string, title: string, body: string}>}>
     */
    public function getAll(): array
    {
        $result = [];
        foreach (array_keys(self::APPS) as $appId) {
            $changelog = $this->getChangelog($appId);
            if ($changelog !== null) {
                $result[] = $changelog;
            }
        }
        return $result;
    }

    /**
     * @param list<array{version: string, date: string, title: string, body: string}> $entries
     * @return array{app_id: string, app_label: string, entries: array}
     */
    private function buildResponse(string $appId, array $entries): array
    {
        return [
            'app_id' => $appId,
            'app_label' => self::APPS[$appId],
            'entries' => $entries,
        ];
    }

    private function getBaseUrl(): string
    {
        $configured = trim((string) $this->config->getAppValue(
            'souvera_central',
            'changelog_base_url',
            'https://cm.host-on.network/api/v1/changelogs'
        ));
        return rtrim($configured, '/');
    }

    /**
     * @return array{app_id: string, app_label: string, entries: array}|null
     */
    private function fetchFromCloudManager(string $appId): ?array
    {
        try {
            $client = $this->clientService->newClient();
            $response = $client->get(
                $this->getBaseUrl() . '/' . $appId,
                [
                    'timeout' => 10,
                    'connect_timeout' => 5,
                    'http_errors' => false,
                ]
            );
            if ($response->getStatusCode() >= 400) {
                $this->logger->warning('Changelog: CloudManager HTTP ' . $response->getStatusCode() . ' for ' . $appId);
                return null;
            }
            $data = json_decode((string) $response->getBody(), true);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            $this->logger->warning('Changelog: CloudManager fetch threw for ' . $appId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate + normalize the CloudManager entries payload; drops any
     * malformed entry (defensive — the endpoint is external).
     *
     * @return list<array{version: string, date: string, title: string, body: string}>
     */
    private function normalizeEntries(array $payload): array
    {
        $entries = $payload['entries'] ?? [];
        if (!is_array($entries)) {
            return [];
        }
        $result = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $version = isset($entry['version']) ? (string) $entry['version'] : '';
            $date = isset($entry['date']) ? (string) $entry['date'] : '';
            $title = isset($entry['title']) ? (string) $entry['title'] : '';
            $body = isset($entry['body']) ? (string) $entry['body'] : '';
            if ($version === '') {
                continue;
            }
            $result[] = [
                'version' => $version,
                'date' => $date,
                'title' => $title,
                'body' => $body,
            ];
        }
        return $result;
    }

    /**
     * @return array{ts: int, entries: array}|null
     */
    private function readCache(string $appId): ?array
    {
        try {
            $folder = $this->getCacheFolder();
            $file = $folder->getFile($appId . '.json');
            $data = json_decode($file->getContent(), true);
            if (is_array($data) && isset($data['ts'], $data['entries']) && is_array($data['entries'])) {
                return $data;
            }
        } catch (NotFoundException $e) {
            // No cache yet — normal on first request.
        } catch (\Throwable $e) {
            $this->logger->warning('Changelog: cache read failed: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * @param list<array{version: string, date: string, title: string, body: string}> $entries
     */
    private function writeCache(string $appId, array $entries): void
    {
        try {
            $folder = $this->getCacheFolder();
            $file = $folder->fileExists($appId . '.json')
                ? $folder->getFile($appId . '.json')
                : $folder->newFile($appId . '.json');
            $file->putContent(json_encode(['ts' => time(), 'entries' => $entries], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            $this->logger->warning('Changelog: cache write failed: ' . $e->getMessage());
        }
    }

    private function getCacheFolder(): \OCP\Files\SimpleFS\ISimpleFolder
    {
        try {
            return $this->appData->getFolder('changelogs');
        } catch (NotFoundException $e) {
            return $this->appData->newFolder('changelogs');
        }
    }
}
