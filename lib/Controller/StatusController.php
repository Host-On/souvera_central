<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;

class StatusController extends Controller
{
    public function __construct(IRequest $request, private IConfig $config)
    {
        parent::__construct('souvera_central', $request);
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     */
    public function devops(): DataResponse
    {
        $apps = ['souvera_mail', 'souvera_central', 'souvera_shield'];
        $result = [];

        foreach ($apps as $appId) {
            $version = \OC_App::getAppVersion($appId);
            $channel = \trim((string) $this->config->getAppValue($appId, 'devops.channel', 'stable'));
            $lastCheck = (int) $this->config->getAppValue($appId, 'devops.last_check', '0');

            $repo = match ($appId) {
                'souvera_mail' => 'PhiGi87/souvera_mail',
                'souvera_central' => 'PhiGi87/souvera_central',
                'souvera_shield' => 'PhiGi87/souvera_shield',
            };

            $latest = $this->fetchLatestThree($repo);

            $result[$appId] = [
                'installed' => $version,
                'channel' => $channel,
                'last_check_ago_min' => $lastCheck ? \intval((\time() - $lastCheck) / 60) : null,
                'releases' => $latest,
            ];
        }

        return new DataResponse($result);
    }

    private function fetchLatestThree(string $repo): array
    {
        $url = "https://api.github.com/repos/$repo/releases?per_page=3";
        $ctx = \stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Souvera-DevOps\r\nAccept: application/vnd.github+json\r\n",
                'timeout' => 10,
            ],
        ]);
        $json = @\file_get_contents($url, false, $ctx);
        if ($json === false) return [];

        $data = \json_decode($json, true);
        if (!\is_array($data)) return [];

        return \array_map(fn($r) => [
            'tag' => $r['tag_name'] ?? '',
            'published' => $r['published_at'] ?? '',
        ], $data);
    }
}
