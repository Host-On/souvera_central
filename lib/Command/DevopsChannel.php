<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Command;

use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DevopsChannel extends Command
{
    public function __construct(private IConfig $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('souvera_central:devops:channel')
            ->setDescription('Switch the ONE suite-wide update channel: stable (latest release, daily in maintenance window) or dev (main HEAD, every 5 min)')
            ->addArgument('channel', InputArgument::REQUIRED, 'stable or dev');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $channel = strtolower(trim((string) $input->getArgument('channel')));
        if (!in_array($channel, ['stable', 'dev'], true)) {
            $output->writeln('<error>Channel must be "stable" or "dev"</error>');
            return Command::FAILURE;
        }
        // Zentraler Suite-Channel: gilt für ALLE Souvera-Apps
        \OCP\Server::get(\OCA\SouveraCentral\Service\ConfigService::class)->setSuiteUpdateChannel($channel);
        $interval = $channel === 'dev' ? '5 min (branch HEAD of main)' : 'daily (within Nextcloud maintenance window)';
        $output->writeln("<info>Suite update channel set to '$channel' for ALL Souvera apps (check $interval)</info>");
        return Command::SUCCESS;
    }
}
