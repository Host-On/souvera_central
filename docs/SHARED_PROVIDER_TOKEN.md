# Souvera – Zentraler Provider-Token (Central als Hub)

Souvera **Central** ist die Zentrale. Gemeinsame Zugangsdaten werden **einmal**
zentral in Central hinterlegt und von dort an die anderen Souvera-Apps
(**Shield**, **Mail**) ausgegeben – sie müssen **nicht** in jeder App gespeichert
werden.

Aktuell zentral verwaltet: der API-Token für **`provider.tools`**.

---

## 1. Token setzen (Hoster, via occ)

Der Token wird **verschlüsselt** abgelegt (Nextcloud `OCP\Security\ICrypto`,
gebunden an das Instanz-`secret` aus `config.php`). Klartext liegt nie in der DB.

```bash
# Empfohlen: über STDIN (keine Shell-History)
printf '%s' 'DEIN_PROVIDER_TOOLS_TOKEN' | sudo -u www-data php occ souvera:provider-token:set --stdin

# Alternativ interaktiv (verdeckte Eingabe)
sudo -u www-data php occ souvera:provider-token:set

# Status prüfen (maskiert)
sudo -u www-data php occ souvera:provider-token:show
sudo -u www-data php occ souvera:provider-token:show --reveal   # Klartext (Debug)
sudo -u www-data php occ souvera:provider-token:show --output=json

# Entfernen
sudo -u www-data php occ souvera:provider-token:delete
```

---

## 2. Token abrufen (aus Shield / Mail)

**Voraussetzung:** `souvera_central` ist installiert **und aktiviert**. Dadurch ist
der Namespace `OCA\SouveraCentral\` autoloadbar und der Service über den
DI-Container abrufbar.

```php
use OCA\SouveraCentral\Service\ProviderTokenService;

/** In einem Controller/Service der anderen App: */
$token = \OCP\Server::get(ProviderTokenService::class)->getToken();
// string  -> Token im Klartext (entschlüsselt)
// null    -> nicht gesetzt oder nicht entschlüsselbar
```

Per Constructor-Injection (sauberer, falls die andere App Central als harte
Abhängigkeit voraussetzt):

```php
public function __construct(
    private \OCA\SouveraCentral\Service\ProviderTokenService $providerToken,
) {}

$token = $this->providerToken->getToken();
```

### Defensive Nutzung (empfohlen)

```php
$svc = null;
if (\OCP\Server::get(\OCP\App\IAppManager::class)->isEnabledForUser('souvera_central')) {
    try {
        $svc = \OCP\Server::get(\OCA\SouveraCentral\Service\ProviderTokenService::class);
    } catch (\Throwable $e) {
        $svc = null; // Central (noch) nicht verfügbar
    }
}
$token = $svc?->getToken();
if ($token === null) {
    // Fallback / klare Fehlermeldung: "provider.tools-Token in Souvera Central hinterlegen"
}
```

---

## 3. Öffentliche Service-API (Contract)

`OCA\SouveraCentral\Service\ProviderTokenService`

| Methode | Rückgabe | Beschreibung |
|---|---|---|
| `hasToken()` | `bool` | Ist ein Token hinterlegt? |
| `getToken()` | `?string` | Entschlüsselter Token oder `null`. |
| `getMaskedToken()` | `?string` | Maskiert (nur letzte 4 Zeichen), nie Klartext. |
| `getSetAt()` | `?string` | ISO-8601-Zeitpunkt des letzten Setzens. |
| `setToken(string)` | `void` | Verschlüsselt speichern (wirft bei leer). I. d. R. via occ. |
| `clearToken()` | `void` | Token entfernen. |
| Konstante `PROVIDER` | `'provider.tools'` | Provider-Kennung. |

**Nur lesen** aus Shield/Mail (`getToken`). Das Setzen/Löschen erfolgt zentral
durch den Hoster in Central (occ).

---

## 4. Hinweise

- **Instanz-Secret-Wechsel:** Ändert sich `secret` in `config.php`, ist der Token
  nicht mehr entschlüsselbar → `getToken()` liefert `null`; einmal neu setzen.
- **Erweiterbarkeit:** Weitere gemeinsame Credentials können analog als eigene
  Service-Methoden/Keys ergänzt werden – Central bleibt die einzige Quelle.
