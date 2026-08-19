<?php

declare(strict_types=1);

/**
 * Souvera Central - Repair-Step: Branding-l10n-Overrides automatisch ins Theme
 * schreiben (bei App-Installation/-Update).
 *
 * So wird das instanzweite Umbenennen (Talk->Link, Office/Collabora->Desk) auf
 * dem sauberen, dauerhaften Nextcloud-Weg (Theme-l10n) automatisch aktuell
 * gehalten – flackerfrei und auch im von Vue gerenderten App-Menü.
 *
 * Ist bereits ein Theme aktiv, wird in dieses geschrieben. Ist KEIN Theme aktiv,
 * legt der Step automatisch ein minimales "souvera"-Theme an, aktiviert es in
 * config.php und schreibt die Overrides hinein. Das lässt sich per App-Config
 * abschalten:  occ config:app:set souvera_central branding_auto_activate_theme --value no
 */

namespace OCA\SouveraCentral\Repair;

use OCA\SouveraCentral\Service\BrandingThemeInstaller;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class InstallBrandingTheme implements IRepairStep {
    private const LANGS = ['de', 'de_DE', 'en', 'nl'];
    private const AUTO_THEME = 'souvera';

    public function __construct(
        private BrandingThemeInstaller $installer,
        private IConfig $config,
    ) {
    }

    public function getName(): string {
        return 'Souvera Central: Branding-Overrides (Talk→Link, Office→Desk) ins Theme schreiben';
    }

    public function run(IOutput $output): void {
        $theme = $this->installer->activeTheme();
        $autoActivated = false;

        if ($theme === '') {
            if (!$this->autoActivateEnabled()) {
                $output->info('Kein aktives Theme; automatische Theme-Aktivierung ist deaktiviert '
                    . '(souvera_central.branding_auto_activate_theme=no). Branding-Overrides übersprungen. '
                    . 'Manuell: occ souvera:branding:install-theme --activate');
                return;
            }
            $theme = self::AUTO_THEME;
            try {
                $this->installer->ensureThemeDir($theme);
                $this->installer->activate($theme);
                $autoActivated = true;
            } catch (\Throwable $e) {
                $output->warning('Konnte das "' . self::AUTO_THEME . '"-Theme nicht automatisch aktivieren: ' . $e->getMessage());
                return;
            }
        }

        try {
            $res = $this->installer->install($theme, self::LANGS, false);
            if ($autoActivated) {
                $output->info('Kein Theme aktiv gewesen – "' . $theme . '"-Theme automatisch angelegt und in config.php aktiviert.');
            }
            $output->info(sprintf(
                'Branding-Overrides in Theme "%s" geschrieben (%d Datei[en]).%s',
                $res['theme'],
                $res['files'],
                $res['missing'] ? ' Übersprungen: ' . implode(', ', $res['missing']) : ''
            ));
            if ($autoActivated) {
                $output->info('Hinweis: Bei Bedarf Caches leeren mit  occ maintenance:theme:update');
            }
        } catch (\Throwable $e) {
            $output->warning('Branding-Overrides konnten nicht geschrieben werden: ' . $e->getMessage());
        }
    }

    private function autoActivateEnabled(): bool {
        return $this->config->getAppValue('souvera_central', 'branding_auto_activate_theme', 'yes') !== 'no';
    }
}
