# Souvera Central

**Modulare Management-Zentrale für Nextcloud**

Erweiterte Benutzerverwaltung mit Lizenz-Limits, Gruppenverwaltung und Dashboard.

## Features

- ✅ Benutzerverwaltung (CRUD, Manager-Zuweisungen, Quota, Aktivieren/Deaktivieren)
- ✅ Gruppenverwaltung (CRUD, Mitgliederverwaltung)
- ✅ Geteilte Postfächer (Stalwart Mail Server Integration)
- ✅ E-Mail Aliase pro Benutzer
- ✅ Dashboard mit Statistiken
- ✅ Konfigurierbare Limits mit Warnungen (Lizenzen, Gruppen, Postfächer, Aliase)
- ✅ E-Mail Domain-Whitelist
- ✅ Dynamische Reseller-Kontakt-Links

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

// Stalwart Mail Server Integration
'souvera_central.stalwart_api_url' => 'http://stalwart:8080',
'souvera_central.stalwart_admin_user' => 'admin',
'souvera_central.stalwart_admin_password' => 'your-password',
```

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
