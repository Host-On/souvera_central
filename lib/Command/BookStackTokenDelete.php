<?php

declare(strict_types=1);

/**
 * Souvera Central - zentralen BookStack-Token löschen.
 *
 *   occ souvera:bookstack-token:delete
 *   occ souvera:bookstack-token:delete --yes
 */

namespace OCA\SouveraCentral\Command;

use OCA\SouveraCentral\Service\BookStackTokenService;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class BookStackTokenDelete extends Base {
    public function __construct(
        private BookStackTokenService $service,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:bookstack-token:delete')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:bookstack-token:delete'])
            ->setDescription('Entfernt den zentralen BookStack-Token.')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Ohne Rückfrage löschen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->service->hasToken()) {
            $output->writeln('<comment>Es ist kein BookStack-Token gesetzt.</comment>');
            return 0;
        }

        if (!$input->getOption('yes') && $input->isInteractive()) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('BookStack-Token wirklich löschen? Die Hilfe-Seite und Shield/Mail verlieren den Zugang. [y/N] ', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Abgebrochen.');
                return 0;
            }
        }

        $this->service->clearToken();
        $output->writeln('<info>✓ BookStack-Token entfernt.</info>');
        return 0;
    }
}
