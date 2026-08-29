<?php

declare(strict_types=1);

/**
 * Souvera Central - occ souvera_central:ai:enable
 * Aktiviert die KI-Funktion. Nur erlaubt, wenn die Instanz AI gebucht hat.
 *
 * Exit-Codes: 0 aktiviert · 2 nicht gebucht / ungültig · 3 nicht initialisiert.
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
            ->setDescription('Aktiviert die KI-Funktion (nur wenn AI gebucht ist)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ergebnis als JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->svc->isBooked()) {
            $output->writeln('<error>Souvera AI ist für diese Instanz nicht gebucht. Zuerst mit `occ souvera_central:ai:book` buchen.</error>');
            return 2;
        }

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
