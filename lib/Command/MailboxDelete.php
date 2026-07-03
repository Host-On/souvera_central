<?php

declare(strict_types=1);

/**
 * Souvera Central - Stalwart-Postfach löschen
 *
 * Löscht NUR das Stalwart-Postfach (inkl. Inhalt) – der Nextcloud-Benutzer
 * bleibt bestehen. Zum Schutz erst mit --yes ausführbar.
 *
 * Beispiele:
 *   occ souvera:mailbox:delete alt@example.com          (zeigt nur an, was passiert)
 *   occ souvera:mailbox:delete alt@example.com --yes    (löscht)
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MailboxDelete extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:mailbox:delete')
            ->setDescription('Löscht ein Stalwart-Postfach (der NC-Benutzer bleibt bestehen).')
            ->addArgument('email', InputArgument::REQUIRED, 'Mailadresse des Postfachs')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Löschen ohne Rückfrage bestätigen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $email = strtolower(trim((string) $input->getArgument('email')));
        if (!$this->stalwart->principalExists($email)) {
            $output->writeln('<comment>Kein Postfach für ' . $email . ' vorhanden – nichts zu löschen.</comment>');
            return 0;
        }

        if (!$input->getOption('yes')) {
            $output->writeln('<comment>Das Postfach ' . $email . ' (inkl. aller E-Mails) würde gelöscht.</comment>');
            $output->writeln('Zum Ausführen erneut mit <info>--yes</info> aufrufen.');
            return 0;
        }

        $ok = $this->stalwart->deletePrincipal($email);
        if (!$ok) {
            $output->writeln('<error>Postfach konnte nicht gelöscht werden: ' . $email . '</error>');
            $err = $this->stalwart->getLastError();
            if ($err !== null) {
                $output->writeln('<comment>Stalwart-Detail: ' . json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</comment>');
            }
            return 1;
        }

        $output->writeln('<info>✓ Postfach gelöscht: ' . $email . '</info>');
        return 0;
    }
}
