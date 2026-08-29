<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Data access for {@see AiKbArticle} rows in `souvera_ai_kb`.
 *
 * @extends QBMapper<AiKbArticle>
 */
class AiKbArticleMapper extends QBMapper
{
    public const TABLE = 'souvera_ai_kb';

    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, self::TABLE, AiKbArticle::class);
    }

    /**
     * All articles, ordered by sort_order then id.
     *
     * @return AiKbArticle[]
     */
    public function findAll(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE)
            ->orderBy('sort_order', 'ASC')
            ->addOrderBy('id', 'ASC');

        return $this->findEntities($qb);
    }

    public function find(int $id): ?AiKbArticle
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        $entities = $this->findEntities($qb);
        return $entities[0] ?? null;
    }

    /**
     * Case-insensitive search over title and content.
     *
     * @return AiKbArticle[]
     */
    public function search(string $query, int $limit = 10): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->iLike('title', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($query) . '%')),
                    $qb->expr()->iLike('content', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($query) . '%'))
                )
            )
            ->orderBy('sort_order', 'ASC')
            ->addOrderBy('id', 'ASC')
            ->setMaxResults(max(1, $limit));

        return $this->findEntities($qb);
    }

    public function count(): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('*', 'cnt'))
            ->from(self::TABLE);

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return (int) ($row['cnt'] ?? 0);
    }
}
