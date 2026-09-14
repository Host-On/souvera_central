<?php

declare(strict_types=1);

/**
 * Souvera Central — occ souvera_central:signature:hook-config
 *
 * Verkabelt den zentralen Signatur-MTA-Hook AUTOMATISCH in die Stalwart-
 * Config (WebAdmin-API): Pre-Check (Kollisionen) → Snapshot → Write →
 * Verify. Rollback immer verfügbar.
 *
 *   (ohne Option)         : Hook-URL + Secret + Snippet ausgeben (read-only)
 *   --apply               : kollisions-sicher in die Stalwart-Config schreiben
 *   --rollback            : letzten Config-Stand wiederherstellen
 *   --status              : aktuelle Stalwart-Hook-/Webhook-Keys anzeigen
 *   --rotate-secret       : neues Hook-Secret generieren
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\AppInfo\Application;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartConfigService;
use OCP\IConfig;
use OCP\IURLGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SignatureHookConfig extends Base {
    public function __construct(
        private IConfig $config,
        private IURLGenerator $urlGenerator,
        private ConfigService $configService,
        private StalwartConfigService $stalwartConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setName('souvera_central:signature:hook-config')
            ->setDescription('Zentrale Signatur: Stalwart-Hook-Konfiguration anzeigen/automatisch verkabeln')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Hook kollisions-sicher automatisch in die Stalwart-Config schreiben')
            ->addOption('rollback', null, InputOption::VALUE_NONE, 'Letzten Config-Stand wiederherstellen')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Aktuelle Hook-/Webhook-Keys der Stalwart-Config anzeigen')
            ->addOption('rotate-secret', null, InputOption::VALUE_NONE, 'Neues Hook-Secret generieren');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if ($input->getOption('rotate-secret')) {
            $secret = \bin2hex(\random_bytes(32));
            $this->config->setAppValue(Application::APP_ID, 'settings.mail_signature.hook_secret', $secret);
            $output->writeln('<info>Neues Hook-Secret generiert.</info>');
        }

        $secret = (string) $this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.hook_secret', '');
        $hookUrl = $this->urlGenerator->getAbsoluteURL('/apps/souvera_central/signature/hook');
        $enabled = $this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.hook_enabled', '0') === '1';

        if ($input->getOption('status')) {
            return $this->showStatus($output);
        }
        if ($input->getOption('rollback')) {
            return $this->doRollback($output);
        }
        if ($input->getOption('apply')) {
            return $this->doApply($output, $secret);
        }

        // ---- Read-only Ausgabe ----
        if ($secret === '') {
            $output->writeln('<error>Kein Hook-Secret gesetzt. Erst mit --rotate-secret generieren (oder in der Admin-UI).</error>');
            return 1;
        }

        $output->writeln('Hook-URL:    ' . $hookUrl);
        $output->writeln('Secret:      ' . $secret);
        $output->writeln('Hook aktiv:  ' . ($enabled ? 'ja' : 'NEIN — in der Signatures-Admin-UI aktivieren'));
        $output->writeln('');
        $output->writeln('Automatisches Verkabeln:  occ souvera_central:signature:hook-config --apply');
        $output->writeln('Rollback:                 occ souvera_central:signature:hook-config --rollback');
        $output->writeln('Status:                   occ souvera_central:signature:hook-config --status');

        return 0;
    }

    private function doApply(OutputInterface $output, string $secret): int {
        if ($secret === '') {
            $output->writeln('<error>Kein Hook-Secret gesetzt — erst --rotate-secret oder die Admin-UI nutzen.</error>');
            return 1;
        }
        $hookUrl = $this->urlGenerator->getAbsoluteURL('/apps/souvera_central/signature/hook');
        $output->writeln('Wende Hook-Config an (Pre-Check → Snapshot → Write → Verify)…');
        $result = $this->stalwartConfig->apply($hookUrl, $secret);

        if (!$result['ok']) {
            $output->writeln('<error>FEHLGESCHLAGEN: ' . $result['error'] . '</error>');
            if (($result['precheck']['foreignHooks'] ?? []) !== []) {
                $output->writeln('Kollidierende Keys (NICHT angerührt):');
                foreach ($result['precheck']['foreignHooks'] as $k => $v) {
                    $output->writeln('  ' . $k . ' = ' . $v);
                }
            }
            if ($result['rollbackAvailable']) {
                $output->writeln('Rollback verfügbar: occ souvera_central:signature:hook-config --rollback');
            }
            return 1;
        }

        $output->writeln('<info>OK — Hook verkabelt und verifiziert.</info>');
        foreach ($result['written'] as $k => $v) {
            $output->writeln('  ' . $k . ' = ' . $v);
        }
        $output->writeln('Nächster Schritt: Hook in der Signatures-Admin-UI aktivieren (falls noch nicht geschehen) und eine Testmail aus Thunderbird senden.');
        return 0;
    }

    private function doRollback(OutputInterface $output): int {
        $result = $this->stalwartConfig->rollback();
        if ($result['ok']) {
            $output->writeln('<info>Rollback OK — vorheriger Config-Stand wiederhergestellt.</info>');
            return 0;
        }
        $output->writeln('<error>Rollback fehlgeschlagen: ' . $result['error'] . '</error>');
        return 1;
    }

    private function showStatus(OutputInterface $output): int {
        $result = $this->stalwartConfig->status();
        if (!$result['ok']) {
            $output->writeln('<error>' . $result['error'] . '</error>');
            return 1;
        }
        $output->writeln('Signatur-Hook-Keys (session.data.hooks*):');
        if (($result['signatureHook'] ?? []) === []) {
            $output->writeln('  (keine)');
        }
        foreach ($result['signatureHook'] as $k => $v) {
            $output->writeln('  ' . $k . ' = ' . $v);
        }
        $output->writeln('Event-Webhook-Keys (webhook.*) — von Signaturen UNBERÜHRT, u. a. Push-Benachrichtigungen:');
        $webhooks = $result['webhookKeys'] ?? [];
        if ($webhooks === []) {
            $output->writeln('  (keine)');
        }
        foreach ($webhooks as $k) {
            $output->writeln('  ' . $k);
        }
        $output->writeln('Rollback verfügbar: ' . ($result['rollbackAvailable'] ? 'ja' : 'nein'));
        return 0;
    }
}
