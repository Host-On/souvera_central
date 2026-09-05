<?php

declare(strict_types=1);

/**
 * Souvera Central - provider.tools API-Token setzen (verschlüsselt).
 *
 * Legt den gemeinsamen provider.tools-Token zentral + verschlüsselt in Central
 * ab. Andere Souvera-Apps (Shield, Mail) beziehen ihn von hier.
 *
 * Beispiele:
 *   occ souvera:provider-token:set --stdin <<< "$TOKEN"      (empfohlen, keine Shell-History)
 *   occ souvera:provider-token:set "meintoken"               (Argument)
 *   occ souvera:provider-token:set                            (interaktive, verdeckte Eingabe)
 */

namespace OCA\SouveraCentral\Command;

use OCA\SouveraCentral\Service\ProviderTokenService;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ProviderTokenSet extends Base {
    public function __construct(
        private ProviderTokenService $service,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:provider-token:set')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:provider-token:set'])
            ->setDescription('Speichert den provider.tools API-Token zentral + verschlüsselt.')
            ->addArgument('token', InputArgument::OPTIONAL, 'Der API-Token (alternativ --stdin oder interaktiv).')
            ->addOption('stdin', null, InputOption::VALUE_NONE, 'Token von STDIN lesen (empfohlen).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $token = (string) ($input->getArgument('token') ?? '');

        if ($input->getOption('stdin')) {
            $token = trim((string) stream_get_contents(STDIN));
        } elseif ($token === '' && $input->isInteractive()) {
            $helper = $this->getHelper('question');
            $question = new Question('provider.tools API-Token: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $token = (string) $helper->ask($input, $output, $question);
        }

        $token = trim($token);
        if ($token === '') {
            $output->writeln('<error>Kein Token angegeben. Nutze --stdin, ein Argument oder die interaktive Eingabe.</error>');
            return 1;
        }

        try {
            $this->service->setToken($token);
        } catch (\Throwable $e) {
            $output->writeln('<error>Token konnte nicht gespeichert werden: ' . $e->getMessage() . '</error>');
            return 1;
        }

        $output->writeln('<info>✓ provider.tools-Token verschlüsselt gespeichert (' . $this->service->getMaskedToken() . ').</info>');
        $output->writeln('<comment>Shield und Mail beziehen den Token nun zentral von Souvera Central.</comment>');
        return 0;
    }
}
