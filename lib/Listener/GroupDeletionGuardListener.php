<?php

declare(strict_types=1);

/**
 * Souvera Central - Schutz der Mail-Gruppe ("Souvera Users")
 *
 * Macht die Mail-Gruppe faktisch zu einer geschützten Systemgruppe: Wird sie
 * (z. B. versehentlich) in der NC-Oberfläche gelöscht, wird sie sofort wieder
 * angelegt und ihre Mitglieder (alle Postfach-Inhaber) automatisch
 * wiederhergestellt. So bleibt die smail-Sichtbarkeitssteuerung intakt.
 */

namespace OCA\SouveraCentral\Listener;

use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\MailGroupService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<GroupDeletedEvent> */
class GroupDeletionGuardListener implements IEventListener {
    public function __construct(
        private ConfigService $config,
        private MailGroupService $mailGroup,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void {
        if (!($event instanceof GroupDeletedEvent)) {
            return;
        }
        if (!$this->config->isMailGroupSyncEnabled()) {
            return;
        }
        if ($event->getGroup()->getGID() !== $this->config->getMailGroupId()) {
            return;
        }

        $this->logger->warning('SouveraCentral: Geschützte Mail-Gruppe wurde gelöscht – wird wiederhergestellt', [
            'gid' => $this->config->getMailGroupId(),
        ]);

        try {
            $this->mailGroup->ensureGroup();
            $restored = $this->mailGroup->repopulate();
            $this->logger->info('SouveraCentral: Mail-Gruppe wiederhergestellt', [
                'gid' => $this->config->getMailGroupId(),
                'membersRestored' => $restored,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('SouveraCentral: Wiederherstellung der Mail-Gruppe fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
