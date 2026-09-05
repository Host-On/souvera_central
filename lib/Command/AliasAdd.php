<?php

declare(strict_types=1);

/**
 * Souvera Central - E-Mail-Alias hinzufügen
 *
 * Beispiel:
 *   occ souvera:alias:add falk@example.com vertrieb@example.com
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AliasAdd extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:alias:add')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:alias:add'])
            ->setDescription('Fügt einem Postfach einen E-Mail-Alias hinzu.')
            ->addArgument('email', InputArgument::REQUIRED, 'Haupt-Mailadresse des Postfachs')
            ->addArgument('alias', InputArgument::REQUIRED, 'Neuer Alias (z. B. vertrieb@example.com)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $email = strtolower(trim((string) $input->getArgument('email')));
        $alias = strtolower(trim((string) $input->getArgument('alias')));

        if (!filter_var($alias, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Ungültiger Alias: ' . $alias . '</error>');
            return 1;
        }
        if (!$this->config->isEmailDomainAllowed($alias)) {
            $output->writeln('<error>Alias-Domain nicht erlaubt: ' . $alias . '</error>');
            return 1;
        }
        if (!$this->stalwart->principalExists($email)) {
            $output->writeln('<error>Kein Postfach für ' . $email . ' vorhanden. Alias nicht möglich.</error>');
            return 1;
        }

        $ok = $this->stalwart->addAlias($email, $alias);
        if (!$ok) {
            $output->writeln('<error>Alias konnte nicht hinzugefügt werden.</error>');
            $err = $this->stalwart->getLastError();
            if ($err !== null) {
                $output->writeln('<comment>Stalwart-Detail: ' . json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</comment>');
            }
            return 1;
        }

        $output->writeln('<info>✓ Alias hinzugefügt: ' . $alias . ' → ' . $email . '</info>');
        return 0;
    }
}
