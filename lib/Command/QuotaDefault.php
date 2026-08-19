<?php

declare(strict_types=1);

/**
 * Souvera Central - Globalen Standard für das Postfach-Speicherlimit setzen (CLI)
 *
 * Setzt NUR den globalen Standard, mit dem NEUE Postfächer angelegt werden.
 * Bestehende Postfächer werden dabei NICHT verändert (dafür siehe
 * souvera_central:quota:set --all). Dieser Wert wird bewusst nur vom Hoster per
 * occ gesetzt, nicht über die Weboberfläche.
 *
 * Beispiele:
 *   occ souvera_central:quota:default 50G
 *   occ souvera_central:quota:default 0        # 0 = unbegrenzt
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\QuotaParser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QuotaDefault extends Base {
    public function __construct(
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:quota:default')
            ->setDescription('Setzt den globalen Standard für das Postfach-Speicherlimit neuer Postfächer (nur Hoster/CLI).')
            ->addArgument('size', InputArgument::REQUIRED, 'Standard-Speicherlimit, z. B. 50G, 500M, 1T oder 0/none für unbegrenzt');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $bytes = QuotaParser::toBytes((string) $input->getArgument('size'));
        if ($bytes === null) {
            $output->writeln('<error>Ungültige Größe: ' . $input->getArgument('size') . '. Beispiele: 50G, 500M, 1T, 0 (unbegrenzt).</error>');
            return 1;
        }

        $this->config->setDefaultMailboxQuota($bytes);

        $output->writeln('<info>Standard-Postfach-Speicherlimit für NEUE Postfächer gesetzt: ' . QuotaParser::format($bytes) . '</info>');
        $output->writeln('<comment>Hinweis: Bestehende Postfächer bleiben unverändert. Mit "souvera_central:quota:set <size> --all" auch alle bestehenden anpassen.</comment>');
        return 0;
    }
}
