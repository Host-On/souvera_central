<?php

declare(strict_types=1);

/**
 * Souvera Central - Alias serverweit entfernen (Adresse freigeben)
 *
 * Entfernt einen Alias von JEDEM Konto (User + geteilte Postfächer), das ihn
 * führt – unabhängig vom Hauptpostfach. Dient dazu, eine "gekaperte" Adresse
 * freizugeben, damit sie als eigenes Postfach angelegt werden kann. Zum Schutz
 * erst mit --yes ausführbar.
 *
 * Beispiele:
 *   occ souvera:alias:global-remove joerg@example.com          (zeigt nur an, was passiert)
 *   occ souvera:alias:global-remove joerg@example.com --yes    (entfernt)
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AliasGlobalRemove extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:alias:global-remove')
            ->setDescription('Entfernt einen Alias serverweit von JEDEM Konto, das ihn führt (gibt die Adresse frei).')
            ->addArgument('alias', InputArgument::REQUIRED, 'Zu entfernende Alias-Adresse')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Entfernen ohne Rückfrage bestätigen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $alias = strtolower(trim((string) $input->getArgument('alias')));
        $owners = $this->stalwart->findAliasOwners($alias, true);

        if (empty($owners)) {
            $output->writeln('<comment>Alias ' . $alias . ' wird von keinem Konto geführt – nichts zu entfernen.</comment>');
            if ($this->stalwart->principalExists($alias)) {
                $output->writeln('<comment>Hinweis: ' . $alias . ' ist eine Haupt-Adresse (Postfach). Zum Löschen: occ souvera:mailbox:delete ' . $alias . ' --yes</comment>');
            }
            return 0;
        }

        $output->writeln('<info>Alias ' . $alias . ' hängt an ' . count($owners) . ' Konto(en):</info>');
        foreach ($owners as $o) {
            $output->writeln('  - ' . ($o['owner'] ?? '(Adresse nicht lesbar)')
                . ' (' . ($o['ownerType'] === 'group' ? 'geteiltes Postfach' : 'User')
                . ', id=' . $o['accountId'] . ')');
        }

        if (!$input->getOption('yes')) {
            $output->writeln('');
            $output->writeln('<comment>Der Alias würde von allen o. g. Konten entfernt.</comment>');
            $output->writeln('Zum Ausführen erneut mit <info>--yes</info> aufrufen.');
            return 0;
        }

        $results = $this->stalwart->removeAliasByAddress($alias, true);
        $failed = 0;
        foreach ($results as $r) {
            $who = $r['owner'] ?? $r['accountId'];
            if ($r['removed']) {
                $output->writeln('<info>✓ Entfernt von ' . $who . '</info>');
            } else {
                $failed++;
                $output->writeln('<error>✗ Konnte nicht entfernt werden von ' . $who . '</error>');
            }
        }

        if ($failed > 0) {
            $err = $this->stalwart->getLastError();
            if ($err !== null) {
                $output->writeln('<comment>Stalwart-Detail: ' . json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</comment>');
            }
            return 1;
        }

        $output->writeln('');
        $output->writeln('<info>Die Adresse ' . $alias . ' ist jetzt frei und kann als Postfach angelegt werden.</info>');
        return 0;
    }
}
