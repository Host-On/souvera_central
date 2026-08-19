<?php

declare(strict_types=1);

/**
 * Souvera Central - Mail-Gruppen Service
 *
 * Pflegt die Mitgliedschaft einer dedizierten Nextcloud-Gruppe (Standard-GID:
 * "souvera-users", Anzeigename "Souvera Users"). Jeder Benutzer mit einem
 * Stalwart-Postfach wird automatisch Mitglied dieser Gruppe. Die smail-App wird
 * in den Nextcloud-App-Einstellungen auf diese Gruppe beschränkt - so sehen
 * Benutzer ohne Postfach die App nicht (nativer NC-Mechanismus, keine Hacks).
 */

namespace OCA\SouveraCentral\Service;

use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class MailGroupService {
    public function __construct(
        private IGroupManager $groupManager,
        private ConfigService $config,
        private IUserManager $userManager,
        private StalwartService $stalwart,
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
        if ($group !== null) {
            $this->applyDisplayName($group);
        }
        return $group;
    }

    /**
     * Setzt den Anzeigenamen der Gruppe (best-effort; Backend muss es unterstützen).
     */
    private function applyDisplayName(IGroup $group): void {
        $name = $this->config->getMailGroupDisplayName();
        try {
            if ($group->getDisplayName() !== $name) {
                $group->setDisplayName($name);
            }
        } catch (\Throwable $e) {
            $this->logger->debug('SouveraCentral: Anzeigename konnte nicht gesetzt werden', [
                'error' => $e->getMessage(),
            ]);
        }
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
     * Stellt die Mitgliedschaft wieder her: fügt alle NC-Benutzer mit
     * Stalwart-Postfach (erneut) zur Mail-Gruppe hinzu. Liefert die Anzahl der
     * neu hinzugefügten Mitglieder. Wird u. a. nach versehentlichem Löschen der
     * geschützten Gruppe genutzt.
     */
    public function repopulate(): int {
        if (!$this->isEnabled() || !$this->config->isStalwartConfigured()) {
            return 0;
        }
        $group = $this->ensureGroup();
        if ($group === null) {
            return 0;
        }

        try {
            $mailboxes = array_flip(array_map('strtolower', $this->stalwart->listPrincipalNames()));
            if (empty($mailboxes)) {
                return 0;
            }

            $added = 0;
            $limit = 500;
            $offset = 0;
            do {
                $users = $this->userManager->search('', $limit, $offset);
                foreach ($users as $user) {
                    $mail = $this->stalwart->mailFor($user);
                    if ($mail !== null && isset($mailboxes[strtolower($mail)]) && !$group->inGroup($user)) {
                        $group->addUser($user);
                        $added++;
                    }
                }
                $offset += $limit;
            } while (count($users) === $limit);

            return $added;
        } catch (\Throwable $e) {
            $this->logger->error('SouveraCentral: Repopulation der Mail-Gruppe fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Prüft, ob ein Benutzer ein "Souvera User" ist (Mitglied der souvera-users-Gruppe).
     */
    public function isMember(IUser $user): bool {
        $group = $this->groupManager->get($this->getGroupId());
        return $group !== null && $group->inGroup($user);
    }

    /**
     * Macht einen Benutzer zum lizenzierten "Souvera User": Mitgliedschaft in
     * der souvera-users-Gruppe + Stalwart-Postfach. Idempotent. Die
     * Gruppen-Mitgliedschaft wird unabhängig von mail_group_sync gesetzt, da sie
     * die Lizenz markiert.
     *
     * @param IUser $user
     * @param string|null $password Klartext-Passwort fürs Postfach (optional);
     *                              ohne Angabe wird ein Zufallspasswort verwendet
     *                              (der Benutzer muss dann sein Passwort neu setzen,
     *                              damit es nach Stalwart gespiegelt wird).
     * @param int|null $quota Disk-Quota in Bytes fürs neue Postfach (0 = unbegrenzt,
     *                        null = globaler Config-Standard). Der Aufrufer sollte den
     *                        Wert vorher via StorageService::resolveNewMailboxQuota()
     *                        gegen den Mail-Speicher-Pool validieren.
     */
    public function makeSouveraUser(IUser $user, ?string $password = null, ?int $quota = null): bool {
        $group = $this->ensureGroup();
        if ($group !== null && !$group->inGroup($user)) {
            $group->addUser($user);
            $this->logger->info('SouveraCentral: Benutzer als Souvera User markiert', [
                'uid' => $user->getUID(),
                'gid' => $group->getGID(),
            ]);
        }
        if ($this->config->isStalwartConfigured()) {
            try {
                $mail = $this->stalwart->mailFor($user);
                if ($mail !== null && !$this->stalwart->principalExists($mail)) {
                    $this->stalwart->createPrincipal(
                        $mail,
                        $password ?? bin2hex(random_bytes(24)),
                        $user->getDisplayName(),
                        $quota
                    );
                }
            } catch (\Throwable $e) {
                $this->logger->error('SouveraCentral: Postfach-Anlage für Souvera User fehlgeschlagen', [
                    'uid' => $user->getUID(),
                    'error' => $e->getMessage(),
                ]);
                return false;
            }
        }
        return true;
    }

    /**
     * Entfernt den "Souvera User"-Status: Benutzer verlässt die souvera-users-Gruppe
     * (gibt die Lizenz frei). Das Stalwart-Postfach bleibt erhalten (nicht destruktiv).
     */
    public function makeNextcloudUser(IUser $user): void {
        $group = $this->groupManager->get($this->getGroupId());
        if ($group !== null && $group->inGroup($user)) {
            $group->removeUser($user);
            $this->logger->info('SouveraCentral: Souvera-User-Status entfernt (Nextcloud User)', [
                'uid' => $user->getUID(),
                'gid' => $group->getGID(),
            ]);
        }
    }

    /**
     * Info-Objekt für UI (Gruppe, Mitgliederzahl, Status).
     *
     * @return array{id: string, displayName: string, exists: bool, members: int, enabled: bool}
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
            'displayName' => $this->config->getMailGroupDisplayName(),
            'exists' => $group !== null,
            'members' => $members,
            'enabled' => $this->isEnabled(),
        ];
    }
}
