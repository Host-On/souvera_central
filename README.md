# Souvera User Management

Nextcloud App für erweiterte Benutzerverwaltung mit Lizenzlimit und Domain-Whitelist.

## Entwicklung

### Berechtigungen setzen

Falls Dateien im Nextcloud-Verzeichnis nicht bearbeitbar sind (gehören `www-data`), setze ACL-Rechte:

```bash
sudo setfacl -R -m u:synistic:rwx /home/synistic/NEXTCLOUD_APP/nextcloud-dev
```

Dies erlaubt sowohl `www-data` (Nextcloud) als auch `synistic` (Entwickler) vollen Zugriff.

## Konfiguration

### System Config (config.php)

Die App nutzt **Read-Only** Konfigurationswerte aus der Nextcloud `config/config.php`.

Füge folgende Werte in deine `config/config.php` ein:

```php
<?php
$CONFIG = array(
  // ... bestehende Config ...

  /**
   * Souvera User Management - Lizenz-Limit
   *
   * Maximale Anzahl an Benutzern, die erstellt werden dürfen
   */
  'souvera_user_management.max_licenses' => 10,

  /**
   * Souvera User Management - Erlaubte E-Mail-Domains
   *
   * Array von erlaubten E-Mail-Domains für neue Benutzer
   * Leeres Array = alle Domains erlaubt
   */
  'souvera_user_management.allowed_domains' => [
    'example.com',
    'test.de',
    'company.org',
  ],
);
```

### Konfigurationswerte

| Parameter | Typ | Beschreibung | Standard |
|-----------|-----|--------------|----------|
| `souvera_user_management.max_licenses` | `int` | Maximale Anzahl erlaubter Benutzer | `10` |
| `souvera_user_management.allowed_domains` | `array` | Whitelist erlaubter E-Mail-Domains. Leeres Array = alle Domains erlaubt | `[]` |

### Beispiel-Konfigurationen

**Produktionsumgebung (limitiert):**
```php
'souvera_user_management.max_licenses' => 50,
'souvera_user_management.allowed_domains' => ['company.com', 'subsidiary.com'],
```

**Entwicklungsumgebung (unlimitiert):**
```php
'souvera_user_management.max_licenses' => 999,
'souvera_user_management.allowed_domains' => [], // Alle Domains erlaubt
```

**Streng limitiert:**
```php
'souvera_user_management.max_licenses' => 5,
'souvera_user_management.allowed_domains' => ['example.com'],
```

### Konfiguration abrufen

**Via API:**
```bash
curl http://localhost:8080/apps/souvera_user_management/api/config
```

**Response:**
```json
{
  "ocs": {
    "data": {
      "max_licenses": 10,
      "allowed_domains": ["example.com", "test.de"],
      "current_user_count": 3
    }
  }
}
```

**Im Frontend:**
```javascript
const response = await axios.get(generateUrl('/apps/souvera_user_management/api/config'))
const config = response.data.ocs.data

console.log('Max Lizenzen:', config.max_licenses)
console.log('Erlaubte Domains:', config.allowed_domains)
console.log('Aktuelle Benutzer:', config.current_user_count)
```

### Validierung

**Lizenz-Check:**
- Wird bei User-Erstellung geprüft
- `current_user_count >= max_licenses` → HTTP 403 Forbidden

**Domain-Check:**
- Wird bei User-Erstellung geprüft
- Wenn `allowed_domains` leer → alle Domains erlaubt
- Wenn `allowed_domains` gesetzt → nur diese Domains erlaubt
- Case-insensitive Vergleich

**Fehler-Messages:**

Lizenzlimit erreicht:
```json
{
  "error": "Lizenzlimit erreicht. Maximal 10 Benutzer erlaubt."
}
```

Domain nicht erlaubt:
```json
{
  "error": "E-Mail-Domain nicht erlaubt. Erlaubte Domains: example.com, test.de"
}
```

### Konfiguration ändern

1. `config/config.php` bearbeiten
2. Werte anpassen
3. **Kein Reload nötig** - Änderungen sind sofort aktiv

Bei Docker ist die Config typischerweise unter `/var/www/html/config/config.php`