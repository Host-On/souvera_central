<?php

declare(strict_types=1);

/**
 * Souvera Central - Stalwart-Verbindungsstatus & Diagnose
 *
 * Zeigt, ob Stalwart konfiguriert/erreichbar ist, listet die Domains und
 * diagnostiziert optional eine konkrete Mailadresse (Domain aufgelöst?
 * Konto vorhanden? Postfach-Status?). Ideal, um "Postfach wird nicht
 * angelegt"-Probleme einzugrenzen, wenn keine Logs verfügbar sind.
 *
 * Beispiele:
 *   occ souvera:stalwart:status
 *   occ souvera:stalwart:status --email falk@example.com
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\QuotaParser;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StalwartStatus extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:stalwart:status')
            ->setDescription('Zeigt Stalwart-Verbindungsstatus, Domains und (optional) eine E-Mail-Diagnose.')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Mailadresse für eine gezielte Diagnose (z. B. falk@example.com)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $status = $this->stalwart->getStatus();
        $output->writeln('<info>Stalwart-Status</info>');
        $output->writeln('  Konfiguriert: ' . ($status['configured'] ? '<info>ja</info>' : '<error>nein</error>'));
        $output->writeln('  Erreichbar:   ' . ($status['available'] ? '<info>ja</info>' : '<error>nein</error>'));
        $output->writeln('  API-URL:      ' . ($status['url'] ?? '—'));

        if (!$status['configured']) {
            $output->writeln('<error>Stalwart ist nicht konfiguriert (config.php: souvera_central.stalwart_*).</error>');
            return 1;
        }
        if (!$status['available']) {
            $this->printLastError($output);
            return 1;
        }

        $domains = $this->stalwart->domainNameMap();
        $output->writeln('');
        $output->writeln('<info>Domains in Stalwart (' . count($domains) . '):</info>');
        foreach ($domains as $id => $name) {
            $output->writeln('  - ' . $name . ' <comment>(id=' . $id . ')</comment>');
        }

        $email = $input->getOption('email');
        if ($email !== null && $email !== '') {
            $this->diagnose($output, strtolower(trim((string) $email)));
        }

        return 0;
    }

    private function diagnose(OutputInterface $output, string $email): void {
        $output->writeln('');
        $output->writeln('<info>Diagnose für ' . $email . '</info>');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('  <error>Ungültiges Mailformat.</error>');
            return;
        }
        $pos = strrpos($email, '@');
        $domain = substr($email, $pos + 1);

        $allowed = $this->config->isEmailDomainAllowed($email);
        $output->writeln('  Domain erlaubt (Config): ' . ($allowed ? '<info>ja</info>' : '<error>nein</error>'));

        $domainId = $this->stalwart->resolveDomainId($domain);
        $output->writeln('  Domain in Stalwart:      ' . ($domainId !== null ? '<info>ja (id=' . $domainId . ')</info>' : '<error>NEIN – Domain fehlt in Stalwart!</error>'));

        $accountId = $this->stalwart->findAccountId($email, 'User');
        $output->writeln('  Konto vorhanden:         ' . ($accountId !== null ? '<info>ja (id=' . $accountId . ')</info>' : '<comment>nein (Postfach noch nicht angelegt)</comment>'));

        if ($accountId !== null) {
            $st = $this->stalwart->getMailboxStatus($email);
            $output->writeln('  Haupt-Adresse:           ' . ($st['email'] ?? '—'));
            $output->writeln('  Aliase:                  ' . (empty($st['aliases']) ? '—' : implode(', ', $st['aliases'])));
            $output->writeln('  Limit / Belegung:        ' . ($st['quota'] > 0 ? QuotaParser::format($st['quota']) : 'Unbegrenzt') . ' / ' . QuotaParser::format($st['used']));
        }
        $this->printLastError($output);
    }

    private function printLastError(OutputInterface $output): void {
        $err = $this->stalwart->getLastError();
        if ($err !== null) {
            $output->writeln('<comment>Letztes Stalwart-Detail: ' . json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</comment>');
        }
    }
}
