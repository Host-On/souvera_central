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

use OCA\SouveraCentral\Service\BrandingThemeInstaller;
use OCA\SouveraCentral\Service\BrandingThemeService;
use OC\Core\Command\Base;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BrandingInstallTheme extends Base {
    public function __construct(
        private BrandingThemeService $branding,
        private BrandingThemeInstaller $installer,
        private IConfig $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:branding:install-theme')
            ->setDescription('Installiert l10n-Overrides (Talk->Link, Office/Collabora->Desk) in ein NC-Theme.')
            ->addOption('theme', null, InputOption::VALUE_REQUIRED, 'Theme-Name (Verzeichnis unter themes/). Default: aktives Theme oder "souvera".')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Sprachen als CSV (z. B. de,de_DE,en,nl). Ignoriert bei --all-langs.', 'de,de_DE,en,nl')
            ->addOption('all-langs', null, InputOption::VALUE_NONE, 'Alle vorhandenen Sprachdateien der Ziel-Apps verarbeiten.')
            ->addOption('activate', null, InputOption::VALUE_NONE, 'Theme in config.php aktivieren (system value "theme").')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, was passieren wuerde (nichts schreiben).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $dry = (bool) $input->getOption('dry-run');
        $allLangs = (bool) $input->getOption('all-langs');
        $theme = $this->installer->resolveTheme((string) ($input->getOption('theme') ?: ''));
        $langs = $allLangs
            ? null
            : array_values(array_filter(array_map('trim', explode(',', (string) $input->getOption('lang')))));

        $output->writeln('<info>Theme:</info> ' . $theme);
        $output->writeln('<info>Ersatz:</info> Talk -> "' . $this->branding->getReplacements('spreed')['Talk']
            . '", Office/Collabora -> "' . ($this->branding->getReplacements('richdocuments')['Office'] ?? '') . '"');
        if ($dry) {
            $output->writeln('<comment>DRY-RUN: es wird nichts geschrieben.</comment>');
        }

        try {
            $res = $this->installer->install($theme, $langs, $dry);
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }

        foreach ($res['perApp'] as $row) {
            $output->writeln(sprintf('  %s/%s: %d Uebersetzung(en) geaendert', $row['app'], $row['lang'], $row['changes']));
        }
        if (!empty($res['missing'])) {
            $output->writeln('<comment>Nicht installiert / uebersprungen: ' . implode(', ', $res['missing']) . '</comment>');
        }

        if ($dry) {
            $output->writeln('<comment>DRY-RUN beendet. Ohne --dry-run erneut ausfuehren, um zu schreiben.</comment>');
            return 0;
        }

        if ((bool) $input->getOption('activate')) {
            $this->config->setSystemValue('theme', $theme);
            $output->writeln('<info>Theme "' . $theme . '" in config.php aktiviert.</info>');
        }

        $output->writeln(sprintf('<info>Fertig: %d Sprachdatei-Paar(e) geschrieben (%d Dateien).</info>', $res['written'], $res['files']));
        $output->writeln('<comment>Hinweis:</comment>');
        if (!(bool) $input->getOption('activate')) {
            $output->writeln("  - Theme aktivieren: config.php -> 'theme' => '" . $theme . "', (oder erneut mit --activate).");
        }
        $output->writeln('  - Danach Caches leeren: occ maintenance:theme:update');
        return 0;
    }
}
