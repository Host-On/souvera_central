<?php

declare(strict_types=1);

/**
 * Souvera Central - BookStack-Dokumentations-Gateway (Hilfe-Seite)
 *
 * Liest die Doku-Inhalte read-only aus der BookStack-Instanz
 * (fester Default: https://doku.souvera.eu) über deren REST-API und stellt sie
 * der Central-Hilfe-Seite bereit.
 *
 * Der API-Token wird NICHT mehr im Klartext in config.php gehalten, sondern
 * zentral + verschlüsselt über BookStackTokenService (occ
 * souvera:bookstack-token:set). Die BookStack-URL wird nicht konfiguriert.
 *
 * Sichtbarkeit: normale Souvera-User sehen die Regale aus
 * getHelpUserShelfIds() (Default "Benutzer"), Souvera-Admins zusätzlich die
 * Regale aus getHelpAdminShelfIds() (Default "Administratoren").
 */

namespace OCA\SouveraCentral\Service;

use OCA\SouveraCentral\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

class BookStackService {
    private bool $cacheResolved = false;
    private ?ICache $cache = null;

    public function __construct(
        private ConfigService $config,
        private BookStackTokenService $tokenService,
        private IClientService $clientService,
        private LoggerInterface $logger,
        private ICacheFactory $cacheFactory,
    ) {
    }

    public function isConfigured(): bool {
        return $this->tokenService->hasToken();
    }

    /**
     * Gemeinsamer (instanzweiter) Cache für BookStack-Antworten. Bevorzugt den
     * verteilten Cache (Redis/Memcached), fällt auf lokalen Cache (APCu) zurück;
     * ohne Memcache = kein Cache (null). BookStack-Inhalte sind für alle Nutzer
     * identisch, daher ist ein geteilter Cache ideal.
     */
    private function getCache(): ?ICache {
        if ($this->cacheResolved) {
            return $this->cache;
        }
        $this->cacheResolved = true;
        $prefix = Application::APP_ID . '_help/';
        if ($this->cacheFactory->isAvailable()) {
            $this->cache = $this->cacheFactory->createDistributed($prefix);
        } elseif ($this->cacheFactory->isLocalCacheAvailable()) {
            $this->cache = $this->cacheFactory->createLocal($prefix);
        }
        return $this->cache;
    }

    /**
     * Leert den Hilfe-Cache. Rückgabe false, wenn kein Cache verfügbar ist.
     */
    public function clearCache(): bool {
        $cache = $this->getCache();
        if ($cache === null) {
            return false;
        }
        $cache->clear();
        return true;
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
            'html' => self::sanitizeInlineStyles((string) ($page['html'] ?? '')),
        ];
    }

    /**
     * Entfernt hartkodierte Farb-/Hintergrund-Angaben (color, background,
     * background-color) aus inline style-Attributen der BookStack-HTML. Diese
     * Werte sind für das BookStack-Light-Theme gedacht und machen Textboxen in
     * der Dark-Ansicht unlesbar (z. B. weißer Text auf hellem Kasten). Ränder,
     * Padding etc. bleiben erhalten; die Farbgebung übernimmt das NC-Theme.
     */
    public static function sanitizeInlineStyles(string $html): string {
        if ($html === '') {
            return $html;
        }
        return (string) preg_replace_callback(
            '/\sstyle\s*=\s*"([^"]*)"/i',
            static function (array $m): string {
                $kept = [];
                foreach (explode(';', $m[1]) as $decl) {
                    $decl = trim($decl);
                    if ($decl === '') {
                        continue;
                    }
                    $prop = strtolower(trim((string) (explode(':', $decl, 2)[0])));
                    if (in_array($prop, ['color', 'background', 'background-color'], true)) {
                        continue;
                    }
                    $kept[] = $decl;
                }
                return $kept === [] ? '' : ' style="' . implode('; ', $kept) . '"';
            },
            $html
        );
    }

    /**
     * Führt einen GET gegen die BookStack-API aus. Erfolgreiche Antworten werden
     * (sofern ein Cache verfügbar und help_cache_ttl > 0) instanzweit
     * zwischengespeichert – das verkürzt Ladezeiten und minimiert den Traffic
     * zur BookStack-Instanz. Fehler/leer werden nicht gecacht (erneuter Versuch).
     *
     * @return array<string,mixed>|null
     */
    protected function apiGet(string $path): ?array {
        $token = $this->tokenService->getToken();
        if ($token === null || $token === '') {
            return null;
        }

        $ttl = $this->config->getHelpCacheTtl();
        $cache = $ttl > 0 ? $this->getCache() : null;
        $cacheKey = $cache !== null ? 'bs:' . md5($path) : null;
        if ($cache !== null) {
            $hit = $cache->get($cacheKey);
            if (is_string($hit)) {
                $decoded = json_decode($hit, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $url = $this->config->getBookStackUrl() . $path;
        try {
            $client = $this->clientService->newClient();
            $res = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Token ' . $token,
                    'Accept' => 'application/json',
                ],
                'timeout' => 15,
            ]);
            $data = json_decode((string) $res->getBody(), true);
            if (!is_array($data)) {
                return null;
            }
            if ($cache !== null && $cacheKey !== null) {
                $cache->set($cacheKey, json_encode($data), $ttl);
            }
            return $data;
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
