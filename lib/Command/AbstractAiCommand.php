<?php

declare(strict_types=1);

/**
 * Souvera Central - gemeinsame Basis der AI-occ-Befehle.
 * Bündelt die Service-Injektion und die einheitliche Ausgabe (Tabelle/JSON).
 */

namespace OCA\SouveraCentral\Command;

use OC\Core\Command\Base;
use OCA\SouveraCentral\Service\AiConfigService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractAiCommand extends Base
{
    public function __construct(
        protected AiConfigService $svc,
    ) {
        parent::__construct();
    }

    /** Snapshot ausgeben: --json (falls vorhanden und gesetzt) oder Tabelle. */
    protected function emit(InputInterface $input, OutputInterface $output, ?string $humanMsg = null): void
    {
        $snap = $this->svc->snapshot();
        if ($input->hasOption('json') && $input->getOption('json')) {
            $output->writeln((string) json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }
        if ($humanMsg !== null) {
            $output->writeln('<info>' . $humanMsg . '</info>');
        }
        $yn = fn (bool $b) => $b ? 'ja' : 'nein';
        $output->writeln('Souvera AI');
        $output->writeln('----------');
        $output->writeln(sprintf('%-20s %s', 'Aktiviert:', $yn($snap['enabled'])));
        $output->writeln(sprintf('%-20s %d', 'KB-Artikel:', $snap['kb_count']));
        $output->writeln(sprintf('%-20s %s', 'MCP-Token:', $yn($snap['mcp']['token_set'])
            . ($snap['mcp']['created_at'] !== null ? ' (seit ' . $snap['mcp']['created_at'] . ')' : '')));
        $output->writeln(sprintf('%-20s %s', 'Central-Version:', $snap['central_version'] !== '' ? $snap['central_version'] : '(unbekannt)'));
    }
}
