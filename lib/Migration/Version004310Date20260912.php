<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Zentrale E-Mail-Signaturen — Schema für die serverseitige Signatur-
 * Injektion (Stalwart MTA-Hook) und die Webmail-Compose-Injection:
 *
 *  - souvera_central_sig_overrides: Template-Overrides pro NC-Gruppe oder
 *    NC-User (Priorität vor dem globalen Template aus dem AppConfig).
 *  - souvera_central_sig_fields: optionale, von Admins gepflegte
 *    Zusatzfelder pro User (%title%, %phone%, … jenseits des NC-Profils).
 *  - souvera_central_sig_assets: Assets (Firmen-Logo) für Inline-CID
 *    (multipart/related) — Bytes liegen in der DB, damit der Hook sie
 *    ohne Dateisystem-Abhängigkeit einbetten kann.
 *
 * Das GLOBALE Template bleibt (abwärtskompatibel) im AppConfig
 * (`settings.mail_signature.*`) — siehe ConfigService.
 */
class Version004310Date20260912 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('souvera_central_sig_overrides')) {
            $table = $schema->createTable('souvera_central_sig_overrides');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
                'unsigned' => true,
            ]);
            $table->setPrimaryKey(['id']);

            $table->addColumn('scope', Types::STRING, [
                'notnull' => true,
                'length' => 16,
                'comment' => 'group|user',
            ]);
            $table->addColumn('scope_value', Types::STRING, [
                'notnull' => true,
                'length' => 255,
                'comment' => 'GID bzw. UID',
            ]);
            $table->addColumn('html', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->addColumn('text', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->addColumn('priority', Types::INTEGER, [
                'notnull' => true,
                'default' => 100,
            ]);
            $table->addColumn('replace_personal', Types::BOOLEAN, [
                'notnull' => true,
                'default' => true,
            ]);
            $table->addColumn('active', Types::BOOLEAN, [
                'notnull' => true,
                'default' => true,
            ]);
            $table->addColumn('created_at', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);

            $table->addIndex(['scope', 'scope_value'], 'souvera_sig_scope');
        }

        if (!$schema->hasTable('souvera_central_sig_fields')) {
            $table = $schema->createTable('souvera_central_sig_fields');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
                'unsigned' => true,
            ]);
            $table->setPrimaryKey(['id']);

            $table->addColumn('uid', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('field_key', Types::STRING, [
                'notnull' => true,
                'length' => 64,
                'comment' => 'title|department|phone|company|…',
            ]);
            $table->addColumn('field_value', Types::TEXT, [
                'notnull' => false,
            ]);

            $table->addUniqueIndex(['uid', 'field_key'], 'souvera_sig_field_uq');
        }

        if (!$schema->hasTable('souvera_central_sig_assets')) {
            $table = $schema->createTable('souvera_central_sig_assets');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 20,
                'unsigned' => true,
            ]);
            $table->setPrimaryKey(['id']);

            $table->addColumn('name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
                'default' => '',
            ]);
            $table->addColumn('mime', Types::STRING, [
                'notnull' => true,
                'length' => 128,
                'default' => 'image/png',
            ]);
            $table->addColumn('data', Types::BLOB, [
                'notnull' => false,
            ]);

            $table->addUniqueIndex(['name'], 'souvera_sig_asset_uq');
        }

        return $schema;
    }
}
