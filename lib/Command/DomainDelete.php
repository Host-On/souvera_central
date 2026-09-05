<?php

declare(strict_types=1);

/**
 * Souvera Central - Domain in Stalwart löschen
 *
 * Entfernt eine Domain aus Stalwart. Zum Schutz erst mit --yes ausführbar.
 * Achtung: Postfächer/Aliase auf dieser Domain werden dadurch unbrauchbar.
 *
 * Beispiele:
 *   occ souvera:domain:delete alt-domain.de
 *   occ souvera:domain:delete alt-domain.de --yes
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DomainDelete extends Base {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:domain:delete')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:domain:delete'])
            ->setDescription('Löscht eine Domain aus Stalwart.')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domainname')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Löschen ohne Rückfrage bestätigen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->config->isStalwartConfigured() || !$this->stalwart->isAvailable()) {
            $output->writeln('<error>Stalwart nicht konfiguriert oder nicht erreichbar. Abbruch.</error>');
            return 1;
        }

        $domain = strtolower(trim((string) $input->getArgument('domain')));
        if ($this->stalwart->resolveDomainId($domain) === null) {
            $output->writeln('<comment>Domain ' . $domain . ' existiert nicht in Stalwart – nichts zu löschen.</comment>');
            return 0;
        }

        if (!$input->getOption('yes')) {
            $output->writeln('<comment>Die Domain ' . $domain . ' würde aus Stalwart entfernt.</comment>');
            $output->writeln('<comment>Postfächer/Aliase auf dieser Domain werden dadurch unbrauchbar.</comment>');
            $output->writeln('Zum Ausführen erneut mit <info>--yes</info> aufrufen.');
            return 0;
        }

        $ok = $this->stalwart->deleteDomain($domain);
        if (!$ok) {
            $output->writeln('<error>Domain konnte nicht gelöscht werden: ' . $domain . '</error>');
            $err = $this->stalwart->getLastError();
            if ($err !== null) {
                $output->writeln('<comment>Stalwart-Detail: ' . json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</comment>');
            }
            return 1;
        }

        $output->writeln('<info>✓ Domain gelöscht: ' . $domain . '</info>');
        return 0;
    }
}
