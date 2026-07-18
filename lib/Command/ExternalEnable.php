<?php

declare(strict_types=1);

/**
 * Souvera Central - occ souvera_central:external:enable
 * Aktiviert die Funktion „Externe Mail-Konten" (Policy für souvera_mail).
 *
 * Exit-Codes: 0 angewendet · 2 ungültige Eingabe · 3 Central nicht initialisiert.
 */

namespace OCA\SouveraCentral\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExternalEnable extends AbstractExternalCommand {
    protected function configure(): void {
        $this
            ->setName('souvera_central:external:enable')
            ->setDescription('Aktiviert „Externe Mail-Konten" (souvera_mail liest die Policy aus Central)')
            ->addOption('groups', null, InputOption::VALUE_REQUIRED, 'Nur diese NC-Gruppen (kommagetrennt, z. B. power-users,mail-beta)')
            ->addOption('max-per-user', null, InputOption::VALUE_REQUIRED, 'Max. Konten pro Nutzer (1..20, Default 3)')
            ->addOption('consent-required', null, InputOption::VALUE_REQUIRED, 'DSGVO-Dialog bei jedem Hinzufügen (y|n, Default y)')
            ->addOption('smtp-guard', null, InputOption::VALUE_REQUIRED, 'Auto-Deaktivierung nach 3x SMTP-Fehler (y|n, Default y)')
            ->addOption('migration-handoff', null, InputOption::VALUE_REQUIRED, '„Als externes Konto behalten?" im Wizard (y|n, Default y)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Ergebnis als JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $err = $this->applyPolicyOptions($input, $output);
            if ($err !== null) {
                return $err;
            }
            $this->svc->setEnabled(true);
            $this->emit($input, $output, 'Externe Mail-Konten AKTIVIERT.');
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>Central nicht vollständig initialisiert: ' . $e->getMessage() . '</error>');
            return 3;
        }
    }
}
