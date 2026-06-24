<?php

declare(strict_types=1);

/**
 * Souvera Central - Mail-Gruppen Service
 *
 * Pflegt die Mitgliedschaft einer dedizierten Nextcloud-Gruppe (Standard:
 * "mail-users"). Jeder Benutzer mit einem Stalwart-Postfach wird automatisch
 * Mitglied dieser Gruppe. Die smail-App wird in den Nextcloud-App-Einstellungen
 * auf diese Gruppe beschränkt - so sehen Benutzer ohne Postfach die App nicht
 * (nativer NC-Mechanismus, keine Hacks).
 */

namespace OCA\SouveraCentral\Service;

use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class MailGroupService {
    public function __construct(
        private IGroupManager $groupManager,
        private ConfigService $config,
        private LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool {
        return $this->config->isMailGroupSyncEnabled();
    }

    public function getGroupId(): string {
        return $this->config->getMailGroupId();
    }

    /**
     * Stellt sicher, dass die Mail-Gruppe existiert (legt sie bei Bedarf an).
     */
    public function ensureGroup(): ?IGroup {
        $gid = $this->getGroupId();
        $group = $this->groupManager->get($gid);
        if ($group === null) {
            $group = $this->groupManager->createGroup($gid);
            if ($group !== null) {
                $this->logger->info('SouveraCentral: Mail-Gruppe angelegt', ['gid' => $gid]);
            }
        }
        return $group;
    }

    /**
     * Fügt einen Benutzer der Mail-Gruppe hinzu (idempotent).
     */
    public function addUser(IUser $user): bool {
        if (!$this->isEnabled()) {
            return false;
        }
        try {
            $group = $this->ensureGroup();
            if ($group === null) {
                return false;
            }
            if (!$group->inGroup($user)) {
                $group->addUser($user);
                $this->logger->info('SouveraCentral: Benutzer zur Mail-Gruppe hinzugefügt', [
                    'uid' => $user->getUID(),
                    'gid' => $group->getGID(),
                ]);
            }
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('SouveraCentral: Mail-Gruppe addUser fehlgeschlagen', [
                'uid' => $user->getUID(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Entfernt einen Benutzer aus der Mail-Gruppe (idempotent).
     */
    public function removeUser(IUser $user): bool {
        if (!$this->isEnabled()) {
            return false;
        }
        try {
            $group = $this->groupManager->get($this->getGroupId());
            if ($group === null) {
                return false;
            }
            if ($group->inGroup($user)) {
                $group->removeUser($user);
                $this->logger->info('SouveraCentral: Benutzer aus Mail-Gruppe entfernt', [
                    'uid' => $user->getUID(),
                    'gid' => $group->getGID(),
                ]);
            }
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('SouveraCentral: Mail-Gruppe removeUser fehlgeschlagen', [
                'uid' => $user->getUID(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Info-Objekt für UI (Gruppe, Mitgliederzahl, Status).
     *
     * @return array{id: string, exists: bool, members: int, enabled: bool}
     */
    public function getInfo(): array {
        $gid = $this->getGroupId();
        $group = $this->groupManager->get($gid);
        $members = 0;
        if ($group !== null) {
            $count = $group->count('');
            $members = is_int($count) ? $count : count($group->getUsers());
        }
        return [
            'id' => $gid,
            'exists' => $group !== null,
            'members' => $members,
            'enabled' => $this->isEnabled(),
        ];
    }
}
