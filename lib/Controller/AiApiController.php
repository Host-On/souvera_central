<?php

declare(strict_types=1);

/**
 * Souvera Central - AI Admin API
 *
 * Status- und Steuer-Endpoints für die KI-Funktion, CRUD für die internen
 * KB-Artikel sowie MCP-Token-Verwaltung. Alle Endpoints sind über die
 * SouveraAdminMiddleware für Souvera-Admins geschützt.
 *
 * Die KB-Artikel werden über den MCP-Endpoint (`/mcp`) an den Nextcloud-
 * Agenten ausgeliefert; der Zugriffs-Token wird intern (Shared API) an den
 * Agenten übergeben — siehe docs/SHARED_AI_MCP.md.
 */

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\Db\AiKbArticle;
use OCA\SouveraCentral\Service\AiConfigService;
use OCA\SouveraCentral\Service\AiKbService;
use OCA\SouveraCentral\Service\AiMcpTokenService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IURLGenerator;

class AiApiController extends OCSController
{
    public function __construct(
        string $appName,
        IRequest $request,
        private AiConfigService $aiConfig,
        private AiKbService $kb,
        private AiMcpTokenService $mcpToken,
        private IURLGenerator $urlGenerator,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function status(): DataResponse
    {
        $snap = $this->aiConfig->snapshot();
        $snap['mcp']['endpoint'] = $this->urlGenerator->linkToRouteAbsolute('souvera_central.mcp.call');
        return new DataResponse($snap);
    }

    #[NoAdminRequired]
    public function enable(): DataResponse
    {
        $this->aiConfig->setEnabled(true);
        return $this->status();
    }

    #[NoAdminRequired]
    public function disable(): DataResponse
    {
        $this->aiConfig->setEnabled(false);
        return $this->status();
    }

    #[NoAdminRequired]
    public function mcpRotate(): DataResponse
    {
        $this->mcpToken->rotateToken();
        return $this->status();
    }

    // ================================================================
    // Knowledge Base CRUD
    // ================================================================

    /**
     * Liste aller KB-Artikel (ohne Vollinhalt, mit Auszug).
     */
    #[NoAdminRequired]
    public function kbList(): DataResponse
    {
        $this->kb->ensureSeeded();

        $files = array_map(function (AiKbArticle $a) {
            $out = $a->toArray(false);
            $out['excerpt'] = $this->kb->excerpt($a);
            return $out;
        }, $this->kb->list());

        return new DataResponse(['articles' => $files, 'total' => count($files)]);
    }

    #[NoAdminRequired]
    public function kbGet(int $id): DataResponse
    {
        $article = $this->kb->get($id);
        if ($article === null) {
            throw new OCSNotFoundException('KB article not found');
        }
        return new DataResponse($article->toArray());
    }

    #[NoAdminRequired]
    public function kbCreate(string $title = '', string $content = '', int $sortOrder = 0): DataResponse
    {
        if (trim($title) === '') {
            return new DataResponse(['error' => 'Title is required'], Http::STATUS_BAD_REQUEST);
        }
        $article = $this->kb->create($title, $content, $sortOrder);
        return new DataResponse($article->toArray(), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function kbUpdate(int $id, string $title = '', string $content = '', ?int $sortOrder = null): DataResponse
    {
        if (trim($title) === '') {
            return new DataResponse(['error' => 'Title is required'], Http::STATUS_BAD_REQUEST);
        }
        $article = $this->kb->update($id, $title, $content, $sortOrder);
        if ($article === null) {
            throw new OCSNotFoundException('KB article not found');
        }
        return new DataResponse($article->toArray());
    }

    #[NoAdminRequired]
    public function kbDelete(int $id): DataResponse
    {
        if (!$this->kb->delete($id)) {
            throw new OCSNotFoundException('KB article not found');
        }
        return new DataResponse(['deleted' => true, 'id' => $id]);
    }
}
