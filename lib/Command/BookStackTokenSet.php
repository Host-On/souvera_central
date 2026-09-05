<?php

declare(strict_types=1);

/**
 * Souvera Central - BookStack API-Token setzen (verschlüsselt).
 *
 * Legt den BookStack-Token ("<TOKEN_ID>:<TOKEN_SECRET>") zentral + verschlüsselt in
 * Central ab. Central-Hilfe sowie andere Souvera-Apps (Shield, Mail) beziehen ihn
 * von hier. Die BookStack-URL wird NICHT benötigt (fester Default).
 *
 * Beispiele:
 *   occ souvera:bookstack-token:set --stdin <<< "$TOKEN"     (empfohlen, keine Shell-History)
 *   occ souvera:bookstack-token:set "ID:SECRET"              (Argument)
 *   occ souvera:bookstack-token:set                          (interaktive, verdeckte Eingabe)
 */

namespace OCA\SouveraCentral\Command;

use OCA\SouveraCentral\Service\BookStackTokenService;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class BookStackTokenSet extends Base {
    public function __construct(
        private BookStackTokenService $service,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera_central:bookstack-token:set')
            // Legacy-Alias (Namespace vor der Vereinheitlichung)
            ->setAliases(['souvera:bookstack-token:set'])
            ->setDescription('Speichert den BookStack API-Token zentral + verschlüsselt.')
            ->addArgument('token', InputArgument::OPTIONAL, 'Der BookStack-Token "<ID>:<SECRET>" (alternativ --stdin oder interaktiv).')
            ->addOption('stdin', null, InputOption::VALUE_NONE, 'Token von STDIN lesen (empfohlen).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $token = (string) ($input->getArgument('token') ?? '');

        if ($input->getOption('stdin')) {
            $token = trim((string) stream_get_contents(STDIN));
        } elseif ($token === '' && $input->isInteractive()) {
            $helper = $this->getHelper('question');
            $question = new Question('BookStack API-Token (<ID>:<SECRET>): ');
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

        $output->writeln('<info>✓ BookStack-Token verschlüsselt gespeichert (' . $this->service->getMaskedToken() . ').</info>');
        $output->writeln('<comment>Die Hilfe-Seite sowie Shield/Mail beziehen den Token nun zentral von Souvera Central.</comment>');
        return 0;
    }
}
