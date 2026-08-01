<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\Service\ChangelogService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Public changelog viewer for the Souvera apps.
 *
 * GET /api/v1/changelogs/{appId} — no authentication required.
 * Sources are the per-repo CHANGELOG.md files on GitHub (public).
 */
class ChangelogController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private ChangelogService $changelogService,
    ) {
        parent::__construct($appName, $request);
    }

    #[PublicPage]
    #[NoCSRFRequired]
    public function get(string $appId): JSONResponse
    {
        if (!$this->changelogService->isKnownApp($appId)) {
            return new JSONResponse(
                ['error' => 'Unknown app', 'app_id' => $appId],
                Http::STATUS_NOT_FOUND
            );
        }
        return new JSONResponse($this->changelogService->getChangelog($appId));
    }
}
