<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Command;

use OCA\SouveraCentral\DevOps\SelfUpdateTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `occ souvera:self-update` — THE suite-wide updater: pulls the newest
 * version for every installed Souvera app (mail, central, shield,
 * mailarchiv, documents) according to the ONE central update channel:
 *
 *   dev    = branch HEAD of main (every push)
 *   stable = latest published release (tag), daily in the maintenance window
 *
 * Set the channel via `occ souvera_central:devops:channel dev|stable` or
 * `occ config:system:set souvera.update.channel --value dev`.
 * Manual runs bypass the maintenance window and the 24h throttle.
 * App migrations run automatically after every applied update.
 *
 * Nextcloud's built-in `occ app:update` does nothing for custom apps
 * ("is up-to-date or no updates could be found" — they are not in the
 * App Store), so this is the reliable manual trigger.
 */
class SelfUpdate extends Command {

    use SelfUpdateTrait;

    private string $appId = 'souvera_central';

    protected function configure(): void {
        $this
            ->setName('souvera:self-update')
            // Kurzer Alias (primär dokumentiert) + Legacy-Name
            ->setAliases(['souvera:update', 'souvera_central:self-update'])
            ->setDescription('Update ALL installed Souvera apps according to the central update channel (dev = main HEAD, stable = latest release)');
    }

    protected function getAppId(): string {
        return $this->appId;
    }

    /** Alle Souvera-Apps der Suite, in Update-Reihenfolge. */
    public const SUITE_APPS = [
        'souvera_mail', 'souvera_central', 'souvera_shield',
        'souvera_mailarchiv', 'souvera_documents',
    ];

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $ok = true;
        $appManager = \OCP\Server::get(\OCP\App\IAppManager::class);
        $channel = \OCP\Server::get(\OCA\SouveraCentral\Service\ConfigService::class)->getSuiteUpdateChannel();
        $output->writeln('<info>Suite update channel: ' . $channel . '</info>');

        foreach (self::SUITE_APPS as $appId) {
            if (!$appManager->isInstalled($appId)) {
                $output->writeln($appId . ': not installed — skipped');
                continue;
            }
            try {
                $this->appId = $appId;
                $output->writeln('checking ' . $appId . ' …');
                // Explicit manual run: bypass throttle and maintenance window.
                $result = $this->checkAndUpdate(true);
                $output->writeln($appId . ': ' . json_encode($result, JSON_UNESCAPED_SLASHES));
                if (!empty($result['error'])) {
                    $ok = false;
                }
            } catch (\Throwable $e) {
                $output->writeln('<error>' . $appId . ': ' . $e->getMessage() . '</error>');
                $ok = false;
            }
        }
        // ---- Phase 2: Upgrade-Reconciliation (per-App `occ upgrade`-Äquivalent) ----
        // Ein Zipball-Swap setzt nur Dateien ein; laufen Migrationen schief oder
        // hängt NC im „upgrade pending"-Zustand (info.xml-Version > installed_version),
        // lädt NC die App gar nicht mehr (alle Routen 404). Hier schließt die
        // offizielle Upgrade-Routine die Lücke: OC_App::updateApp führt die
        // App-Migrationen aus und schreibt installed_version — für JEDE
        // Souvera-App, deren Code-Version vor der DB-Version liegt.
        $config = \OCP\Server::get(\OCP\IConfig::class);
        foreach (self::SUITE_APPS as $appId) {
            if (!$appManager->isInstalled($appId)) {
                continue;
            }
            try {
                $codeVersion = $appManager->getAppVersion($appId, false);
                $installedVersion = trim((string) $config->getAppValue($appId, 'installed_version', ''));
                if ($codeVersion === '' || $codeVersion === '0' || $installedVersion === '') {
                    continue;
                }
                if (version_compare($codeVersion, $installedVersion, '>')) {
                    $output->writeln($appId . ': upgrade pending (' . $installedVersion
                        . ' → ' . $codeVersion . ') — running app upgrade …');
                    \OC_App::updateApp($appId);
                    $config->setAppValue($appId, 'installed_version', $codeVersion);
                    $output->writeln($appId . ': upgrade resolved');
                }
            } catch (\Throwable $e) {
                $output->writeln('<error>' . $appId . ': upgrade reconciliation failed: ' . $e->getMessage() . '</error>');
                $ok = false;
            }
        }

        return $ok ? Command::SUCCESS : Command::FAILURE;
    }
}
