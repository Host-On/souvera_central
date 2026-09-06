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
        if ($this->appManager->isInstalled('souvera_documents')) {
            $apps[] = 'souvera_documents';
        }
        $result = [];

        foreach ($apps as $appId) {
            $version = $this->appManager->getAppVersion($appId);
            // EIN zentraler Suite-Channel für alle Apps
            $suiteChannel = trim((string) $this->config->getSystemValue('souvera.update.channel', ''));
            if ($suiteChannel !== 'dev' && $suiteChannel !== 'stable') {
                $suiteChannel = trim((string) $this->config->getAppValue('souvera_central', 'devops.channel', 'stable'));
            }
            $channel = ($suiteChannel === 'dev') ? 'dev' : 'stable';
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

        if ($this->isGitlabRepo($appId)) {
            $json = $this->gitlabApiGet($this->gitlabBase() . '/api/v4/projects/'
                . $this->gitlabProject($repo) . '/releases?per_page=3');
            if ($json === null || !\is_array($json)) return [];
            return \array_map(fn($r) => [
                'tag' => $r['tag_name'] ?? '',
                'published' => $r['released_at'] ?? '',
            ], $json);
        }

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

        if ($this->isGitlabRepo($appId)) {
            $base = $this->gitlabBase();
            $project = $this->gitlabProject($repo);
            $commit = $this->gitlabApiGet("$base/api/v4/projects/$project/repository/branches/"
                . \rawurlencode($branch));
            if ($commit === null || !isset($commit['commit']['id'])) return null;

            $version = null;
            $rawXml = $this->gitlabRawGet("$base/api/v4/projects/$project/repository/files/"
                . \rawurlencode('appinfo/info.xml') . "/raw?ref=" . \rawurlencode($branch));
            if ($rawXml !== null && \preg_match('/<version>([^<]+)<\/version>/', $rawXml, $m)) {
                $version = \trim($m[1]);
            }

            return [
                'sha' => \substr((string) $commit['commit']['id'], 0, 8),
                'message' => \mb_substr((string) ($commit['commit']['message'] ?? ''), 0, 80),
                'version' => $version,
            ];
        }

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
            'souvera_mail' => 'souvera/souvera_mail',
            'souvera_central' => 'souvera/souvera_central',
            'souvera_shield' => 'souvera/souvera_shield',
            'souvera_mailarchiv' => 'souvera/souvera_mailarchiv',
            'souvera_documents' => 'souvera/souvera_documents',
            default => '',
        };
    }

    private function isGitlabRepo(string $appId): bool
    {
        // Seit der GitLab-Migration liegen ALLE Souvera-Apps auf
        // git.host-on.dev — GitHub-Pfade sind toter Übergangscode.
        return true;
    }

    private function gitlabBase(): string
    {
        try {
            $url = \trim((string) $this->config->getSystemValue('souvera.gitlab_url', 'https://git.host-on.dev'));
            return $url !== '' ? \rtrim($url, '/') : 'https://git.host-on.dev';
        } catch (\Throwable) {
            return 'https://git.host-on.dev';
        }
    }

    private function gitlabToken(): string
    {
        try {
            return \trim((string) $this->config->getSystemValue('souvera.gitlab_devops_token', ''));
        } catch (\Throwable) {
            return '';
        }
    }

    private function gitlabProject(string $repo): string
    {
        return \str_replace('/', '%2F', $repo);
    }

    private function gitlabApiGet(string $url): ?array
    {
        $token = $this->gitlabToken();
        if ($token === '') return null;
        $json = @\file_get_contents($url, false, \stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Souvera-DevOps\r\nPRIVATE-TOKEN: $token\r\n",
                'timeout' => 10,
            ],
        ]));
        if ($json === false) return null;
        $data = \json_decode($json, true);
        return \is_array($data) ? $data : null;
    }

    private function gitlabRawGet(string $url): ?string
    {
        $token = $this->gitlabToken();
        if ($token === '') return null;
        $body = @\file_get_contents($url, false, \stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Souvera-DevOps\r\nPRIVATE-TOKEN: $token\r\n",
                'timeout' => 10,
            ],
        ]));
        return ($body !== false) ? $body : null;
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
