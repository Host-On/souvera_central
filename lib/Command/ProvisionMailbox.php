<?php

declare(strict_types=1);

/**
 * Souvera Central - Einzelnes Postfach provisionieren (Build-/Setup-Schritt)
 *
 * Legt im Build-Prozess gezielt ein Stalwart-Postfach an - typischerweise für
 * den Nextcloud-Admin (ncadmin), der vom automatischen Backfill (souvera:sync-mailboxes)
 * ausgenommen ist. Idempotent: existiert das Postfach bereits, wird nur das
 * Passwort gesetzt.
 *
 * Beispiele:
 *   occ souvera:provision-mailbox admin@example.com --password-stdin <<< "$ADMIN_PW"
 *   occ souvera:provision-mailbox admin@example.com -p "geheim" --display-name "Administrator"
 *   occ souvera:provision-mailbox info@example.com --generate --quota 5368709120
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\MailGroupService;
use OCA\SouveraCentral\Service\StalwartService;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProvisionMailbox extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
        private MailGroupService $mailGroup,
        private IUserManager $userManager,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:provision-mailbox')
            ->setDescription('Legt gezielt ein Stalwart-Postfach an (z. B. für den ncadmin im Build).')
            ->addArgument('email', InputArgument::REQUIRED, 'Mailadresse des Postfachs (z. B. admin@example.com)')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Klartext-Passwort')
            ->addOption('password-stdin', null, InputOption::VALUE_NONE, 'Passwort aus STDIN lesen (sicher für Pipelines)')
            ->addOption('generate', null, InputOption::VALUE_NONE, 'Zufälliges Passwort erzeugen und ausgeben')
            ->addOption('display-name', null, InputOption::VALUE_REQUIRED, 'Anzeigename (description)')
            ->addOption('quota', null, InputOption::VALUE_REQUIRED, 'Disk-Quota in Bytes (0 = unbegrenzt; ohne Angabe = globaler Config-Standard)');
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

        $email = strtolower(trim((string) $input->getArgument('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Ungültige Mailadresse: ' . $email . '</error>');
            return 1;
        }
        if (!$this->config->isEmailDomainAllowed($email)) {
            $output->writeln('<error>Domain nicht erlaubt für: ' . $email . '</error>');
            return 1;
        }

        $generated = false;
        $password = $this->resolvePassword($input, $generated);
        if ($password === null || $password === '') {
            $output->writeln('<error>Kein Passwort angegeben. Nutze --password, --password-stdin oder --generate.</error>');
            return 1;
        }

        $displayName = $input->getOption('display-name');
        $quotaOpt = $input->getOption('quota');
        $quota = ($quotaOpt === null || $quotaOpt === '') ? null : max(0, (int) $quotaOpt);

        $existed = $this->stalwart->principalExists($email);
        $ok = $this->stalwart->createPrincipal($email, $password, $displayName ?: null, $quota);
        if (!$ok) {
            $output->writeln('<error>Postfach konnte nicht angelegt/aktualisiert werden: ' . $email . '</error>');
            return 1;
        }

        // Wenn ein passender NC-Benutzer existiert: Mail-Gruppe pflegen (smail-Sichtbarkeit)
        $user = $this->userManager->get($email);
        if ($user !== null) {
            $this->mailGroup->addUser($user);
        }

        $output->writeln(sprintf(
            '<info>%s Postfach %s</info>',
            $existed ? '✓ Aktualisiert:' : '✓ Angelegt:',
            $email
        ));
        if ($generated) {
            $output->writeln('<comment>Erzeugtes Passwort: ' . $password . '</comment>');
        }

        return 0;
    }

    /**
     * Passwort aus den Optionen ermitteln (Priorität: stdin > password > generate).
     */
    private function resolvePassword(InputInterface $input, bool &$generated): ?string {
        if ($input->getOption('password-stdin')) {
            $line = fgets(STDIN);
            return $line === false ? null : trim($line);
        }
        $password = $input->getOption('password');
        if ($password !== null && $password !== '') {
            return (string) $password;
        }
        if ($input->getOption('generate')) {
            $generated = true;
            return bin2hex(random_bytes(24));
        }
        return null;
    }
}
