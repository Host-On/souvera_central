<?php

declare(strict_types=1);

/**
 * Souvera Central - Nextcloud-Benutzer zum Souvera User machen (CLI)
 *
 * Macht einen bestehenden Nextcloud-Benutzer zum lizenzierten "Souvera User":
 * Aufnahme in die Gruppe souvera-users + Stalwart-Postfach. Verwendet exakt
 * dieselbe Logik wie der UI-Button "Zum Souvera User machen"
 * (MailGroupService::makeSouveraUser) inklusive Lizenzprüfung.
 *
 * Beispiele:
 *   occ souvera:make-souvera-user anna@example.com
 *   occ souvera:make-souvera-user anna@example.com --generate
 *   occ souvera:make-souvera-user anna@example.com --password-stdin <<< "$PW"
 *   occ souvera:make-souvera-user anna@example.com --force      # Lizenzlimit ignorieren
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\LicenseService;
use OCA\SouveraCentral\Service\MailGroupService;
use OCA\SouveraCentral\Service\StorageService;
use OCP\IGroupManager;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MakeSouveraUser extends Base {
    public function __construct(
        private IUserManager $userManager,
        private IGroupManager $groupManager,
        private ConfigService $config,
        private MailGroupService $mailGroup,
        private LicenseService $license,
        private StorageService $storage,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:make-souvera-user')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:make-souvera-user'])
            ->setDescription('Macht einen bestehenden Nextcloud-Benutzer zum lizenzierten Souvera User (souvera-users + Postfach).')
            ->addArgument('user', InputArgument::REQUIRED, 'UID des Benutzers (i. d. R. = E-Mail-Adresse)')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Klartext-Passwort fürs Postfach')
            ->addOption('password-stdin', null, InputOption::VALUE_NONE, 'Postfach-Passwort aus STDIN lesen (sicher für Pipelines)')
            ->addOption('generate', null, InputOption::VALUE_NONE, 'Zufälliges Postfach-Passwort erzeugen und ausgeben')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Lizenzlimit ignorieren');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $arg = trim((string) $input->getArgument('user'));
        $user = $this->resolveUser($arg);
        if ($user === null) {
            $output->writeln('<error>Benutzer nicht gefunden (UID oder E-Mail): ' . $arg . '</error>');
            return 1;
        }
        $uid = $user->getUID();

        if ($this->mailGroup->isMember($user)) {
            $output->writeln('<comment>Benutzer ist bereits ein Souvera User: ' . $uid . '</comment>');
            return 0;
        }

        // Lizenzprüfung (Souvera-Administratoren in scadmin verbrauchen keine Lizenz)
        $force = (bool) $input->getOption('force');
        $isScadmin = $this->groupManager->isInGroup($uid, $this->config->getScadminGroupId());
        if (!$isScadmin && !$force && $this->license->isLimitReached()) {
            $output->writeln(sprintf(
                '<error>Lizenzlimit erreicht (%d/%d). Mit --force überschreiben.</error>',
                $this->license->getUsedLicenses(),
                $this->license->getMaxLicenses()
            ));
            return 1;
        }

        $generated = false;
        $password = $this->resolvePassword($input, $generated); // null => Zufallspasswort in makeSouveraUser

        // Mail-Speicher-Pool auflösen + erzwingen (mit --force überspringbar).
        $resolved = $this->storage->resolveNewMailboxQuota(null);
        if ($resolved['error'] !== null && !$force) {
            $output->writeln('<error>' . $resolved['error'] . ' Mit --force überschreiben.</error>');
            return 1;
        }

        $ok = $this->mailGroup->makeSouveraUser($user, $password, $resolved['quota']);

        $output->writeln('<info>✓ ' . $uid . ' ist jetzt ein Souvera User (souvera-users).</info>');
        if (!$ok) {
            $output->writeln('<comment>Hinweis: Postfach konnte nicht angelegt werden (Stalwart nicht erreichbar/konfiguriert?). Gruppen-Mitgliedschaft ist gesetzt.</comment>');
        } elseif ($generated && $password !== null) {
            $output->writeln('<comment>Erzeugtes Postfach-Passwort: ' . $password . '</comment>');
        }
        $output->writeln(sprintf(
            '<info>Lizenzen genutzt: %d/%d</info>',
            $this->license->getUsedLicenses(),
            $this->license->getMaxLicenses()
        ));

        return 0;
    }

    /**
     * Benutzer per UID auflösen, sonst per E-Mail-Adresse (CM/CLI nutzt oft die Mail).
     */
    private function resolveUser(string $arg): ?\OCP\IUser {
        $user = $this->userManager->get($arg);
        if ($user !== null) {
            return $user;
        }
        if (filter_var($arg, FILTER_VALIDATE_EMAIL)) {
            $byEmail = $this->userManager->getByEmail($arg);
            if (count($byEmail) >= 1) {
                return $byEmail[0];
            }
        }
        return null;
    }

    /**
     * Postfach-Passwort ermitteln (Priorität: stdin > password > generate). null = Zufallspasswort.
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
