<?php

declare(strict_types=1);

/**
 * Souvera Central - occ souvera_central:external:disable
 * Deaktiviert die Funktion „Externe Mail-Konten".
 *
 * --purge setzt zusätzlich einen einmaligen Lösch-Marker; die eigentlichen
 * pro-Nutzer-Konten löscht souvera_mail (Datenhoheit dort) beim nächsten Boot.
 *
 * Exit-Codes: 0 deaktiviert · 3 Central nicht initialisiert.
 */

namespace OCA\SouveraCentral\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExternalDisable extends AbstractExternalCommand {
    protected function configure(): void {
        $this
            ->setName('souvera_central:external:disable')
            ->setDescription('Deaktiviert „Externe Mail-Konten"')
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Zusätzlich: alle bereits verbundenen externen Konten löschen (souvera_mail arbeitet den Marker ab; unwiderruflich)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ergebnis als JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $this->svc->setEnabled(false);
            $purge = (bool) $input->getOption('purge');
            if ($purge) {
                $this->svc->requestPurge();
            }
            $this->emit($input, $output, 'Externe Mail-Konten DEAKTIVIERT.');
            if ($purge && !($input->hasOption('json') && $input->getOption('json'))) {
                $output->writeln('<comment>Purge angefordert: souvera_mail löscht die verbundenen externen Konten beim nächsten Start (unwiderruflich).</comment>');
            }
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>Central nicht vollständig initialisiert: ' . $e->getMessage() . '</error>');
            return 3;
        }
    }
}
