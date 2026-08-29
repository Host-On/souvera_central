<?php

declare(strict_types=1);

/**
 * Souvera Central - AI Knowledge Base Article
 *
 * Row in `souvera_ai_kb`. One article is a self-contained Markdown document
 * (company info, FAQ, process …) the Nextcloud agent reads via MCP.
 */

namespace OCA\SouveraCentral\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getTitle()
 * @method void   setTitle(string $title)
 * @method string getContent()
 * @method void   setContent(string $content)
 * @method int    getSortOrder()
 * @method void   setSortOrder(int $sortOrder)
 * @method int    getCreatedAt()
 * @method void   setCreatedAt(int $createdAt)
 * @method int    getUpdatedAt()
 * @method void   setUpdatedAt(int $updatedAt)
 */
class AiKbArticle extends Entity
{
    /** @var string */
    protected $title = '';

    /** @var string */
    protected $content = '';

    /** @var int */
    protected $sortOrder = 0;

    /** @var int */
    protected $createdAt = 0;

    /** @var int */
    protected $updatedAt = 0;

    public function __construct()
    {
        $this->addType('sortOrder', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $withContent = true): array
    {
        $out = [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'sort_order' => $this->getSortOrder(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
        if ($withContent) {
            $out['content'] = $this->getContent();
        }
        return $out;
    }
}
