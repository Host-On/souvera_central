# Changelog

## [0.41.0] — 2026-09

### Added

- **Central-Integration für Souvera Documents:** neue Admin-Sektion
  „Souvera Documents" (DMS-Einstellungen zentral verwaltbar: aktiv,
  Ein-/Archivordner, Auto-Verarbeitung/-Archivierung; Integrations-Modus;
  Typen-Übersicht) — Konsumiert die Documents-Settings-API.

## [0.40.100] — 2026-09

### Fixed

- **Kritisch:** App-Login (iOS/Android) und öffentliche Seiten brachen mit
  500 — der Listener rief `getTemplateResponse()` auf
  `BeforeTemplateRenderedEvent`, das in NC 34 nur `getResponse()`
  exponiert (verifiziert in der NC-Quelle). Betroffen waren alle
  nicht-Login-Gast-Renders: 401-/Fehler-Seiten (App-Login-Flow!),
  öffentliche Shares und die Passwort-vergessen-Seite (die frühere
  „Interner Serverfehler"-Diagnose war dieser Bug, nicht die
  Mail-Konfiguration).
- Dadurch funktioniert jetzt auch der serverseitige Titel-Patch erstmals
  wirklich (er crashte bisher still in seinem catch) — Titel-FOUC ist
  damit ebenfalls geschlossen.

## [0.40.99] — 2026-09

### Fixed

- Titel-Blitz endgültig geschlossen (zweigleisig): Serverseitig wird der
  `pageTitle` jetzt auch FORCIERT, wenn die App ihn nicht setzt
  (App-ID aus dem Pfad → gebrandeter Name). Clientseitig interceptiert
  branding.js den `document.title`-Setter — Vue-Apps (Talk, Office)
  schreiben keinen ungebrandeten Titel mehr, jede Zuweisung wird sofort
  umbenannt. Kein Observer-Rennen mehr.

## [0.40.98] — 2026-09

### Fixed

- **Titel-FOUC behoben (serverseitig):** der `pageTitle`-Parameter der
  Response wird vor dem Rendern gebrandet (Talk→Link, Office→Desk) — der
  Tab-Titel ist ab dem ersten Byte korrekt, kein „Talk"-Blitz mehr.
  (Das L10n-Theme greift nicht auf info.xml-Namen — NC übersetzt die
  App-Namen nicht über die L10N-Factory; stattdessen wird der
  pageTitle-Parameter gepatcht.)

## [0.40.97] — 2026-09

### Added

- Theme-L10n-Dateien werden jetzt automatisch geschrieben (einmalig,
  gethrottelt, best effort beim ersten Seitenaufruf) — die Aktivierung
  bleibt ein einziger Kern-Befehl:
  `occ config:system:set theme --value souvera`
  (unabhängig vom Command-Loading).

## [0.40.96] — 2026-09

### Fixed

- occ-Befehl korrekt registriert: NC 34 kennt kein
  `IRegistrationContext::registerCommand` (verifiziert) — der Command wird
  jetzt über den klassischen `info.xml`-`<commands>`-Weg geladen. Der
  Bootstrap-Fatal (der auch den Rest der App lahmlegen konnte) ist entfernt.

## [0.40.95] — 2026-09

### Added

- **Kein Branding-FOUC mehr im angemeldeten Bereich:** header.css/-js
  werden statisch im Head ausgeliefert (vor dem First Paint), der
  dynamische `?t=`-Loader bleibt nur als Fallback. Header erscheint erst
  gebrandet — mit 1,2s-Failsafe-Auto-Reveal, falls JS versagt.
- `occ souvera:branding:install-theme`: schreibt das Souvera-L10n-Theme
  (serverseitige Umbenennung Talk→Link, Office→Desk — auch im `<title>`);
  Aktivierung manuell via `--activate` oder
  `occ config:system:set theme --value souvera`.

### Fixed

- UTF-8-sicheres Base64 beim Initial-State-Patch (Umlaut-Falle bei
  atob/btoa).

## [0.40.94] — 2026-09

### Fixed

- Checkbox-Hover von „An mich erinnern" neutralisiert (NC malte einen
  grauen Hover-Block).
- Passwort-vergessen-/2FA-Seiten im Kartendesign: generische Regeln für
  Headline, Felder und Buttons in allen Gastboxen (bisher nur
  login-box-spezifisch — dort war die Headline blass und das Feld
  ungestylt).

## [0.40.93] — 2026-09

### Fixed

- Blassheit endgültig behoben: „An mich erinnern" ist eine Vue-Checkbox
  (Text-Span `#rememberme-label`), „Mit einem Gerät anmelden" ein
  NcButton (tertiary) — Farbe/Gewicht/Opacity jetzt auf jeder Knotenebene
  erzwungen statt über Link-Selektoren, die ins Leere gingen.

## [0.40.92] — 2026-09

### Fixed

- Login-Karte jetzt wirklich breiter: NC limitierte die Gast-Content-
  Breite (~341px) — aufgehoben, die Karte nutzt ihre 504px.
