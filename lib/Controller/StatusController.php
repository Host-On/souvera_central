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
        $json = \shell_exec(\sprintf(
            'gh release list -R %s -L 3 --json tagName,publishedAt 2>/dev/null',
            \escapeshellarg($repo)
        ));
        if ($json === null || $json === '') return [];

        $data = \json_decode($json, true);
        if (!\is_array($data)) return [];

        return \array_map(fn($r) => [
            'tag' => $r['tagName'] ?? '',
            'published' => $r['publishedAt'] ?? '',
        ], $data);
    }
}
