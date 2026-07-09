# Cloud-Initialisierung für den CloudManager (CM) — Souvera Central

Diese Anleitung beschreibt die **korrekte Reihenfolge** der Provisionierung, damit
`ncadmin`, `swadmin` und die Lizenz-/Rollen-Logik von Souvera Central v0.12.2+ stimmen.

> **Mindestversion:** `souvera_central >= 0.12.2` (Admin-Gruppe `souvera-admins`, korrigierte Stalwart-v0.16-Provisionierung).
> Vorher (z. B. das alte v0.12.0) kennt diesen Befehl nicht.

---

## ⚠️ Die zwei wichtigsten Regeln

1. **App ZUERST aktivieren, DANN `souvera:*`-Befehle.**
   occ-Befehle einer App existieren erst **nach** `occ app:enable souvera_central`.
   Im bisherigen CM-Log lief `souvera:provision-mailbox` *vor* dem Aktivieren →
   `There are no commands defined in the "souvera" namespace`. Reihenfolge umdrehen.

2. **Gruppe `souvera-users` ist die kanonische Mail-/Lizenzgruppe** (nicht mehr `mail-users`).
   - `souvera-users`  = lizenzierte „Souvera User" (mit Postfach, sehen smail/Shield).
   - `souvera-admins` = Souvera-Administratoren (delegierte Verwaltung, **kein** NC-Superadmin).
     Hinweis: `scadmin`/`swadmin` ist der Admin-**Benutzer**, die Admin-**Gruppe** heißt `souvera-admins`.
   Beide werden ohnehin schon vom CM angelegt und von Central geschützt. Die alte Gruppe
   `mail-users` wird **nicht** mehr benötigt — smail & souvera_shield auf `souvera-users` binden.

---

## Empfohlene Reihenfolge (occ)

```bash
# 0) Apps-Code ausrollen (wie bisher)

# 1) Konfiguration schreiben (darf vor dem Enable laufen, sind nur config:system:set)
occ config:system:set souvera_central.stalwart_api_url       --value="https://<stalwart>:443"
occ config:system:set souvera_central.stalwart_admin_user    --value="admin"
occ config:system:set souvera_central.stalwart_admin_password --value="<secret>"
occ config:system:set souvera_central.allowed_domains        --value="buxtehude.link"
occ config:system:set souvera_central.cloud_uuid             --value="<uuid>"
occ config:system:set souvera_central.max_licenses           --value=25     # Lizenzlimit

# 2) Gruppen anlegen (idempotent)
occ group:add souvera-users     # Lizenz-/Mailgruppe (Central schützt sie)
occ group:add souvera-admins    # Souvera-Administratoren (Central schützt sie)

# 3) Apps installieren + AKTIVIEREN  ← VOR allen souvera:*-Befehlen!
occ app:enable souvera_central
occ app:enable souvera_shield --groups souvera-users   # Shield nur für lizenzierte User
occ app:enable smail          --groups souvera-users   # smail nur für lizenzierte User
occ smail:bootstrap --mail-imap-host ...

# 4) Technischen Admin ncadmin absichern + Postfach provisionieren
occ user:resetpassword ncadmin                          # internes NC-Passwort
occ souvera:provision-mailbox ncadmin@buxtehude.link --password-stdin <<< "$NCADMIN_MAIL_PW"
# Hinweis: ncadmin wird in Central automatisch ausgeblendet (hidden_users-Default).

# 5) Kunden-Admin swadmin anlegen (delegierter Admin, KEIN NC-Superadmin)
occ user:add swadmin --display-name "Administrator" --password-from-env=SWADMIN_PW
occ user:setting swadmin settings email "swadmin@buxtehude.link"
occ group:adduser souvera-admins swadmin                # → darf Central sehen + bedienen, sieht das Icon
occ group:removeuser admin  swadmin                     # sicherstellen: NICHT NC-Superadmin

# 6) swadmin zum lizenzierten? -> NEIN: souvera-admins verbraucht KEINE Lizenz, bekommt aber ein Postfach
occ souvera:make-souvera-user swadmin@buxtehude.link
#   ^ akzeptiert UID ODER E-Mail. Fügt swadmin zu souvera-users hinzu + legt Stalwart-Postfach an.
#     Da swadmin in 'souvera-admins' ist, zählt er NICHT auf max_licenses.
```

---

## Ergebnis / Sollzustand

| Benutzer  | NC-Superadmin | Gruppe `souvera-admins` | Gruppe `souvera-users` | Postfach | Lizenz | In Central sichtbar |
|-----------|:------------:|:----------------:|:----------------------:|:--------:|:------:|:-------------------:|
| `ncadmin` | ✅ (technisch)| –                | – (oder optional)      | ✅       | nein   | **ausgeblendet**    |
| `swadmin` | ❌           | ✅               | ✅                     | ✅       | **nein** (souvera-admins)| ✅ (sieht Icon, voller Funktionsumfang) |
| Endkunde X| ❌           | ❌               | ✅                     | ✅       | **ja** | ❌ (sieht die App NICHT) |

- **Souvera-Administrator** = Mitglied von `souvera-admins`. Darf Central vollständig bedienen
  (Benutzer, Gruppen, geteilte Postfächer, Einstellungen) **ohne** NC-Superadmin-Rechte.
