<?php

declare(strict_types=1);

/**
 * Souvera Central - occ souvera_central:mailsignature:sieve
 *
 * Gibt das aus der zentralen Signatur-Vorlage generierte Stalwart-Sieve-
 * System-Script aus (für die serverseitige Zustellung an ALLE SMTP-Clients).
 * Auf einer Live-Stalwart-Instanz (>= 0.16.6) einzurichten:
 *   Settings > Sieve > System Scripts  (Script anlegen)
 *   Settings > MTA > Session > DATA Stage  (Script auswählen)
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\MailSignatureService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MailSignatureSieve extends Base {
    public function __construct(
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setName('souvera_central:mailsignature:sieve')
            ->setDescription('Generiert das Stalwart-Sieve-System-Script für die globale Mail-Signatur (serverseitig)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isMailSignatureEnabled()) {
            $output->writeln('<comment>Hinweis: Die globale Mail-Signatur ist derzeit DEAKTIVIERT (Central > Einstellungen > Mail-Signatur).</comment>');
        }
        if (!$this->config->isMailSignatureServerSide()) {
            $output->writeln('<comment>Hinweis: „Serverseitig erzwingen" ist AUS – die Signatur wird aktuell nur im Webmail (souvera_mail) gerendert.</comment>');
        }

        $template = $this->config->getMailSignatureTemplate();
        if (trim($template) === '') {
            $output->writeln('<error>Keine Signatur-Vorlage hinterlegt. Bitte zuerst in der Central-Oberfläche setzen.</error>');
            return 1;
        }

        $script = MailSignatureService::buildSieveScript($template);

        $output->writeln('');
        $output->writeln('<info>===== Stalwart Sieve System Script =====</info>');
        $output->writeln($script);
        $output->writeln('<info>========================================</info>');
        $output->writeln('');
        $output->writeln('Einrichtung in Stalwart (>= 0.16.6):');
        $output->writeln('  1. Settings > Sieve > System Scripts: neues Script (z. B. "souvera_signature") mit obigem Inhalt.');
        $output->writeln('  2. Settings > MTA > Session > DATA Stage: dieses Script auswählen, Save & Reload.');
        $output->writeln('  3. Testmail senden und DKIM prüfen.');
        return 0;
    }
}
