<?php

declare(strict_types=1);

/**
 * Souvera Central - Mails ALLER Postfächer reindexieren (Store-weit)
 *
 * Löst in Stalwart eine Reindexierung der Mails aller Konten aus. Übersetzt den
 * stalwart-cli-Befehl
 *   create task/StoreMaintenance --field maintenanceType=reindexAccounts ...
 * in einen JMAP x:Task/set-Aufruf. Zum Schutz erst mit --yes ausführbar, da
 * dies eine schwere, serverweite Operation ist.
 *
 * Beispiele:
 *   occ souvera:mailbox:reindex-all              (zeigt nur an, was passiert)
 *   occ souvera:mailbox:reindex-all --yes        (startet die Reindexierung)
 *   occ souvera:mailbox:reindex-all --yes --due 2026-07-05T16:00:00Z
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MailboxReindexAll extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:mailbox:reindex-all')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:mailbox:reindex-all'])
            ->setDescription('Reindexiert die Mails ALLER Postfächer in Stalwart (serverweit).')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Ausführen ohne Rückfrage bestätigen')
            ->addOption('due', null, InputOption::VALUE_REQUIRED, 'Fälligkeit (ISO-8601, UTC). Ohne Angabe: sofort.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        if (!$input->getOption('yes')) {
            $output->writeln('<comment>Es würde eine Reindexierung ALLER Postfächer (serverweit) gestartet.</comment>');
            $output->writeln('Zum Ausführen erneut mit <info>--yes</info> aufrufen.');
            return 0;
        }

        $due = $input->getOption('due');
        $ok = $this->stalwart->reindexAllAccounts($due !== null ? (string) $due : null);
        if (!$ok) {
            $output->writeln('<error>Serverweite Reindexierung konnte nicht gestartet werden.</error>');
            $err = $this->stalwart->getLastError();
            if ($err !== null) {
                $output->writeln('<comment>Stalwart-Detail: ' . json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</comment>');
            }
            return 1;
        }

        $output->writeln('<info>✓ Serverweite Reindexierung eingeplant.</info>');
        $output->writeln('<comment>Stalwart verarbeitet den Task im Hintergrund (kann je nach Datenmenge dauern).</comment>');
        return 0;
    }
}
