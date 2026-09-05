<?php

declare(strict_types=1);

/**
 * Souvera Central - zentralen provider.tools-Token löschen.
 *
 *   occ souvera:provider-token:delete
 *   occ souvera:provider-token:delete --yes
 */

namespace OCA\SouveraCentral\Command;

use OCA\SouveraCentral\Service\ProviderTokenService;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ProviderTokenDelete extends Base {
    public function __construct(
        private ProviderTokenService $service,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:provider-token:delete')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:provider-token:delete'])
            ->setDescription('Entfernt den zentralen provider.tools-Token.')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Ohne Rückfrage löschen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->service->hasToken()) {
            $output->writeln('<comment>Es ist kein provider.tools-Token gesetzt.</comment>');
            return 0;
        }

        if (!$input->getOption('yes') && $input->isInteractive()) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('provider.tools-Token wirklich löschen? Shield/Mail verlieren den Zugang. [y/N] ', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Abgebrochen.');
                return 0;
            }
        }

        $this->service->clearToken();
        $output->writeln('<info>✓ provider.tools-Token entfernt.</info>');
        return 0;
    }
}
