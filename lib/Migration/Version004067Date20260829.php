<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Creates `souvera_ai_kb` — the internal knowledge base for the Nextcloud
 * agent: free-form Markdown articles (company info, FAQ, processes …) that
 * the AI reads live via the Central MCP endpoint.
 *
 * Articles are managed exclusively through the Central admin UI / the
 * ai_api. The `resources/ai/*.md` files are only the initial seed content.
 */
class Version004067Date20260829 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('souvera_ai_kb')) {
            return $schema;
        }

        $table = $schema->createTable('souvera_ai_kb');

        $table->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
            'length' => 20,
            'unsigned' => true,
        ]);
        $table->setPrimaryKey(['id']);

        $table->addColumn('title', Types::STRING, [
            'notnull' => true,
            'length' => 512,
            'default' => '',
        ]);
        $table->addColumn('content', Types::TEXT, [
            'notnull' => false,
        ]);
        $table->addColumn('sort_order', Types::INTEGER, [
            'notnull' => true,
            'default' => 0,
        ]);
        $table->addColumn('created_at', Types::BIGINT, [
            'notnull' => true,
            'default' => 0,
        ]);
        $table->addColumn('updated_at', Types::BIGINT, [
            'notnull' => true,
            'default' => 0,
        ]);

        $table->addIndex(['sort_order'], 'souvera_ai_kb_sort_idx');

        return $schema;
    }
}
