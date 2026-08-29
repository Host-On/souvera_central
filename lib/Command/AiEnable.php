<?php

declare(strict_types=1);

/**
 * Souvera Central - occ souvera_central:ai:enable
 * Aktiviert die KI-Funktion. Die Prüfung, ob die Instanz das Add-on gebucht
 * hat, liegt beim Hoster/CloudManager — er ruft enable nur auf gebuchten
 * Instanzen auf.
 *
 * Exit-Codes: 0 aktiviert · 3 nicht initialisiert.
 */

namespace OCA\SouveraCentral\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AiEnable extends AbstractAiCommand
{
    protected function configure(): void
    {
        $this
            ->setName('souvera_central:ai:enable')
            ->setDescription('Aktiviert die KI-Funktion')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ergebnis als JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->svc->setEnabled(true);
            $this->emit($input, $output, 'Souvera AI AKTIVIERT.');
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>Central nicht vollständig initialisiert: ' . $e->getMessage() . '</error>');
            return 3;
        }
    }
}
