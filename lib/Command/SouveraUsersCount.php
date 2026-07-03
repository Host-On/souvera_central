<?php

declare(strict_types=1);

/**
 * Souvera Central - Anzahl Souvera Users ausgeben
 *
 * Gibt die Gesamtzahl der Souvera Users aus = Mitglieder der Gruppe
 * "souvera-users", OHNE:
 *   - den technischen Souvera-Admin-Benutzer "scadmin"
 *   - ausgeblendete technische Benutzer (z. B. "ncadmin")
 *   - normale Nextcloud User (die sind ohnehin nicht in souvera-users)
 *
 * WICHTIG: Ein regulärer Souvera User, der zusätzlich Souvera-Admin-Rechte
 * erhält (Gruppe souvera-admins), wird MITGEZÄHLT – nur der scadmin selbst nicht.
 *
 * Beispiele:
 *   occ souvera_central:users:count
 *   occ souvera_central:users:count --json
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\LicenseService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SouveraUsersCount extends Base {
    public function __construct(
        private LicenseService $licenseService,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:users:count')
            ->setDescription('Gibt die Anzahl der Souvera Users aus (ohne scadmin, ohne ncadmin, ohne Nextcloud User).')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ausgabe als JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $count = $this->licenseService->countSouveraUsers();

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode([
                'souvera_users' => $count,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $output->writeln('Souvera Users: <info>' . $count . '</info>');
        return 0;
    }
}
