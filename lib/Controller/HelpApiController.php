<?php

declare(strict_types=1);

/**
 * Souvera Central - Hilfe API (BookStack-Proxy)
 *
 * Liefert der Central-Hilfe-Seite den Doku-Navigationsbaum + Seiteninhalte aus
 * BookStack. Zugriff für Souvera-User UND Souvera-Admins (canSeeHelp); von der
 * SouveraAdminMiddleware ausgenommen (eigene Berechtigungsprüfung). Souvera-User
 * sehen nur die für sie freigegebenen Regale/Bücher - der Zugriff auf einzelne
 * Bücher/Seiten wird serverseitig gegen die erlaubten Buch-IDs geprüft, damit
 * ein Souvera-User keine Admin-Doku per ID abrufen kann.
 */

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\Service\BookStackService;
use OCA\SouveraCentral\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class HelpApiController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private PermissionService $permission,
        private BookStackService $bookStack,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function tree(): DataResponse {
        if (!$this->permission->canSeeHelp()) {
            return $this->forbidden();
        }
        if (!$this->bookStack->isConfigured()) {
            return new DataResponse(['configured' => false, 'shelves' => []]);
        }
        return new DataResponse([
            'configured' => true,
            'shelves' => $this->bookStack->getTree($this->permission->isSouveraAdmin()),
        ]);
    }

    #[NoAdminRequired]
    public function book(int $id): DataResponse {
        if (!$this->permission->canSeeHelp()) {
            return $this->forbidden();
        }
        if (!in_array($id, $this->bookStack->allowedBookIds($this->permission->isSouveraAdmin()), true)) {
            return $this->forbidden();
        }
        $book = $this->bookStack->getBookContents($id);
        if ($book === null) {
            return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
        }
        return new DataResponse($book);
    }

    #[NoAdminRequired]
    public function page(int $id): DataResponse {
        if (!$this->permission->canSeeHelp()) {
            return $this->forbidden();
        }
        $page = $this->bookStack->getPage($id);
        if ($page === null) {
            return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
        }
        // Absicherung: Seite muss zu einem für den Betrachter erlaubten Buch gehören.
        if (!in_array($page['book_id'], $this->bookStack->allowedBookIds($this->permission->isSouveraAdmin()), true)) {
            return $this->forbidden();
        }
        return new DataResponse($page);
    }

    private function forbidden(): DataResponse {
        return new DataResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
    }
}
