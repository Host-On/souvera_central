<?php

declare(strict_types=1);

/**
 * Souvera Central - Lizenz-Service
 *
 * Es zählen NUR die Mitglieder der Gruppe "souvera-users" – aber NICHT der
 * technische scadmin-Benutzer. Konkret nicht mitgezählt:
 *   - Der scadmin-BENUTZER (erkannt über ConfigService::isAdminAccount()).
 *   - Ausgeblendete technische Benutzer (z. B. "ncadmin").
 *   - Normale Nextcloud User (sind ohnehin nicht in souvera-users).
 *
 * Die Zählung basiert AUSSCHLIESSLICH auf der souvera-users-Mitgliedschaft;
 * Souvera-Admin-Rechte (Gruppe souvera-admins) sind dafür irrelevant. Ein
 * souvera-users-Mitglied zählt also unabhängig davon, ob es zusätzlich
 * Souvera-Admin ist – nur der scadmin-Benutzer wird nie mitgezählt.
 *
 * Das Limit ist die in config.php konfigurierte souvera_central.max_licenses.
 */

namespace OCA\SouveraCentral\Service;

use OCP\IGroupManager;

class LicenseService {
    public function __construct(
        private IGroupManager $groupManager,
        private ConfigService $config,
    ) {
    }

    /**
     * Anzahl der genutzten Lizenzen = Mitglieder von souvera-users, OHNE den
     * scadmin-Benutzer und OHNE ausgeblendete User (z. B. ncadmin). Die Zählung
     * hängt allein an der souvera-users-Mitgliedschaft (Admin-Rechte irrelevant).
     */
    public function getUsedLicenses(): int {
        $souvera = $this->groupManager->get($this->config->getMailGroupId());
        if ($souvera === null) {
            return 0;
        }
        $count = 0;
        foreach ($souvera->getUsers() as $user) {
            $uid = $user->getUID();
            if ($this->config->isHiddenUser($uid)) {
                continue; // z. B. ncadmin
            }
            if ($this->config->isAdminAccount($uid, $user->getEMailAddress())) {
                continue; // technischer scadmin-Benutzer (User+Admin, aber lizenzfrei)
            }
            $count++;
        }
        return $count;
    }

    public function getMaxLicenses(): int {
        return $this->config->getMaxLicenses();
    }

    /**
     * Gesamtzahl der Souvera Users = identisch zu den genutzten Lizenzen
     * (Mitglieder von souvera-users, ohne den technischen scadmin-Benutzer und
     * ohne ausgeblendete User; promoted Admins zählen mit).
     */
    public function countSouveraUsers(): int {
        return $this->getUsedLicenses();
    }

    public function isLimitReached(): bool {
        return $this->getUsedLicenses() >= $this->getMaxLicenses();
    }

    /**
     * Darf ein weiterer lizenzierter Souvera-User aufgenommen werden?
     */
    public function canAddLicensedUser(): bool {
        return $this->getUsedLicenses() < $this->getMaxLicenses();
    }
}
