<?php

declare(strict_types=1);

/**
 * Souvera Central - Mails eines einzelnen Postfachs reindexieren
 *
 * Löst in Stalwart eine Reindexierung der Mails eines Kontos aus (Volltextindex
 * neu aufbauen). Übersetzt den stalwart-cli-Befehl
 *   create task/AccountMaintenance --field maintenanceType=reindex ...
 * in einen JMAP x:Task/set-Aufruf.
 *
 * Beispiele:
 *   occ souvera:mailbox:reindex falk@example.com
 *   occ souvera:mailbox:reindex falk@example.com --due 2026-07-05T16:00:00Z
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MailboxReindex extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:mailbox:reindex')
            ->setDescription('Reindexiert die Mails eines einzelnen Postfachs in Stalwart.')
            ->addArgument('email', InputArgument::REQUIRED, 'Mailadresse des Postfachs')
            ->addOption('due', null, InputOption::VALUE_REQUIRED, 'Fälligkeit (ISO-8601, UTC). Ohne Angabe: sofort.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $email = strtolower(trim((string) $input->getArgument('email')));
        if (!$this->stalwart->principalExists($email)) {
            $output->writeln('<error>Kein Postfach für ' . $email . ' vorhanden.</error>');
            return 1;
        }

        $due = $input->getOption('due');
        $ok = $this->stalwart->reindexAccount($email, $due !== null ? (string) $due : null);
        if (!$ok) {
            $output->writeln('<error>Reindexierung konnte nicht gestartet werden: ' . $email . '</error>');
            $err = $this->stalwart->getLastError();
            if ($err !== null) {
                $output->writeln('<comment>Stalwart-Detail: ' . json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</comment>');
            }
            return 1;
        }

        $output->writeln('<info>✓ Reindexierung eingeplant für ' . $email . '.</info>');
        $output->writeln('<comment>Stalwart verarbeitet den Task im Hintergrund.</comment>');
        return 0;
    }
}
