<?php

declare(strict_types=1);

/**
 * Souvera Central - gemeinsame Basis der externen-Konten-occ-Befehle.
 * Bündelt Option-Parsing, Policy-Anwendung und Ausgabe (Tabelle/JSON).
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\ExternalAccountsConfigService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractExternalCommand extends Base {
    public function __construct(
        protected ExternalAccountsConfigService $svc,
    ) {
        parent::__construct();
    }

    /** Kommagetrennte Gruppenliste -> Array (leer = alle Nutzer). */
    protected function parseGroups(string $csv): array {
        $out = [];
        foreach (explode(',', $csv) as $g) {
            $g = trim($g);
            if ($g !== '') {
                $out[] = $g;
            }
        }
        return $out;
    }

    /**
     * Validiert + wendet die gemeinsamen Policy-Optionen an (nur die tatsächlich
     * übergebenen). Liefert null bei Erfolg oder einen Exit-Code (2) bei
     * ungültiger Eingabe (Fehlermeldung wird ausgegeben).
     */
    protected function applyPolicyOptions(InputInterface $input, OutputInterface $output): ?int {
        // max-per-user
        $maxRaw = $input->getOption('max-per-user');
        $max = null;
        if ($maxRaw !== null && trim((string) $maxRaw) !== '') {
            $m = trim((string) $maxRaw);
            if (!ctype_digit($m)) {
                $output->writeln('<error>Ungültiger Wert für --max-per-user: "' . $m . '" (ganze Zahl erwartet).</error>');
                return 2;
            }
            $max = (int) $m;
            if ($max < ExternalAccountsConfigService::MIN_MAX || $max > ExternalAccountsConfigService::MAX_MAX) {
                $output->writeln(sprintf(
                    '<error>--max-per-user muss zwischen %d und %d liegen.</error>',
                    ExternalAccountsConfigService::MIN_MAX,
                    ExternalAccountsConfigService::MAX_MAX
                ));
                return 2;
            }
        }

        // y/n-Flags
        try {
            $consent = ExternalAccountsConfigService::parseYesNo($input->getOption('consent-required'));
            $guard = ExternalAccountsConfigService::parseYesNo($input->getOption('smtp-guard'));
            $handoff = ExternalAccountsConfigService::parseYesNo($input->getOption('migration-handoff'));
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . ' (erlaubt: y|n)</error>');
            return 2;
        }

        // groups (auch leerer String zulässig -> alle Nutzer)
        $groupsRaw = $input->getOption('groups');
        if ($groupsRaw !== null) {
            $this->svc->setAllowedGroups($this->parseGroups((string) $groupsRaw));
        }
        if ($max !== null) {
            $this->svc->setMaxAccountsPerUser($max);
        }
        if ($consent !== null) {
            $this->svc->setConsentRequired($consent);
        }
        if ($guard !== null) {
            $this->svc->setSmtpFailGuardEnabled($guard);
        }
        if ($handoff !== null) {
            $this->svc->setMigrationHandoffEnabled($handoff);
        }
        return null;
    }

    /** Snapshot ausgeben: --json (falls Option vorhanden+gesetzt) oder Tabelle. */
    protected function emit(InputInterface $input, OutputInterface $output, ?string $humanMsg = null): void {
        $snap = $this->svc->snapshot();
        if ($input->hasOption('json') && $input->getOption('json')) {
            $output->writeln((string) json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }
        if ($humanMsg !== null) {
            $output->writeln('<info>' . $humanMsg . '</info>');
        }
        $yn = fn (bool $b) => $b ? 'ja' : 'nein';
        $groups = $snap['allowed_groups'];
        $output->writeln('Externe Mail-Konten');
        $output->writeln('-------------------');
        $output->writeln(sprintf('%-24s %s', 'Aktiviert:', $yn($snap['enabled'])));
        $output->writeln(sprintf('%-24s %s', 'Erlaubte Gruppen:', $groups === [] ? '(alle Nutzer)' : implode(', ', $groups)));
        $output->writeln(sprintf('%-24s %d', 'Max. pro Nutzer:', $snap['max_per_user']));
        $output->writeln(sprintf('%-24s %s', 'Migration-Handoff:', $yn($snap['migration_handoff'])));
        $output->writeln(sprintf('%-24s %s', 'SMTP-Fehler-Schutz:', $yn($snap['smtp_fail_guard'])));
        $output->writeln(sprintf('%-24s %s', 'Consent (DSGVO):', $yn($snap['consent_required'])));
        $output->writeln(sprintf('%-24s %s', 'Central-Version:', $snap['central_version'] !== '' ? $snap['central_version'] : '(unbekannt)'));
        $purge = $this->svc->getPurgeRequestedAt();
        if ($purge !== null) {
            $output->writeln(sprintf('%-24s %s', 'Purge angefordert:', $purge . ' (wird von souvera_mail abgearbeitet)'));
        }
    }
}
