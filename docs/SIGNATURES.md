# Zentrale E-Mail-Signaturen — Admin- und Betriebsdokumentation

Stand: souvera_central >= 0.44.0, souvera_mail >= 1.2.69, Stalwart >= 0.16

## Funktionsprinzip

```
Thunderbird/Outlook/Mobile ──SMTP-Submission──▶ Stalwart
                                                  │  DATA-Stage MTA-Hook
                                                  ▼  (VOR DKIM-Signierung)
                             souvera_central: POST /apps/souvera_central/signature/hook
                               ├─ Bearer-Secret prüfen (hook_secret)
                               ├─ Skip: verschlüsselt/signiert, ICS, >Size-Limit,
                               │   bereits signiert (Marker), kein bekannter User
                               ├─ Signatur auflösen: User-Override > Gruppen-Override
                               │   > globales Template, Variablen aus Zusatzfeldern /
                               │   NC-Profil / Admin-Fallbacks
                               └─ Injektion in text/html + text/plain (Logo als CID)
                                                  ▼
                             Stalwart signiert DKIM ──▶ Empfänger

Webmail (souvera_mail): Compose fügt dieselbe Signatur direkt ein
  (GET /apps/souvera_central/api/mail-settings/signature). Da die gesendete
  Mail die Marker-Klasse `souvera-sig` enthält, überspringt der Hook sie —
  keine Doppel-Signatur.
```

## Wichtig für den Stable-/Dev-Kanal (GitLab-Archiv-Cache)

GitLab cachet `repository/archive.zip` pro SHA. Nach dem Pushen neuer
Versionen zusätzlich sicherstellen, dass der Self-Update einen aktuellen
Commit zieht (neuer Commit = neue SHA = frisches Archiv). Ein stale
Archiv-Cache hat bereits dazu geführt, dass Clouds einen Stand ohne
js/css/img installierten (weiße Seiten).

## Einrichtung (pro Cloud)

1. **Central aktualisieren** (≥ 0.44.0) — Migrationen laufen automatisch.
2. **Admin-UI**: `Central → Einstellungen → Mail signature`
   - Globale Signatur aktivieren + HTML-Template pflegen (bestehender Editor)
   - Abschnitt „SignatureAdminSection": Fallback-Werte, Logo hochladen,
     Group/User-Overrides pflegen, MTA-Hook aktivieren, Secret generieren
3. **Secret rotieren/auslesen**:
   ```
   occ souvera_central:signature:hook-config [--rotate-secret]
   ```
   → Hook-URL, Secret und Stalwart-Config-Snippet.
4. **Stalwart konfigurieren — AUTOMATISCH**:
   ```
   occ souvera_central:signature:hook-config --apply
   ```
   oder in der Admin-UI (Signatures-Seite): „Apply hook config to Stalwart".
   Der Apply-Flow liest die Config, prüft auf Kollisionen (fremde Hooks →
   Abbruch ohne Schreiben), sichert den Ist-Zustand (Rollback), schreibt
   nur die eigenen Keys und verifiziert per Read-back. Der Push-Benach-
   richtigungs-Webhook (`webhook.*` — anderes Subsystem, feuert nach der
   Zustellung) bleibt garantiert unberührt und wird im Status angezeigt.
   Rollback: `--rollback` bzw. „Rollback"-Button. Kalibrierung der
   Stalwart-Hook-Key-Syntax: zentral in StalwartConfigService::hookTemplate().
5. **Test**: Mail aus Thunderbird senden → Signatur muss erscheinen;
   `X-Souvera-Signature: injected` im Header prüfen; DKIM-Validator
   (z. B. dkimchecker.com) → `body hash: pass`.

## Variablen

| Variable | Quellen (erste nicht-leere gewinnt) |
|---|---|
| `%name%`, `%first_name%`, `%last_name%` | NC-Anzeigename (Vor/Nachname-Heuristik) |
| `%email%`, `%domain%` | NC-Profil-E-Mail |
| `%phone%` | Zusatzfeld → NC-Profil (Telefon) → Fallback |
| `%company%` | Zusatzfeld → NC-Profil (Organisation) → Fallback |
| `%title%`, `%department%` | Zusatzfeld → Fallback |
| Weitere | beliebige eigene Felder in `sig_user_fields` (%schlüssel%) |

## Overrides (Priorität)

1. **User-Override** (niedrigste priority-Nummer gewinnt)
2. **Gruppen-Override** (niedrigste priority-Nummer gewinnt)
3. **Globales Template** (Settings.vue)

## Skip-Regeln (Mail bleibt unverändert)

- `multipart/signed`, `multipart/encrypted`, `application/pkcs7-mime`, PGP
- `text/calendar` (Kalendereinladungen)
- Nachrichten über dem Size-Limit (AppConfig, Default 10 MB)
- Bereits signiert: `X-Souvera-Signature`-Header oder HTML-Klasse `souvera-sig`
- Absender ohne NC-User-Mapping oder ohne aktive Signatur
- Hook deaktiviert / Central nicht erreichbar → **fail-open**

## Wichtig: Ausschließlichkeit zum Sieve-Pfad

Der alte Sieve-Pfad (`occ souvera_central:mailsignature:sieve --deploy`)
und der MTA-Hook dürfen **nicht gleichzeitig** aktiv sein (doppelte
Signaturen). Vor Hook-Aktivierung: `--remove` ausführen.

## Offene Live-Verifikation (P0, host-on.souvera.work)

1. Hook feuert bei SMTP-Submission (Payload-Shape kalibrieren:
   Log von `SignatureHookController` prüfen; Extract-Methoden sind
   tolerant, ggf. Feldpfade anpassen)
2. `replace_body`-Antwort wird von Stalwart übernommen (inkl.
   restrukturiertem MIME für Logo-CID)
3. DKIM nach Injection: `body hash: pass`
4. Reply/Forward: Signatur über dem Zitat, keine Stapelung
5. Größe > Limit: Mail geht ohne Hook-Kontakt durch
