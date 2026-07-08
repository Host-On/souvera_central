<?php

declare(strict_types=1);

/**
 * Souvera Central - BookStack-Dokumentations-Gateway (Hilfe-Seite)
 *
 * Liest die Doku-Inhalte read-only aus der BookStack-Instanz
 * (Standard: https://doku.souvera.eu) über deren REST-API und stellt sie der
 * Central-Hilfe-Seite bereit. Zugang via Config (config.php):
 *   souvera_central.bookstack_url   (Default https://doku.souvera.eu)
 *   souvera_central.bookstack_token ("<TOKEN_ID>:<TOKEN_SECRET>")
 *
 * Sichtbarkeit: normale Souvera-User sehen die Regale aus
 * getHelpUserShelfIds() (Default "Benutzer"), Souvera-Admins zusätzlich die
 * Regale aus getHelpAdminShelfIds() (Default "Administratoren").
 */

namespace OCA\SouveraCentral\Service;

use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class BookStackService {
    public function __construct(
        private ConfigService $config,
        private IClientService $clientService,
        private LoggerInterface $logger,
    ) {
    }

    public function isConfigured(): bool {
        return $this->config->isBookStackConfigured();
    }

    /**
     * Erlaubte Shelf-IDs für den aktuellen Betrachter.
     *
     * @return int[]
     */
    public function shelfIdsFor(bool $includeAdmin): array {
        $ids = $this->config->getHelpUserShelfIds();
        if ($includeAdmin) {
            $ids = array_merge($ids, $this->config->getHelpAdminShelfIds());
        }
        return array_values(array_unique($ids));
    }

    /**
     * Navigations-Baum: Regale -> Bücher (nur id + Name; Inhalte werden lazy
     * über getBookContents() nachgeladen).
     *
     * @return array<int,array{id:int,name:string,books:array}>
     */
    public function getTree(bool $includeAdmin): array {
        $shelves = [];
        foreach ($this->shelfIdsFor($includeAdmin) as $sid) {
            $shelf = $this->apiGet('/api/shelves/' . $sid);
            if ($shelf === null) {
                continue;
            }
            $shelves[] = [
                'id' => (int) ($shelf['id'] ?? $sid),
                'name' => (string) ($shelf['name'] ?? ('Regal ' . $sid)),
                'books' => self::extractBooks($shelf),
            ];
        }
        return $shelves;
    }

    /**
     * Buch-IDs, auf die der Betrachter zugreifen darf (über alle erlaubten Regale).
     *
     * @return int[]
     */
    public function allowedBookIds(bool $includeAdmin): array {
        $ids = [];
        foreach ($this->shelfIdsFor($includeAdmin) as $sid) {
            $shelf = $this->apiGet('/api/shelves/' . $sid);
            if ($shelf === null) {
                continue;
            }
            foreach (self::extractBooks($shelf) as $b) {
                $ids[] = (int) $b['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Buch-Inhalt (Kapitel + Seiten) als vereinfachter Baum.
     *
     * @return array{id:int,name:string,contents:array}|null
     */
    public function getBookContents(int $bookId): ?array {
        $book = $this->apiGet('/api/books/' . $bookId);
        if ($book === null) {
            return null;
        }
        return [
            'id' => (int) ($book['id'] ?? $bookId),
            'name' => (string) ($book['name'] ?? ''),
            'contents' => self::simplifyContents($book),
        ];
    }

    /**
     * Einzelne Seite: id, name, book_id, html.
     *
     * @return array{id:int,name:string,book_id:int,html:string}|null
     */
    public function getPage(int $pageId): ?array {
        $page = $this->apiGet('/api/pages/' . $pageId);
        if ($page === null) {
            return null;
        }
        return [
            'id' => (int) ($page['id'] ?? $pageId),
            'name' => (string) ($page['name'] ?? ''),
            'book_id' => (int) ($page['book_id'] ?? 0),
            'html' => (string) ($page['html'] ?? ''),
        ];
    }

    /**
     * Führt einen GET gegen die BookStack-API aus.
     *
     * @return array<string,mixed>|null
     */
    protected function apiGet(string $path): ?array {
        if (!$this->config->isBookStackConfigured()) {
            return null;
        }
        $url = $this->config->getBookStackUrl() . $path;
        try {
            $client = $this->clientService->newClient();
            $res = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->config->getBookStackToken(),
                    'Accept' => 'application/json',
                ],
                'timeout' => 15,
            ]);
            $data = json_decode((string) $res->getBody(), true);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            $this->logger->warning('[souvera_central] BookStack API GET fehlgeschlagen (' . $path . '): ' . $e->getMessage());
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Reine, testbare Hilfsfunktionen (ohne HTTP/Nextcloud-Abhängigkeit)
    // ------------------------------------------------------------------

    /**
     * Extrahiert die Bücher aus einem Shelf-Payload.
     *
     * @param array<string,mixed> $shelf
     * @return array<int,array{id:int,name:string,slug:string}>
     */
    public static function extractBooks(array $shelf): array {
        $out = [];
        foreach (($shelf['books'] ?? []) as $b) {
            if (!is_array($b) || !isset($b['id'])) {
                continue;
            }
            $out[] = [
                'id' => (int) $b['id'],
                'name' => (string) ($b['name'] ?? ''),
                'slug' => (string) ($b['slug'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Vereinfacht die contents eines Buchs zu [{type,id,name,pages?}].
     * Kapitel enthalten ihre Seiten; Entwürfe (draft) werden übersprungen.
     *
     * @param array<string,mixed> $book
     * @return array<int,array<string,mixed>>
     */
    public static function simplifyContents(array $book): array {
        $out = [];
        foreach (($book['contents'] ?? []) as $node) {
            if (!is_array($node) || !isset($node['type'])) {
                continue;
            }
            if ($node['type'] === 'chapter') {
                $pages = [];
                foreach (($node['pages'] ?? []) as $p) {
                    if (is_array($p) && isset($p['id']) && empty($p['draft'])) {
                        $pages[] = ['id' => (int) $p['id'], 'name' => (string) ($p['name'] ?? '')];
                    }
                }
                $out[] = [
                    'type' => 'chapter',
                    'id' => (int) ($node['id'] ?? 0),
                    'name' => (string) ($node['name'] ?? ''),
                    'pages' => $pages,
                ];
            } elseif ($node['type'] === 'page') {
                if (!empty($node['draft'])) {
                    continue;
                }
                $out[] = [
                    'type' => 'page',
                    'id' => (int) ($node['id'] ?? 0),
                    'name' => (string) ($node['name'] ?? ''),
                ];
            }
        }
        return $out;
    }
}
