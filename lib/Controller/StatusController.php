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
        if ($this->appManager->isInstalled('souvera_mailarchiv')) {
            $apps[] = 'souvera_mailarchiv';
        }
        $result = [];

        foreach ($apps as $appId) {
            $version = $this->appManager->getAppVersion($appId);
            $channel = trim((string) $this->config->getAppValue($appId, 'devops.channel', 'stable'));
            $lastCheck = (int) $this->config->getAppValue($appId, 'devops.last_check', '0');
            $branch = trim((string) $this->config->getAppValue($appId, 'devops.branch', 'main'));

            $releases = $this->fetchReleases($appId);
            $branchHead = ($channel === 'dev') ? $this->fetchBranchHead($appId, $branch) : null;

            // On dev channel, prepend the branch HEAD as a synthetic
            // "release" so the CM's SouveraDevopsPanel (which only reads
            // releases[0].tag) shows the current branch version instead
            // of the last GitHub release.
            if ($branchHead !== null && $branchHead['version'] !== null) {
                \array_unshift($releases, [
                    'tag' => 'v' . $branchHead['version'],
                    'published' => $branchHead['message'] ?? '',
                ]);
            }

            $result[$appId] = [
                'installed' => $version,
                'channel' => $channel,
                'last_check_ago_min' => $lastCheck ? intval((time() - $lastCheck) / 60) : null,
                'releases' => $releases,
                'branch_head' => $branchHead,
            ];
        }

        return new DataResponse($result);
    }

    private function fetchReleases(string $appId): array
    {
        $repo = $this->repoFor($appId);
        if ($repo === '') return [];
        $json = $this->apiGet("https://api.github.com/repos/$repo/releases?per_page=3");
        if ($json === null || !\is_array($json)) return [];
        return \array_map(fn($r) => [
            'tag' => $r['tag_name'] ?? '',
            'published' => $r['published_at'] ?? '',
        ], $json);
    }

    /**
     * Returns HEAD commit info + info.xml version from the branch.
     */
    private function fetchBranchHead(string $appId, string $branch): ?array
    {
        $repo = $this->repoFor($appId);
        if ($repo === '') return null;

        $commit = $this->apiGet("https://api.github.com/repos/$repo/commits/$branch");
        if ($commit === null || empty($commit['sha'])) return null;

        $sha = (string) $commit['sha'];
        $msg = \mb_substr((string) ($commit['commit']['message'] ?? ''), 0, 80);

        // Read info.xml from the branch HEAD to get the version number.
        $version = null;
        $rawXml = $this->rawGet(
            "https://raw.githubusercontent.com/$repo/$branch/appinfo/info.xml"
        );
        if ($rawXml !== null) {
            if (\preg_match('/<version>([^<]+)<\/version>/', $rawXml, $m)) {
                $version = \trim($m[1]);
            }
        }

        return [
            'sha' => \substr($sha, 0, 8),
            'message' => $msg,
            'version' => $version,
        ];
    }

    private function apiGet(string $url): ?array
    {
        $token = $this->readToken();
        if ($token === '') return null;
        $json = @\file_get_contents($url, false, \stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Souvera-DevOps\r\nAuthorization: Bearer $token\r\nAccept: application/vnd.github+json\r\n",
                'timeout' => 10,
            ],
        ]));
        if ($json === false) return null;
        $data = \json_decode($json, true);
        return \is_array($data) ? $data : null;
    }

    private function rawGet(string $url): ?string
    {
        $token = $this->readToken();
        if ($token === '') return null;
        $body = @\file_get_contents($url, false, \stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Souvera-DevOps\r\nAuthorization: Bearer $token\r\n",
                'timeout' => 10,
            ],
        ]));
        return ($body !== false) ? $body : null;
    }

    private function repoFor(string $appId): string
    {
        return match ($appId) {
            'souvera_mail' => 'PhiGi87/souvera_mail',
            'souvera_central' => 'PhiGi87/souvera_central',
            'souvera_shield' => 'PhiGi87/souvera_shield',
            'souvera_mailarchiv' => 'PhiGi87/souvera_mailarchiv',
            default => '',
        };
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
