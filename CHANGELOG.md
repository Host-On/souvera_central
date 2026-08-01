# Changelog

All notable changes to Souvera Central will be documented in this file.

Format: [Semantic Versioning](https://semver.org/) — MAJOR.MINOR.PATCH

## [Unreleased]

## [0.40.43] — 2026-08-01 (Release-Zusammenfassung: 2026-07-11 bis 2026-08-01)

Alle Änderungen der letzten drei Wochen, gebündelt in der aktuellen Version.

### Neue Funktionen
- Automatisches Selbst-Update über GitHub (mehrere Iterationen): Auto-Update-Webhook-Endpunkt, stable/dev-Kanäle, Cron-Self-Update, Zero-Config, `occ souvera_central:devops:channel`, `occ souvera_central:self-update` (nicht `occ app:update` — bewirkt bei Custom-Apps nichts); `occ app:update` löst das Update über einen Pre-Update-Repair-Step aus; stable-Kanal checkt 1×/24h im Wartungsfenster (`maintenance_window_start`, 1h), dev-Kanal alle 5 Minuten.
- Öffentlicher DevOps-Status-Endpunkt `GET /api/status/devops` (PHP-8-Attribute + PublicPage) — liefert den Versionsstand aller drei Apps.
- Changelog-Viewer als Menüpunkt der Central-Haupt-App ("Changelog", `/apps/souvera_central/changelogs`): Tabs für Souvera Mail / Central / Shield; die Daten kommen aus den ÖFFENTLICHEN CloudManager-Endpunkten (`https://cm.host-on.network/api/v1/changelogs/{app}`, kein Auth), vermittelt über den internen Feed `/api/changelogs` mit 10-Minuten-Cache und Stale-Fallback; Base-URL per App-Config änderbar; Zugriff für Souvera-User und Admins (canSeeHelp), Nicht-Admins sehen ausschließlich den Changelog (client- und serverseitige Route-Autorisierung).
- Archiv-Verwaltung (Phase 7): ConfigService-Archive-Methoden, Archive-Bereich im SettingsApiController, ArchiveStatusService.
- Suspicious-Login-Detection: Admin-Einstellungen im Shield-Bereich.
- Vue-Build wird committet (`js/souvera_central-*.js`, nicht mehr gitignored) — Fallback, damit git-clone-Deploys die Vue-UI enthalten.

### Behobene Fehler
- SelfUpdateJob-Registrierung fehlertolerant — ein Job-List-Fehler darf den Boot nie abbrechen (behob das hängende "Lade Souvera Central"); Job war zuvor nie eingeplant (Registrierung für Central/Mail/Shield), Job-App-ID als Argument, PHP_BINARY für occ, ZIP-Magic-Check.
- `SelfUpdateTrait`-Namespace korrigiert (Trait wurde nie gefunden → Befehl/Job lief nicht); HTTP über IClientService mit echten Timeouts, exec-Guard, frühe Fortschrittsausgabe.
- EXDEV-sicheres Update-Install: Datei-Kopie (`copyRecursive`) statt `rename()` über NFS-Mount-Grenzen ("Cannot move extracted app into place").
- SelfUpdate-Härtung: SHA-Check, atomarer Swap, Exit-Code-Auswertung, Locks, Logging, `is_writable`-Guard; veraltete `OC_App`-Aufrufe + falscher OCC-Pfad; `OC_App::getAppVersion` durch IAppManager ersetzt.
- StatusController durch die SouveraAdmin-Middleware zugelassen (zuvor 403).
- Changelog-Viewer-Review-Fixes: `t()`-Render-Fix, ISimpleFolder-Cache (Typ-Fehler deaktivierte Caching), strikte CloudManager-Payload-Validierung (ungültige Antworten überschreiben den Stale-Cache nicht), zentrale `authorizeRoute()` inkl. Browser-Back/Forward, PopState-Fallback für Admins.

### Technik / Infrastruktur
- Routing/Experimentierphase des Selbst-Updates (gh CLI → git → GitHub-API/ZIP) auf ZIP-basierten Download vereinheitlicht; SelfUpdate-Job aus info.xml entfernt (wird programmatisch registriert).
- Locks/`acquireLock`-Rückgabetyp; Job-Registrierung mit ITimeFactory + 5-Minuten-Intervall.
- Vollständige CHANGELOG.md als kanonische Changelog-Quelle.
