<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Service;

/**
 * Verkabelt den zentralen Signatur-MTA-Hook AUTOMATISCH in die
 * Stalwart-Konfiguration der Cloud — über die Stalwart-WebAdmin-API
 * (`GET/POST /api/settings`, Basic-Auth mit den Stalwart-Admin-Credentials
 * aus der System-Config, dieselben wie StalwartService).
 *
 * Sicherheitsdesign ("nichts kaputt machen"):
 *  - PRE-CHECK: die Config wird VOR dem Schreiben gelesen. Existiert in
 *    `session.data.hooks` bereits ein FREMDER Hook (z. B. Rspamd/AV) →
 *    Abbruch ohne Schreiboperation, Ist-Zustand wird als Diff ausgegeben.
 *  - MERGE statt Overwrite: es werden ausschließlich die eigene-Hook-Keys
 *    aus dem Template geschrieben; alle anderen Keys (insb. `webhook.*`
 *    — der Push-Benachrichtigungs-Event-Webhook von souvera_mail — und
 *    sämtliche Sieve/AV-Einstellungen) bleiben unberührt.
 *  - ROLLBACK: vor dem Schreiben wird der Ist-Zustand aller betroffenen
 *    Keys in der AppConfig gesichert; --rollback/Unwire stellt ihn wieder her.
 *  - VERIFY: nach dem Schreiben wird die Config neu gelesen und mit den
 *    Soll-Werten verglichen.
 *  - Idempotent: erneutes Apply aktualisiert nur URL/Secret.
 *
 * Die HOOK-KEYS sind bewusst als TEMPLATE isoliert — die exakte Stalwart-
 * Syntax für HTTP-Hooks an der DATA-Stage wird im P0-Live-Test auf
 * host-on.souvera.work kalibriert (eine Stelle, siehe HOOK_TEMPLATE()).
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

    /**
     * Die Hook-Keys, die dieser Service schreibt (Key → Wert).
     * P0-KALIBRIERUNG: passt die exakte Stalwart-Syntax an.
     * @return array<string, string>
     */
    public function hookTemplate(string $hookUrl, string $secret): array {
        // Stalwart SMTP-Hooks an der DATA-Stage: Liste von HTTP-Hook-URLs.
        // Der Secret wird als Query-Parameter transportiert (Stalwart-
        // HTTP-Hooks unterstützen keine Auth-Header-Konfiguration pro Hook;
        // die URL ist das Secret-Träger-Element — der Hook-Controller prüft
        // zusätzlich Bearer/X-Souvera-Hook-Secret, siehe Controller).
        return [
            'session.data.hooks' => \json_encode([$hookUrl . '?secret=' . $secret]),
        ];
    }

    /**
     * Vollständiger Apply-Flow: Pre-Check → Snapshot → Write → Verify.
     * @return array{ok: bool, action: string, precheck: array, written: array, rollbackAvailable: bool, error: ?string}
     */
    public function apply(string $hookUrl, string $secret): array {
        $result = ['ok' => false, 'action' => 'apply', 'precheck' => [], 'written' => [], 'rollbackAvailable' => false, 'error' => null];

        $config = $this->fetchConfig();
        if ($config === null) {
            $result['error'] = 'Stalwart-Config-API nicht erreichbar oder Credentials unvollständig (souvera_central.stalwart_api_url / stalwart_admin_user / stalwart_admin_password prüfen)';
            return $result;
        }

        // ---- Pre-Check: bestehende Hooks + Push-Webhook-Übersicht ----
        $existing = $this->collectKeys($config, 'session.data.hooks');
        $foreign = [];
        foreach ($existing as $key => $value) {
            if (\str_contains((string) $value, '/signature/hook')) {
                continue; // unser eigener Eintrag (Re-Apply)
            }
            if (\trim((string) $value) !== '' && $value !== null) {
                $foreign[$key] = (string) $value;
            }
        }
        $result['precheck'] = [
            'existingHooks' => $existing,
            'foreignHooks' => $foreign,
            'webhookKeys' => \array_keys($this->collectKeys($config, 'webhook')),
        ];

        if ($foreign !== []) {
            // Kollision: fremder Hook in derselben Stage → NIEMALS überschreiben.
            $result['error'] = 'Kollision: in session.data.hooks existiert bereits ein fremder Hook. Manuelle Prüfung erforderlich — es wurde NICHTS geschrieben.';
            return $result;
        }

        // ---- Snapshot für Rollback ----
        $snapshot = [];
        foreach (\array_keys($this->hookTemplate($hookUrl, $secret)) as $key) {
            $snapshot[$key] = $config[$key] ?? null;
        }
        $this->appConfig->setAppValue('souvera_central', self::ROLLBACK_KEY, \json_encode([
            'keys' => $snapshot,
            'at' => \time(),
        ]));
        $result['rollbackAvailable'] = true;

        // ---- Write (nur die eigenen Keys) ----
        $write = $this->hookTemplate($hookUrl, $secret);
        if (!$this->writeConfig($write)) {
            $result['error'] = 'Schreiben der Stalwart-Config fehlgeschlagen (WebAdmin-API). Rollback möglich.';
            return $result;
        }
        $result['written'] = $write;

        // ---- Verify: read-back ----
        $after = $this->fetchConfig();
        $verifyOk = true;
        if ($after === null) {
            $verifyOk = false;
        } else {
            foreach ($write as $key => $value) {
                if (($after[$key] ?? null) !== $value) {
                    $verifyOk = false;
                }
            }
        }
        $result['ok'] = $verifyOk;
        if (!$verifyOk) {
            $result['error'] = 'Verify fehlgeschlagen: die geschriebenen Werte wurden nicht bestätigt (Key-Format ggf. falsch — P0-Kalibrierung an HOOK_TEMPLATE()). Rollback möglich.';
        }
        return $result;
    }

    /** Rollback auf den Snapshot vor dem letzten Apply. */
    public function rollback(): array {
        $raw = $this->appConfig->getAppValue('souvera_central', self::ROLLBACK_KEY, '');
        $snapshot = \json_decode($raw, true);
        if (!\is_array($snapshot) || !isset($snapshot['keys']) || !\is_array($snapshot['keys'])) {
            return ['ok' => false, 'action' => 'rollback', 'error' => 'Kein Rollback-Snapshot vorhanden'];
        }

        // Keys, die im Snapshot null waren, aus der Config entfernen (leerer
        // Wert schreiben löscht in der Stalwart-Settings-API).
        $write = [];
        foreach ($snapshot['keys'] as $key => $value) {
            $write[$key] = $value === null ? '' : (string) $value;
        }
        $ok = $this->writeConfig($write);
        if ($ok) {
            $this->appConfig->deleteAppValue('souvera_central', self::ROLLBACK_KEY);
        }
        return ['ok' => $ok, 'action' => 'rollback', 'error' => $ok ? null : 'Rollback-Write fehlgeschlagen'];
    }

    /** Status: Hook-Keys + Push-Webhook-Keys aus der Live-Config. */
    public function status(): array {
        $config = $this->fetchConfig();
        if ($config === null) {
            return ['ok' => false, 'error' => 'Config-API nicht erreichbar'];
        }
        return [
            'ok' => true,
            'signatureHook' => $this->collectKeys($config, 'session.data.hooks'),
            'webhookKeys' => \array_keys($this->collectKeys($config, 'webhook')),
            'rollbackAvailable' => $this->appConfig->getAppValue('souvera_central', self::ROLLBACK_KEY, '') !== '',
        ];
    }

    // ------------------------------------------------------------------
    // WebAdmin-API
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

    private function fetchConfig(): ?array {
        $base = $this->stalwartBase();
        if ($base === null) {
            return null;
        }
        $response = $this->httpGet($base . '/api/settings');
        if ($response === null || !\is_array($response)) {
            return null;
        }
        return $response;
    }

    private function writeConfig(array $values): bool {
        $base = $this->stalwartBase();
        if ($base === null) {
            return false;
        }
        $response = $this->httpPost($base . '/api/settings', $values);
        return $response !== null;
    }

    /** @return array<string, string> */
    private function collectKeys(array $config, string $prefix): array {
        $out = [];
        foreach ($config as $key => $value) {
            if (\str_starts_with((string) $key, $prefix)) {
                $out[(string) $key] = \is_string($value) ? $value : \json_encode($value);
            }
        }
        return $out;
    }

    private function httpGet(string $url): ?array {
        $ch = \curl_init();
        \curl_setopt_array($ch, [
            \CURLOPT_URL => $url,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => 20,
            \CURLOPT_HTTPHEADER => ['Accept: application/json'],
            \CURLOPT_USERPWD => $this->configService->getStalwartAdminUser() . ':' . $this->configService->getStalwartAdminPassword(),
            \CURLOPT_SSL_VERIFYPEER => false,
            \CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body = \curl_exec($ch);
        $code = (int) \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $err = \curl_error($ch);
        \curl_close($ch);
        if ($err || $code < 200 || $code >= 300) {
            $this->logger->warning('StalwartConfigService: GET settings failed', ['code' => $code, 'error' => $err]);
            return null;
        }
        $decoded = \json_decode((string) $body, true);
        return \is_array($decoded) ? $decoded : null;
    }

    private function httpPost(string $url, array $values): bool {
        $ch = \curl_init();
        \curl_setopt_array($ch, [
            \CURLOPT_URL => $url,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => 20,
            \CURLOPT_CUSTOMREQUEST => 'POST',
            \CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            \CURLOPT_USERPWD => $this->configService->getStalwartAdminUser() . ':' . $this->configService->getStalwartAdminPassword(),
            \CURLOPT_POSTFIELDS => \json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            \CURLOPT_SSL_VERIFYPEER => false,
            \CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        \curl_exec($ch);
        $code = (int) \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $err = \curl_error($ch);
        \curl_close($ch);
        if ($err || $code < 200 || $code >= 300) {
            $this->logger->warning('StalwartConfigService: POST settings failed', ['code' => $code, 'error' => $err]);
            return false;
        }
        return true;
    }
}
