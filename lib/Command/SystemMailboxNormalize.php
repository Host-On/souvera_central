<?php

declare(strict_types=1);

/**
 * Souvera Central - occ souvera_central:system-mailbox:normalize
 *
 * Setzt alle internen System-/Dienst-Postfächer (postmaster@, mailer-daemon@, …)
 * auf ein kleines Limit (Default 1 GiB). Diese Postfächer gehören nicht dem
 * Kunden, werden ihm nicht angezeigt und zählen nicht in den Mail-Speicher-Pool.
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\QuotaParser;
use OCA\SouveraCentral\Service\StalwartService;
use OCA\SouveraCentral\Service\StorageService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SystemMailboxNormalize extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setName('souvera_central:system-mailbox:normalize')
            ->setDescription('Setzt interne System-Postfächer (postmaster@, …) auf ein kleines Limit (Default 1 GB) und nimmt sie aus dem Pool')
            ->addOption('quota', null, InputOption::VALUE_REQUIRED, 'Zielgröße, nur G/GB oder T/TB (Standard: konfigurierter System-Wert, i. d. R. 1G)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, was geändert würde – nichts schreiben');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured()) {
            $output->writeln('<error>Stalwart ist nicht konfiguriert.</error>');
            return 1;
        }

        $target = $this->config->getSystemMailboxQuota();
        $quotaOpt = $input->getOption('quota');
        if ($quotaOpt !== null && $quotaOpt !== '') {
            if (!QuotaParser::isMailStoragePoolInput((string) $quotaOpt)) {
                $output->writeln('<error>Ungültiges --quota: ' . $quotaOpt . '. Erlaubt sind nur G/GB oder T/TB.</error>');
                return 1;
            }
            $target = (int) QuotaParser::toBytes((string) $quotaOpt);
        }

        $parts = $this->config->getSystemMailboxLocalParts();
        $dry = (bool) $input->getOption('dry-run');

        $all = $this->stalwart->listMailboxUsage() + $this->stalwart->listSharedMailboxUsage();
        $changed = 0;
        $skipped = 0;
        $found = 0;

        foreach ($all as $email => $usage) {
            if (!StorageService::isSystemMailbox((string) $email, $parts)) {
                continue;
            }
            $found++;
            $current = (int) ($usage['quota'] ?? 0);
            if ($current === $target) {
                $output->writeln(sprintf('  = %s bereits %s', $email, QuotaParser::format($target)));
                $skipped++;
                continue;
            }
            if ($dry) {
                $output->writeln(sprintf('  ~ %s %s -> %s (dry-run)', $email, QuotaParser::format($current), QuotaParser::format($target)));
                continue;
            }
            $ok = $this->stalwart->setMailboxQuota((string) $email, $target);
            if ($ok) {
                $output->writeln(sprintf('  <info>✓</info> %s %s -> %s', $email, QuotaParser::format($current), QuotaParser::format($target)));
                $changed++;
            } else {
                $output->writeln(sprintf('  <error>✗ %s: Limit konnte nicht gesetzt werden</error>', $email));
            }
        }

        if ($found === 0) {
            $output->writeln('<comment>Keine System-Postfächer gefunden (' . implode(', ', $parts) . ').</comment>');
        } else {
            $output->writeln(sprintf('<info>Fertig. Gefunden: %d, geändert: %d, unverändert: %d.</info>', $found, $changed, $skipped));
        }
        return 0;
    }
}
