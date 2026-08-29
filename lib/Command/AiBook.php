<?php

declare(strict_types=1);

/**
 * Souvera Central - occ souvera_central:ai:book
 * Bucht das Add-on Souvera AI für die Instanz (bzw. entbucht mit --unbook).
 *
 * Wird vom Hoster / CloudManager gesetzt. Das Entbuchen deaktiviert die
 * Funktion automatisch mit.
 *
 * Exit-Codes: 0 angewendet · 3 nicht initialisiert.
 */

namespace OCA\SouveraCentral\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AiBook extends AbstractAiCommand
{
    protected function configure(): void
    {
        $this
            ->setName('souvera_central:ai:book')
            ->setDescription('Bucht das Add-on Souvera AI (--unbook zum Entbuchen)')
            ->addOption('unbook', null, InputOption::VALUE_NONE, 'Buchung entfernen (deaktiviert die Funktion)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ergebnis als JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $booked = !(bool) $input->getOption('unbook');

        try {
            $this->svc->setBooked($booked);
            $this->emit($input, $output, $booked ? 'Souvera AI GEBUCHT.' : 'Souvera AI ENTBUCHT (und deaktiviert).');
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>Central nicht vollständig initialisiert: ' . $e->getMessage() . '</error>');
            return 3;
        }
    }
}
