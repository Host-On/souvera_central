<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\AppInfo\Application;
use OCA\SouveraCentral\Service\SignatureInjectionService;
use OCA\SouveraCentral\Service\SignatureResolverService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Stalwart MTA-Hook (DATA-Stage): injiziert die zentrale Signatur in
 * ausgehende Mails authentifizierter Absender — VOR der DKIM-Signierung.
 *
 * Vertrag (tolerant, siehe P0-Verifikation):
 *   Request: JSON-Body des Stalwart MTA-Hooks. Erwartet werden (in mehreren
 *   möglichen Shapes, siehe extract*) der authentifizierte Absender
 *   (`authenticated_as`/`sasl_user`/`auth`-Pfad) und die Roh-Nachricht
 *   (`message`/`data`/`raw` — String oder base64).
 *   Antwort: {"replace_body": "<vollständige modifizierte Roh-Mail>"} bzw.
 *   ohne replace_body (= unverändert weiter).
 *
 * Fail-open: JEDE Störung (Central-Down, Resolver-Fehler, MIME-Fehler,
 * Skip-Regel) führt dazu, dass die Mail UNVERÄNDERT weitergeht — das Hook
 * darf den Mailfluss nie blockieren.
 */
class SignatureHookController extends Controller {
    public const HOOK_SECRET_KEY = 'settings.mail_signature.hook_secret';
    public const HOOK_ENABLED_KEY = 'settings.mail_signature.hook_enabled';
    public const SIZE_LIMIT_KEY = 'settings.mail_signature.size_limit';
    private const DEFAULT_SIZE_LIMIT = 10485760;

    public function __construct(
        string $appName,
        IRequest $request,
        private IConfig $config,
        private IDBConnection $db,
        private SignatureResolverService $resolver,
        private SignatureInjectionService $injection,
        private LoggerInterface $logger,
    ) {
        parent::__construct($appName, $request);
    }

    #[PublicPage]
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function hook(): JSONResponse {
        try {
            if (!$this->isHookEnabled() || !$this->checkSecret()) {
                return new JSONResponse(['status' => 'skipped', 'reason' => 'disabled or unauthorized'], Http::STATUS_UNAUTHORIZED);
            }

            $raw = $this->extractRawMessage();
            if ($raw === null || $raw === '') {
                return new JSONResponse(['status' => 'skipped', 'reason' => 'no message in payload']);
            }

            $sizeLimit = (int) $this->config->getAppValue(Application::APP_ID, self::SIZE_LIMIT_KEY, (string) self::DEFAULT_SIZE_LIMIT);
            if ($sizeLimit > 0 && \strlen($raw) > $sizeLimit) {
                return new JSONResponse(['status' => 'skipped', 'reason' => 'size limit']);
            }

            $sender = $this->extractAuthenticatedSender();
            if ($sender === null || $sender === '') {
                return new JSONResponse(['status' => 'skipped', 'reason' => 'no authenticated sender']);
            }

            // Eigene Skip-Regeln, die den Roh-Text brauchen (Marker-Header).
            $injection = $this->injection;
            if ($injection->isAlreadySigned($raw)) {
                return new JSONResponse(['status' => 'skipped', 'reason' => 'already signed']);
            }

            $sig = $this->resolver->resolveForEmail($sender);
            if ($sig === null) {
                return new JSONResponse(['status' => 'skipped', 'reason' => 'no signature for sender']);
            }

            $assets = $this->loadAssets();
            $modified = $injection->inject($raw, $sig, $assets);
            if ($modified === null) {
                return new JSONResponse(['status' => 'skipped', 'reason' => 'injection not applicable']);
            }

            $this->logger->info('Souvera signature: injected for ' . $sender, ['app' => Application::APP_ID]);
            return new JSONResponse(['status' => 'injected', 'replace_body' => $modified]);
        } catch (\Throwable $e) {
            // Fail-open — niemals den Mailfluss blockieren.
            $this->logger->error('Souvera signature hook failed (fail-open): ' . $e->getMessage(), [
                'app' => Application::APP_ID,
                'exception' => $e,
            ]);
            return new JSONResponse(['status' => 'skipped', 'reason' => 'internal error (fail-open)']);
        }
    }

    private function isHookEnabled(): bool {
        return $this->config->getAppValue(Application::APP_ID, self::HOOK_ENABLED_KEY, '0') === '1'
            && $this->config->getAppValue(Application::APP_ID, self::HOOK_SECRET_KEY, '') !== '';
    }

