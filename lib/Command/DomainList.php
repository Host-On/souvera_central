<?php

declare(strict_types=1);

/**
 * Souvera Central - Stalwart-Domains auflisten (mit Diagnose)
 *
 * Zeigt alle in Stalwart konfigurierten Domains. Werden Roh-IDs, aber keine
 * Namen gefunden, deutet das auf ein Schema-Problem hin – dann werden die
 * Roh-Objekte ausgegeben.
 *
 * Beispiel:
 *   occ souvera:domain:list
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DomainList extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:domain:list')
            ->setDescription('Listet die in Stalwart konfigurierten Domains (mit Diagnose).')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ausgabe als JSON (Roh-Objekte)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $raw = $this->stalwart->listDomainsRaw();

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $map = $this->stalwart->domainNameMap();
        if (!empty($map)) {
            $table = new Table($output);
            $table->setHeaders(['Domain', 'ID']);
            foreach ($map as $id => $name) {
                $table->addRow([$name, $id]);
            }
            $table->render();
            $output->writeln('Domains gesamt: <info>' . count($map) . '</info>');
            return 0;
        }

        $output->writeln('<comment>Keine Domains lesbar. Roh-IDs vom Server: ' . count($raw['ids']) . '</comment>');
        if (!empty($raw['list'])) {
            $output->writeln('<comment>Roh-Objekte (Schema-Prüfung):</comment>');
            $output->writeln((string) json_encode($raw['list'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $output->writeln('→ Es existiert keine Domain. Anlegen mit: <info>occ souvera:domain:add &lt;domain&gt;</info>');
        }
        return 0;
    }
}
