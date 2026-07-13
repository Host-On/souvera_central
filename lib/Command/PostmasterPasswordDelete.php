<?php

declare(strict_types=1);

/**
 * Souvera Central - zentrales Postmaster-App-Passwort löschen.
 *
 *   occ souvera:postmaster-password:delete
 *   occ souvera:postmaster-password:delete --yes
 */

namespace OCA\SouveraCentral\Command;

use OCA\SouveraCentral\Service\PostmasterCredentialService;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PostmasterPasswordDelete extends Base {
    public function __construct(
        private PostmasterCredentialService $service,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:postmaster-password:delete')
            ->setDescription('Entfernt das zentrale Postmaster-App-Passwort.')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Ohne Rückfrage löschen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->service->hasPassword()) {
            $output->writeln('<comment>Es ist kein Postmaster-App-Passwort gesetzt.</comment>');
            return 0;
        }

        if (!$input->getOption('yes') && $input->isInteractive()) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Postmaster-App-Passwort wirklich löschen? Abhängige Apps verlieren den Zugang. [y/N] ', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Abgebrochen.');
                return 0;
            }
        }

        $this->service->clearPassword();
        $output->writeln('<info>✓ Postmaster-App-Passwort entfernt.</info>');
        return 0;
    }
}
