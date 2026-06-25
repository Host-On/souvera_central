<?php

declare(strict_types=1);

/**
 * Souvera Central - Berechtigungs-Service
 *
 * Zentralisiert die Frage "Darf dieser Benutzer Souvera Central verwalten?".
 * Verwaltungsberechtigt ist, wer entweder echter Nextcloud-Superadmin ist
 * ODER Mitglied der Souvera-Administrator-Gruppe (Standard-GID "scadmin").
 *
 * Damit lassen sich Benutzer innerhalb von Central verwalten, ohne globale
 * Nextcloud-Superadmin-Rechte zu besitzen (delegierte Administration).
 */

namespace OCA\SouveraCentral\Service;

use OCP\IGroupManager;
use OCP\IUserSession;

class PermissionService {
    public function __construct(
        private IGroupManager $groupManager,
        private IUserSession $userSession,
        private ConfigService $config,
    ) {
    }

    /**
     * UID des aktuell angemeldeten Benutzers (oder null).
     */
    public function getCurrentUserId(): ?string {
        $user = $this->userSession->getUser();
        return $user?->getUID();
    }

    /**
     * Prüft, ob der (aktuelle oder angegebene) Benutzer Souvera Central
     * verwalten darf: NC-Superadmin ODER Mitglied der scadmin-Gruppe.
     *
     * @param string|null $userId UID; null = aktuell angemeldeter Benutzer
     */
    public function isSouveraAdmin(?string $userId = null): bool {
        $userId ??= $this->getCurrentUserId();
        if ($userId === null || $userId === '') {
            return false;
        }
        if ($this->groupManager->isAdmin($userId)) {
            return true;
        }
        // isInGroup() liefert false, falls die Gruppe (noch) nicht existiert.
        return $this->groupManager->isInGroup($userId, $this->config->getScadminGroupId());
    }

    /**
     * Prüft, ob der Benutzer ein echter Nextcloud-Superadmin ist.
     */
    public function isNextcloudAdmin(?string $userId = null): bool {
        $userId ??= $this->getCurrentUserId();
        if ($userId === null || $userId === '') {
            return false;
        }
        return $this->groupManager->isAdmin($userId);
    }

    /**
     * Prüft, ob ein Benutzer Mitglied der scadmin-Gruppe ist.
     */
    public function isScadmin(string $userId): bool {
        return $this->groupManager->isInGroup($userId, $this->config->getScadminGroupId());
    }
}
