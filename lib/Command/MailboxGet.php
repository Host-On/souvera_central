<?php

declare(strict_types=1);

/**
 * Souvera Central - Ein Stalwart-Postfach anzeigen
 *
 * Zeigt Details eines Postfachs: existiert es? Haupt-Adresse, Aliase,
 * Limit und Belegung. Nützlich zur Kontrolle nach dem Anlegen.
 *
 * Beispiel:
 *   occ souvera:mailbox:get falk@example.com
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\QuotaParser;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MailboxGet extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:mailbox:get')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:mailbox:get'])
            ->setDescription('Zeigt Details eines Stalwart-Postfachs (Aliase, Limit, Belegung).')
            ->addArgument('email', InputArgument::REQUIRED, 'Mailadresse des Postfachs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $email = strtolower(trim((string) $input->getArgument('email')));
        $st = $this->stalwart->getMailboxStatus($email);

        if (!$st['exists']) {
            $output->writeln('<comment>Kein Postfach für ' . $email . ' vorhanden.</comment>');
            return 1;
        }

        $output->writeln('<info>Postfach ' . $email . '</info>');
        $output->writeln('  Haupt-Adresse: ' . ($st['email'] ?? '—'));
        $output->writeln('  Aliase (' . count($st['aliases']) . '): ' . (empty($st['aliases']) ? '—' : implode(', ', $st['aliases'])));
        $output->writeln('  Limit:         ' . ($st['quota'] > 0 ? QuotaParser::format((int) $st['quota']) : 'Unbegrenzt'));
        $output->writeln('  Belegung:      ' . QuotaParser::format((int) $st['used'])
            . ($st['quota'] > 0 ? ' (' . (int) round($st['used'] / $st['quota'] * 100) . ' %)' : ''));
        return 0;
    }
}
