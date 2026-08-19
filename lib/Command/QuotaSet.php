<?php

declare(strict_types=1);

/**
 * Souvera Central - Postfach-Speicherlimit (Stalwart) setzen (CLI)
 *
 * Setzt das Disk-Quota (quotas/maxDiskQuota) eines einzelnen Postfachs oder
 * aller Postfächer. Bei --all wird zusätzlich der globale Standard aktualisiert,
 * mit dem neue Postfächer angelegt werden.
 *
 * Beispiele:
 *   occ souvera_central:quota:set 50G scadmin@example.com
 *   occ souvera_central:quota:set 50G --all
 *   occ souvera_central:quota:set 0 info@example.com      # 0 = unbegrenzt
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\QuotaParser;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QuotaSet extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:quota:set')
            ->setDescription('Setzt das Postfach-Speicherlimit (Stalwart) für ein Postfach oder für alle Postfächer.')
            ->addArgument('size', InputArgument::REQUIRED, 'Speicherlimit, z. B. 50G, 500M, 1T oder 0/none für unbegrenzt')
            ->addArgument('email', InputArgument::OPTIONAL, 'Mailadresse des Postfachs (weglassen bei --all)')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Auf ALLE Postfächer anwenden UND den globalen Standard aktualisieren');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured()) {
            $output->writeln('<error>Stalwart ist nicht konfiguriert (souvera_central.stalwart_*). Abbruch.</error>');
            return 1;
        }
        if (!$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart Mail-Server (JMAP) nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $bytes = QuotaParser::toBytes((string) $input->getArgument('size'));
        if ($bytes === null) {
            $output->writeln('<error>Ungültige Größe: ' . $input->getArgument('size') . '. Beispiele: 50G, 500M, 1T, 0 (unbegrenzt).</error>');
            return 1;
        }

        $email = $input->getArgument('email');
        $all = (bool) $input->getOption('all');

        if ($all && $email) {
            $output->writeln('<error>Bitte entweder eine E-Mail-Adresse ODER --all angeben, nicht beides.</error>');
            return 1;
        }
        if (!$all && ($email === null || trim((string) $email) === '')) {
            $output->writeln('<error>Bitte eine E-Mail-Adresse angeben oder --all verwenden.</error>');
            return 1;
        }

        $human = QuotaParser::format($bytes);

        if ($all) {
            $emails = $this->stalwart->listPrincipalNames();
            if (empty($emails)) {
                $output->writeln('<comment>Keine Postfächer gefunden.</comment>');
            }
            $ok = 0;
            $fail = 0;
            foreach ($emails as $mailbox) {
                if ($this->stalwart->setMailboxQuota($mailbox, $bytes)) {
                    $ok++;
                    $output->writeln('  <info>✓ ' . $mailbox . ' → ' . $human . '</info>');
                } else {
                    $fail++;
                    $output->writeln('  <error>✗ ' . $mailbox . '</error>');
                }
            }

            // Neuer globaler Standard für künftige Postfächer.
            $this->config->setDefaultMailboxQuota($bytes);

            $output->writeln('');
            $output->writeln(sprintf(
                '<info>Fertig.</info> Aktualisiert: %d, Fehler: %d. Neuer globaler Standard für neue Postfächer: <info>%s</info>.',
                $ok,
                $fail,
                $human
            ));
            return $fail > 0 ? 1 : 0;
        }

        $email = strtolower(trim((string) $email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Ungültige Mailadresse: ' . $email . '</error>');
            return 1;
        }
        if (!$this->stalwart->principalExists($email)) {
            $output->writeln('<error>Kein Postfach gefunden für: ' . $email . '</error>');
            return 1;
        }

        if ($this->stalwart->setMailboxQuota($email, $bytes)) {
            $output->writeln('<info>✓ Speicherlimit gesetzt: ' . $email . ' → ' . $human . '</info>');
            return 0;
        }

        $output->writeln('<error>Speicherlimit konnte nicht gesetzt werden: ' . $email . '</error>');
        return 1;
    }
}