- „Passwort vergessen?"/„An mich erinnern" wirklich kräftig: die alten
  Regeln griffen ins Leere (`#lost-password` ist selbst der Link, das
  Remember-Label teils ohne for-Attribut) — Selektoren gehärtet.
- Doppelter blauer Fokus-Rahmen: NC zeichnet ein eigenes Outline aufs
  fokussierte Input — entfernt, der Wrapper-Ring ist der einzige
  Indikator.

## [0.40.91] — 2026-09

### Fixed

- Dunkler Schleier beim Scrollen (Mobil): das Panel war `static`, seine
  Scrim-Schicht verankerte sich am Viewport statt am Panel — jetzt
  `relative`.
- Mobile Brand-Kopfzeile deutlich kompakter.
- Login-Karte ~20 % breiter (Desktop 504px, mobil 552px).
- „Passwort vergessen?", „An mich erinnern" und „Mit einem Gerät
  anmelden" kräftiger (Kontrast + Gewicht).

## [0.40.90] — 2026-09

### Fixed

- Letzter Mobil-Überlauf: auch `.v-align` mit content-box — `border-box`
  erzwungen, Breite 100 % verankert.

## [0.40.89] — 2026-09

### Fixed

- Mobil 11px-Seitenversatz: NC rechnet Gast-Boxen mit content-box —
  `width:100%` + Padding lief 438px in einen 390px-Viewport. Jetzt
  `border-box` erzwungen, Body-Zentrierung neutralisiert.

## [0.40.88] — 2026-09

### Fixed

- Feld-Placeholders ergänzt (NC 34 liefert absichtlich leere) und
  NCs `.wrapper`-Max-Width neutralisiert; Mobil ohne Seiten-Offset.

## [0.40.87] — 2026-09

### Fixed

- Split-Layout robust gegen NCs eigene Guest-Body-Regeln (Flex-Column +
  Zentrierung): Brand-Panel jetzt `position: fixed` (immer exakt die linke
  Hälfte), Wrapper per Margin auf die rechte Hälfte. Mobil: Karte direkt
  unter der Brand-Kopfzeile statt in einer Viewport-Höhe zentriert.

## [0.40.86] — 2026-09

### Fixed

- Kein Layout-Flackern mehr: login.css wird statisch im Head geliefert
  (vor dem ersten Paint), die Seite erscheint erst, wenn Panel und Karte
  stehen — mit 1,5s-Failsafe-Reveal, falls JS versagt. Panel-Text
  linksbündig (NC erbt zentrierten Text in die Gast-Seite).

## [0.40.85] — 2026-09

### Fixed

- Login-Layout wurde nie injiziert: NC feuert für Login-Seiten seit 28
  das dedizierte `BeforeLoginTemplateRenderedEvent` — der Listener lauscht
  jetzt zusätzlich darauf.

## [0.40.84] — 2026-09

### Added

