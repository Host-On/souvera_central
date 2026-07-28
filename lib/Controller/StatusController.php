<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Controller;

use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;

class StatusController extends Controller
{
    public function __construct(
        IRequest $request,
        private IConfig $config,
        private IAppManager $appManager,
    ) {
        parent::__construct('souvera_central', $request);
    }

    #[PublicPage]
    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function devops(): DataResponse
    {
        $apps = ['souvera_mail', 'souvera_central', 'souvera_shield'];
        $result = [];

        foreach ($apps as $appId) {
            $version = $this->appManager->getAppVersion($appId);
            $channel = trim((string) $this->config->getAppValue($appId, 'devops.channel', 'stable'));
            $lastCheck = (int) $this->config->getAppValue($appId, 'devops.last_check', '0');

            $releases = $this->fetchReleases($appId);

            $result[$appId] = [
                'installed' => $version,
                'channel' => $channel,
                'last_check_ago_min' => $lastCheck ? intval((time() - $lastCheck) / 60) : null,
                'releases' => $releases,
            ];
        }

        return new DataResponse($result);
    }

    private function fetchReleases(string $appId): array
    {
        $repo = match ($appId) {
            'souvera_mail' => 'PhiGi87/souvera_mail',
            'souvera_central' => 'PhiGi87/souvera_central',
            'souvera_shield' => 'PhiGi87/souvera_shield',
            default => '',
        };
        if ($repo === '') {
            return [];
        }

        $token = $this->readToken();
        if ($token === '') {
            return [];
        }

        $json = @file_get_contents(
            "https://api.github.com/repos/$repo/releases?per_page=3",
            false,
            stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Souvera-DevOps\r\nAuthorization: Bearer $token\r\nAccept: application/vnd.github+json\r\n",
                    'timeout' => 10,
                ],
            ])
        );

        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        return array_map(fn($r) => [
            'tag' => $r['tag_name'] ?? '',
            'published' => $r['published_at'] ?? '',
        ], $data);
    }

    private function readToken(): string
    {
        try {
            return trim((string) $this->config->getSystemValue('souvera.devops_token', ''));
        } catch (\Throwable) {
            return '';
        }
    }
}
