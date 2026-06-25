<?php

declare(strict_types=1);

/**
 * Souvera Central - Schutz der verwalteten Gruppen
 *
 * Macht zwei Gruppen faktisch zu geschützten Systemgruppen:
 *   - die Mail-/Lizenz-Gruppe ("Souvera Users", Standard-GID souvera-users)
 *   - die Souvera-Administrator-Gruppe (Standard-GID scadmin), die vom
 *     CloudManager bei der Installation angelegt wird.
 *
 * Wird eine davon (z. B. versehentlich) in der NC-Oberfläche gelöscht, wird sie
 * sofort wieder angelegt. Bei der Mail-Gruppe werden zusätzlich alle
 * Postfach-Inhaber als Mitglieder wiederhergestellt.
 */

namespace OCA\SouveraCentral\Listener;

use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\MailGroupService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<GroupDeletedEvent> */
class GroupDeletionGuardListener implements IEventListener {
    public function __construct(
        private ConfigService $config,
        private MailGroupService $mailGroup,
        private IGroupManager $groupManager,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void {
        if (!($event instanceof GroupDeletedEvent)) {
            return;
        }
        $gid = $event->getGroup()->getGID();

        // Souvera-Administrator-Gruppe schützen (leer neu anlegen).
        if ($gid === $this->config->getScadminGroupId()) {
            $this->restoreScadminGroup($gid);
            return;
        }

        // Mail-/Lizenz-Gruppe schützen (neu anlegen + Mitglieder wiederherstellen).
        if ($gid === $this->config->getMailGroupId()) {
            if (!$this->config->isMailGroupSyncEnabled()) {
                return;
            }
            $this->restoreMailGroup($gid);
        }
    }

    private function restoreMailGroup(string $gid): void {
        $this->logger->warning('SouveraCentral: Geschützte Mail-Gruppe wurde gelöscht – wird wiederhergestellt', [
            'gid' => $gid,
        ]);
        try {
            $this->mailGroup->ensureGroup();
            $restored = $this->mailGroup->repopulate();
            $this->logger->info('SouveraCentral: Mail-Gruppe wiederhergestellt', [
                'gid' => $gid,
                'membersRestored' => $restored,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('SouveraCentral: Wiederherstellung der Mail-Gruppe fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function restoreScadminGroup(string $gid): void {
        $this->logger->warning('SouveraCentral: Geschützte scadmin-Gruppe wurde gelöscht – wird wiederhergestellt', [
            'gid' => $gid,
        ]);
        try {
            if ($this->groupManager->get($gid) === null) {
                $group = $this->groupManager->createGroup($gid);
                if ($group !== null) {
                    try {
                        $group->setDisplayName($this->config->getScadminGroupName());
                    } catch (\Throwable $e) {
                        // Backend unterstützt setDisplayName ggf. nicht – ignorieren.
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('SouveraCentral: Wiederherstellung der scadmin-Gruppe fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
