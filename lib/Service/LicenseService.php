<?php

declare(strict_types=1);

/**
 * Souvera Central - Lizenz-Service
 *
 * Eine "Lizenz" entspricht einem lizenzierten "Souvera User" = Mitglied der
 * Gruppe "souvera-users". NICHT mitgezählt werden:
 *   - Souvera-Administratoren (Mitglieder der scadmin-Gruppe) – sie erhalten
 *     zwar ein Postfach, verbrauchen aber keine Lizenz.
 *   - Ausgeblendete technische Benutzer (z. B. "ncadmin").
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
     * Anzahl der genutzten Lizenzen = lizenzierte Souvera-User
     * (Mitglieder von souvera-users, ohne scadmin und ohne ausgeblendete User).
     */
    public function getUsedLicenses(): int {
        $souvera = $this->groupManager->get($this->config->getMailGroupId());
        if ($souvera === null) {
            return 0;
        }
        $scadminGid = $this->config->getScadminGroupId();
        $count = 0;
        foreach ($souvera->getUsers() as $user) {
            $uid = $user->getUID();
            if ($this->config->isHiddenUser($uid)) {
                continue;
            }
            if ($this->groupManager->isInGroup($uid, $scadminGid)) {
                continue; // Souvera-Administratoren zählen nicht
            }
            $count++;
        }
        return $count;
    }

    public function getMaxLicenses(): int {
        return $this->config->getMaxLicenses();
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
