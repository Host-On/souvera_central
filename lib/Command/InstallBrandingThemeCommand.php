<?php

declare(strict_types=1);

/**
 * Souvera Central - Branding Theme (serverseitige Umbenennung)
 *
 * Schreibt ein NC-Verzeichnis-Theme unter <serverroot>/themes/souvera/ mit
 * L10n-Overrides, damit NC „Talk"/„Nextcloud Office" SERVERSEITIG als
 * „Link"/„Desk" rendert — null FOUC, auch im <title>, in Widgets, Suche und
 * E-Mails. Die L10N-Factory merged Theme-Übersetzungen bei aktivem
 * `theme`-Config (verifiziert in NC-Quelle L10N/Factory.php).
 *
 *   occ souvera:branding:install-theme              → Theme-Dateien schreiben
 *   occ souvera:branding:install-theme --activate   → zusätzlich theme=souvera aktivieren
 *
 * Aktivierung ist bewusst MANUELL (Operator), damit nichts am Live-System
 * Überraschendes umschaltet. Die theming-app (Logo/Hintergrund via occ
 * theming:config) bleibt unberührt — es wird KEIN defaults.php angelegt.
 *
 * Das JS-Renaming (branding.js) bleibt als Fallback bestehen und wird zum
 * No-Op, sobald das Theme aktiv ist.
 */

namespace OCA\SouveraCentral\Command;

use OCA\SouveraCentral\AppInfo\Application;
use OCA\SouveraCentral\Service\ConfigService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InstallBrandingThemeCommand extends Command {
	private const THEME_NAME = 'souvera';

	public function __construct(
		private ConfigService $branding,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('souvera_central:branding:install-theme');
		// Legacy-Alias (Namespace vor der Vereinheitlichung)
		$this->setAliases(['souvera:branding:install-theme']);
		$this->setDescription('Schreibt das Souvera-L10n-Theme (Talk→Link, Office→Desk) nach themes/souvera/');
		$this->addOption('activate', null, InputOption::VALUE_NONE, 'Theme sofort aktivieren (occ config:system:set theme=souvera)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$serverRoot = \OC::$SERVERROOT;
		if (!is_string($serverRoot) || $serverRoot === '' || !is_dir($serverRoot)) {
			$output->writeln('<error>Server-Root nicht ermittelbar.</error>');
			return Command::FAILURE;
		}

		$branding = $this->branding->getBrandingConfig();
		$link = (string)($branding['names']['spreed'] ?? 'Link');
		$desk = (string)($branding['names']['richdocuments'] ?? 'Desk');

		// Key = exakter Name-String aus der jeweiligen appinfo/info.xml,
		// übersetzt über die L10N der App (Theme-L10n merged darüber).
		$targets = [
			'spreed' => ['Talk' => $link],
			'richdocuments' => ['Nextcloud Office' => $desk],
		];

		$plural = 'nplurals=2; plural=(n != 1);';
		$written = 0;
		$failed = 0;

		foreach ($targets as $appId => $map) {
			$dir = $serverRoot . '/themes/' . self::THEME_NAME . '/l10n/apps/' . $appId . '/l10n';
			if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
				$output->writeln('<error>Konnte Verzeichnis nicht anlegen: ' . $dir . '</error>');
				$failed++;
				continue;
			}
			foreach (['de', 'en'] as $lang) {
				$json = json_encode(
					['translations' => $map, 'pluralForm' => $plural],
					JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				);
				$path = $dir . '/' . $lang . '.json';
				if (file_put_contents($path, $json . "\n") === false) {
					$output->writeln('<error>Konnte Datei nicht schreiben: ' . $path . '</error>');
					$failed++;
					continue;
				}
				$written++;
				$output->writeln('geschrieben: ' . $path);
			}
		}

		if ($failed > 0) {
			return Command::FAILURE;
		}

		$active = (string)$this->config->getSystemValueString('theme', '');
		if ($active === self::THEME_NAME) {
			$output->writeln('<info>Theme „' . self::THEME_NAME . '" ist aktiv — „' . $link . '" und „' . $desk . '" werden serverseitig gerendert.</info>');
		} elseif ($input->getOption('activate')) {
			$this->config->setSystemValue('theme', self::THEME_NAME);
			$this->logger->info('Souvera-L10n-Theme aktiviert', ['app' => Application::APP_ID]);
			$output->writeln('<info>Theme „' . self::THEME_NAME . '" aktiviert (theme=souvera).</info>');
			$output->writeln('Hinweis: theming-app-Branding (Logo/Hintergrund) bleibt unberührt — bitte einmal gegenprüfen.');
		} else {
			$output->writeln('Theme-Dateien liegen bereit. Aktivieren mit:');
			$output->writeln('  occ souvera:branding:install-theme --activate');
			$output->writeln('oder:  occ config:system:set theme --value ' . self::THEME_NAME);
		}

		return Command::SUCCESS;
	}
}
