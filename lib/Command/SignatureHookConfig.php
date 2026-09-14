<?php

declare(strict_types=1);

/**
 * Souvera Central — occ souvera_central:signature:hook-config
 *
 * Gibt die Stalwart-MTA-Hook-Konfiguration für die zentrale Signatur-
 * Injection aus (URL + Secret + Stalwart-Config-Snippet zum Copy-Paste)
 * und kann das Secret rotieren.
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\AppInfo\Application;
use OCA\SouveraCentral\Service\ConfigService;
use OCP\IConfig;
use OCP\IURLGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SignatureHookConfig extends Base {
    public function __construct(
        private IConfig $config,
        private IURLGenerator $urlGenerator,
        private ConfigService $configService,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setName('souvera_central:signature:hook-config')
            ->setDescription('Stalwart MTA-Hook-Konfiguration für die zentrale Signatur ausgeben')
            ->addOption('rotate-secret', null, InputOption::VALUE_NONE, 'Neues Hook-Secret generieren');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if ($input->getOption('rotate-secret')) {
            $secret = \bin2hex(\random_bytes(32));
            $this->config->setAppValue(Application::APP_ID, 'settings.mail_signature.hook_secret', $secret);
            $output->writeln('<info>Neues Hook-Secret generiert.</info>');
        }

        $secret = (string) $this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.hook_secret', '');
        if ($secret === '') {
            $output->writeln('<error>Kein Hook-Secret gesetzt. Erst ausführen mit --rotate-secret oder in der Central-Admin-UI generieren.</error>');
            return 1;
        }

        $url = \rtrim($this->urlGenerator->getAbsoluteURL('/apps/souvera_central/signature/hook'), '/');
        $enabled = $this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.hook_enabled', '0') === '1';

        $output->writeln('Hook-URL:    ' . $url);
        $output->writeln('Secret:      ' . $secret);
        $output->writeln('Hook aktiv:  ' . ($enabled ? 'ja' : 'NEIN — in der Central-Admin-UI aktivieren'));

        $output->writeln('');
        $output->writeln('Stalwart-Config-Snippet (in die Stalwart-Konfiguration der Cloud einpflegen):');
        $output->writeln(<<<'SNIPPET'
        # Zentrale Signatur-Injection (DATA-Stage) — vor DKIM-Signierung
        [session.data.hooks]
        hook = "http"
        url = "<HOOK-URL>"
        # Optional: nur Mailgrößen unter dem Limit an den Hook schicken
        # expression = "message_size < 10485760"
        SNIPPET);
        $output->writeln('');
        $output->writeln('Im Snippet <HOOK-URL> durch die Hook-URL ersetzen und den Secret als');
        $output->writeln('Authorization:-Header konfigurieren (siehe Stalwart-Doku: session.data.hooks).');
        $output->writeln('Hinweis: Wenn der SIEVE-Signatur-Pfad (mailsignature:sieve --deploy) aktiv ist,');
        $output->writeln('zuerst mit --remove entfernen — sonst doppelte Signaturen.');

        return 0;
    }
}
