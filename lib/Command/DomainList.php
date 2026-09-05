<?php

declare(strict_types=1);

/**
 * Souvera Central - occ souvera:domain:list
 *
 * Listet die Mail-Domains des Workspaces mit Stalwart-Status und
 * Belegung (Postfächer/Aliase) — die occ-Seite der Domain-Verwaltung
 * (UI/API: {@see DomainManagementService}, Contract: docs/MULTI_DOMAIN.md).
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\DomainManagementService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DomainList extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
        private DomainManagementService $domains,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:domain:list')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:domain:list'])
            ->setDescription('Listet die Mail-Domains mit Stalwart-Status und Belegung.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ergebnis als JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $result = $this->domains->listDomains();

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $table = new Table($output);
        $table->setHeaders(['Domain', 'Stalwart', 'Erlaubt', 'Postfächer', 'Aliase']);
        foreach ($result['domains'] as $d) {
            $table->addRow([
                $d['domain'],
                $d['in_stalwart'] ? '✓' : '✗',
                $d['allowed'] ? '✓' : '—',
                (string) $d['accounts'],
                (string) $d['aliases'],
            ]);
        }
        $table->render();

        $output->writeln('Mail-Domains gesamt: <info>' . count($result['domains']) . '</info>');
        if (!$result['stalwart_available']) {
            $output->writeln('<comment>Stalwart-Werte konnten nicht abgefragt werden.</comment>');
        }
        return 0;
    }
}
