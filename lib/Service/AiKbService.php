<?php

declare(strict_types=1);

/**
 * Souvera Central - AI Knowledge Base Service
 *
 * Verwaltet die internen KB-Artikel (Firmeninfos, FAQ, Prozesse …), die der
 * Nextcloud-Agent über den Central-MCP-Endpoint live liest. Die Artikel
 * liegen in der DB (`souvera_ai_kb`); die Dateien unter resources/ai sind
 * ausschließlich Initial-Seed und werden nie wieder synchronisiert.
 */

namespace OCA\SouveraCentral\Service;

use OCA\SouveraCentral\Db\AiKbArticle;
use OCA\SouveraCentral\Db\AiKbArticleMapper;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class AiKbService
{
    public const APP_ID = 'souvera_central';

    private const KEY_SEEDED = 'ai.kb.seeded';

    public function __construct(
        private AiKbArticleMapper $mapper,
        private IAppConfig $appConfig,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Importiert resources/ai/*.md einmalig als Startinhalt (Seed).
     * Idempotent über einen AppConfig-Marker; läuft sicher auf jedem Zugriff.
     */
    public function ensureSeeded(): void
    {
        if ($this->appConfig->getValueString(self::APP_ID, self::KEY_SEEDED, '0') === '1') {
            return;
        }

        try {
            if ($this->mapper->count() === 0) {
                $seedDir = \dirname(__DIR__, 2) . '/resources/ai';
                $files = glob($seedDir . '/*.md') ?: [];
                sort($files);

                $order = 0;
                foreach ($files as $path) {
                    $content = file_get_contents($path);
                    if ($content === false || trim($content) === '') {
                        continue;
                    }
                    $title = '';
                    if (preg_match('/^#\s+(.+)$/m', $content, $m) === 1) {
                        $title = trim($m[1]);
                    }
                    $this->create($title !== '' ? $title : basename($path), $content, $order++);
                }
            }

            $this->appConfig->setValueString(self::APP_ID, self::KEY_SEEDED, '1');
        } catch (\Throwable $e) {
            // Nie einen Request an der Seed-Logik scheitern lassen.
            $this->logger->warning('Souvera AI: KB seed failed: ' . $e->getMessage(), ['app' => self::APP_ID]);
        }
    }

    /**
     * @return AiKbArticle[]
     */
    public function list(): array
    {
        return $this->mapper->findAll();
    }

    public function get(int $id): ?AiKbArticle
    {
        return $this->mapper->find($id);
    }

    public function create(string $title, string $content, int $sortOrder = 0): AiKbArticle
    {
        $now = time();
        $article = new AiKbArticle();
        $article->setTitle($this->normalizeTitle($title));
        $article->setContent($content);
        $article->setSortOrder($sortOrder);
        $article->setCreatedAt($now);
        $article->setUpdatedAt($now);
        return $this->mapper->insert($article);
    }

    public function update(int $id, string $title, string $content, ?int $sortOrder = null): ?AiKbArticle
    {
        $article = $this->mapper->find($id);
        if ($article === null) {
            return null;
        }
        $article->setTitle($this->normalizeTitle($title));
        $article->setContent($content);
        if ($sortOrder !== null) {
            $article->setSortOrder($sortOrder);
        }
        $article->setUpdatedAt(time());
        return $this->mapper->update($article);
    }

    public function delete(int $id): bool
    {
        $article = $this->mapper->find($id);
        if ($article === null) {
            return false;
        }
        $this->mapper->delete($article);
        return true;
    }

    /**
     * Volltextsuche über Titel und Inhalt (case-insensitive).
     *
     * @return AiKbArticle[]
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        return $this->mapper->search($query, $limit);
    }

    public function count(): int
    {
        return $this->mapper->count();
    }

    /** Kompakter Auszug für Listen/Suchergebnisse (MCP + UI). */
    public function excerpt(AiKbArticle $article, int $length = 240): string
    {
        $text = trim((string) preg_replace('/^#+\s*/m', '', $article->getContent()));
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . '…';
    }

    private function normalizeTitle(string $title): string
    {
        return mb_substr(trim($title), 0, 512);
    }
}
