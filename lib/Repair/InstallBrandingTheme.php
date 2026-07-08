<?php

declare(strict_types=1);

/**
 * Souvera Central - Repair-Step: Branding-l10n-Overrides automatisch ins AKTIVE
 * Theme schreiben (bei App-Installation/-Update).
 *
 * So wird das instanzweite Umbenennen (Talk->Link, Office/Collabora->Desk) auf
 * dem sauberen, dauerhaften Nextcloud-Weg (Theme-l10n) automatisch aktuell
 * gehalten – auch im von Vue gerenderten App-Menü. Es wird NICHT erzwungen ein
 * Theme aktiviert: Ist kein Theme aktiv, gibt der Step nur einen Hinweis aus.
 */

namespace OCA\SouveraCentral\Repair;

use OCA\SouveraCentral\Service\BrandingThemeInstaller;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class InstallBrandingTheme implements IRepairStep {
    public function __construct(
        private BrandingThemeInstaller $installer,
    ) {
    }

    public function getName(): string {
        return 'Souvera Central: Branding-Overrides (Talk→Link, Office→Desk) ins aktive Theme schreiben';
    }

    public function run(IOutput $output): void {
        $theme = $this->installer->activeTheme();
        if ($theme === '') {
            $output->info('Kein aktives Theme gesetzt – Branding-Overrides übersprungen. '
                . 'Für das saubere Umbenennen im App-Menü: occ souvera:branding:install-theme --activate');
            return;
        }
        try {
            $res = $this->installer->install($theme, ['de', 'de_DE', 'en', 'nl'], false);
            $output->info(sprintf(
                'Branding-Overrides in Theme "%s" geschrieben (%d Datei[en]).%s',
                $res['theme'],
                $res['files'],
                $res['missing'] ? ' Übersprungen: ' . implode(', ', $res['missing']) : ''
            ));
        } catch (\Throwable $e) {
            $output->warning('Branding-Overrides konnten nicht geschrieben werden: ' . $e->getMessage());
        }
    }
}
