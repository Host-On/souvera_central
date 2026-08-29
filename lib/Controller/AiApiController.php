<?php

declare(strict_types=1);

/**
 * Souvera Central - AI Admin API
 *
 * Status- und Steuer-Endpoints für die KI-Funktion sowie die lokale Ansicht
 * der Wissensbasis (resources/ai/*.md). Alle Endpoints sind über die
 * SouveraAdminMiddleware für Souvera-Admins geschützt.
 *
 * Hinweis zur Wissensbasis: Die Dateien werden im Repository gepflegt und
 * vom CloudManager per GitHub-API in die KI-Clouds gespiegelt. Diese
 * Endpoints dienen der lokalen Anzeige — die Pflege bleibt im Repository.
 */

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\Service\AiConfigService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class AiApiController extends OCSController
{
    public function __construct(
        string $appName,
        IRequest $request,
        private AiConfigService $aiConfig,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function status(): DataResponse
    {
        return new DataResponse($this->aiConfig->snapshot());
    }

    #[NoAdminRequired]
    public function enable(): DataResponse
    {
        $this->aiConfig->setEnabled(true);
        return new DataResponse($this->aiConfig->snapshot());
    }

    #[NoAdminRequired]
    public function disable(): DataResponse
    {
        $this->aiConfig->setEnabled(false);
        return new DataResponse($this->aiConfig->snapshot());
    }

    /**
     * Lokale Ansicht der Wissensbasis: alle Markdown-Dateien mit Inhalt.
     *
     * @return DataResponse list<array{name:string, title:string, content:string}>
     */
    #[NoAdminRequired]
    public function kbList(): DataResponse
    {
        $dir = \dirname(__DIR__, 2) . '/resources/ai';
        if (!is_dir($dir)) {
            return new DataResponse(['files' => []]);
        }

        $files = [];
        foreach (glob($dir . '/*.md') ?: [] as $path) {
            $content = (string) file_get_contents($path);
            $title = '';
            if (preg_match('/^#\s+(.+)$/m', $content, $m) === 1) {
                $title = trim($m[1]);
            }
            $files[] = [
                'name' => basename($path),
                'title' => $title,
                'content' => $content,
            ];
        }
        sort($files);

        return new DataResponse(['files' => $files]);
    }
}
