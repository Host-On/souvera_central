<?php

declare(strict_types=1);

/**
 * Souvera Central - Lizenz-Anzahl setzen (CLI, nur Hoster)
 *
 * Setzt die maximale Anzahl an Souvera-Lizenzen (config.php:
 * souvera_central.max_licenses). Der Wert wird von der App überall aus der
 * Config gelesen; ohne Setzen greift der Default 10. Wird bewusst nur per occ
 * durch den Hoster gesetzt, nicht über die Weboberfläche.
 *
 * Beispiele:
 *   occ souvera_central:licenses:set 50
 *   occ souvera_central:licenses:set 250
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\LicenseService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LicensesSet extends Base {
    public function __construct(
        private ConfigService $config,
        private LicenseService $license,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:licenses:set')
            ->setDescription('Setzt die maximale Anzahl an Souvera-Lizenzen (config.php). Nur Hoster/CLI.')
            ->addArgument('count', InputArgument::REQUIRED, 'Anzahl der Lizenzen (ganze Zahl >= 0)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $raw = trim((string) $input->getArgument('count'));
        if ($raw === '' || !ctype_digit($raw)) {
            $output->writeln('<error>Ungültige Anzahl: "' . $raw . '". Bitte eine ganze Zahl >= 0 angeben (z. B. 50).</error>');
            return 1;
        }

        $count = (int) $raw;
        $this->config->setMaxLicenses($count);

        $used = $this->license->getUsedLicenses();
        $output->writeln('<info>✓ Lizenz-Limit gesetzt: ' . $count . '</info>');
        $output->writeln(sprintf('Aktuell genutzt: <info>%d/%d</info>', $used, $count));

        if ($used > $count) {
            $output->writeln(sprintf(
                '<comment>Achtung: Es sind bereits %d Souvera User lizenziert – das ist mehr als das neue Limit (%d). Bestehende Nutzer bleiben aktiv, es können aber keine neuen aufgenommen werden.</comment>',
                $used,
                $count
            ));
        }

        return 0;
    }
}
