<?php

declare(strict_types=1);

/**
 * Souvera Central - occ souvera_central:external:configure
 * Feinjustierung einzelner Toggles, OHNE den enable/disable-Zustand zu ändern.
 * --reset setzt alle Keys auf ihre Defaults (Test-Hook / sauberer Neustart).
 *
 * Exit-Codes: 0 angewendet · 2 ungültige Eingabe · 3 Central nicht initialisiert.
 */

namespace OCA\SouveraCentral\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExternalConfigure extends AbstractExternalCommand {
    protected function configure(): void {
        $this
            ->setName('souvera_central:external:configure')
            ->setDescription('Feinjustierung der Policy „Externe Mail-Konten" (ohne enable/disable)')
            ->addOption('groups', null, InputOption::VALUE_REQUIRED, 'Erlaubte NC-Gruppen überschreiben (kommagetrennt; leer = alle)')
            ->addOption('max-per-user', null, InputOption::VALUE_REQUIRED, 'Cap überschreiben (1..20)')
            ->addOption('consent-required', null, InputOption::VALUE_REQUIRED, 'DSGVO-Dialog (y|n)')
            ->addOption('smtp-guard', null, InputOption::VALUE_REQUIRED, 'SMTP-Fehler-Schutz (y|n)')
            ->addOption('migration-handoff', null, InputOption::VALUE_REQUIRED, 'Migration-Handoff (y|n)')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Alle Keys auf Default zurücksetzen')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ergebnis als JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            if ($input->getOption('reset')) {
                $this->svc->resetToDefaults();
                $this->emit($input, $output, 'Policy „Externe Mail-Konten" auf Standard zurückgesetzt.');
                return 0;
            }
            $err = $this->applyPolicyOptions($input, $output);
            if ($err !== null) {
                return $err;
            }
            $this->emit($input, $output, 'Policy „Externe Mail-Konten" aktualisiert.');
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>Central nicht vollständig initialisiert: ' . $e->getMessage() . '</error>');
            return 3;
        }
    }
}
