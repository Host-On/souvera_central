<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\DevOps;

use OC\DB\Connection;
use OC\DB\ConnectionAdapter;
use OC\DB\MigrationService;
use OCP\IDBConnection;
use OCP\Server;

/**
 * Führt ausstehende App-Migrationen im Prozess aus — identisch zu
 * `occ migrations:migrate <app>`.
 *
 * Wichtig: MigrationService verlangt das INNERE OC\DB\Connection-Objekt,
 * nicht den öffentlichen IDBConnection-Adapter (ConnectionAdapter). Der
 * Adapter leitet Doctrine-Methoden nicht weiter; übergibt man ihn trotzdem,
 * wirft der Konstruktor einen TypeError. Genau dieser Fehler blockierte
 * zuvor Selbstheilung (SignatureAdminController::withSigTables) und den
 * Migrationslauf des Self-Updates (SelfUpdateTrait::runAppMigrations) —
 * die Signatur-Tabellen wurden dadurch nie angelegt.
 *
 * Kompatibilität verifiziert gegen NC 30.0.0 und 34.0.3:
 *  - ConnectionAdapter::getInner() existiert in beiden Versionen,
 *  - MigrationService::__construct(string, Connection) unverändert,
 *  - Fallback-Kette deckt auch künftige Umbauten des Adapters ab.
 */
final class MigrationRunner {

    /**
     * Führt alle ausstehenden Migrationen der App aus.
     *
     * @throws \Throwable bei fehlgeschlagener Migration (Aufrufer fängt)
     */
    public static function migrate(string $appId): void {
        $ms = new MigrationService($appId, self::innerConnection());
        $ms->migrate();
    }

    /**
     * Inneres Connection-Objekt besorgen (NC 30–34):
     *  1. regulär: getInner() am ConnectionAdapter,
     *  2. direkt, falls IDBConnection bereits das innere Objekt ist,
     *  3. DI-Auflösung (Core-Pattern, siehe OC\Updater::doUpgrade).
     */
    private static function innerConnection(): Connection {
        $db = Server::get(IDBConnection::class);
        if ($db instanceof ConnectionAdapter && \method_exists($db, 'getInner')) {
            return $db->getInner();
        }
        if ($db instanceof Connection) {
            return $db;
        }
        return Server::get(Connection::class);
    }
}
