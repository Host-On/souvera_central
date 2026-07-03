<?php

declare(strict_types=1);

/**
 * Souvera Central - Domain in Stalwart anlegen
 *
 * Legt eine Domain in Stalwart an – Voraussetzung, damit Postfächer und
 * Aliase auf dieser Domain erstellt werden können. Idempotent.
 *
 * Beispiele:
 *   occ souvera:domain:add gratify.it
 *   occ souvera:domain:add example.com --allow   (auch in die Central-Erlaubnisliste eintragen)
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DomainAdd extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:domain:add')
            ->setDescription('Legt eine Domain in Stalwart an (Voraussetzung für Postfächer/Aliase).')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domainname (z. B. gratify.it)')
            ->addOption('allow', null, InputOption::VALUE_NONE, 'Domain zusätzlich in die Central-Erlaubnisliste (allowed_domains) aufnehmen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $domain = strtolower(trim((string) $input->getArgument('domain')));
        if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            $output->writeln('<error>Ungültiger Domainname: ' . $domain . '</error>');
            return 1;
        }

        $existed = $this->stalwart->resolveDomainId($domain) !== null;
        $ok = $this->stalwart->createDomain($domain);
        if (!$ok) {
            $output->writeln('<error>Domain konnte nicht angelegt werden: ' . $domain . '</error>');
            $err = $this->stalwart->getLastError();
            if ($err !== null) {
                $output->writeln('<comment>Stalwart-Detail: ' . json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</comment>');
            }
            return 1;
        }

        $output->writeln('<info>' . ($existed ? '✓ Domain existierte bereits: ' : '✓ Domain angelegt: ') . $domain . '</info>');

        if ($input->getOption('allow')) {
            $this->config->addAllowedDomain($domain);
            $output->writeln('<info>✓ In Central-Erlaubnisliste aufgenommen.</info>');
        } elseif (!$this->config->isEmailDomainAllowed('x@' . $domain)) {
            $output->writeln('<comment>Hinweis: ' . $domain . ' ist nicht in der Central-Erlaubnisliste. Mit --allow hinzufügen.</comment>');
        }

        return 0;
    }
}
