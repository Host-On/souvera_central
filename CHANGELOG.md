# Changelog

All notable changes to Souvera Central will be documented in this file.

Format: [Semantic Versioning](https://semver.org/) — MAJOR.MINOR.PATCH

## [Unreleased]

## [0.40.40] — 2026-08-01 (Changelog in der Central-Navigation)

Der Changelog-Viewer ist jetzt ein vollwertiger Menüpunkt der Central-Haupt-App
(„Changelog"): neue Ansicht unter `/apps/souvera_central/changelogs` mit
Tabs für Souvera Mail / Central / Shield. Daten weiterhin aus den öffentlichen
CloudManager-Endpunkten (`cm.host-on.network/api/v1/changelogs/{app}`, kein
Auth), vermittelt über `/api/changelogs` mit 10-Min-Cache. Zugriff für
Souvera-User und Admins (canSeeHelp).

## [0.40.38] — 2026-08-01 (Changelog-Viewer-Fixes)

Render-Fix: Übersetzungsfunktion `t` im ViewModel exponiert (Reparatur des
`_ctx.t`-Render-Fehlers); strikte Payload-Validierung — ungültige
CloudManager-Antworten überschreiben den Stale-Cache nicht mehr.

## [0.40.37] — 2026-08-01 (Changelog-Viewer)

Neue Seite `/apps/souvera_central/changelogs`: zeigt die Changelogs von
Souvera Mail / Central / Shield. Die Daten kommen aus den ÖFFENTLICHEN
CloudManager-Endpunkten (`https://cm.host-on.network/api/v1/changelogs/{app}`,
kein Auth) — vermittelt über einen internen JSON-Feed (`/api/changelogs`,
10-Minuten-Cache, Stale-Fallback). Base-URL per App-Config änderbar
(`occ config:app:set souvera_central changelog_base_url --value …`).
Zugriff wie die Hilfe-Seite (Souvera-User + Admins).

## [0.40.36] — 2026-08-01 (Changelog-Cache-Fix)

Der Changelog-Cache nutzt die korrekte `ISimpleFolder`-Schnittstelle —
zuvor deaktivierte ein Typ-Fehler das Caching und jede öffentliche
Request traf GitHub direkt.

## [0.40.35] — 2026-08-01 (Öffentlicher Changelog-Viewer)

Neue öffentliche API `GET /api/v1/changelogs/{appId}` (ohne Auth) für
Souvera Mail / Central / Shield: parst die jeweilige `CHANGELOG.md` von
GitHub (main-Branch), 10-Minuten-Cache im AppData, Stale-Cache-Fallback
bei Netzwerkfehlern, 404 für unbekannte Apps. Antwort: `{app_id,
app_label, entries: [{version, date, title, body}]}`.

## [0.40.34] — 2026-08-01 (Wartungsfenster-Länge 1h)

Das stable-Kanal-Wartungsfenster ist exakt 1 Stunde lang
(`maintenance_window_start` bis +1h, Mitternachts-Wrap-sicher) — bewusste
Abweichung von Nextcloud-Core (+4h), per Operator-Vorgabe.

## [0.40.33] — 2026-08-01 (Stable-Kanal: 24h im Wartungsfenster)

Stable-Release-Updates laufen höchstens einmal pro 24h und nur innerhalb
des Nextcloud-Wartungsfensters (`maintenance_window_start` aus config.php);
der Dev-Kanal bleibt unverändert bei 5 Minuten (Branch-HEAD).

## [0.40.31] — 2026-08-01 (EXDEV-sicheres Update-Install)

Das Selbst-Update installierte neue Versionen per `rename()` vom Temp-
Verzeichnis (/tmp) auf das NFS-Ziel (`custom_apps`) — `rename()` über
Dateisystem-Grenzen schlägt mit EXDEV fehl ("Cannot move extracted app
into place"). Jetzt: rekursive Datei-Kopie (`copyRecursive`) mit Rollback
und `opendir`-Guard — funktioniert über Mount-Grenzen hinweg.

## [0.40.30] — 2026-08-01 (Vue-Build committed)

Webpack-Output (`js/souvera_central-*.js`) wird committet und nicht mehr
ignoriert — Fallback, damit git-clone-Deploys die Vue-UI enthalten.

## [0.40.29] — 2026-08-01 (SelfUpdate-Kette gehärtet)

IClientService-HTTP mit connect_timeout, exec-Guard, frühe
Fortschrittsausgabe.

## [0.40.28] — 2026-08-01 (expliziter Self-Update-Befehl)

`occ souvera_central:self-update` aktualisiert alle verwalteten Apps
direkt von GitHub.

## [0.40.27] — 2026-08-01 (Update über occ app:update)

`occ app:update souvera_central` löst über einen Pre-Update-Repair-Step
das GitHub-Self-Update für alle verwalteten Apps aus.

## [0.40.26] — 2026-08-01 (Boot-Crash-Schutz)

`SelfUpdateJob`-Registrierung ist fehlertolerant — ein Job-List-Fehler
darf den Boot nie abbrechen (behob das hängende "Lade Souvera Central").

## [0.40.21] — 2026-07-29 (Suspicious-Login-Admin-Settings)

Admin-Einstellungen für die Verdächtige-Anmelde-Erkennung im
Shield-Bereich.

## [0.40.20] — 2026-07-28 (SelfUpdate-Härtung)

SHA-Check, atomarer Swap, Exit-Code-Auswertung, Locks, Logging,
`is_writable`-Guard.

## [0.40.19] — 2026-07-28 (Deprecations entfernt)

Veraltete `OC_App`-Aufrufe + falscher OCC-Pfad im SelfUpdate ersetzt.

## [0.40.18] — 2026-07-28 (IAppManager)

`OC_App::getAppVersion` durch `IAppManager` ersetzt.

## [0.40.17] — 2026-07-28 (StatusController-Fix)

Korrektur nach dem öffentlichen Devops-Endpunkt.

## [0.40.16] — 2026-07-28 (Öffentlicher Devops-Endpunkt)

`GET /api/status/devops` öffentlich (PHP-8-Attribute + PublicPage) —
liefert Versionsstand aller drei Apps.

## [0.40.15] — 2026-07-27 (Middleware-Freigabe)

StatusController über die SouveraAdmin-Middleware zugelassen.

## [0.40.14] — 2026-07-26 (Self-Update + Status)

Self-Update-Mechanik und Status-Endpunkt erstmals kombiniert.

## [0.40.13] — 2026-07-26 (Job-Registrierung bereinigt)

`SelfUpdateJob` aus info.xml entfernt (wird programmatisch registriert).

## [0.40.12] — 2026-07-26 (Routing stabilisiert)

Ursprüngliche `routes.php` wiederhergestellt.

## [0.40.11] — 2026-07-26 (Syntax-Fix)

Bracket-Mismatch behoben.

## [0.40.10] — 2026-07-26 (Syntax-Fix)

Syntaxfehler in `routes.php` behoben.

## [0.40.9] — 2026-07-26 (ZIP-Update)

Update über die GitHub-API als ZIP-Download statt git.

## [0.40.8] — 2026-07-26 (Versionscheck via git)

Git-basierter Versionsvergleich.

## [0.40.7] — 2026-07-26 (gh-CLI für private Repos)

`gh`-CLI für Private-Repo-Zugriff auf GitHub.

## [0.40.6] — 2026-07-26 (Devops-Status-Endpunkt)

`GET /api/status/devops` erstmals eingeführt.

## [0.40.5] — 2026-07-26 (Kanal-Befehl)

`occ souvera_central:devops:channel` — Umschalten zwischen stable/dev.

## [0.40.4] — 2026-07-26 (Zero-Config-Self-Update)

Self-Update ohne manuelle Einrichtung.

## [0.40.3] — 2026-07-26 (Cron-Self-Update)

Self-Update über den Background-Job (Cron).

## [0.40.2] — 2026-07-26 (Update-Kanäle)

Stable- und Dev-Update-Kanäle eingeführt.

## [0.40.1] — 2026-07-26 (Auto-Update-Webhook)

Webhook-Endpunkt `devops/update` für automatische Updates.
