# Shared Contract: External Mail Account Settings — Implementierung in `souvera_central`

**Status:** IMPLEMENTIERT in `souvera_central` v0.40.0 (Contract v1.0).
**Producer:** `souvera_central` (Single Source of Truth der Policy).
**Consumer:** `souvera_mail` (read-only), optional `souvera_shield` (read-only).

---

## Bestätigte FQN (für den Consumer-Konstanten `ExternalAccountsConfig::CENTRAL_SERVICE_FQN`)

```
\OCA\SouveraCentral\Service\ExternalAccountsConfigService
```

Abruf (lazy, bleibt ladbar wenn Central fehlt):

```php
try {
    $svc = \OCP\Server::get(\OCA\SouveraCentral\Service\ExternalAccountsConfigService::class);
    $enabled = $svc->isEnabled();
    $allowed = $svc->isAllowedForUser($uid);
    $max     = $svc->getMaxAccountsPerUser();
} catch (\Throwable $e) {
    // Central nicht installiert/auflösbar -> Feature als DEAKTIVIERT behandeln
}
```

Die READ-Methoden sind seiteneffektfrei und günstig (nur `app_config`-Lesezugriffe
+ `IGroupManager::isInGroup` im Gruppen-Gate) — sicher auf jedem Request.

---

## Öffentliche API (implementiert 1:1 nach Contract)

READ: `isEnabled()`, `getAllowedGroups()`, `getMaxAccountsPerUser()`,
`isMigrationHandoffEnabled()`, `isSmtpFailGuardEnabled()`, `isConsentRequired()`,
`isAllowedForUser(string $uid)`, `snapshot()`.

WRITE (nur occ/Admin): `setEnabled()`, `setAllowedGroups()`, `setMaxAccountsPerUser()`,
`setMigrationHandoffEnabled()`, `setSmtpFailGuardEnabled()`, `setConsentRequired()`.

`snapshot()`-Form (keine Secrets):

```json
{
  "enabled": false,
  "allowed_groups": [],
  "max_per_user": 3,
  "migration_handoff": true,
  "smtp_fail_guard": true,
  "consent_required": true,
  "central_version": "0.40.0"
}
```

Semantik `getAllowedGroups()`/`isAllowedForUser()`:
- Leeres Array → jeder Nutzer erlaubt (Default bei enable ohne `--groups`).
- Nicht-leer → Nutzer muss in mindestens einer gelisteten Gruppe sein; nicht mehr
  existierende Gruppen werden ignoriert.

`getMaxAccountsPerUser()`: Default 3, Getter klemmt fehlerhafte Werte < 1 auf 1.

---

## `app_config`-Keys (Eigentum von `souvera_central`, App-ID `souvera_central`)

| Key | Typ | Default |
| --- | --- | --- |
| `external_accounts.enabled` | bool (0/1) | `0` |
| `external_accounts.groups` | JSON-Liste | `[]` |
| `external_accounts.max_per_user` | int | `3` |
| `external_accounts.migration_handoff` | bool (0/1) | `1` |
| `external_accounts.smtp_fail_guard` | bool (0/1) | `1` |
| `external_accounts.consent_required` | bool (0/1) | `1` |
| `external_accounts.purge_requested_at` | ISO-8601 | (leer) |

Defaults werden von den Gettern garantiert — es ist kein `maintenance:repair`
nötig, um die App funktionsfähig zu machen (Consumer sehen sofort die Defaults).

---

## OCC-Befehle

```
occ souvera_central:external:enable [--groups=g1,g2] [--max-per-user=N]
    [--consent-required=y|n] [--smtp-guard=y|n] [--migration-handoff=y|n] [--json]
    # Exit: 0 ok · 2 ungültige Eingabe · 3 Central nicht initialisiert
    # Setzt nur die übergebenen Optionen (idempotent), aktiviert das Feature.

occ souvera_central:external:disable [--purge] [--json]
    # Exit: 0 ok · 3 Central nicht initialisiert
    # --purge setzt einen Lösch-Marker (siehe unten).

occ souvera_central:external:status [--json]
    # Read-only, exit 0 immer (health-probe-sicher). Gibt snapshot() aus.

occ souvera_central:external:configure [--groups=…] [--max-per-user=…]
    [--consent-required=y|n] [--smtp-guard=y|n] [--migration-handoff=y|n]
    [--reset] [--json]
    # Feinjustierung OHNE enable/disable zu ändern. --reset = alle Keys auf Default.
    # Exit: 0 ok · 2 ungültige Eingabe · 3 nicht initialisiert
```

---

## `--purge` (Cross-App-Hinweis, kleine Contract-Erweiterung)

Zur Erinnerung (Non-Goal des Contracts): die pro-Nutzer-Zugangsdaten der externen
Konten liegen in **souvera_mail**, nicht in Central. Central kann sie daher NICHT
selbst löschen. `disable --purge` deaktiviert das Feature UND setzt einen
**einmaligen Marker** `external_accounts.purge_requested_at`.

Zusätzliche Service-Methoden für den Consumer (nicht im ursprünglichen Contract):

```php
$svc->getPurgeRequestedAt(): ?string   // ISO-Zeitpunkt oder null
$svc->acknowledgePurge(): void         // nach dem Löschen aufrufen (Marker leeren)
```

Empfohlener Ablauf in `souvera_mail` beim Boot:
`if ($ts = $svc->getPurgeRequestedAt()) { /* alle externen Konten löschen */ $svc->acknowledgePurge(); }`

Falls souvera_mail den Marker (noch) nicht auswertet, ist `--purge` faktisch wie
ein normales `disable` (Konten bleiben verborgen, aber erhalten). Bitte bei Bedarf
im Consumer verdrahten und Rückmeldung geben.

---

## Test-Hook

`ExternalAccountsConfigService::resetToDefaults()` (bzw. `occ …:external:configure
--reset`) setzt alle Keys auf ihre Defaults, ohne andere Produktionsdaten zu
berühren — für Consumer-Regressionstests.

Unit-Tests: `tests/external_accounts_test.php` (40 Asserts).
