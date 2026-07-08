<?php

declare(strict_types=1);

/**
 * Souvera Central - BookStack Token-Status anzeigen.
 *
 *   occ souvera:bookstack-token:show            (maskiert)
 *   occ souvera:bookstack-token:show --reveal   (Klartext, z. B. zum Debuggen)
 *   occ souvera:bookstack-token:show --output=json
 */

namespace OCA\SouveraCentral\Command;

use OCA\SouveraCentral\Service\BookStackTokenService;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BookStackTokenShow extends Base {
    public function __construct(
        private BookStackTokenService $service,
    ) {
        parent::__construct();
    }

    protected function configure() {
        parent::configure();
        $this
            ->setName('souvera:bookstack-token:show')
            ->setDescription('Zeigt den Status des zentralen BookStack-Tokens.')
            ->addOption('reveal', null, InputOption::VALUE_NONE, 'Token im Klartext ausgeben.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $configured = $this->service->hasToken();
        $reveal = (bool) $input->getOption('reveal');

        $data = [
            'provider' => BookStackTokenService::PROVIDER,
            'configured' => $configured,
            'set_at' => $this->service->getSetAt(),
            'token' => $configured
                ? ($reveal ? $this->service->getToken() : $this->service->getMaskedToken())
                : null,
        ];

        if ($input->getOption('output') === 'json') {
            $output->writeln((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $output->writeln('<info>Provider:</info>     ' . $data['provider']);
        $output->writeln('<info>Konfiguriert:</info> ' . ($configured ? 'ja' : 'nein'));
        if ($configured) {
            $output->writeln('<info>Gesetzt am:</info>   ' . ($data['set_at'] ?? 'unbekannt'));
            $output->writeln('<info>Token:</info>        ' . ($data['token'] ?? '(nicht lesbar)'));
        }
        return 0;
    }
}