- **Souvera-Login: Split-Screen im Host-On-Stil.** Linke Bühne mit dem
  Instanz-Hintergrundbild (`--image-background`, occ-Theming), Souvera-Logo
  + Wordmark (aus dem Theming), Headline, Subline und Feature-Chips;
  rechts die Anmeldekarte („Willkommen zurück", sichtbare Feld-Labels,
  „Passwort vergessen?" beim Passwortfeld, Souvera-Blau-Button).
  Mobil: kompakte Brand-Kopfzeile über der Karte. 2FA-/Geräte-/Fehler-
  seiten erben das Layout; Public-Share-Seiten bleiben unangetastet.
  Notbremse: `occ config:system:set souvera_login.enabled --value 0`.

## [0.40.83] — 2026-08

### Changed

- Neues „Link"-Icon (ersetzt Talk-Branding) — jetzt als SVG
  (`img/link.svg`), das alte `img/link.png` wurde entfernt.

## [0.40.82] — 2026-08

### Fixed

- „Mehr"-Dropdown: App-Icons waren weiß auf weiß — NCs Nav-Icons sind
  weiße Mask-Icons; das Dropdown tönt sie jetzt je Theme (schwarz im
  hellen, weiß im dunklen Modus, per Helligkeits-Erkennung beim Öffnen).
- Suche weiter nach links gerückt (Ende bei 260px statt 190px), damit der
  Assistant-Button (✨) nicht mehr hinter dem Suchfeld verschwindet.

## [0.40.81] — 2026-08

### Fixed

- Mobile vollständig zurückgebaut: Header-Umbau greift NUR ≥1025px —
  mobil bleibt der Original-NC-Header (Hamburger-Menü, Assistant-Button,
  Suche-Icon) unangetastet. Resize wechselt sauber zwischen den Modi.
- `.header-end` wird nicht mehr angefasst (Assistant-Button war dadurch
  verschwunden) — die Suche wird stattdessen präzise per
  `inset-inline-end: 190px` (320px) neben den Header-Icons platziert.

## [0.40.80] — 2026-08

### Fixed

- Reste im Header entfernt: Grid-Icon + „Current-App"-Display wurden
  NICHT in `#header-start__appmenu` gerendert (Server-HTML leer, NC
  mountet woanders) — jetzt werden ALLE weiteren Kinder von
  `.header-start` (außer Logo und eigenen Buttons) per Inline-Style
  versteckt, plus CSS-Backup für ID/Klasse/Kind-Selektoren.

## [0.40.79] — 2026-08

### Fixed

- **Browser-Cache entwaffnet**: header.css/-js lädt das Branding-Script
  jetzt DYNAMISCH mit Zeitstempel — NCs `?v=` an Asset-URLs ist der
  Core-Hash und ändert sich bei App-Updates nie, deshalb bekamen
  Dev-Instanzen nach Updates dauerhaft alte Header-Assets.

## [0.40.78] — 2026-08

### Fixed

- Header: neues NC-App-Menü (`#header-start__appmenu`) wird nach
  erfolgreichem Rendern direkt per JS ausgeblendet (CSS-Marker allein
  griff auf der Instanz nicht).
- „Mehr"-Dropdown: App-Icons hatten feste Größe 18×18 in Originalfarben
  — das riesige Shield-SVG kam daher, dass das Dropdown am BODY hängt und
  der alte Selektor unter `#souvera-header-apps` nie griff; Icons jetzt
  korrekt_formatiert in Originalfarben.
- Neue Reihenfolge: Logo | Mail | Kalender | Link | Desk | Deck |
  Dateien | Mehr.

## [0.40.77] — 2026-08

### Fixed

- Header-CSS gegen die ECHTEN v34-Server-Regeln gebaut (aus
  core/css/server.css + dem Unified-Search-Bundle der Instanz gezogen):
  Logo-Fix (30×30-Override hatte das absolute Logo-Layout zerstört → Logo
  weg; jetzt nur padding-inline-start 48px + .logo 34px, Absolute-
  Positionierung unangetastet) und Suche präzise verschoben (echter
  Selektor `.unified-search-input`, 320px, `inset-inline-end: 170px`
  vor den Icons statt zentriert über allem).

## [0.40.76] — 2026-08

### Fixed

- Header-Feinschliff: Logo kompakt (nur Icon, minimaler Rand), Suche aus
  der absoluten Zentrierung in den Flex-Fluss von .header-end geholt
  (320px, rechts vor den Icons) — überlappte zuvor den „Mehr"-Button.

## [0.40.75] — 2026-08

### Fixed

- **Root-Cause des toten Headers gefunden (offline reproduziert):**
  Variablen-Verschattung — im render()-forEach wurde `header.adminOnly`
  auf dem DOM-Element statt auf der Config gelesen (`header` = DOM-Element
  aus `headerEl()`) → TypeError beim ersten Button → stiller Abbruch.
  Config-Zugriffe auf `cfg.*` umgestellt, DOM-Root in `headerRoot`
  umbenannt. Verifiziert: 7 Buttons + „Mehr" rendern mit den echten
  Instanzdaten.

## [0.40.74] — 2026-08

### Fixed

- Souvera-Header gegen den ECHTEN v34-DOM neu gebaut (aus der Instanz
  verifiziert): eigener Container als Geschwister von #nextcloud (kein
  Eindringen in Vue-Territorium → kein DOM-Krieg), „Mehr" als EIGENES
  Dropdown (restliche Apps aus dem core-apps State), Failsafe-Marker
  `html.souvera-header-ok` (NC-App-Menü wird erst ausgeblendet, wenn die
  Buttons wirklich hängen), Suche rechts kompakt via `#unified-search`.
  Debug-Log via `?souveraDebug=1`.

## [0.40.73] — 2026-08

### Added

- **Souvera-Header** (global, Notbremse `branding.header.enabled=0`):
  gepinnte App-Buttons direkt im Header (Dashboard, Dateien, Mail, Link,
  Deck, Kalender, Central nur für Admins), „Dashboard"-Breadcrumb ausge-
  blendet, Suche rechts kompakt (320px), „Mehr" = bestehender App-Grid-
  Dropdown. Mobile <1024px: Buttons aus, Seitenleiste bleibt.
  Gepinnte Liste konfigurierbar via `souvera_central.branding.header.pinned`.

## [0.40.72] — 2026-08

### Added

- Externe Authentifizierung als User-Quelle (Authentik/Keycloak via NC
  `user_oidc`/`user_saml`): Bei aktivem Feature-Flag
  (`souvera_central.ext_idp.enabled`) bekommen föderierte Benutzer mit
  erlaubter Claim-Adresse automatisch ein Stalwart-Postfach (zufälliges
  internes Passwort — Login via SSO, Mail-Auth via H2CK/oidc-JWT).
  Setup-Contract: `docs/EXTERNAL_IDP.md`.

## [0.40.71] — 2026-08

### Changed

- `occ souvera:domain:list` zeigt jetzt Stalwart-Status, Erlaubnis-Flag
  und Postfach-/Alias-Belegung je Mail-Domain (identisch zur Admin-UI,
  `--json` für den CloudManager).

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
