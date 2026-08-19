<?php

declare(strict_types=1);

/**
 * Souvera Central - Globale Alias-Übersicht (serverweit)
 *
 * Listet ALLE E-Mail-Aliase über den kompletten Stalwart-Server hinweg – aus
 * normalen Postfächern (User) UND geteilten Postfächern (Group) – inkl. des
 * Kontos, das den Alias hält. Ideal, um "Adresse hängt als Alias auf einem
 * Altkonto"-Konflikte aufzuspüren, die die Anlage eines neuen Postfachs
 * blockieren.
 *
 * Beispiele:
 *   occ souvera:alias:global-list
 *   occ souvera:alias:global-list --json
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AliasGlobalList extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:alias:global-list')
            ->setDescription('Listet ALLE E-Mail-Aliase serverweit (User + geteilte Postfächer) inkl. Besitzer.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ausgabe als JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $aliases = $this->stalwart->listAllAliases(true);
        usort($aliases, fn ($a, $b) => strcmp($a['alias'], $b['alias']));

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode([
                'count' => count($aliases),
                'aliases' => $aliases,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        if (empty($aliases)) {
            $output->writeln('<comment>Keine Aliase serverweit gefunden.</comment>');
            return 0;
        }

        $table = new Table($output);
        $table->setHeaders(['Alias', 'Besitzer (Postfach)', 'Typ']);
        foreach ($aliases as $a) {
            $table->addRow([
                $a['alias'],
                $a['owner'] ?? '—',
                $a['ownerType'] === 'group' ? 'Geteilt' : 'User',
            ]);
        }
        $table->render();
        $output->writeln('');
        $output->writeln('Aliase gesamt: <info>' . count($aliases) . '</info>');
        return 0;
    }
}
