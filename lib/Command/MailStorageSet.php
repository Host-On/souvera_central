<?php

declare(strict_types=1);

/**
 * Souvera Central - Mail-Speicher-Pool ("Mail Storage") setzen (CLI, nur Hoster)
 *
 * Setzt den GESAMTEN Mail-Speicher (config.php: souvera_central.max_mail_storage,
 * Bytes), den die Central-UI dann in GB-Schritten auf Souvera-User-Postfächer und
 * geteilte Postfächer verteilt. Bewusst NUR per occ durch den Hoster/CloudManager
 * (nicht in der Weboberfläche änderbar) – nicht zu verwechseln mit dem
 * Nextcloud-Dateispeicher.
 *
 * Downgrade nur bis zur bereits verteilten Gesamtmenge. 0/none = Pool entfernen
 * (unbegrenzt, wie ohne Pool).
 *
 * Beispiele:
 *   occ souvera_central:mailstorage:set 100G
 *   occ souvera_central:mailstorage:set 500G
 *   occ souvera_central:mailstorage:set 0        # Pool entfernen (unbegrenzt)
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\QuotaParser;
use OCA\SouveraCentral\Service\StalwartService;
use OCA\SouveraCentral\Service\StorageService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MailStorageSet extends Base {
    public function __construct(
        private ConfigService $config,
        private StalwartService $stalwart,
        private StorageService $storage,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:mailstorage:set')
            ->setDescription('Setzt den gesamten Mail-Speicher-Pool (config.php), der in der Central-UI verteilt wird. Nur Hoster/CLI.')
            ->addArgument('size', InputArgument::REQUIRED, 'Gesamt-Mail-Speicher – NUR in G/GB oder T/TB, z. B. 100G, 500GB, 2T, 2TB (oder 0/none zum Entfernen)')
            ->addOption('mailbox-default', null, InputOption::VALUE_REQUIRED, 'Standard-Postfach-Limit bei aktivem Pool (z. B. 1G), das neuen Postfächern ohne explizites Limit zugewiesen wird')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Downgrade auch unter die bereits verteilte Gesamtmenge erzwingen (nicht empfohlen)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $raw = (string) $input->getArgument('size');

        // Nur G/GB oder T/TB erlauben (nackte Zahl = Bytes, K/M, GiB/TiB usw. ablehnen).
        if (!QuotaParser::isMailStoragePoolInput($raw)) {
            $output->writeln('<error>Ungültige Größe: ' . $raw . '. Erlaubt sind nur G/GB oder T/TB, z. B. 100G, 500GB, 2T, 2TB (oder 0/none zum Entfernen).</error>');
            return 1;
        }

        $bytes = QuotaParser::toBytes($raw);
        if ($bytes === null) {
            $output->writeln('<error>Ungültige Größe: ' . $raw . '. Erlaubt sind nur G/GB oder T/TB, z. B. 100G, 500GB, 2T, 2TB (oder 0/none zum Entfernen).</error>');
            return 1;
        }

        // Verteilte Gesamtmenge nur ermitteln, wenn Stalwart erreichbar ist.
        $allocated = null;
        if ($this->config->isStalwartConfigured() && $this->stalwart->isAvailable()) {
            $allocated = $this->storage->getAllocatedStorage();
        }

        // Downgrade-Schutz: neuer Pool darf die verteilte Menge nicht unterschreiten.
        if ($bytes > 0 && $allocated !== null && $bytes < $allocated && !$input->getOption('force')) {
            $output->writeln(sprintf(
                '<error>Abbruch: Der Pool (%s) ist kleiner als die bereits verteilte Gesamtmenge (%s).</error>',
                QuotaParser::format($bytes),
                QuotaParser::format($allocated)
            ));
            $output->writeln('<comment>Erst Postfach-Limits in der Central reduzieren oder --force verwenden.</comment>');
            return 1;
        }

        $this->config->setMaxMailStorage($bytes);

        // Optional: Standard-Postfach-Limit bei aktivem Pool setzen.
        $mailboxDefaultOpt = $input->getOption('mailbox-default');
        if ($mailboxDefaultOpt !== null && $mailboxDefaultOpt !== '') {
            if (!QuotaParser::isMailStoragePoolInput((string) $mailboxDefaultOpt)) {
                $output->writeln('<error>Ungültiges --mailbox-default: ' . $mailboxDefaultOpt . '. Erlaubt sind nur G/GB oder T/TB, z. B. 1G, 5GB.</error>');
                return 1;
            }
            $defBytes = QuotaParser::toBytes((string) $mailboxDefaultOpt);
            if ($defBytes === null || $defBytes <= 0) {
                $output->writeln('<error>Ungültiges --mailbox-default: ' . $mailboxDefaultOpt . '. Muss größer als 0 sein.</error>');
                return 1;
            }
            $this->config->setPoolDefaultMailboxQuota($defBytes);
            $output->writeln('<info>✓ Standard-Postfach-Limit (Pool) gesetzt: ' . QuotaParser::format($defBytes) . '</info>');
        }

        if ($bytes <= 0) {
            $output->writeln('<info>✓ Mail-Speicher-Pool entfernt (unbegrenzt).</info>');
            return 0;
        }

        $output->writeln('<info>✓ Mail-Speicher-Pool gesetzt: ' . QuotaParser::format($bytes) . '</info>');
        if ($allocated !== null) {
            $available = StorageService::available($bytes, $allocated);
            $output->writeln(sprintf(
                'Verteilt: <info>%s</info> | Verfügbar: <info>%s</info>',
                QuotaParser::format($allocated),
                QuotaParser::format($available)
            ));
        } else {
            $output->writeln('<comment>Hinweis: Stalwart war nicht erreichbar – verteilte Menge nicht geprüft.</comment>');
        }
        return 0;
    }
}
