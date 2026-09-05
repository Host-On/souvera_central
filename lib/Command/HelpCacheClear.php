<?php

declare(strict_types=1);

/**
 * Souvera Central - Hilfe-Cache (BookStack) leeren.
 *
 * Erzwingt beim nächsten Aufruf ein Neuladen der Doku aus BookStack (z. B. nach
 * einer Dokumentations-Aktualisierung).
 *
 *   occ souvera:help:cache-clear
 */

namespace OCA\SouveraCentral\Command;

use OCA\SouveraCentral\Service\BookStackService;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HelpCacheClear extends Base {
    public function __construct(
        private BookStackService $bookStack,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:help:cache-clear')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:help:cache-clear'])
            ->setDescription('Leert den zwischengespeicherten BookStack-Inhalt der Hilfe.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if ($this->bookStack->clearCache()) {
            $output->writeln('<info>✓ Hilfe-Cache geleert. Die Doku wird beim nächsten Aufruf neu geladen.</info>');
        } else {
            $output->writeln('<comment>Kein Memcache konfiguriert – es ist kein Hilfe-Cache aktiv (nichts zu leeren).</comment>');
        }
        return 0;
    }
}
