<?php

declare(strict_types=1);

/**
 * Souvera Central - Schreibt die Branding-l10n-Overrides (Talk->Link,
 * Office/Collabora->Desk) in ein Nextcloud-Theme. Gemeinsame FS-Logik für den
 * occ-Befehl `souvera:branding:install-theme` UND den automatischen Repair-Step
 * (bei App-Update). Die reine Transformationslogik liegt (testbar) im
 * BrandingThemeService; hier passieren nur die Dateisystem-Operationen.
 */

namespace OCA\SouveraCentral\Service;

use OCP\App\IAppManager;
use OCP\IConfig;

class BrandingThemeInstaller {
    public function __construct(
        private BrandingThemeService $branding,
        private IAppManager $appManager,
        private IConfig $config,
    ) {
    }

    /** Aktuell in config.php gesetztes Theme ('' = keins). */
    public function activeTheme(): string {
        return (string) $this->config->getSystemValue('theme', '');
    }

    /** Ziel-Theme auflösen: expliziter Wert > aktives Theme > "souvera". */
    public function resolveTheme(?string $theme): string {
        $theme = trim((string) $theme);
        if ($theme !== '') {
            return $theme;
        }
        $active = $this->activeTheme();
        return $active !== '' ? $active : 'souvera';
    }

    /**
     * Installiert die Overrides für alle Ziel-Apps.
     *
     * @param string[]|null $langs Sprachcodes; null = alle vorhandenen der App.
     * @return array{theme:string,written:int,files:int,missing:string[],perApp:array<int,array{app:string,lang:string,changes:int}>}
     */
    public function install(string $theme, ?array $langs, bool $dryRun = false): array {
        $themeBase = \OC::$SERVERROOT . '/themes/' . $theme;
        $written = 0;
        $files = 0;
        $missing = [];
        $perApp = [];

        foreach (BrandingThemeService::TARGET_APPS as $appId) {
            try {
                $appPath = $this->appManager->getAppPath($appId);
            } catch (\Throwable $e) {
                $missing[] = $appId;
                continue;
            }
            $l10nDir = $appPath . '/l10n';
            if (!is_dir($l10nDir)) {
                $missing[] = $appId;
                continue;
            }

            $useLangs = $langs === null ? $this->availableLangs($l10nDir) : $langs;
            $destDir = $themeBase . '/apps/' . $appId . '/l10n';

            foreach ($useLangs as $lang) {
                $jsonFile = $l10nDir . '/' . $lang . '.json';
                if (!is_file($jsonFile)) {
                    continue;
                }
                $data = json_decode((string) file_get_contents($jsonFile), true);
                if (!is_array($data) || !isset($data['translations']) || !is_array($data['translations'])) {
                    continue;
                }
                $map = $data['translations'];
                $plural = (string) ($data['pluralForm'] ?? BrandingThemeService::DEFAULT_PLURAL);
                $perApp[] = ['app' => $appId, 'lang' => $lang, 'changes' => $this->branding->countChanges($appId, $map)];

                if ($dryRun) {
                    continue;
                }
                $newMap = $this->branding->transformTranslations($appId, $map);
                if (!is_dir($destDir) && !@mkdir($destDir, 0o755, true) && !is_dir($destDir)) {
                    throw new \RuntimeException('Konnte Verzeichnis nicht anlegen: ' . $destDir);
                }
                file_put_contents($destDir . '/' . $lang . '.json', $this->branding->renderJson($newMap, $plural));
                file_put_contents($destDir . '/' . $lang . '.js', $this->branding->renderJs($appId, $newMap, $plural));
                $files += 2;
                $written++;
            }
        }

        return ['theme' => $theme, 'written' => $written, 'files' => $files, 'missing' => $missing, 'perApp' => $perApp];
    }

    /** @return string[] Sprachcodes mit vorhandener <lang>.json */
    private function availableLangs(string $l10nDir): array {
        $langs = [];
        foreach (glob($l10nDir . '/*.json') ?: [] as $path) {
            $langs[] = basename($path, '.json');
        }
        sort($langs);
        return $langs;
    }
}
