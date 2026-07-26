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
            ->setDescription('Switch update channel: stable or dev')
            ->addArgument('channel', InputArgument::REQUIRED, 'stable or dev');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $channel = strtolower(trim((string) $input->getArgument('channel')));
        if (!in_array($channel, ['stable', 'dev'], true)) {
            $output->writeln('<error>Channel must be "stable" or "dev"</error>');
            return Command::FAILURE;
        }
        $this->config->setAppValue('souvera_central', 'devops.channel', $channel);
        $output->writeln("<info>Channel set to '$channel'</info>");
        return Command::SUCCESS;
    }
}
