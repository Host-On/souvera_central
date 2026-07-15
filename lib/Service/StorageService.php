<?php

declare(strict_types=1);

/**
 * Souvera Central - Mail-Speicher-Pool ("Mail Storage")
 *
 * Der Hoster kauft einen GESAMT-Mail-Speicher (z. B. 100 GB) und setzt ihn per
 * occ (souvera_central:mailstorage:set). Dieser Pool wird in der Central-UI
 * manuell auf Souvera-User-Postfächer UND geteilte Postfächer verteilt – in
 * ganzen GB-Schritten. Die Summe der verteilten Limits darf den Pool nie
 * überschreiten. Ein Downgrade des Pools ist nur bis zur bereits verteilten
 * Gesamtmenge erlaubt.
 *
 * Wichtig (bewusste Design-Entscheidungen des Nutzers):
 *   - Pool = 0  => kein Pool gesetzt: alles wie bisher, "Unbegrenzt" erlaubt
 *                  (abwärtskompatibel).
 *   - Pool > 0  => "Unbegrenzt" pro Postfach ist NICHT mehr erlaubt; jedes
 *                  Postfach braucht ein konkretes Limit in ganzen GB-Schritten.
 *
 * Die reine Rechen-/Validierungslogik liegt in statischen, abhängigkeitsfreien
 * Methoden (unit-testbar); die Instanzmethoden orchestrieren die JMAP-Abfragen.
 */

namespace OCA\SouveraCentral\Service;

class StorageService {
    /** 1 GiB in Bytes – die Verteilungs-Schrittweite. */
    public const GIB = 1073741824;

    public function __construct(
        private ConfigService $config,
        private StalwartService $stalwart,
    ) {
    }

    // ========================================================================
    // Reine Logik (statisch, ohne Abhängigkeiten – vollständig unit-testbar)
    // ========================================================================

    /** Verfügbarer (noch nicht verteilter) Pool in Bytes. */
    public static function available(int $max, int $allocated): int {
        return max(0, $max - $allocated);
    }

    /**
     * Prüft, ob ein neues Postfach-Limit zulässig ist.
     * Liefert null bei OK oder einen Fehler-Schlüssel:
     *   'unlimited_not_allowed' | 'not_gb_step' | 'pool_exceeded'.
     *
     * @param int $max          Gesamt-Pool (0 = kein Pool → keine Begrenzung)
     * @param int $allocated    aktuell verteilte Gesamtmenge (inkl. $currentQuota)
     * @param int $currentQuota bisheriges Limit DIESES Postfachs (wird herausgerechnet)
     * @param int $newQuota     gewünschtes neues Limit
     */
    public static function checkAllocation(int $max, int $allocated, int $currentQuota, int $newQuota): ?string {
        if ($max <= 0) {
            return null; // kein Pool → keine Begrenzung (abwärtskompatibel)
        }
        if ($newQuota <= 0) {
            return 'unlimited_not_allowed';
        }
        if ($newQuota % self::GIB !== 0) {
            return 'not_gb_step';
        }
        $prospective = $allocated - $currentQuota + $newQuota;
        if ($prospective > $max) {
            return 'pool_exceeded';
        }
        return null;
    }

    /**
     * Prüft, ob der Pool auf $newMax gesetzt werden darf.
     * Liefert null bei OK oder 'below_allocated', wenn kleiner als bereits verteilt.
     * $newMax = 0 (Pool entfernen/unbegrenzt) ist immer erlaubt.
     */
    public static function checkPoolChange(int $newMax, int $allocated): ?string {
        if ($newMax <= 0) {
            return null;
        }
        if ($newMax < $allocated) {
            return 'below_allocated';
        }
        return null;
    }

    // ========================================================================
    // Orchestrierung (JMAP)
    // ========================================================================

    public function getMaxStorage(): int {
        return $this->config->getMaxMailStorage();
    }

    public function isPoolEnabled(): bool {
        return $this->getMaxStorage() > 0;
    }

    /**
     * Ermittelt in EINEM Durchlauf (User- + geteilte Postfächer):
     *   - allocated: Summe der maxDiskQuota aller Postfächer mit konkretem Limit
     *   - unlimited: Anzahl der Postfächer OHNE Limit (0 = unbegrenzt)
     *
     * Wichtig: Postfächer ohne Limit belegen real unbegrenzt Speicher, tragen aber
     * 0 zur verteilten Menge bei. Bei aktivem Pool sind sie daher „nicht erfassbar"
     * und werden separat als Warnung ausgewiesen (unlimited_count).
     *
     * @return array{allocated:int, unlimited:int}
     */
    public function getDistribution(): array {
        $allocated = 0;
        $unlimited = 0;
        foreach ($this->stalwart->listMailboxUsage() as $u) {
            $q = (int) ($u['quota'] ?? 0);
            if ($q > 0) {
                $allocated += $q;
            } else {
                $unlimited++;
            }
        }
        foreach ($this->stalwart->listSharedMailboxUsage() as $u) {
            $q = (int) ($u['quota'] ?? 0);
            if ($q > 0) {
                $allocated += $q;
            } else {
                $unlimited++;
            }
        }
        return ['allocated' => $allocated, 'unlimited' => $unlimited];
    }

