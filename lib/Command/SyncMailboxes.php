<?php

declare(strict_types=1);

/**
 * Souvera Central - Backfill bestehender Postfächer (§6 der Bau-Anleitung)
 *
 * Legt Stalwart-Principals für bereits bestehende Nextcloud-Benutzer an, die
 * noch kein Postfach haben. Für Bestandsnutzer liegt kein Klartext-Passwort
 * vor, daher wird ein zufälliges Platzhalter-Secret gesetzt. Der Benutzer
 * setzt sein Mail-Passwort danach via "Passwort zurücksetzen" neu - das feuert
 * PasswordUpdatedEvent und der PasswordSyncListener spiegelt es nach Stalwart.
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncMailboxes extends Base {
    public function __construct(
        private IUserManager $userManager,
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:sync-mailboxes')
            ->setDescription('Legt fehlende Stalwart-Postfächer für bestehende Benutzer an (Backfill).')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Nur anzeigen, was passieren würde - ohne Änderungen'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured()) {
            $output->writeln('<error>Stalwart ist nicht konfiguriert (souvera_central.stalwart_*). Abbruch.</error>');
            return 1;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        if ($dryRun) {
            $output->writeln('<comment>DRY-RUN: Es werden keine Änderungen vorgenommen.</comment>');
        }

        $created = 0;
        $skipped = 0;
        $noMail = 0;
        $errors = 0;

        $this->userManager->callForAllUsers(function (IUser $user) use (&$created, &$skipped, &$noMail, &$errors, $dryRun, $output) {
            $uid = $user->getUID();

            // Admin-User wird von der Provisionierung ausgenommen
            if ($this->config->isAdminUser($uid)) {
                $skipped++;
                return;
            }

            try {
                if ($this->stalwart->principalExists($uid)) {
                    $skipped++;
                    return;
                }

                $mail = $this->stalwart->mailFor($user);
                if ($mail === null) {
                    $noMail++;
                    $output->writeln("  <comment>⊘ $uid: keine gültige Mail-Adresse/Domain</comment>");
                    return;
                }

                if ($dryRun) {
                    $output->writeln("  <info>+ würde anlegen: $uid → $mail</info>");
                    $created++;
                    return;
                }

                $ok = $this->stalwart->createPrincipal(
                    $uid,
                    $this->randomSecret(),
                    $mail,
                    $user->getDisplayName()
                );

                if ($ok) {
                    $created++;
                    $output->writeln("  <info>✓ angelegt: $uid → $mail</info>");
                } else {
                    $errors++;
                    $output->writeln("  <error>✗ Fehler bei: $uid</error>");
                }
            } catch (\Throwable $e) {
                $errors++;
                $output->writeln("  <error>✗ $uid: " . $e->getMessage() . '</error>');
            }
        });

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Fertig.</info> Angelegt: %d, Übersprungen: %d, Ohne Mail: %d, Fehler: %d',
            $created,
            $skipped,
            $noMail,
            $errors
        ));

        return $errors > 0 ? 1 : 0;
    }

    private function randomSecret(): string {
        return bin2hex(random_bytes(24));
    }
}