- **Souvera User** = Mitglied von `souvera-users` (lizenziert, Postfach).
- **Nextcloud User** = nicht in `souvera-users` (unlizenziert, kein Postfach).
- **Lizenzzählung** = Mitglieder von `souvera-users` **ohne** `souvera-admins` und **ohne** `ncadmin`.
- **Sichtbarkeit/Zugriff:** Die App `souvera_central` (Icon + Routen) ist **ausschließlich** für
  Mitglieder von `souvera-admins` (sowie echte NC-Superadmins) sichtbar und bedienbar – alle
  anderen sehen weder das Icon noch können sie die Seite/API öffnen (403).

---

## Souvera-Shield-Einstellungen (von Central bereitgestellt)

Central pflegt diese global in der AppConfig der App `souvera_central`; Shield liest sie aus
(siehe `README.md` → „Souvera Shield"). Keys:

| Key (`app = souvera_central`)              | Typ           | Default |
|--------------------------------------------|---------------|---------|
| `settings.shield.desktop_notifications`    | `'0'`/`'1'`   | `'0'`   |
| `settings.shield.daily_summary`            | `'0'`/`'1'`   | `'0'`   |
| `settings.shield.min_spam_score`           | Float 0..10   | `'2.5'` |

```php
// In souvera_shield (PHP), via IConfig:
$minScore = (float) $config->getAppValue('souvera_central', 'settings.shield.min_spam_score', '2.5');
```

---

## Optionale Konfig-Keys (Defaults passen i. d. R. schon)

```bash
occ config:system:set souvera_central.mail_group     --value="souvera-users"          # Default
occ config:system:set souvera_central.admin_group    --value="souvera-admins"         # Default (Souvera-Admin-Gruppe)
occ config:system:set souvera_central.hidden_users   --value='["ncadmin"]' --type=json # in Central ausblenden
```

---

## Hilfe-Seite (BookStack) & App-Freigabe (ab v0.28.0)

Die Hilfe ist Teil von Central, aber – anders als die Verwaltung – auch für
**souvera-users** zugänglich. Verwaltung bleibt ausschließlich für **souvera-admins**.

**1) App für BEIDE Gruppen freigeben** (sonst blockt NC den Hilfe-Zugriff für
souvera-users komplett – die Verwaltung bleibt trotzdem admin-only):

```bash
occ app:enable souvera_central --groups souvera-admins --groups souvera-users
```

> Ab **v0.32.2** sichert ein automatischer Repair-Step das ab: Ist die App per
> Gruppen beschränkt, wird `souvera-users` (+ `souvera-admins`) bei jedem
> App-Update automatisch zur Freigabe ergänzt (idempotent – bei bereits korrekter
> Freigabe passiert nichts). Bei global aktivierter App bleibt alles unverändert.

**2) BookStack-Doku anbinden** — der API-Token wird **verschlüsselt** per occ
gesetzt (nicht mehr in `config.php`). Die BookStack-URL wird nicht benötigt
(fester Default `https://doku.souvera.eu`):

```bash
# Empfohlen: über STDIN (keine Shell-History)
printf '%s' '<TOKEN_ID>:<TOKEN_SECRET>' | occ souvera:bookstack-token:set --stdin
occ souvera:bookstack-token:show          # Status (maskiert)
# Optional (Shelf-IDs), Defaults: User=1 (Benutzer), Admin=2 (Administratoren)
occ config:system:set souvera_central.help_user_shelves  --value='[1]' --type=json
occ config:system:set souvera_central.help_admin_shelves --value='[2]' --type=json
```

Ohne Token zeigt die Hilfe einen „nicht konfiguriert"-Hinweis. Der „Hilfe"-Eintrag
erscheint oben rechts im Nutzer-Menü (Avatar-Dropdown) für souvera-users + souvera-admins.
Der Token ist – wie der provider.tools-Token – zentral und auch für Shield/Mail abrufbar
(siehe `docs/SHARED_PROVIDER_TOKEN.md`).

**Cache (ab v0.31.0):** BookStack-Antworten (Baum/Bücher/Seiten) werden instanzweit
zwischengespeichert (geteilt über Redis/APCu) → schnellere Ladezeiten, weniger Traffic.

```bash
# TTL in Sekunden (Standard 86400 = 24 h; 0 = Cache aus)
occ config:system:set souvera_central.help_cache_ttl --value=86400 --type=integer
occ souvera:help:cache-clear      # nach Doku-Änderungen sofort neu laden
```

---

## App-Umbenennung / Branding (Talk→Link, Office/Collabora→Desk)

Der **saubere, dauerhafte** Weg (auch im von Vue gerenderten App-Menü oben) ist der
Theme-l10n-Override. Ab v0.31.0 schreibt ein **Repair-Step** die Overrides bei jedem
App-Update automatisch in das **aktive** Theme. Ist noch kein Theme aktiv, einmalig:

```bash
occ souvera:branding:install-theme --activate   # schreibt Overrides + aktiviert Theme "souvera"
occ maintenance:theme:update                      # Theme-Cache neu aufbauen
```

Zusätzlich läuft ein JS-Fallback (best effort) für Instanzen ohne aktives Theme.
Bei aktivem Theme greift der saubere Override; das JS ändert dann nichts mehr.

---

## Zentraler provider.tools-Token (Souvera-Hub, ab v0.28.0)

Central hält gemeinsame Zugangsdaten einmal verschlüsselt vor; Shield/Mail beziehen
sie von hier (siehe `docs/SHARED_PROVIDER_TOKEN.md`).

```bash
printf '%s' 'PROVIDER_TOOLS_TOKEN' | occ souvera:provider-token:set --stdin
occ souvera:provider-token:show          # Status (maskiert)
```
