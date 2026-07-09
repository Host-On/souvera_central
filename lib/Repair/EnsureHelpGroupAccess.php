<?php

declare(strict_types=1);

/**
 * Souvera Central - Repair-Step: Hilfe-Zugriff für souvera-users absichern.
 *
 * Ist die App per Gruppen auf `souvera-admins` beschränkt, können souvera-users
 * die Hilfe (/apps/souvera_central/help) nicht öffnen, weil Nextcloud ALLE
 * Routen der App für sie sperrt. Dieser Step ergänzt bei App-Installation/-Update
 * automatisch die Gruppen `souvera-users` (+ `souvera-admins`) zur App-Freigabe.
 *
 * Idempotent & konservativ:
 *  - global freigegeben ('yes')        -> nichts tun
 *  - deaktiviert ('no'/'')             -> nichts tun (App nicht auto-aktivieren)
 *  - bereits beide Gruppen freigegeben -> nichts tun
 *  - nur dann schreiben, wenn tatsächlich eine Gruppe fehlt.
 */

namespace OCA\SouveraCentral\Repair;

use OCA\SouveraCentral\Service\ConfigService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class EnsureHelpGroupAccess implements IRepairStep {
    private const APP_ID = 'souvera_central';

    public function __construct(
        private IConfig $config,
        private IAppManager $appManager,
        private IGroupManager $groupManager,
        private ConfigService $configService,
    ) {
    }

    public function getName(): string {
        return 'Souvera Central: Hilfe-Zugriff für souvera-users sicherstellen';
    }

    public function run(IOutput $output): void {
        try {
            $raw = (string) $this->config->getAppValue(self::APP_ID, 'enabled', 'no');
            $target = self::computeTargetGroups(
                $raw,
                $this->configService->getMailGroupId(),
                $this->configService->getScadminGroupId()
            );

            if ($target === null) {
                $output->info('Hilfe-Zugriff ok – keine Anpassung der App-Freigabe nötig.');
                return;
            }

            $groups = [];
            foreach ($target as $gid) {
                $g = $this->groupManager->get($gid);
                if ($g !== null) {
                    $groups[] = $g;
                }
            }
            if ($groups === []) {
                $output->warning('Zielgruppen (' . implode(', ', $target) . ') existieren nicht – App-Freigabe unverändert.');
                return;
            }

            $this->appManager->enableAppForGroups(self::APP_ID, $groups);
            $output->info('App-Freigabe erweitert auf: ' . implode(', ', $target) . ' (souvera-users können jetzt die Hilfe öffnen).');
        } catch (\Throwable $e) {
            $output->warning('Hilfe-Zugriff konnte nicht automatisch angepasst werden: ' . $e->getMessage());
        }
    }

    /**
     * Reine Entscheidungslogik (testbar).
     *
     * @return string[]|null  Gruppen-IDs, die freigegeben werden sollen, oder
     *                        null, wenn nichts zu tun ist (idempotent).
     */
    public static function computeTargetGroups(string $rawEnabled, string $mailGroup, string $adminGroup): ?array {
        // 'yes' = global (alle haben Zugriff), 'no'/'' = deaktiviert -> nicht anfassen
        if ($rawEnabled === 'yes' || $rawEnabled === 'no' || $rawEnabled === '') {
            return null;
        }
        $current = json_decode($rawEnabled, true);
        if (!is_array($current)) {
            return null;
        }
        $current = array_values(array_unique(array_filter(
            array_map('strval', $current),
            static fn (string $g): bool => $g !== ''
        )));
        $want = array_values(array_unique(array_merge($current, [$mailGroup, $adminGroup])));

        $c = $current;
        sort($c);
        $w = $want;
        sort($w);
        // Bereits identisch (beide Zielgruppen vorhanden) -> nichts tun.
        return $c === $w ? null : $want;
    }
}