    private function checkSecret(): bool {
        $expected = \trim((string) $this->config->getAppValue(Application::APP_ID, self::HOOK_SECRET_KEY, ''));
        $provided = (string) $this->request->getHeader('Authorization');
        if (\str_starts_with(\strtolower($provided), 'bearer ')) {
            $provided = \substr($provided, 7);
        }
        if ($provided === '') {
            $provided = (string) $this->request->getHeader('X-Souvera-Hook-Secret');
        }
        return $expected !== '' && \hash_equals($expected, \trim($provided));
    }

    /**
     * Tolerante Payload-Auswertung: Stalwarts MTA-Hook-JSON kann je Version
     * unterschiedliche Shapes haben. P0-Verifikation kalibriert die
     * Feldpfade; hier werden die plausiblen Varianten geprüft.
     * @return string|null Roh-Mail (bereits dekodiert)
     */
    private function extractRawMessage(): ?string {
        $payload = $this->readJsonPayload();
        if ($payload === null) {
            return null;
        }

        foreach (['message', 'raw', 'data', 'body'] as $key) {
            $v = $payload[$key] ?? null;
            if (\is_string($v) && $v !== '') {
                // base64-kodierte Roh-Mail erkennen (kein MIME-Header-Anfang).
                if (!\str_contains($v, "\n") && \base64_decode($v, true) !== false
                    && \preg_match('/^[A-Za-z0-9+\/=]+$/', \substr($v, 0, 256)) === 1) {
                    $decoded = \base64_decode($v, true);
                    if ($decoded !== false && $decoded !== '') {
                        return $decoded;
                    }
                }
                return $v;
            }
        }
        // Verschachtelt: payload.data.message o. ä.
        if (isset($payload['data']) && \is_array($payload['data'])) {
            foreach (['message', 'raw', 'body'] as $key) {
                $v = $payload['data'][$key] ?? null;
                if (\is_string($v) && $v !== '') {
                    return $v;
                }
            }
        }
        return null;
    }

    /** Authentifizierter SMTP-Absender aus dem Hook-Payload (tolerant). */
    private function extractAuthenticatedSender(): ?string {
        $payload = $this->readJsonPayload();
        if ($payload === null) {
            return null;
        }
        foreach (['authenticated_as', 'sasl_user', 'authenticated-user', 'auth'] as $key) {
            $v = $payload[$key] ?? null;
            if (\is_string($v) && \str_contains($v, '@')) {
                return \strtolower(\trim(\ltrim($v, '<')));
            }
        }
        if (isset($payload['env']) && \is_array($payload['env'])) {
            foreach (['authenticated_as', 'sasl_user'] as $key) {
                $v = $payload['env'][$key] ?? null;
                if (\is_string($v) && \str_contains($v, '@')) {
                    return \strtolower(\trim($v));
                }
            }
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    private function readJsonPayload(): ?array {
        $body = (string) ($this->request->getRawBody() ?? '');
        if ($body === '') {
            $body = \file_get_contents('php://input') ?: '';
        }
        if ($body === '') {
            return null;
        }
        $decoded = \json_decode($body, true);
        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * Alle Signatur-Assets als Injection-Liste (cid/name/mime/data) — der
     * CID leitet sich deterministisch aus dem Dateinamen ab (souvera-sig-<slug>),
     * identisch zur Slug-Berechnung im SignatureAdminController.
     * @return list<array{cid: string, name: string, mime: string, data: string}>|null
     */
    private function loadAssets(): ?array {
        try {
            $rows = $this->db->fetchAllAssociative(
                'SELECT name, mime, data FROM *PREFIX*souvera_central_sig_assets ORDER BY name ASC'
            );
            $out = [];
            foreach ($rows as $row) {
                $data = (string) ($row['data'] ?? '');
                if ($data === '') { continue; }
                $name = (string) ($row['name'] ?? 'bild');
                $slug = \strtolower(\preg_replace('/[^a-z0-9]+/i', '-', \pathinfo($name, PATHINFO_FILENAME)) ?? 'bild');
                $slug = \trim($slug, '-') ?: 'bild';
                $out[] = [
                    'cid' => 'souvera-sig-' . $slug,
                    'name' => $name,
                    'mime' => (string) ($row['mime'] ?? 'image/png'),
                    'data' => $data,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
