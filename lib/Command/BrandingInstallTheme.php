<?php

declare(strict_types=1);

/**
 * Souvera Central - l10n-Overrides in ein NC-Theme installieren
 *
 * Schreibt fuer Talk (spreed) und Office/Collabora (richdocuments,
 * richdocumentscode) transformierte Uebersetzungsdateien in ein Nextcloud-Theme
 * (themes/<theme>/apps/<appId>/l10n/<lang>.{js,json}). Damit werden die
 * Produktnamen instanzweit fuer ALLE Benutzer und AUCH INNERHALB der Apps
 * (Menue, Titel, Benachrichtigungstexte) umbenannt: "Talk" -> "Link",
 * "Office"/"Collabora" -> "Desk".
 *
 * Der native NC-Theme-l10n-Mechanismus laedt bei aktivem Theme die Datei aus
 * themes/<theme>/... anstelle der App-Originaldatei. Es wird jeweils die
 * VOLLSTAENDIGE (nur in den Produktnamen geaenderte) Datei geschrieben, damit
 * keine anderen Uebersetzungen verloren gehen.
 *
 * Beispiele:
 *   occ souvera:branding:install-theme                     (Theme: aktiv oder "souvera", Sprachen de,en,nl)
 *   occ souvera:branding:install-theme --activate          (Theme zusaetzlich in config.php aktivieren)
 *   occ souvera:branding:install-theme --all-langs         (alle vorhandenen Sprachdateien)
 *   occ souvera:branding:install-theme --theme=custom --dry-run
 */

namespace OCA\SouveraCentral\Command;

use OCA\SouveraCentral\Service\BrandingThemeService;
use OC\Core\Command\Base;
use OCP\App\IAppManager;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BrandingInstallTheme extends Base {
    public function __construct(
        private BrandingThemeService $branding,
        private IAppManager $appManager,
        private IConfig $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:branding:install-theme')
            ->setDescription('Installiert l10n-Overrides (Talk->Link, Office/Collabora->Desk) in ein NC-Theme.')
            ->addOption('theme', null, InputOption::VALUE_REQUIRED, 'Theme-Name (Verzeichnis unter themes/). Default: aktives Theme oder "souvera".')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Sprachen als CSV (z. B. de,en,nl). Ignoriert bei --all-langs.', 'de,en,nl')
            ->addOption('all-langs', null, InputOption::VALUE_NONE, 'Alle vorhandenen Sprachdateien der Ziel-Apps verarbeiten.')
            ->addOption('activate', null, InputOption::VALUE_NONE, 'Theme in config.php aktivieren (system value "theme").')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, was passieren wuerde (nichts schreiben).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $dry = (bool) $input->getOption('dry-run');
        $allLangs = (bool) $input->getOption('all-langs');
        $theme = (string) ($input->getOption('theme') ?: '');
        if ($theme === '') {
            $active = (string) $this->config->getSystemValue('theme', '');
            $theme = $active !== '' ? $active : 'souvera';
        }

        $serverRoot = \OC::$SERVERROOT;
        $themeBase = $serverRoot . '/themes/' . $theme;
        $output->writeln('<info>Theme:</info> ' . $theme . '  (' . $themeBase . ')');
        $output->writeln('<info>Ersatz:</info> Talk -> "' . $this->branding->getReplacements('spreed')['Talk']
            . '", Office/Collabora -> "' . ($this->branding->getReplacements('richdocuments')['Office'] ?? '') . '"');
        if ($dry) {
            $output->writeln('<comment>DRY-RUN: es wird nichts geschrieben.</comment>');
        }

        $wanted = array_values(array_filter(array_map('trim', explode(',', (string) $input->getOption('lang')))));
        $written = 0;
        $files = 0;
        $missingApps = [];

        foreach (BrandingThemeService::TARGET_APPS as $appId) {
            try {
                $appPath = $this->appManager->getAppPath($appId);
            } catch (\Throwable $e) {
                $missingApps[] = $appId;
                continue;
            }
            $l10nDir = $appPath . '/l10n';
            if (!is_dir($l10nDir)) {
                $missingApps[] = $appId;
                continue;
            }

            $langs = $allLangs ? $this->availableLangs($l10nDir) : $wanted;
            $destDir = $themeBase . '/apps/' . $appId . '/l10n';

            foreach ($langs as $lang) {
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
                $changes = $this->branding->countChanges($appId, $map);
                $newMap = $this->branding->transformTranslations($appId, $map);

                $output->writeln(sprintf('  %s/%s: %d Uebersetzung(en) geaendert', $appId, $lang, $changes));

                if ($dry) {
                    continue;
                }
                if (!is_dir($destDir) && !@mkdir($destDir, 0o755, true) && !is_dir($destDir)) {
                    $output->writeln('<error>Konnte Verzeichnis nicht anlegen: ' . $destDir . '</error>');
                    return 1;
                }
                file_put_contents($destDir . '/' . $lang . '.json', $this->branding->renderJson($newMap, $plural));
                file_put_contents($destDir . '/' . $lang . '.js', $this->branding->renderJs($appId, $newMap, $plural));
                $files += 2;
                $written++;
            }
        }

        if (!empty($missingApps)) {
            $output->writeln('<comment>Nicht installiert / uebersprungen: ' . implode(', ', $missingApps) . '</comment>');
        }

        if ($dry) {
            $output->writeln('<comment>DRY-RUN beendet. Ohne --dry-run erneut ausfuehren, um zu schreiben.</comment>');
            return 0;
        }

        if ((bool) $input->getOption('activate')) {
            $this->config->setSystemValue('theme', $theme);
            $output->writeln('<info>Theme "' . $theme . '" in config.php aktiviert.</info>');
        }

        $output->writeln(sprintf('<info>Fertig: %d Sprachdatei-Paar(e) geschrieben (%d Dateien).</info>', $written, $files));
        $output->writeln('<comment>Hinweis:</comment>');
        if (!(bool) $input->getOption('activate')) {
            $output->writeln("  - Theme aktivieren: config.php -> 'theme' => '" . $theme . "', (oder erneut mit --activate).");
        }
        $output->writeln('  - Danach Caches leeren: occ maintenance:theme:update');
        return 0;
    }

    /**
     * @return string[] Sprachcodes, fuer die eine <lang>.json existiert
     */
    private function availableLangs(string $l10nDir): array {
        $langs = [];
        foreach (glob($l10nDir . '/*.json') ?: [] as $path) {
            $langs[] = basename($path, '.json');
        }
        sort($langs);
        return $langs;
    }
}
