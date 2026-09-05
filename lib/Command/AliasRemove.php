<?php

declare(strict_types=1);

/**
 * Souvera Central - E-Mail-Alias entfernen
 *
 * Beispiel:
 *   occ souvera:alias:remove falk@example.com vertrieb@example.com
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AliasRemove extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:alias:remove')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:alias:remove'])
            ->setDescription('Entfernt einen E-Mail-Alias von einem Postfach.')
            ->addArgument('email', InputArgument::REQUIRED, 'Haupt-Mailadresse des Postfachs')
            ->addArgument('alias', InputArgument::REQUIRED, 'Zu entfernender Alias');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $email = strtolower(trim((string) $input->getArgument('email')));
        $alias = strtolower(trim((string) $input->getArgument('alias')));

        if (!$this->stalwart->principalExists($email)) {
            $output->writeln('<error>Kein Postfach für ' . $email . ' vorhanden.</error>');
            return 1;
        }

        $ok = $this->stalwart->removeAlias($email, $alias);
        if (!$ok) {
            $output->writeln('<error>Alias konnte nicht entfernt werden: ' . $alias . '</error>');
            $err = $this->stalwart->getLastError();
            if ($err !== null) {
                $output->writeln('<comment>Stalwart-Detail: ' . json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</comment>');
            }
            return 1;
        }

        $output->writeln('<info>✓ Alias entfernt: ' . $alias . '</info>');
        return 0;
    }
}
