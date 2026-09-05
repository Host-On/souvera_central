<?php

declare(strict_types=1);

/**
 * Souvera Central - Alias serverweit finden
 *
 * Zeigt, welches Konto (Postfach) einen bestimmten Alias hält – unabhängig
 * davon, welche Haupt-Mailadresse damit verbunden ist. Nützlich, wenn eine
 * Adresse sich nicht als Postfach anlegen lässt, weil sie noch als Alias auf
 * einem (Alt-)Konto hängt.
 *
 * Beispiel:
 *   occ souvera:alias:global-find joerg@example.com
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AliasGlobalFind extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:alias:global-find')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:alias:global-find'])
            ->setDescription('Zeigt, welches Konto einen bestimmten Alias serverweit hält.')
            ->addArgument('alias', InputArgument::REQUIRED, 'Zu suchende Alias-/Mailadresse');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $alias = strtolower(trim((string) $input->getArgument('alias')));
        $owners = $this->stalwart->findAliasOwners($alias, true);

        if (empty($owners)) {
            $output->writeln('<comment>Alias ' . $alias . ' wird von KEINEM Konto als Alias geführt.</comment>');
            if ($this->stalwart->principalExists($alias)) {
                $output->writeln('<comment>Hinweis: ' . $alias . ' ist bereits eine Haupt-Adresse (Postfach), kein Alias.</comment>');
            }
            return 0;
        }

        $output->writeln('<info>Alias ' . $alias . ' gefunden auf ' . count($owners) . ' Konto(en):</info>');
        foreach ($owners as $o) {
            $output->writeln('  - ' . ($o['owner'] ?? '(Adresse nicht lesbar)')
                . ' <comment>(' . ($o['ownerType'] === 'group' ? 'geteiltes Postfach' : 'User')
                . ', id=' . $o['accountId'] . ')</comment>');
        }
        $output->writeln('');
        $output->writeln('Zum Freigeben: <info>occ souvera:alias:global-remove ' . $alias . ' --yes</info>');
        return 0;
    }
}
