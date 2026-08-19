# Changelog

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
