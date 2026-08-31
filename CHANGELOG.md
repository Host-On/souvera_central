# Changelog

## [0.40.70] — 2026-08

### Added

- Multi-Domain: Mail-Domains als first-class — Verwaltung in den
  Central-Einstellungen (Liste mit Stalwart-Status und Postfach-/Alias-
  Belegung, Anlegen/Entfernen mit Benutzungs-Guard), Admin-API
  (`/api/domains`), Contract für den CloudManager
  (`docs/MULTI_DOMAIN.md`). Neue Benutzer können über das bestehende
  Domain-Dropdown mit jeder erlaubten Domain angelegt werden.

## [0.40.69] — 2026-08

### Fixed

- MCP-Endpoint: interner Fehler (undefined method beim Request-Body) bezogen;
  Request-Body wird jetzt über `php://input` gelesen. Zusätzlich fängt der
  Endpoint jede interne Exception ab und antwortet mit sauberem
  JSON-RPC-Fehler (-32603) statt HTTP 500; Details landen im Server-Log
  (app: souvera_central).
- `ai:status` / Status-UI tolerant, wenn die KB-Tabelle noch nicht existiert
  (zählt als 0 statt Absturz).

## [0.40.68] — 2026-08

### Fixed

- AI-Seite ist jetzt direkt verlinkbar (F5/Deep-Link auf
  `/apps/souvera_central/ai` lieferte „Seite nicht gefunden") — die
  Server-Route `page#ai` fehlte.

## [0.40.67] — 2026-08

### Added

- Interne Wissensbasis für den Nextcloud-Agenten: KB-Artikel (Titel +
  Markdown) werden in der Central-UI verwaltet (anlegen, bearbeiten,
  löschen) und in der DB gespeichert. Startinhalt wird einmalig aus
  `resources/ai/*.md` importiert.
- MCP-Endpoint `/apps/souvera_central/mcp` (stateless HTTP/JSON-RPC,
  read-only): `kb_list`, `kb_get`, `kb_search` — der Agent liest die
  Wissensbasis live, kein Datei-Sync.
- MCP-Zugriffs-Token wird bei Aktivierung der KI automatisch erzeugt und
  verschlüsselt in der DB abgelegt; der Agent erhält ihn intern über die
  Shared API (`AiMcpTokenService::getToken()`). Rotation über UI/occ.

## [0.40.66] — 2026-08

### Changed

- AI toggle vereinfacht: `ai:book` entfernt, nur noch `ai:enable` /
  `ai:disable` / `ai:status`. Die Buchungsprüfung liegt beim Hoster.

### Added

- „Souvera AI"-Sektion in der Central-Verwaltung: Status-Schalter und
  Ansicht der Wissensbasis (`resources/ai`), admin-only.

## [0.40.65] — 2026-08

### Added

- Souvera AI feature toggle: new occ commands `souvera_central:ai:book`,
  `souvera_central:ai:enable`, `souvera_central:ai:disable` and
  `souvera_central:ai:status`. Enabling requires the instance to have booked
  Souvera AI (`souvera_central.ai_booked`).

- Souvera knowledge base under `resources/ai/` (identity, products,
  features, terminology, support) as the factual RAG source for Souvera AI.

## [0.40.64] — 2026-08

### Fixed

- Dashboard: the user/group count helpers are now actually defined (the
  v0.40.62 edit introduced calls without the method definitions, which
  made /api/users, /api/config and /api/groups fail). Group and shared
  mailbox counters now load independently of the user request, and the
  group counter uses the same proven endpoint as the group module.

## [0.40.63] — 2026-08

### Fixed

- Shield settings: "Daily report time" and the PMG report switch are now
  actually persisted (the PUT handler was missing both fields) and are
  translated in the German JS translations (l10n/de.js, l10n/de_DE.js).

## [0.40.62] — 2026-08

### Fixed

- Dashboard-Zähler (Benutzer/Gruppen) robust gemacht: Backend listet alle
  Benutzer/Gruppen mit Fallback-Kette für Backends, die bei leerem
  Suchbegriff nichts liefern (z. B. LDAP). Frontend lädt die Zähler beim
  Dashboard-Aufruf neu und loggt Fehler statt sie still zu verschlucken.

## [0.40.61] — 2026-08

### Added

- Souvera Shield settings: daily report time (default 06:00) and the
  "disable PMG built-in spam report" switch — Souvera sends its own daily
  spam report.

## [0.40.60] — 2026-08

Repository moved to the Host-On organization. This release repoints the
self-update sources and neutralizes internal references for the open-source
publish.

## [0.40.59] — 2026-08

### Fixed

- Self-update swap uses copy+delete instead of rename() (NFS-safe, avoids
  open-file-handle errors).
