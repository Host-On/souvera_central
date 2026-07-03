<?php

declare(strict_types=1);

/**
 * Souvera Central - Aliase eines Postfachs auflisten
 *
 * Beispiel:
 *   occ souvera:alias:list falk@example.com
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AliasList extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:alias:list')
            ->setDescription('Listet die E-Mail-Aliase eines Postfachs.')
            ->addArgument('email', InputArgument::REQUIRED, 'Mailadresse des Postfachs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $email = strtolower(trim((string) $input->getArgument('email')));
        if (!$this->stalwart->principalExists($email)) {
            $output->writeln('<comment>Kein Postfach für ' . $email . ' vorhanden.</comment>');
            return 1;
        }

        $aliases = $this->stalwart->getAliases($email);
        $output->writeln('<info>Aliase für ' . $email . ' (' . count($aliases) . '):</info>');
        if (empty($aliases)) {
            $output->writeln('  <comment>Keine Aliase.</comment>');
        }
        foreach ($aliases as $alias) {
            $output->writeln('  - ' . $alias);
        }
        return 0;
    }
}
