<?php

declare(strict_types=1);

/**
 * Souvera Central - Alle Stalwart-Postfächer auflisten (Quelle: Stalwart)
 *
 * Listet direkt aus Stalwart alle individuellen Postfächer mit Limit und
 * Belegung. Anders als `souvera_central:report` (das an NC-Benutzer joint)
 * zeigt dies die reine Stalwart-Sicht – ideal, um Waisen/Abweichungen zu finden.
 *
 * Beispiele:
 *   occ souvera:mailbox:list
 *   occ souvera:mailbox:list --json
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\QuotaParser;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MailboxList extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:mailbox:list')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:mailbox:list'])
            ->setDescription('Listet alle Stalwart-Postfächer (Quelle: Stalwart) mit Limit und Belegung.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ausgabe als JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $usage = $this->stalwart->listMailboxUsage();
        ksort($usage);

        if ($input->getOption('json')) {
            $rows = [];
            foreach ($usage as $email => $u) {
                $rows[] = [
                    'email' => $email,
                    'used_bytes' => (int) $u['used'],
                    'quota_bytes' => (int) $u['quota'],
                    'usage_percent' => $u['quota'] > 0 ? (int) round($u['used'] / $u['quota'] * 100) : null,
                ];
            }
            $output->writeln((string) json_encode([
                'count' => count($rows),
                'mailboxes' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        if (empty($usage)) {
            $output->writeln('<comment>Keine Postfächer in Stalwart gefunden.</comment>');
            return 0;
        }

        $table = new Table($output);
        $table->setHeaders(['E-Mail', 'Belegung', 'Limit', 'Auslastung']);
        foreach ($usage as $email => $u) {
            $pct = $u['quota'] > 0 ? (int) round($u['used'] / $u['quota'] * 100) . ' %' : '—';
            $table->addRow([
                $email,
                QuotaParser::format((int) $u['used']),
                $u['quota'] > 0 ? QuotaParser::format((int) $u['quota']) : 'Unbegrenzt',
                $pct,
            ]);
        }
        $table->render();
        $output->writeln('');
        $output->writeln('Postfächer gesamt: <info>' . count($usage) . '</info>');
        return 0;
    }
}
