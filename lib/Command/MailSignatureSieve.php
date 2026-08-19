<?php

declare(strict_types=1);

/**
 * Souvera Central - occ souvera_central:mailsignature:sieve
 *
 * Standard (ohne Option): gibt das aus der zentralen Signatur-Vorlage generierte
 * Stalwart-Sieve-System-Script aus (Vorschau / manuelle Einrichtung).
 *
 * Optionen:
 *   --deploy   Deployt das Script per JMAP direkt nach Stalwart und hängt es
 *              (gefahrlos) in die SMTP-DATA-Stage ein.
 *   --remove   Entfernt das Signatur-Script wieder (aus DATA-Stage aushängen + löschen).
 *   --status   Zeigt den aktuellen Deployment-Status (deployed/aktiv/verdrahtet).
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\MailSignatureDeployService;
use OCA\SouveraCentral\Service\MailSignatureService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MailSignatureSieve extends Base {
    public function __construct(
        private ConfigService $config,
        private MailSignatureDeployService $deploy,
        private StalwartService $stalwart,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setName('souvera_central:mailsignature:sieve')
            ->setDescription('Globale Mail-Signatur als Stalwart-Sieve-System-Script anzeigen/deployen/entfernen')
            ->addOption('deploy', null, InputOption::VALUE_NONE, 'Script per JMAP nach Stalwart deployen + in DATA-Stage einhängen')
            ->addOption('remove', null, InputOption::VALUE_NONE, 'Signatur-Script wieder entfernen (aushängen + löschen)')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Aktuellen Deployment-Status anzeigen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if ($input->getOption('status')) {
            return $this->showStatus($output);
        }
        if ($input->getOption('remove')) {
            return $this->doRemove($output);
        }
        if ($input->getOption('deploy')) {
            return $this->doDeploy($output);
        }
        return $this->showScript($output);
    }

    private function showScript(OutputInterface $output): int {
        if (!$this->config->isMailSignatureEnabled()) {
            $output->writeln('<comment>Hinweis: Die globale Mail-Signatur ist derzeit DEAKTIVIERT (Central > Einstellungen > Mail-Signatur).</comment>');
        }
        if (!$this->config->isMailSignatureServerSide()) {
            $output->writeln('<comment>Hinweis: „Serverseitig erzwingen" ist AUS – die Signatur wird aktuell nur im Webmail (souvera_mail) gerendert.</comment>');
        }

        $template = $this->config->getMailSignatureTemplate();
        if (trim($template) === '') {
            $output->writeln('<error>Keine Signatur-Vorlage hinterlegt. Bitte zuerst in der Central-Oberfläche setzen.</error>');
            return 1;
        }

        $script = MailSignatureService::buildSieveScript($template);
        $output->writeln('');
        $output->writeln('<info>===== Stalwart Sieve System Script =====</info>');
        $output->writeln($script);
        $output->writeln('<info>========================================</info>');
        $output->writeln('');
        $output->writeln('Automatisch ausrollen:  occ souvera_central:mailsignature:sieve --deploy');
        $output->writeln('Manuell (Alternative):');
        $output->writeln('  1. Settings > Sieve > System Scripts: neues Script "' . StalwartService::SIGNATURE_SCRIPT_NAME . '" mit obigem Inhalt.');
        $output->writeln('  2. Settings > MTA > Session > DATA Stage: dieses Script auswählen, Save & Reload.');
        return 0;
    }

    private function doDeploy(OutputInterface $output): int {
        if (!$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart ist nicht konfiguriert oder nicht erreichbar.</error>');
            return 1;
        }
        if (trim($this->config->getMailSignatureTemplate()) === '') {
            $output->writeln('<error>Keine Signatur-Vorlage hinterlegt. Bitte zuerst in der Central-Oberfläche setzen.</error>');
            return 1;
        }

        $res = $this->deploy->deploy();
        if (!$res['deployed']) {
            $output->writeln('<error>Deployment fehlgeschlagen: ' . ($res['error'] ?? 'unbekannt') . '</error>');
            $err = $this->stalwart->getLastError();
            if ($err !== null) {
                $output->writeln('<comment>Stalwart-Detail: ' . json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</comment>');
            }
            return 1;
        }

        $output->writeln('<info>✓ Sieve-System-Script "' . StalwartService::SIGNATURE_SCRIPT_NAME . '" deployed (aktiv).</info>');
        if ($res['wired']) {
            $output->writeln('<info>✓ In die SMTP-DATA-Stage eingehängt – serverseitige Signatur ist aktiv.</info>');
        } elseif ($res['existing_script'] !== null) {
            $output->writeln('<comment>⚠ DATA-Stage NICHT automatisch verdrahtet: dort ist bereits ein anderes Script aktiv ("' . $res['existing_script'] . '").</comment>');
            $output->writeln('<comment>  Bitte manuell einbinden, z. B. per include :global "' . StalwartService::SIGNATURE_SCRIPT_NAME . '"; im Haupt-Script,</comment>');
            $output->writeln('<comment>  oder in Settings > MTA > Session > DATA Stage das Script "' . StalwartService::SIGNATURE_SCRIPT_NAME . '" auswählen.</comment>');
        }
        return 0;
    }

    private function doRemove(OutputInterface $output): int {
        if (!$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart ist nicht konfiguriert oder nicht erreichbar.</error>');
            return 1;
        }
        $res = $this->deploy->remove();
        if (!$res['removed']) {
            $output->writeln('<error>Entfernen fehlgeschlagen: ' . ($res['error'] ?? 'unbekannt') . '</error>');
            return 1;
        }
        $output->writeln('<info>✓ Signatur-Sieve-Script entfernt.</info>');
        if ($res['unwired']) {
            $output->writeln('<info>✓ Aus der SMTP-DATA-Stage ausgehängt.</info>');
        }
        return 0;
    }

    private function showStatus(OutputInterface $output): int {
        if (!$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart ist nicht konfiguriert oder nicht erreichbar.</error>');
            return 1;
        }
        $st = $this->stalwart->getSignatureDeploymentStatus();
        $output->writeln('Signatur (Central):  aktiviert=' . ($this->config->isMailSignatureEnabled() ? 'ja' : 'nein')
            . ', serverseitig=' . ($this->config->isMailSignatureServerSide() ? 'ja' : 'nein'));
        $output->writeln('Sieve-Script:        deployed=' . ($st['deployed'] ? 'ja' : 'nein') . ', aktiv=' . ($st['active'] ? 'ja' : 'nein'));
        $output->writeln('DATA-Stage-Script:   ' . ($st['data_stage_script'] ?? '(keins)') . ($st['wired'] ? '  (= Souvera-Signatur)' : ''));
        return 0;
    }
}
