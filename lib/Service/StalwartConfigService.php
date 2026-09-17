<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Service;

/**
 * Verkabelt den zentralen Signatur-MTA-Hook VOLLAUTOMATISCH in die
 * Stalwart-Konfiguration der Cloud — über die Stalwart-Management-API
 * (Basic-Auth mit den Stalwart-Admin-Credentials aus der AppConfig,
 * dieselben wie StalwartService).
 *
 * Mechanismus (VERIFIZIERT gegen den WebAdmin-Source stalwartlabs/webadmin —
 * src/core/http.rs, src/pages/config/{edit,mod}.rs — und die Server-Source
 * crates/smtp/src/inbound/hooks + schema.json):
 *
 *   - Hooks sind Schema-Records unter dem Prefix `session.hook.<id>` mit
 *     kebab-case-Keys (url, enable, timeout, allow-invalid-certs,
 *     options.tempfail-on-error, options.max-response-size, …).
 *   - READ:  GET  /api/settings/list?prefix=… / /api/settings/group?…
 *   - WRITE: POST /api/settings mit UpdateSettings-Operationen
 *            [{type:"insert", prefix, values, assertEmpty}|
 *             {type:"clear", prefix}|{type:"delete", keys}] (camelCase)
 *   - Reload: GET /api/reload/ (best effort)
 *
 * Sicherheitsdesign („nichts kaputt machen"):
 *  - EIGENER NAMESPACE: es wird ausschließlich unter
 *    session.hook.souvera-signature geschrieben — fremde Hooks
 *    (Rspamd/AV/…) und sämtliche anderen Settings bleiben unberührt.
 *  - PRE-CHECK: existiert unser Record schon → Clear+Insert (Update),
 *    sonst Insert mit assertEmpty.
 *  - VERIFY: Read-Back und Soll/Ist-Vergleich nach dem Schreiben.
 *  - Idempotent: erneutes Apply aktualisiert nur unsere Keys.
 *  - Fail-Open: tempfail-on-error=false — ein Hook-Fehler blockiert die
 *    Mailzustellung niemals.
 */
class StalwartConfigService {
    /** AppConfig-Key für das Rollback-Snapshot. */
    public const ROLLBACK_KEY = 'settings.mail_signature.hook_rollback';

    public function __construct(
        private \OCA\SouveraCentral\Service\ConfigService $configService,
        private \OCP\IConfig $appConfig,
        private \Psr\Log\LoggerInterface $logger,
    ) {
    }

    /** Config-ID unseres Signatur-Hooks (eigener Namespace → keine Kollisionen). */
    public const HOOK_ID = 'souvera-signature';
    public const HOOK_PREFIX = 'session.hook.' . self::HOOK_ID;

    /**
     * Die Config-Werte unseres MtaHook-Records (VERIFIZIERT gegen die
     * WebAdmin-Schema-Definition „mta-hooks", prefix session.hook —
     * kebab-case-Keys, alle Werte als String, wie vom Settings-API
     * Insert-Protokoll erwartet).
     *
     * - Secret als ?secret= Query-Parameter in der URL (der Hook-Controller
     *   akzeptiert zusätzlich Bearer/X-Souvera-Hook-Secret-Header).
     * - tempfail-on-error=false → FAIL-OPEN: Ein Hook-Fehler darf die
     *   Mailzustellung nie blockieren.
     */
    public function hookValues(string $hookUrl, string $secret): array {
        return [
            ['url', $hookUrl . '?secret=' . \rawurlencode($secret)],
            ['enable', 'true'],
            ['timeout', '10s'],
            ['allow-invalid-certs', 'false'],
            ['options.tempfail-on-error', 'false'],
            ['options.max-response-size', '10485760'],
        ];
    }

