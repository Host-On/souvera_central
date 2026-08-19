<?php

declare(strict_types=1);

/**
 * Souvera Central - Postmaster-App-Passwort-Status anzeigen.
 *
 *   occ souvera:postmaster-password:show            (maskiert)
 *   occ souvera:postmaster-password:show --reveal   (Klartext, z. B. zum Debuggen)
 *   occ souvera:postmaster-password:show --output=json
 */

namespace OCA\SouveraCentral\Command;

use OCA\SouveraCentral\Service\PostmasterCredentialService;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PostmasterPasswordShow extends Base {
    public function __construct(
        private PostmasterCredentialService $service,
    ) {
        parent::__construct();
    }

    protected function configure() {
        parent::configure();
        $this
            ->setName('souvera:postmaster-password:show')
            ->setDescription('Zeigt den Status des zentralen Postmaster-App-Passworts.')
            ->addOption('reveal', null, InputOption::VALUE_NONE, 'Passwort im Klartext ausgeben.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $configured = $this->service->hasPassword();
        $reveal = (bool) $input->getOption('reveal');

        $data = [
            'credential' => PostmasterCredentialService::CREDENTIAL,
            'configured' => $configured,
            'set_at' => $this->service->getSetAt(),
            'password' => $configured
                ? ($reveal ? $this->service->getPassword() : $this->service->getMaskedPassword())
                : null,
        ];

        if ($input->getOption('output') === 'json') {
            $output->writeln((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $output->writeln('<info>Credential:</info>   ' . $data['credential'] . ' (postmaster@)');
        $output->writeln('<info>Konfiguriert:</info> ' . ($configured ? 'ja' : 'nein'));
        if ($configured) {
            $output->writeln('<info>Gesetzt am:</info>  ' . ($data['set_at'] ?? 'unbekannt'));
            $output->writeln('<info>Passwort:</info>    ' . ($data['password'] ?? '(nicht lesbar)'));
        }
        return 0;
    }
}
