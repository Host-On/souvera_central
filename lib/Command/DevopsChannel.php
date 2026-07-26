<?php
declare(strict_types=1);
namespace OCA\SouveraCentral\Command;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
class DevopsChannel extends Command {
    public function __construct(private IConfig $c) { parent::__construct(); }
    protected function configure(): void {
        $this->setName('souvera_central:devops:channel')->setDescription('Switch update channel: stable or dev')->addArgument('channel', InputArgument::REQUIRED, 'stable or dev');
    }
    protected function execute(InputInterface $i, OutputInterface $o): int {
        $ch = strtolower(trim((string)$i->getArgument('channel')));
        if (!in_array($ch, ['stable','dev'])) { $o->writeln('<error>Channel must be stable or dev</error>'); return Command::FAILURE; }
        $this->c->setAppValue('souvera_central','devops.channel',$ch);
        $o->writeln("<info>Channel set to '$ch' (check every 15 min)</info>");
        return Command::SUCCESS;
    }
}
