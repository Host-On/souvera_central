# Souvera AI – Interne Wissensbasis & MCP (Central als Hub)

Central verwaltet die **interne Wissensbasis** für den Nextcloud-Agenten
(Firmeninfos, FAQ, Prozesse …) und stellt sie als **MCP-Endpoint** bereit.
Kein Datei-Sync, keine GitHub-API: der Agent liest live aus der Central-DB.

---

## 1. Verwaltung (Admin, Central-UI „Souvera AI")

- Artikel anlegen/bearbeiten/löschen (Titel + Markdown-Inhalt)
- occ-Ersatz: `souvera_central:ai:enable` / `ai:disable` / `ai:status`
- Startinhalt: die Dateien unter `resources/ai/*.md` werden **einmalig als
  Seed importiert** (Marker `ai.kb.seeded`); danach sind sie nur noch Referenz.

## 2. MCP-Endpoint

```text
POST {NC-URL}/index.php/apps/souvera_central/mcp
Authorization: Bearer <token>
Content-Type: application/json
```

- **Stateless MCP über HTTP** (JSON-RPC 2.0), kein Legacy-SSE.
- Protokollversion: Server akzeptiert die vom Client angeforderte Version,
  Fallback `2026-07-28`.
- **Nur aktiv, wenn die KI-Funktion aktiviert ist** (`ai:enable`) — sonst 403.
- Nur **read-only Tools**; die Pflege bleibt in der Admin-UI/occ.

### Tools

| Tool | Argumente | Ergebnis |
|---|---|---|
| `kb_list` | – | Liste aller Artikel (id, title) |
| `kb_get` | `id` | Volltext eines Artikels (Markdown) |
| `kb_search` | `query`, `limit?` (max 25) | Treffer mit Auszug (Titel + Inhalt, case-insensitive) |

### JSON-RPC-Beispiel

```json
{"jsonrpc":"2.0","id":1,"method":"tools/call",
 "params":{"name":"kb_search","arguments":{"query":"Faelligkeit Rechnung"}}}
```

## 3. Zugriffs-Token (automatisch, verschlüsselt, intern)

- Bei **Aktivierung** der KI erzeugt Central automatisch einen Token und legt
  ihn **verschlüsselt** ab (`OCP\Security\ICrypto`, gebunden ans
  Instanz-Secret). Klartext liegt nie in der DB.
- Rotation: Button im AI-Tab oder
  `POST /api/ai/mcp/rotate` (Admin). Ein Rotieren invalidiert den alten
  Token sofort.
- Deaktivieren der KI sperrt den Endpoint (403); der Token bleibt erhalten.

## 4. Interner Abruf (Shared API)

Der Agent bzw. seine App liest den Token **intern** — der Token verlässt die
Instanz nicht über Konfigurationsdateien:

```php
$token = \OCP\Server::get(\OCA\SouveraCentral\Service\AiMcpTokenService::class)->getToken();
// + Endpoint: \OCP\Server::get(\OCP\IURLGenerator::class)
//   ->linkToRouteAbsolute('souvera_central.mcp.call')
```

Für Apps außerhalb des PHP-Prozesses (ExApp): Token einmalig über
`souvera_central:ai:status --json` … **zeigt bewusst KEINEN Klartext**. Für
diesen Fall den Token per Rotation im Admin-UI neu erzeugen und über den
konfigurierten internen Übergabeweg (AppAPI/Agent-Konfiguration) eintragen.

## 5. Sicherheit

- Bearer-Auth mit constant-time-Vergleich; Token verschlüsselt at rest.
- Endpoint ausdrücklich **read-only** (keine Schreib-/Admin-Tools).
- KB-Inhalte sind Admin-kuratiert; als MCP-Daten ausgeliefert, nicht als
  Systeminstruktion.
