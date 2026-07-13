<?php

declare(strict_types=1);

/**
 * Souvera Central - Postmaster-App-Passwort setzen (verschlüsselt).
 *
 * Legt das beim Deploy erzeugte App-Passwort des "postmaster@"-Postfachs zentral
 * + verschlüsselt in Central ab. Andere Souvera-Apps beziehen es von hier.
 *
 * Beispiele:
 *   occ souvera:postmaster-password:set --stdin <<< "$PASS"   (empfohlen, keine Shell-History)
 *   occ souvera:postmaster-password:set "meinPasswort"        (Argument)
 *   occ souvera:postmaster-password:set                       (interaktive, verdeckte Eingabe)
 */

namespace OCA\SouveraCentral\Command;

use OCA\SouveraCentral\Service\PostmasterCredentialService;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class PostmasterPasswordSet extends Base {
    public function __construct(
        private PostmasterCredentialService $service,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->setName('souvera:postmaster-password:set')
            ->setDescription('Speichert das Postmaster-App-Passwort zentral + verschlüsselt.')
            ->addArgument('password', InputArgument::OPTIONAL, 'Das App-Passwort (alternativ --stdin oder interaktiv).')
            ->addOption('stdin', null, InputOption::VALUE_NONE, 'Passwort von STDIN lesen (empfohlen).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $password = (string) ($input->getArgument('password') ?? '');

        if ($input->getOption('stdin')) {
            $password = trim((string) stream_get_contents(STDIN));
        } elseif ($password === '' && $input->isInteractive()) {
            $helper = $this->getHelper('question');
            $question = new Question('Postmaster-App-Passwort: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = (string) $helper->ask($input, $output, $question);
        }

        $password = trim($password);
        if ($password === '') {
            $output->writeln('<error>Kein Passwort angegeben. Nutze --stdin, ein Argument oder die interaktive Eingabe.</error>');
            return 1;
        }

        try {
            $this->service->setPassword($password);
        } catch (\Throwable $e) {
            $output->writeln('<error>Passwort konnte nicht gespeichert werden: ' . $e->getMessage() . '</error>');
            return 1;
        }

        $output->writeln('<info>✓ Postmaster-App-Passwort verschlüsselt gespeichert (' . $this->service->getMaskedPassword() . ').</info>');
        $output->writeln('<comment>Andere Souvera-Apps beziehen das Passwort nun zentral von Souvera Central.</comment>');
        return 0;
    }
}
