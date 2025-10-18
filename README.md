# Souvera Central

**Modulare Management-Zentrale für Nextcloud**

Erweiterte Benutzerverwaltung mit Lizenz-Limits, Gruppenverwaltung und Dashboard.

## Features

- ✅ Benutzerverwaltung (CRUD, Manager-Zuweisungen, Quota, Aktivieren/Deaktivieren)
- ✅ Gruppenverwaltung (CRUD, Mitgliederverwaltung)
- ✅ Dashboard mit Statistiken
- ✅ Lizenz-Limits & Warnungen
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
'souvera_central.cloud_uuid' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',  // Für Reseller-API
'souvera_central.max_licenses' => 10,                                    // Max. Benutzer
'souvera_central.allowed_domains' => ['example.com', 'company.de'],     // E-Mail Whitelist (optional)
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
