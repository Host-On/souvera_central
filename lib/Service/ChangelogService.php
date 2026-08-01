<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Service;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Public changelog API for the Souvera apps.
 *
 * Fetches the canonical `CHANGELOG.md` of each app repository (public
 * GitHub, main branch), parses the `## [X.Y.Z] — YYYY-MM-DD (Title)`
 * sections and serves them as JSON. Results are cached in appdata for
 * CACHE_TTL seconds; on fetch failure a stale cache is served as
 * fallback, otherwise an empty `entries` list.
 */
class ChangelogService
{
    private const CACHE_TTL = 600;
    private const BRANCH = 'main';

    private const APPS = [
        'souvera_mail' => ['repo' => 'PhiGi87/souvera_mail', 'label' => 'Souvera Mail'],
        'souvera_central' => ['repo' => 'PhiGi87/souvera_central', 'label' => 'Souvera Central'],
        'souvera_shield' => ['repo' => 'PhiGi87/souvera_shield', 'label' => 'Souvera Shield'],
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
     * @return array{app_id: string, app_label: string, entries: list<array{version: string, date: string, title: string, body: string}>}|null
     */
    public function getChangelog(string $appId): ?array
    {
        if (!isset(self::APPS[$appId])) {
            return null;
        }
        $spec = self::APPS[$appId];

        $cached = $this->readCache($appId);
        if ($cached !== null && $cached['ts'] > time() - self::CACHE_TTL) {
            return $this->buildResponse($appId, $spec, $cached['entries']);
        }

        $markdown = $this->fetchMarkdown($spec['repo']);
        if ($markdown === null) {
            // Stale cache is better than nothing — network failures must
            // not break the public endpoint.
            if ($cached !== null) {
                return $this->buildResponse($appId, $spec, $cached['entries']);
            }
            $this->logger->warning('Changelog: fetch failed for ' . $appId);
            return $this->buildResponse($appId, $spec, []);
        }

        $entries = $this->parseMarkdown($markdown);
        $this->writeCache($appId, $entries);
        return $this->buildResponse($appId, $spec, $entries);
    }

    /**
     * @param array{repo: string, label: string} $spec
     * @param list<array{version: string, date: string, title: string, body: string}> $entries
     * @return array{app_id: string, app_label: string, entries: array}
     */
    private function buildResponse(string $appId, array $spec, array $entries): array
    {
        return [
            'app_id' => $appId,
            'app_label' => $spec['label'],
            'entries' => $entries,
        ];
    }

    private function fetchMarkdown(string $repo): ?string
    {
        try {
            $client = $this->clientService->newClient();
            $response = $client->get(
                'https://raw.githubusercontent.com/' . $repo . '/' . self::BRANCH . '/CHANGELOG.md',
                [
                    'timeout' => 10,
                    'connect_timeout' => 5,
                    'http_errors' => false,
                ]
            );
            if ($response->getStatusCode() >= 400) {
                $this->logger->warning('Changelog: HTTP ' . $response->getStatusCode() . ' for ' . $repo);
                return null;
            }
            $body = (string) $response->getBody();
            return $body === '' ? null : $body;
        } catch (\Throwable $e) {
            $this->logger->warning('Changelog: fetch threw for ' . $repo . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse the canonical changelog format:
     *   ## [1.2.3] — 2026-08-01 (Short title)
     *   <body lines until the next ## heading>
     *
     * Headings without a version/date pair (e.g. `## [Unreleased]`) act
     * as section separators and do not produce an entry.
     *
     * @return list<array{version: string, date: string, title: string, body: string}>
     */
    private function parseMarkdown(string $markdown): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown);
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^##\s*\[([^\]]+)\]\s*(?:—|-)\s*(\d{4}-\d{2}-\d{2})\s*(?:\(([^)]*)\))?/i', $line, $m)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = [
                    'version' => trim($m[1]),
                    'date' => $m[2],
                    'title' => trim($m[3] ?? ''),
                    'body' => '',
                ];
                continue;
            }
            if ($current === null) {
                continue;
            }
            if (preg_match('/^##\s/', $line)) {
                // Next heading (without date) ends the current entry.
                $entries[] = $current;
                $current = null;
                continue;
            }
            $current['body'] .= ($current['body'] === '' ? '' : "\n") . $line;
        }
        if ($current !== null) {
            $entries[] = $current;
        }

        foreach ($entries as &$entry) {
            $entry['body'] = trim($entry['body']);
            if ($entry['title'] === '') {
                // Fall back to the first body line as the title.
                $firstLine = strtok($entry['body'], "\n");
                $entry['title'] = $firstLine !== false && $firstLine !== '' ? trim($firstLine) : 'Release';
                $entry['body'] = ltrim(substr($entry['body'], strlen($firstLine !== false ? $firstLine : '')));
                $entry['body'] = trim($entry['body']);
            }
        }
        unset($entry);

        return $entries;
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
            $file = $folder->newFile($appId . '.json');
            $file->putContent(json_encode(['ts' => time(), 'entries' => $entries], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            $this->logger->warning('Changelog: cache write failed: ' . $e->getMessage());
        }
    }

    private function getCacheFolder(): \OCP\Files\Folder
    {
        $folder = null;
        try {
            $folder = $this->appData->getFolder('changelogs');
        } catch (NotFoundException $e) {
            $folder = $this->appData->newFolder('changelogs');
        }
        if (!$folder instanceof \OCP\Files\Folder) {
            throw new \RuntimeException('appdata changelogs folder is not a folder');
        }
        return $folder;
    }
}
