<?php

declare(strict_types=1);

/**
 * Souvera Central - occ souvera_central:ai:status
 * Read-only-Diagnose (snapshot()). Exit 0 immer (health-probe-sicher).
 */

namespace OCA\SouveraCentral\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AiStatus extends AbstractAiCommand
{
    protected function configure(): void
    {
        $this
            ->setName('souvera_central:ai:status')
            ->setDescription('Zeigt den Status der KI-Funktion (Tabelle oder --json)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ergebnis als JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->emit($input, $output);
        return 0;
    }
}