    /**
     * Aktuell verteilte Gesamtmenge = Summe der maxDiskQuota aller
     * User-Postfächer + aller geteilten Postfächer (Group-Konten).
     * Postfächer ohne Limit (0 = unbegrenzt) zählen mit 0.
     */
    public function getAllocatedStorage(): int {
        return $this->getDistribution()['allocated'];
    }

    public function getAvailableStorage(): int {
        return self::available($this->getMaxStorage(), $this->getAllocatedStorage());
    }

    /**
     * Zusammenfassung für die UI.
     *
     * @return array{max:int, allocated:int, available:int, pool_enabled:bool, step_bytes:int, default_quota:int, unlimited_count:int}
     */
    public function getSummary(): array {
        $max = $this->getMaxStorage();
        $dist = $this->getDistribution();
        $allocated = $dist['allocated'];
        return [
            'max' => $max,
            'allocated' => $allocated,
            'available' => self::available($max, $allocated),
            'pool_enabled' => $max > 0,
            'step_bytes' => self::GIB,
            // Vorschlags-/Default-Limit für ein neues Postfach: bei aktivem Pool der
            // kleine Pool-Default, sonst der generelle Postfach-Standard.
            'default_quota' => $max > 0
                ? $this->config->getPoolDefaultMailboxQuota()
                : $this->config->getDefaultMailboxQuota(),
            // Anzahl unbegrenzter Postfächer – bei aktivem Pool eine Warnung wert.
            'unlimited_count' => $dist['unlimited'],
        ];
    }

    /**
     * Ermittelt das effektive Postfach-Limit für eine NEUE Anlage und validiert es
     * gegen den Pool. Wird von ALLEN Anlage-/Provisionierungswegen genutzt
     * (UI-Anlage, createMailbox, sync, occ), damit der Pool nirgends umgangen wird.
     *
     * @param int|null $requested Gewünschtes Limit in Bytes; null = kein explizites
     *                            Limit (Default anwenden).
     * @return array{quota:int, error:?string} quota = anzuwendendes Limit (Bytes,
     *         0 = unbegrenzt); error = deutsche Fehlermeldung bei Pool-Verstoß, sonst null.
     */
    public function resolveNewMailboxQuota(?int $requested): array {
        $max = $this->getMaxStorage();
        $quota = self::resolveQuotaValue(
            $max,
            $requested,
            $this->config->getPoolDefaultMailboxQuota(),
            $this->config->getDefaultMailboxQuota()
        );
        // Bei aktivem Pool hart gegen die Verteilung prüfen (liefert dt. Meldung).
        $error = $max > 0 ? $this->assertAllocation(0, $quota) : null;
        return ['quota' => $quota, 'error' => $error];
    }

    /**
     * Reine Logik: ermittelt das anzuwendende Limit (Bytes) für eine Neu-Anlage.
     *   - Kein Pool (max<=0): gewünschtes Limit, sonst genereller Standard.
     *   - Pool aktiv:         gewünschtes Limit, sonst (kleiner) Pool-Default.
     * Nie negativ.
     */
    public static function resolveQuotaValue(int $max, ?int $requested, int $poolDefault, int $generalDefault): int {
        if ($max <= 0) {
            return max(0, $requested ?? $generalDefault);
        }
        return max(0, $requested ?? $poolDefault);
    }

    /**
     * Validiert ein neues Postfach-Limit gegen den Pool und liefert bei
     * Verstoß eine fertige deutsche Fehlermeldung, sonst null.
     */
    public function assertAllocation(int $currentQuota, int $newQuota): ?string {
        $max = $this->getMaxStorage();
        $allocated = $this->getAllocatedStorage();
        $err = self::checkAllocation($max, $allocated, $currentQuota, $newQuota);
        if ($err === null) {
            return null;
        }
        $available = self::available($max, $allocated - max(0, $currentQuota));
        switch ($err) {
            case 'unlimited_not_allowed':
                return 'Bei aktivem Mail-Speicher-Pool ist „Unbegrenzt" nicht erlaubt. '
                    . 'Bitte ein konkretes Limit in ganzen GB angeben.';
            case 'not_gb_step':
                return 'Das Speicherlimit muss in ganzen GB-Schritten (mind. 1 GB) angegeben werden.';
            case 'pool_exceeded':
            default:
                return sprintf(
                    'Nicht genügend Mail-Speicher im Pool. Für dieses Postfach verfügbar: %s, angefordert: %s.',
                    QuotaParser::format($available),
                    QuotaParser::format($newQuota)
                );
        }
    }

    /**
     * Validiert eine Pool-Änderung (occ). Liefert bei Verstoß eine deutsche
     * Fehlermeldung, sonst null.
     */
    public function assertPoolChange(int $newMax): ?string {
        $allocated = $this->getAllocatedStorage();
        if (self::checkPoolChange($newMax, $allocated) === null) {
            return null;
        }
        return sprintf(
            'Der Mail-Speicher-Pool kann nicht auf %s gesenkt werden: bereits %s an Postfächer verteilt. '
                . 'Minimum ist die verteilte Gesamtmenge.',
            QuotaParser::format($newMax),
            QuotaParser::format($allocated)
        );
    }
}
