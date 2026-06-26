# Cloud-Initialisierung für den CloudManager (CM) — Souvera Central

Diese Anleitung beschreibt die **korrekte Reihenfolge** der Provisionierung, damit
`ncadmin`, `swadmin` und die Lizenz-/Rollen-Logik von Souvera Central v0.12.1+ stimmen.

> **Mindestversion:** `souvera_central >= 0.12.1` (enthält `occ souvera:make-souvera-user`).
> Vorher (z. B. das alte v0.12.0) kennt diesen Befehl nicht.

---

## ⚠️ Die zwei wichtigsten Regeln

1. **App ZUERST aktivieren, DANN `souvera:*`-Befehle.**
   occ-Befehle einer App existieren erst **nach** `occ app:enable souvera_central`.
   Im bisherigen CM-Log lief `souvera:provision-mailbox` *vor* dem Aktivieren →
   `There are no commands defined in the "souvera" namespace`. Reihenfolge umdrehen.

2. **Gruppe `souvera-users` ist die kanonische Mail-/Lizenzgruppe** (nicht mehr `mail-users`).
   - `souvera-users` = lizenzierte „Souvera User" (mit Postfach, sehen smail/Shield).
   - `scadmin`       = Souvera-Administratoren (delegierte Verwaltung, **kein** NC-Superadmin).
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
occ group:add scadmin           # Souvera-Administratoren (Central schützt sie)

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
occ group:adduser scadmin swadmin                       # → darf Central bedienen, sieht das Icon
occ group:removeuser admin  swadmin                     # sicherstellen: NICHT NC-Superadmin

# 6) swadmin zum lizenzierten? -> NEIN: scadmin verbraucht KEINE Lizenz, bekommt aber ein Postfach
occ souvera:make-souvera-user swadmin@buxtehude.link
#   ^ akzeptiert UID ODER E-Mail. Fügt swadmin zu souvera-users hinzu + legt Stalwart-Postfach an.
#     Da swadmin in 'scadmin' ist, zählt er NICHT auf max_licenses.
```

---

## Ergebnis / Sollzustand

| Benutzer  | NC-Superadmin | Gruppe `scadmin` | Gruppe `souvera-users` | Postfach | Lizenz | In Central sichtbar |
|-----------|:------------:|:----------------:|:----------------------:|:--------:|:------:|:-------------------:|
| `ncadmin` | ✅ (technisch)| –                | – (oder optional)      | ✅       | nein   | **ausgeblendet**    |
| `swadmin` | ❌           | ✅               | ✅                     | ✅       | **nein** (scadmin)| ✅ (sieht Icon, voller Funktionsumfang) |
| Endkunde X| ❌           | ❌               | ✅                     | ✅       | **ja** | ✅                  |

- **Souvera-Administrator** = Mitglied von `scadmin`. Darf Central vollständig bedienen
  (Benutzer, Gruppen, geteilte Postfächer, Einstellungen) **ohne** NC-Superadmin-Rechte.
- **Souvera User** = Mitglied von `souvera-users` (lizenziert, Postfach).
- **Nextcloud User** = nicht in `souvera-users` (unlizenziert, kein Postfach).
- **Lizenzzählung** = Mitglieder von `souvera-users` **ohne** `scadmin` und **ohne** `ncadmin`.

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
occ config:system:set souvera_central.scadmin_group  --value="scadmin"                # Default
occ config:system:set souvera_central.hidden_users   --value='["ncadmin"]' --type=json # in Central ausblenden
```
