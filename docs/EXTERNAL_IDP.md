# Souvera – Externe Authentifizierung als User-Quelle (Authentik, Keycloak, …)

Ein Kunde kann seine **eigene Identitäts-Infrastruktur** (z. B. Authentik,
Keycloak — jeweils OIDC oder SAML) als User-Quelle für seinen Souvera
Workspace nutzen. Benutzer loggen sich dann per SSO in Nextcloud ein und
bekommen **automatisch** ihr Stalwart-Postfach — zentral verwaltet von
Souvera Central.

Stand: 2026-08

---

## 1. Architektur (Identity-Kette)

```text
Authentik/Keycloak (Kunden-IdP)
        │  OIDC / SAML
        ▼
Nextcloud  ← user_oidc / user_saml (offizielle NC-Apps)
        │  Auto-Provisionierung beim ersten Login (Claims → NC-User)
        ▼
Souvera Central (Listener)
        │  UserCreatedEvent → Stalwart-Principal (zufälliges internes Passwort)
        ▼
Stalwart  ← Mail-Auth via H2CK/oidc-JWT (OAUTHBEARER), nicht via Passwort
```

- **NC bleibt die zentrale Benutzerverwaltung** — der externe IdP ist die
  Quelle, aus der NC-Benutzer entstehen (föderierte Backend-Benutzer).
- **H2CK/oidc macht Nextcloud zum OIDC-Provider** für den Mail-Stack. Es
  stellt Tokens pro NC-User aus — unabhängig vom Auth-Backend des Users.
  Das interne Stalwart-Passwort ist deshalb rein technisch (IMAP-Client-
  Fallback) und verlässt die Instanz nicht.
- **Mail-Domain**: Die Claim-Adresse des Benutzers (z. B.
  `alice@kunde.de`) muss eine erlaubte Mail-Domain des Workspaces sein —
  siehe `docs/MULTI_DOMAIN.md` (`occ souvera:domain:add <d> --allow`).

## 2. Aktivierung (Hoster)

```bash
# config.php oder:
occ config:system:set souvera_central.ext_idp.enabled --value true --type boolean
```

Ohne Flag bleibt das Verhalten unverändert (Postfächer nur über
Central-UI/occ mit Klartext-Passwort).

## 3. IdP-seitiges Setup (Beispiel Authentik)

1. **Provider**: OIDC-Provider / Application in Authentik anlegen
2. **Redirect-URIs der NC-Instanz** eintragen (aus der user_oidc-Config)
3. **Claims**: `preferred_username` (eindeutig!), `email` (die
   Mailadresse, die auch das Postfach bekommt), `groups` (optional)
4. **Gruppe** `souvera-users` in Authentik pflegen — sie steuert die
   Lizenz (jedes Mitglied zählt, siehe LicenseService)

## 4. Nextcloud-seitiges Setup

```bash
occ app:install user_oidc
occ user_oidc:provider <kunde-authentik> \
    --client-id="…" --client-secret="…" \
    --discovery-url="https://idp.kunde.de/application/o/<app>/.well-known/openid-configuration" \
    --mapping-uid="preferred_username" \
    --mapping-email="email" \
    --mapping-displayname="name" \
    --mapping-groups="groups"
```

- **Gruppen-Mapping**: Wird in Authentik eine Gruppe `souvera-users`
  gepflegt und in user_oidc auf die NC-Gruppe `souvera-users` gemappt,
  bekommen geförderte Benutzer automatisch die Souvera-Rolle (und damit
  Lizenz + Postfach, sobald das Flag aktiv ist).
- Login-Wechsel: Bestehende lokale Benutzer mit identischem
  `preferred_username`/E-Mail matchen — kein doppelter Account.

## 5. Was automatisch passiert (Central)

| Ereignis | Reaktion |
|---|---|
| Erster Login via IdP | NC-User wird provisioniert → `UserCreatedEvent` |
| `UserCreatedEvent` | Central legt das Stalwart-Postfach mit der **Claim-Adresse** an (zufälliges internes Passwort; nur wenn Mitglied `souvera-users` **und** Flag aktiv) |
| Passwort-Änderung im IdP | Kein `PasswordUpdatedEvent` in NC → kein Sync nötig (Mail-Auth = JWT) |
| Benutzer wird im IdP gelöscht | NC-User löschen (deprovisioniert Postfach, `UserDeletedEvent`) oder manuell in Central |

**Bestandsbenutzer werden nie automatisch angefasst.**

## 6. Grenzen / bewusste Entscheidungen

- **App-Passwörter / Geräte-Flow (Mail)**: Der kombinierte Passwort-Flow
  (Stalwart-PW + NC-Device-Token mit identischem Klartext) setzt ein
  NC-Passwort voraus und ist für föderierte Benutzer nicht verfügbar —
  Webmail (JWT) funktioniert vollständig. Geräte-/DAV-Zugriff läuft über
  NC-App-Passwörter (IToken-API, funktioniert ohne Passwort-Kenntnis).
- **Kein Fallback auf die erste Domain**: Fördertierte Benutzer ohne
  erlaubte Claim-Adresse werden übersprungen (loggt als Warning) statt ein
  Postfach auf der falschen Domain anzulegen.
- **Lizenz**: Jedes `souvera-users`-Mitglied zählt — auch föderiert. Die
  Gruppen-Zuordnung ist der Lizenz-Hebel.
- **user_oidc-Verhalten** (Auto-Provisionierung, Claim-Mapping) ist Teil
  der offiziellen NC-App und nicht Teil dieses Repos.

## 7. Verwandt

- `docs/MULTI_DOMAIN.md` — Kunden-Domain als Mail-Domain (Voraussetzung,
  wenn die IdP-Adressen auf der Kundendomain liegen)
- `docs/SHARED_PROVIDER_TOKEN.md` — Hub-Muster der zentralen Credentials