    /**
     * Vollautomatische Verdrahtung über die Stalwart-Management-API
     * (VERIFIZIERT gegen den WebAdmin-Source stalwartlabs/webadmin):
     *
     *   1. PRE-CHECK:  GET  /api/settings/list?prefix=session.hook.<id>
     *   2. WRITE:      POST /api/settings
     *                  [{type:"insert", prefix:"session.hook.<id>",
     *                    values:[[key,value],…], assertEmpty:!update}]
     *                  (bei Update: vorher {type:"clear", prefix:"…."})
     *   3. RELOAD:     GET  /api/reload/          (best effort)
     *   4. VERIFY:     GET  /api/settings/list…   → Werte vergleichen
     *
     * Wir schreiben ausschließlich unter unserem eigenen Prefix
     * session.hook.souvera-signature — fremde Hooks (Rspamd/AV/…) und
     * sämtliche sonstigen Settings bleiben unberührt.
     *
     * @return array{ok: bool, action: string, mode: string, precheck: array, written: array, rollbackAvailable: bool, error: ?string}
     */
    public function apply(string $hookUrl, string $secret): array {
        $result = ['ok' => false, 'action' => 'apply', 'mode' => 'auto', 'precheck' => [], 'written' => [], 'rollbackAvailable' => false, 'error' => null];

        if ($this->stalwartBase() === null) {
            $result['error'] = 'Stalwart-Admin-Zugang unvollständig (souvera_central.stalwart_api_url / stalwart_admin_user / stalwart_admin_password prüfen)';
            return $result;
        }

        // ---- 1. Pre-Check: existiert unser Hook bereits? ----
        $existing = $this->fetchList(self::HOOK_PREFIX);
        if ($existing === null) {
            $result['error'] = 'Stalwart-Management-API nicht erreichbar [' . ($this->lastHttpError ?? 'unbekannt') . '] — URL/Credentials prüfen (souvera_central.stalwart_api_url / stalwart_admin_user / stalwart_admin_password)';
            return $result;
        }
        $isUpdate = $existing !== [];
        $result['precheck'] = ['hookExisted' => $isUpdate, 'existingKeys' => \array_keys($existing)];

        // ---- 2. Write: Clear (bei Update) + Insert ----
        $values = $this->hookValues($hookUrl, $secret);
        $changes = [];
        if ($isUpdate) {
            $changes[] = ['type' => 'clear', 'prefix' => self::HOOK_PREFIX . '.'];
        }
        $changes[] = [
            'type' => 'insert',
            'prefix' => self::HOOK_PREFIX,
            'values' => $values,
            'assertEmpty' => !$isUpdate,
        ];
        if (!$this->postChanges($changes)) {
            $result['error'] = 'Schreiben der Hook-Konfiguration fehlgeschlagen [' . ($this->lastHttpError ?? 'unbekannt') . '] (POST /api/settings).';
            return $result;
        }
        $result['written'] = [$prefix = self::HOOK_PREFIX => \array_map(static fn ($v) => $v[1], $values)];
        $result['rollbackAvailable'] = true;

        // ---- 3. Reload (best effort — Fehler sind kein Apply-Fehler) ----
        $this->reloadStalwart();

        // ---- 4. Verify: read-back und vergleichen ----
        $after = $this->fetchList(self::HOOK_PREFIX);
        $verifyOk = \is_array($after);
        if ($verifyOk) {
            foreach ($values as [$key, $value]) {
                $read = $after[self::HOOK_PREFIX . '.' . $key] ?? null;
                if ($read !== $value) {
                    $verifyOk = false;
                }
            }
        }
        $result['ok'] = $verifyOk;
        if (!$verifyOk) {
            $result['error'] = 'Verify fehlgeschlagen: die geschriebenen Werte wurden nicht bestätigt.';
        }
        return $result;
    }

    /** Rollback/Unwire: unseren Hook-Prefix komplett leeren (+ Reload). */
    public function rollback(): array {
        $ok = $this->postChanges([['type' => 'clear', 'prefix' => self::HOOK_PREFIX . '.']]);
        if ($ok) {
            $this->reloadStalwart();
            $this->appConfig->deleteAppValue('souvera_central', self::ROLLBACK_KEY);
        }
        return ['ok' => $ok, 'action' => 'rollback', 'error' => $ok ? null : 'Rollback-Write fehlgeschlagen'];
    }

    /**
     * Status: unser Hook-Record + Übersicht der fremden Hooks.
     * Read-Back via GET /api/settings/list (tolerantes Parsing).
     */
    public function status(): array {
        $our = $this->fetchList(self::HOOK_PREFIX);
        if ($our === null) {
            return ['ok' => false, 'error' => 'Stalwart-Management-API nicht erreichbar [' . ($this->lastHttpError ?? 'unbekannt') . '] — souvera_central.stalwart_api_url / stalwart_admin_user / stalwart_admin_password prüfen'];
        }
        $foreign = [];
        $all = $this->fetchGroup('session.hook', 'url');
        foreach (($all ?? []) as $key => $url) {
            // Keys sind "session.hook.<id>[.url]" oder "<id>" je nach Format
            $id = (string) $key;
            if (\str_starts_with($id, 'session.hook.')) {
                $id = \substr($id, \strlen('session.hook.'));
            }
            $id = \rtrim(\explode('.', $id)[0] ?? $id, '.');
            if ($id !== '' && $id !== self::HOOK_ID) {
                $foreign[$id] = (string) $url;
            }
        }
        return [
            'ok' => true,
            'mode' => 'auto',
            'hookConfigured' => $our !== [],
            'signatureHook' => $our,
            'foreignHooks' => $foreign,
            'rollbackAvailable' => $our !== [],
        ];
    }

    // ------------------------------------------------------------------
    // Stalwart-Management-API (verifiziert gegen stalwartlabs/webadmin)
    // ------------------------------------------------------------------

