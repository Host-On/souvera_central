<?php

declare(strict_types=1);

/**
 * Souvera Central - occ souvera_central:ai:disable
 * Deaktiviert die KI-Funktion.
 *
 * Exit-Codes: 0 deaktiviert · 3 nicht initialisiert.
 */

namespace OCA\SouveraCentral\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AiDisable extends AbstractAiCommand
{
    protected function configure(): void
    {
        $this
            ->setName('souvera_central:ai:disable')
            ->setDescription('Deaktiviert die KI-Funktion')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ergebnis als JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->svc->setEnabled(false);
            $this->emit($input, $output, 'Souvera AI DEAKTIVIERT.');
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>Central nicht vollständig initialisiert: ' . $e->getMessage() . '</error>');
            return 3;
        }
    }
}
