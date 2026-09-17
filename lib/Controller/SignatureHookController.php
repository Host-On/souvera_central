<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\AppInfo\Application;
use OCA\SouveraCentral\Service\HookPayload;
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
 * Vertrag (VERIFIZIERT gegen Stalwart-Doku + Server-Source, siehe
 * HookPayload): Request-JSON mit message.contents/envelope/context,
 * Antwort {"action":"accept","modifications":[{"type":"replaceContents",
 * "value":"<vollständige neue Roh-Mail>"}]} bzw. {"action":"accept"}.
 *
 * Fail-open: JEDE Störung (Central-Down, Resolver-Fehler, MIME-Fehler,
 * Skip-Regel) wird mit {"action":"accept"} (ohne Modifications) und HTTP
 * 200 beantwortet — das Hook darf den Mailfluss nie blockieren.
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
            if (!$this->isHookEnabled()) {
                return new JSONResponse(HookPayload::acceptResponse(null));
            }
            if (!$this->checkSecret()) {
                return new JSONResponse(HookPayload::acceptResponse(null), Http::STATUS_UNAUTHORIZED);
            }

            $payload = $this->readJsonPayload();
            if ($payload === null) {
                return new JSONResponse(HookPayload::acceptResponse(null));
            }

            $raw = HookPayload::parseRawMessage($payload);
            if ($raw === null || $raw === '') {
                return new JSONResponse(HookPayload::acceptResponse(null));
            }

            $sizeLimit = (int) $this->config->getAppValue(Application::APP_ID, self::SIZE_LIMIT_KEY, (string) self::DEFAULT_SIZE_LIMIT);
            if ($sizeLimit > 0 && \strlen($raw) > $sizeLimit) {
                return new JSONResponse(HookPayload::acceptResponse(null));
            }

            $sender = HookPayload::parseSender($payload);
            if ($sender === null || $sender === '') {
                return new JSONResponse(HookPayload::acceptResponse(null));
            }

            // Eigene Skip-Regeln, die den Roh-Text brauchen (Marker-Header).
            $injection = $this->injection;
            if ($injection->isAlreadySigned($raw)) {
                return new JSONResponse(HookPayload::acceptResponse(null));
            }

            $sig = $this->resolver->resolveForEmail($sender);
            if ($sig === null) {
                return new JSONResponse(HookPayload::acceptResponse(null));
            }

            $assets = $this->loadAssets();
            $modified = $injection->inject($raw, $sig, $assets);
            if ($modified === null) {
                return new JSONResponse(HookPayload::acceptResponse(null));
            }

            $this->logger->info('Souvera signature: injected for ' . $sender, ['app' => Application::APP_ID]);
            return new JSONResponse(HookPayload::acceptResponse($modified));
        } catch (\Throwable $e) {
            // Fail-open — niemals den Mailfluss blockieren.
            $this->logger->error('Souvera signature hook failed (fail-open): ' . $e->getMessage(), [
                'app' => Application::APP_ID,
                'exception' => $e,
            ]);
            return new JSONResponse(HookPayload::acceptResponse(null));
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
        if ($provided === '') {
            // Fallback: Secret als Query-Parameter (hook URL: …?secret=…)
            $provided = (string) ($this->request->getParam('secret') ?? '');
        }
        return $expected !== '' && \hash_equals($expected, \trim($provided));
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
            $rows = $this->db->executeQuery(
                'SELECT name, mime, data FROM *PREFIX*souvera_central_sig_assets ORDER BY name ASC'
            )->fetchAll();
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
