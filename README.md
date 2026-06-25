# Souvera Central

**Modulare Management-Zentrale für Nextcloud**

Erweiterte Benutzerverwaltung mit Lizenz-Limits, Gruppenverwaltung und Dashboard.

## Features

- ✅ Benutzerverwaltung (CRUD, Manager-Zuweisungen, Quota, Aktivieren/Deaktivieren)
- ✅ Gruppenverwaltung (CRUD, Mitgliederverwaltung)
- ✅ Geteilte Postfächer (Stalwart **0.16** Integration via **JMAP**)
- ✅ Automatische Postfach-Provisionierung (User anlegen/Passwort-Sync/Löschen via Event-Listener)
- ✅ E-Mail Aliase pro Benutzer + Postfach-Quota (Speicherlimit)
- ✅ smail-Sichtbarkeit über dedizierte Mail-Gruppe (nur Benutzer mit Postfach sehen die Mail-App)
- ✅ Dashboard mit Statistiken
- ✅ Konfigurierbare Limits mit Warnungen (Lizenzen, Gruppen, Postfächer, Aliase)
- ✅ E-Mail Domain-Whitelist
- ✅ Dynamische Reseller-Kontakt-Links

## Anforderungen

- **Nextcloud 30–34** (nativer v34-Look, PHP-8-Attribute)
- **Stalwart Mail Server 0.16+** — Kommunikation ausschließlich über die **JMAP-Management-API**
  (`urn:stalwart:jmap`). Die alte REST-API (`/api/principal …`) wurde in 0.16 entfernt und wird
  **nicht** mehr unterstützt.

## Installation

```bash
# Dependencies installieren
npm install

# Production Build
npm run build

# In Nextcloud aktivieren
sudo -u www-data php occ app:enable souvera_central
```

## Konfiguration

Füge in `config/config.php` hinzu:

```php
// Reseller-Integration
'souvera_central.cloud_uuid' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',

// Limits
'souvera_central.max_licenses' => 10,           // Max. Benutzer (Default: 10)
'souvera_central.max_groups' => 20,             // Max. Gruppen (Default: 20)
'souvera_central.max_shared_mailboxes' => 10,   // Max. geteilte Postfächer (Default: 10)
'souvera_central.max_aliases_per_user' => 10,   // Max. Aliase pro Benutzer (Default: 10)
'souvera_central.warning_threshold' => 0.8,     // Warnung ab 80% (Default: 0.8)

// E-Mail Domain-Whitelist (optional, leer = alle erlaubt)
'souvera_central.allowed_domains' => ['example.com', 'company.de'],

// Stalwart Mail Server 0.16 (JMAP)
// WICHTIG: Server-Basis-URL angeben (OHNE /api oder /jmap) – die App ermittelt
// daraus selbst /jmap/session und /jmap.
'souvera_central.stalwart_api_url' => 'https://mail.example.com',
'souvera_central.stalwart_admin_user' => 'admin',
'souvera_central.stalwart_admin_password' => 'your-password',

// smail-Sichtbarkeit: dedizierte Mail-Gruppe (optional)
'souvera_central.mail_group' => 'souvera-users',        // GID, Default: souvera-users
'souvera_central.mail_group_name' => 'Souvera Users',   // Anzeigename, Default: Souvera Users
'souvera_central.mail_group_sync' => true,              // Benutzer mit Postfach automatisch zuordnen

// Delegierte Verwaltung (Souvera-Administrator ohne NC-Superadmin-Rechte)
'souvera_central.scadmin_group' => 'scadmin',           // GID der Souvera-Admin-Gruppe (vom CloudManager angelegt)
'souvera_central.scadmin_group_name' => 'Souvera Administrators',
'souvera_central.hidden_users' => ['ncadmin'],          // in Central ausgeblendete technische Benutzer
```

> **Souvera User vs. Nextcloud User**
> Ein **Souvera User** ist Mitglied der Gruppe `souvera-users`: lizenziert (zählt auf `max_licenses`)
> und erhält ein Stalwart-Postfach. Ein **Nextcloud User** ist *nicht* in dieser Gruppe: unlizenziert,
> ohne Postfach. Der Typ wird beim Anlegen/Bearbeiten eines Benutzers umgeschaltet.
>
> **Souvera-Administrator (`scadmin`)**
> Mitglieder der Gruppe `scadmin` dürfen Souvera Central vollständig bedienen (Benutzer, Gruppen,
> geteilte Postfächer, Einstellungen) – **ohne** echte Nextcloud-Superadmin-Rechte. Sie erhalten
> ebenfalls ein Postfach, **verbrauchen aber keine Lizenz**. Die Gruppe wird vom CloudManager bei der
> Installation angelegt; Central verwendet und schützt sie (Wiederherstellung bei versehentlicher Löschung).
> Das App-Icon erscheint für NC-Superadmins **und** `scadmin`-Mitglieder.

> Beschränke anschließend die **smail**-App in den Nextcloud-App-Einstellungen auf die
> Gruppe **Souvera Users** (`souvera-users`), damit Benutzer ohne Postfach die Mail-App nicht sehen.
> Die Gruppe ist **geschützt**: Wird sie versehentlich gelöscht, legt die App sie automatisch wieder
> an und stellt alle Postfach-Inhaber als Mitglieder wieder her.

## occ-Befehle

```bash
# Einzelnes Postfach gezielt provisionieren (z. B. ncadmin im Build-Prozess) – idempotent
sudo -u www-data php occ souvera:provision-mailbox admin@example.com --password-stdin <<< "$ADMIN_PW"
sudo -u www-data php occ souvera:provision-mailbox info@example.com --generate --quota 5368709120

# Backfill: fehlende Postfächer für alle bestehenden Benutzer anlegen
sudo -u www-data php occ souvera:sync-mailboxes            # mit --dry-run zum Testen
```

`souvera:provision-mailbox` Optionen: `--password` | `--password-stdin` | `--generate`,
`--display-name`, `--quota` (Bytes, 0 = unbegrenzt).

## Entwicklung

```bash
npm run dev         # Development Build mit Watch
npm run build       # Production Build
npm run lint        # Code-Qualität prüfen
npm run lint:fix    # Auto-Fix Lint-Fehler
```

## Reseller-Integration

Die App ruft automatisch die Support-URL des Resellers ab:

**API:** `POST https://manage.souvera.eu/api/public/workspace/reseller`

**Fallback-Logik:** `support_url` → `url` → `souvera.eu`

Ohne konfigurierte `cloud_uuid` wird `souvera.eu` als Fallback genutzt.

## Lizenz

AGPL-3.0
