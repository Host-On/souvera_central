<?php

declare(strict_types=1);

/**
 * Souvera Central - Lizenz-Service
 *
 * Eine "Lizenz" entspricht einem lizenzpflichtigen "Souvera User" = Mitglied
 * der Gruppe "souvera-users". NICHT mitgezählt werden:
 *   - Der technische Souvera-Admin-BENUTZER "scadmin" (er ist User UND Admin,
 *     verbraucht aber keine Lizenz). Erkannt über ConfigService::isAdminAccount().
 *   - Ausgeblendete technische Benutzer (z. B. "ncadmin").
 *   - Normale Nextcloud User (sind ohnehin nicht in souvera-users).
 *
 * WICHTIG: Ein regulärer Souvera User, der ZUSÄTZLICH Souvera-Admin-Rechte
 * (Gruppe souvera-admins) erhält, bleibt lizenzpflichtig und wird MITGEZÄHLT –
 * nur der technische scadmin-Benutzer selbst nicht.
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
     * Anzahl der genutzten Lizenzen = lizenzpflichtige Souvera Users
     * (Mitglieder von souvera-users, ohne den scadmin-Benutzer und ohne
     * ausgeblendete User). Promoted Admins (souvera-users + souvera-admins)
     * zählen weiterhin mit.
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
