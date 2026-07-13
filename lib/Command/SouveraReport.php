<?php

declare(strict_types=1);

/**
 * Souvera Central - Postfach-Report (CLI, für Cloud Manager / Hoster)
 *
 * Gibt eine strukturierte Übersicht aller Souvera-Postfächer mit Speicherlimit
 * und Belegung aus. Standard: Tabelle. Mit --json maschinenlesbar.
 *
 * Beispiele:
 *   occ souvera_central:report
 *   occ souvera_central:report --json
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\QuotaParser;
use OCA\SouveraCentral\Service\StalwartService;
use OCA\SouveraCentral\Service\StorageService;
use OCP\IGroupManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SouveraReport extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
        private IGroupManager $groupManager,
        private StorageService $storage,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:report')
            ->setDescription('Strukturierte Übersicht aller Souvera-Postfächer mit Speicherlimit und Belegung (für den Cloud Manager / Hoster).')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ausgabe als JSON (maschinenlesbar)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured()) {
            $output->writeln('<error>Stalwart ist nicht konfiguriert. Abbruch.</error>');
            return 1;
        }
        if (!$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart Mail-Server (JMAP) nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        // Belegung + Limit je Postfach (Quelle der Wahrheit).
        $usage = $this->stalwart->listMailboxUsage();

        // NC-Info (Anzeigename, Admin) je Mailadresse.
        $adminGid = $this->config->getScadminGroupId();
        $userGid = $this->config->getMailGroupId();
        $adminMembers = [];
        $adminGroup = $this->groupManager->get($adminGid);
        if ($adminGroup !== null) {
            foreach ($adminGroup->getUsers() as $u) {
                $adminMembers[$u->getUID()] = true;
            }
        }
        $index = [];
        foreach ([$userGid, $adminGid] as $gid) {
            $group = $this->groupManager->get($gid);
            if ($group === null) {
                continue;
            }
            foreach ($group->getUsers() as $u) {
                $mail = $this->stalwart->mailFor($u);
                if ($mail === null) {
                    continue;
                }
                $index[strtolower($mail)] = [
                    'uid' => $u->getUID(),
                    'displayName' => $u->getDisplayName(),
                    'isAdmin' => isset($adminMembers[$u->getUID()]),
                ];
            }
        }

        $rows = [];
        $totalUsed = 0;
        $totalQuota = 0;
        $unlimited = 0;
        ksort($usage);
        foreach ($usage as $email => $u) {
            $used = (int) $u['used'];
            $quota = (int) $u['quota'];
            $info = $index[$email] ?? null;
            $pct = $quota > 0 ? (int) round($used / $quota * 100) : null;
            $totalUsed += $used;
            if ($quota > 0) {
                $totalQuota += $quota;
            } else {
                $unlimited++;
            }
            $rows[] = [
                'uid' => $info['uid'] ?? null,
                'displayName' => $info['displayName'] ?? null,
                'email' => $email,
                'isAdmin' => $info['isAdmin'] ?? false,
                'used_bytes' => $used,
                'quota_bytes' => $quota,
                'used' => QuotaParser::format($used),
                'quota' => $quota > 0 ? QuotaParser::format($quota) : 'Unbegrenzt',
                'usage_percent' => $pct,
            ];
        }

        if ($input->getOption('json')) {
            $poolMax = $this->config->getMaxMailStorage();
            $allocated = $this->storage->getAllocatedStorage();
            $output->writeln((string) json_encode([
                'generated_at' => date('c'),
                'default_mailbox_quota_bytes' => $this->config->getDefaultMailboxQuota(),
                'mail_storage_pool_bytes' => $poolMax,
                'mail_storage_allocated_bytes' => $allocated,
                'mail_storage_available_bytes' => StorageService::available($poolMax, $allocated),
                'mailbox_count' => count($rows),
                'total_used_bytes' => $totalUsed,
                'total_quota_bytes' => $totalQuota,
                'mailboxes' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        if (empty($rows)) {
            $output->writeln('<comment>Keine Postfächer gefunden.</comment>');
            return 0;
        }

        $table = new Table($output);
        $table->setHeaders(['UID', 'Name', 'E-Mail', 'Typ', 'Belegung', 'Limit', 'Auslastung']);
        foreach ($rows as $r) {
            $table->addRow([
                $r['uid'] ?? '—',
                $r['displayName'] ?? '—',
                $r['email'],
                $r['isAdmin'] ? 'Admin' : 'User',
                $r['used'],
                $r['quota'],
                $r['usage_percent'] === null ? '—' : $r['usage_percent'] . ' %',
            ]);
        }
        $table->render();

        $output->writeln('');
        $output->writeln(sprintf(
            'Postfächer: <info>%d</info> | Belegt gesamt: <info>%s</info> | Limit gesamt: <info>%s</info>%s | Standard-Limit: <info>%s</info>',
            count($rows),
            QuotaParser::format($totalUsed),
            QuotaParser::format($totalQuota),
            $unlimited > 0 ? sprintf(' (+%d unbegrenzt)', $unlimited) : '',
            QuotaParser::format($this->config->getDefaultMailboxQuota())
        ));

        // Mail-Speicher-Pool (verteilt = User + geteilte Postfächer)
        $poolMax = $this->config->getMaxMailStorage();
        if ($poolMax > 0) {
            $allocated = $this->storage->getAllocatedStorage();
            $output->writeln(sprintf(
                'Mail-Speicher-Pool: <info>%s</info> gesamt | <info>%s</info> verteilt | <info>%s</info> verfügbar',
                QuotaParser::format($poolMax),
                QuotaParser::format($allocated),
                QuotaParser::format(StorageService::available($poolMax, $allocated))
            ));
        } else {
            $output->writeln('Mail-Speicher-Pool: <comment>nicht gesetzt (unbegrenzt)</comment>');
        }
        return 0;
    }
}
