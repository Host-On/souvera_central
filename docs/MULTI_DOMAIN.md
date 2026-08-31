# Souvera Multi-Domain — Contract für den CloudManager

Souvera Central, Mail und Shield sind **Multi-Domain-fähig**. Der CM legt
eine zweite (oder weitere) Mail-Domain an und gibt wie bei der Hauptdomain
die DNS-Records an den Reseller aus. Stand: 2026-08.

## 1. Ablauf (CM)

```text
1. Domain buchen (CM-seitig)
2. DNS-Records ausgeben/anlegen:
       MX    @                    → <pmg-host>   (Prio 10)
       TXT   @                    → "v=spf1 <mechanismus vom CM> ~all"
       TXT   <selector>._domainkey → "v=DKIM1; k=rsa; p=<key>"   (CM/Hoster)
       TXT   _dmarc               → "v=DMARC1; p=quarantine; rua=mailto:…"
       CNAME autodiscover         → <mail-host>   (optional)
3. Domain in Stalwart + Central-Erlaubnisliste:
       occ souvera:domain:add <domain> --allow
   …oder per Admin-API (siehe unten).
4. PMG: Relay-Domain <domain> anlegen (PMG-seitig, wie bei der Hauptdomain)
5. DKIM: Signatur-Keys für <domain> in Stalwart/PMG konfigurieren
   (Hoster-Schritt, wie bei der Hauptdomain)
6. Danach sofort nutzbar:
       - Neue Benutzer mit @<domain> im Central-User-Editor (Dropdown)
       - Aliase @<domain> über die bestehende Alias-Verwaltung
       - Senden als @<domain> in Souvera Mail (Send-As-Identität)
       - Quarantäne (Shield) via PMG-allowed-Domains
```

**Bestandsbenutzer werden NIEMALS automatisch angefasst** — eine
@domain2-Adresse bekommt ein User nur per Opt-in über die Alias-Verwaltung
oder durch Umbenennen (expliziter Admin-Akt).

## 2. Admin-API (Central, Souvera-Admin)

```text
GET    /ocs/v2.php/apps/souvera_central/api/domains
       → { domains: [{ domain, allowed, in_stalwart, accounts, aliases }],
           stalwart_available }
POST   /ocs/v2.php/apps/souvera_central/api/domains      { domain }
       → 201 { domain, created_in_stalwart, allowed }
DELETE /ocs/v2.php/apps/souvera_central/api/domains/{domain}
       → 409 Conflict, wenn Postfächer/Aliase auf der Domain liegen
```

Äquivalente occ-Befehle: `souvera:domain:add <d> [--allow]`,
`souvera:domain:delete <d>`, `souvera:domain:list`.

## 3. App-Status

| App | Multi-Domain-Status |
|---|---|
| Central | ✅ Domain-Verwaltung (UI + API + occ), User-Anlage mit Domain-Dropdown, Aliase |
| Mail | ✅ Send-As über Central-Aliases, Vacation account-weit, Signaturen pro Identität |
| Shield | ✅ DMARC/Reputation/Mail-Test/Spam-Report laufen über alle PMG-Domains |

## 4. Regeln

- Kein Auto-Touch von Bestandsbenutzern.
- Domain-Entfernung nur ohne Postfächer/Aliase (409 + Begründung).
- DNS/DKIM-Werte liefert der CM (wie Hauptdomain) — Central speichert keine
  DNS-Zielwerte.
