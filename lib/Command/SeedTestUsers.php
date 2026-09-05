<?php
/**
 * Souvera Central - Seeder für Testbenutzer
 *
 * OCC Command zum Erstellen von Test-Benutzern
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCP\IUserManager;
use OCP\IGroupManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class SeedTestUsers extends Base {
    private $userManager;
    private $groupManager;

    public function __construct(
        IUserManager $userManager,
        IGroupManager $groupManager
    ) {
        parent::__construct();
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
    }

    protected function configure() {
        $this
            ->setName('souvera_central:seed-users')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:seed-users'])
            ->setDescription('Erstellt Testbenutzer für Souvera Central')
            ->addOption(
                'count',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Anzahl der zu erstellenden Benutzer',
                100
            )
            ->addOption(
                'domain',
                'd',
                InputOption::VALUE_OPTIONAL,
                'E-Mail Domain für Testbenutzer',
                'example.com'
            )
            ->addOption(
                'password',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Passwort für alle Testbenutzer',
                'TestPassword123!'
            )
            ->addOption(
                'prefix',
                null,
                InputOption::VALUE_OPTIONAL,
                'Präfix für Benutzernamen',
                'testuser'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $count = (int) $input->getOption('count');
        $domain = $input->getOption('domain');
        $password = $input->getOption('password');
        $prefix = $input->getOption('prefix');

        $output->writeln("<info>Erstelle $count Testbenutzer...</info>");
        $output->writeln("<comment>Domain: $domain</comment>");
        $output->writeln("<comment>Passwort: $password</comment>");
        $output->writeln("<comment>Präfix: $prefix</comment>");
        $output->writeln('');

        // Progress Bar erstellen
        $progressBar = new ProgressBar($output, $count);
        $progressBar->setFormat('verbose');
        $progressBar->start();

        $created = 0;
        $skipped = 0;
        $errors = 0;
        $errorMessages = [];

        // Testgruppe erstellen (falls nicht vorhanden)
        $testGroup = $this->groupManager->get('test-users');
        if ($testGroup === null) {
            $testGroup = $this->groupManager->createGroup('test-users');
            $output->writeln('');
            $output->writeln("<info>Gruppe 'test-users' erstellt</info>");
        }

        for ($i = 1; $i <= $count; $i++) {
            $username = sprintf('%s%03d', $prefix, $i);
            $displayName = sprintf('Test User %03d', $i);
            $email = sprintf('%s%03d@%s', $prefix, $i, $domain);

            try {
                // Prüfen ob User bereits existiert
                if ($this->userManager->get($username) !== null) {
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                // User erstellen
                $user = $this->userManager->createUser($username, $password);

                if ($user === false || $user === null) {
                    $errors++;
                    $errorMessages[] = "User '$username' konnte nicht erstellt werden (createUser returned false/null)";
                    $progressBar->advance();
                    continue;
                }

                // Eigenschaften setzen
                $user->setDisplayName($displayName);
                $user->setEMailAddress($email);
                $user->setEnabled(true);

                // Zu Testgruppe hinzufügen
                if ($testGroup !== null) {
                    $testGroup->addUser($user);
                }

                $created++;
                $progressBar->advance();

            } catch (\Exception $e) {
                $errors++;
                $errorMessages[] = "User '$username': " . $e->getMessage();
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln('');

        // Zusammenfassung
        $output->writeln('<info>═══════════════════════════════════════</info>');
        $output->writeln('<info>Zusammenfassung:</info>');
        $output->writeln('<info>═══════════════════════════════════════</info>');
        $output->writeln(sprintf('<info>✓ Erstellt:   %d Benutzer</info>', $created));

        if ($skipped > 0) {
            $output->writeln(sprintf('<comment>⊘ Übersprungen: %d (bereits vorhanden)</comment>', $skipped));
        }

        if ($errors > 0) {
            $output->writeln(sprintf('<error>✗ Fehler:     %d</error>', $errors));
        }

        $output->writeln('<info>═══════════════════════════════════════</info>');
        $output->writeln('');

        // Fehlermeldungen anzeigen
        if (!empty($errorMessages)) {
            $output->writeln('<error>Fehlerdetails:</error>');
            foreach (array_slice($errorMessages, 0, 10) as $msg) {
                $output->writeln('  <error>• ' . $msg . '</error>');
            }
            if (count($errorMessages) > 10) {
                $output->writeln(sprintf('  <error>... und %d weitere Fehler</error>', count($errorMessages) - 10));
            }
            $output->writeln('');
        }

        // Login-Infos
        if ($created > 0) {
            $output->writeln('<comment>Login-Informationen:</comment>');
            $output->writeln(sprintf('  Benutzername: %s001 bis %s%03d', $prefix, $prefix, $count));
            $output->writeln(sprintf('  Passwort:     %s', $password));
            $output->writeln(sprintf('  E-Mail:       %s001@%s bis %s%03d@%s', $prefix, $domain, $prefix, $count, $domain));
            $output->writeln('');
        }

        return $errors > 0 ? 1 : 0;
    }
}