    private function stalwartBase(): ?string {
        $url = $this->configService->getStalwartApiUrl();
        $user = $this->configService->getStalwartAdminUser();
        $pass = $this->configService->getStalwartAdminPassword();
        if ($url === null || $user === null || $pass === null) {
            return null;
        }
        return \rtrim($url, '/');
    }

    /**
     * GET /api/settings/list?prefix=… → Settings-Map des Records.
     * @return array<string, string>|null null = API-Fehler, [] = Record existiert nicht
     */
    private function fetchList(string $prefix): ?array {
        $body = $this->httpGet($this->stalwartBase() . '/api/settings/list?prefix=' . \rawurlencode($prefix));
        if ($body === null) {
            return null;
        }
        $items = $body['items'] ?? $body;
        if (!\is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $k => $v) {
            if ($v !== null && !\is_array($v)) {
                $out[(string) $k] = (string) $v;
            }
        }
        return $out;
    }

    /**
     * GET /api/settings/group?prefix=…&suffix=… → id → value (tolerant).
     * @return array<string, string>|null
     */
    private function fetchGroup(string $prefix, string $suffix): ?array {
        $body = $this->httpGet($this->stalwartBase() . '/api/settings/group?prefix=' . \rawurlencode($prefix) . '&suffix=' . \rawurlencode($suffix));
        if ($body === null) {
            return null;
        }
        $out = [];
        $walk = static function ($items) use (&$walk, &$out): void {
            foreach ($items as $k => $v) {
                if (\is_array($v)) {
                    $walk($v);
                } elseif ($v !== null && $v !== '') {
                    $out[(string) $k] = (string) $v;
                }
            }
        };
        $walk(\is_array($body) ? $body : []);
        return $out;
    }

    /**
     * POST /api/settings — Array von UpdateSettings-Operationen
     * ({type:"insert"|"clear"|"delete", …}, camelCase wie im WebAdmin).
     * @param list<array<string, mixed>> $changes
     */
    private function postChanges(array $changes): bool {
        return $this->httpPost($this->stalwartBase() . '/api/settings', $changes);
    }

    /** Settings-Reload anstoßen (best effort — Fehler werden ignoriert). */
    private function reloadStalwart(): void {
        try {
            $this->httpGet($this->stalwartBase() . '/api/reload/');
        } catch (\Throwable $e) {
            $this->logger->info('StalwartConfigService: reload skipped', ['error' => $e->getMessage()]);
        }
    }

    /** Ursache des letzten HTTP-Fehlers (für präzise UI-Meldungen). */
    private ?string $lastHttpError = null;

    /** Basic-Auth-Header manuell bauen — kolon-sicher im Passwort. */
    private function basicAuthHeader(): string {
        return 'Authorization: Basic ' . \base64_encode(
            (string) $this->configService->getStalwartAdminUser() . ':' . (string) $this->configService->getStalwartAdminPassword()
        );
    }

    private function httpGet(string $url): ?array {
        $this->lastHttpError = null;
        $ch = \curl_init();
        \curl_setopt_array($ch, [
            \CURLOPT_URL => $url,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => 20,
            \CURLOPT_CONNECTTIMEOUT => 8,
            \CURLOPT_HTTPHEADER => ['Accept: application/json', $this->basicAuthHeader()],
            \CURLOPT_SSL_VERIFYPEER => false,
            \CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body = \curl_exec($ch);
        $code = (int) \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $err = \curl_error($ch);
        \curl_close($ch);
        if ($err || $code < 200 || $code >= 300) {
            $this->lastHttpError = ($err !== '' ? \mb_substr($err, 0, 120) : 'HTTP ' . $code);
            $this->logger->warning('StalwartConfigService: GET failed', ['url' => $url, 'code' => $code, 'error' => $err]);
            return null;
        }
        $decoded = \json_decode((string) $body, true);
        return \is_array($decoded) ? $decoded : null;
    }

    private function httpPost(string $url, array $values): bool {
        $this->lastHttpError = null;
        $ch = \curl_init();
        \curl_setopt_array($ch, [
            \CURLOPT_URL => $url,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => 20,
            \CURLOPT_CONNECTTIMEOUT => 8,
            \CURLOPT_CUSTOMREQUEST => 'POST',
            \CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', $this->basicAuthHeader()],
            \CURLOPT_POSTFIELDS => \json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            \CURLOPT_SSL_VERIFYPEER => false,
            \CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        \curl_exec($ch);
        $code = (int) \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $err = \curl_error($ch);
        \curl_close($ch);
        if ($err || $code < 200 || $code >= 300) {
            $this->lastHttpError = ($err !== '' ? \mb_substr($err, 0, 120) : 'HTTP ' . $code);
            $this->logger->warning('StalwartConfigService: POST failed', ['url' => $url, 'code' => $code, 'error' => $err]);
            return false;
        }
        return true;
    }
}
