<?php

declare(strict_types=1);

/**
 * Souvera Central — Admin-API für die zentrale E-Mail-Signatur.
 *
 * Verwaltet alles über dem bestehenden globalen Template (Settings.vue):
 * Overrides (Gruppe/User), Admin-Fallbacks für Variablen, Logo-Asset,
 * Hook-Toggle/-Secret. Der eigentliche Stalwart-Hook lebt in
 * SignatureHookController (PublicPage + Bearer-Secret).
 */

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\AppInfo\Application;
use OCA\SouveraCentral\Service\SignatureInjectionService;
use OCA\SouveraCentral\Service\SignatureResolverService;
use OCA\SouveraCentral\Service\StalwartConfigService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;


class SignatureAdminController extends OCSController {
    private const FALLBACK_KEYS = ['title', 'department', 'phone', 'company'];
    private const FALLBACK_KEY = 'settings.mail_signature.fallbacks';
    private const HOOK_ENABLED_KEY = 'settings.mail_signature.hook_enabled';
    private const HOOK_SECRET_KEY = 'settings.mail_signature.hook_secret';
    private const SIZE_LIMIT_KEY = 'settings.mail_signature.size_limit';

    public function __construct(
        string $appName,
        IRequest $request,
        private IConfig $config,
        private IDBConnection $db,
        private IUserManager $userManager,
        private IGroupManager $groupManager,
        private SignatureResolverService $resolver,
        private SignatureInjectionService $injection,
        private StalwartConfigService $stalwartConfig,
    ) {
        parent::__construct($appName, $request);
    }

    /** Übersicht für die Admin-UI. */
    #[NoAdminRequired]
    public function overview(): DataResponse {
        return new DataResponse([
            'globalEnabled' => $this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.enabled', '0') === '1',
            'globalServerSide' => $this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.server_side', '0') === '1',
            'globalTemplate' => (string) $this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.template', ''),
            'variables' => ['%name%', '%first_name%', '%last_name%', '%email%', '%domain%', '%title%', '%department%', '%phone%', '%company%'],
            'fallbacks' => $this->getFallbacks(),
            'overrides' => $this->getOverrides(),
            'assets' => $this->getAssets(),
            'hook' => [
                'enabled' => $this->config->getAppValue(Application::APP_ID, self::HOOK_ENABLED_KEY, '0') === '1',
                'hasSecret' => $this->config->getAppValue(Application::APP_ID, self::HOOK_SECRET_KEY, '') !== '',
                'sizeLimit' => (int) $this->config->getAppValue(Application::APP_ID, self::SIZE_LIMIT_KEY, (string) 10485760),
            ],
        ]);
    }

    #[NoAdminRequired]
    public function setFallbacks(): DataResponse {
        $body = $this->jsonBody();
        $in = \is_array($body['fallbacks'] ?? null) ? $body['fallbacks'] : [];
        $clean = [];
        foreach (self::FALLBACK_KEYS as $key) {
            $clean[$key] = \trim((string) ($in[$key] ?? ''));
        }
        $this->config->setAppValue(Application::APP_ID, self::FALLBACK_KEY, \json_encode($clean, JSON_UNESCAPED_UNICODE));
        $this->resolver->clearCache();
        return new DataResponse(['success' => true, 'fallbacks' => $clean]);
    }

    #[NoAdminRequired]
    public function listOverrides(): DataResponse {
        return new DataResponse(['overrides' => $this->getOverrides()]);
    }

    #[NoAdminRequired]
    public function saveOverride(): DataResponse {
        $body = $this->jsonBody();
        $id = (int) ($body['id'] ?? 0);
        $scope = \in_array($body['scope'] ?? '', ['group', 'user'], true) ? (string) $body['scope'] : '';
        $scopeValue = \trim((string) ($body['scopeValue'] ?? ''));
        $html = (string) ($body['html'] ?? '');
        $text = (string) ($body['text'] ?? '');
        $priority = \max(1, \min(999, (int) ($body['priority'] ?? 100)));
        $replacePersonal = (bool) ($body['replacePersonal'] ?? true);
        $active = (bool) ($body['active'] ?? true);

        if ($scope === '' || $scopeValue === '' || \trim($html) === '') {
            return new DataResponse(['error' => 'scope, scopeValue und html sind Pflicht'], Http::STATUS_BAD_REQUEST);
        }
        if ($scope === 'group' && !$this->groupManager->groupExists($scopeValue)) {
            return new DataResponse(['error' => 'Gruppe existiert nicht'], Http::STATUS_BAD_REQUEST);
        }
        if ($scope === 'user' && $this->userManager->get($scopeValue) === null) {
            return new DataResponse(['error' => 'User existiert nicht'], Http::STATUS_BAD_REQUEST);
        }

        $now = \time();
        if ($id > 0) {
            $this->db->executeStatement(
                'UPDATE *PREFIX*souvera_central_sig_overrides SET scope = ?, scope_value = ?, html = ?, text = ?, priority = ?, replace_personal = ?, active = ? WHERE id = ?',
                [$scope, $scopeValue, $html, $text, $priority, $replacePersonal ? 1 : 0, $active ? 1 : 0, $id]
            );
        } else {
            $this->db->executeStatement(
                'INSERT INTO *PREFIX*souvera_central_sig_overrides (scope, scope_value, html, text, priority, replace_personal, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$scope, $scopeValue, $html, $text, $priority, $replacePersonal ? 1 : 0, $active ? 1 : 0, $now]
            );
        }
        $this->resolver->clearCache();
        return new DataResponse(['success' => true, 'overrides' => $this->getOverrides()]);
    }

    #[NoAdminRequired]
    public function deleteOverride(int $id): DataResponse {
        $this->db->executeStatement('DELETE FROM *PREFIX*souvera_central_sig_overrides WHERE id = ?', [$id]);
        $this->resolver->clearCache();
        return new DataResponse(['success' => true, 'overrides' => $this->getOverrides()]);
    }

    /**
     * Bilder hochladen (multipart/form-data, Feld "assets", mehrfach möglich).
     * Der CID eines Bildes leitet sich deterministisch aus dem Dateinamen ab:
     * souvera-sig-<slug> — damit kann der Template-Autor direkt referenzieren.
     */
    #[NoAdminRequired]
    public function uploadAssets(): DataResponse {
        $files = $_FILES['assets'] ?? null;
        if (!\is_array($files) || !isset($files['name'])) {
            return new DataResponse(['error' => 'Keine Dateien übertragen (Feld "assets")'], Http::STATUS_BAD_REQUEST);
        }
        // Normalisiere Einzel- zu Multi-Upload-Struktur
        $names = (array) $files['name'];
        $tmps = (array) $files['tmp_name'];
        $allowed = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
        $stored = [];
        $errors = [];
        foreach ($names as $i => $name) {
            $tmp = $tmps[$i] ?? null;
            if ($tmp === null || !\is_uploaded_file($tmp)) { continue; }
            $finfo = \function_exists('finfo_open')
                ? \finfo_buffer(\finfo_open(FILEINFO_MIME_TYPE), (string) \file_get_contents($tmp))
                : 'application/octet-stream';
            if ($finfo === false || !\in_array($finfo, $allowed, true)) {
                $errors[] = $name . ': nur PNG/JPG/SVG/WebP';
                continue;
            }
            $data = (string) \file_get_contents($tmp);
            if (\strlen($data) > 512 * 1024) {
                $errors[] = $name . ': zu groß (max. 512 KB)';
                continue;
            }
            $clean = \basename((string) $name);
            $slug = $this->slugForFilename($clean, $this->existingSlugs());
            $this->db->executeStatement(
                'INSERT INTO *PREFIX*souvera_central_sig_assets (name, mime, data) VALUES (?, ?, ?)',
                [$clean, $finfo, $data]
            );
            $stored[] = $slug;
        }
        if ($stored === [] && $errors === []) {
            return new DataResponse(['error' => 'Keine gültigen Dateien'], Http::STATUS_BAD_REQUEST);
        }
        $this->resolver->clearCache();
        $out = ['success' => true, 'stored' => $stored, 'assets' => $this->getAssets()];
        if ($errors !== []) { $out['errors'] = $errors; }
        return new DataResponse($out);
    }

    #[NoAdminRequired]
    public function deleteAsset(string $slug): DataResponse {
        $name = $this->nameForSlug($slug);
        if ($name === null) {
            return new DataResponse(['error' => 'Asset nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $this->db->executeStatement('DELETE FROM *PREFIX*souvera_central_sig_assets WHERE name = ?', [$name]);
        $this->resolver->clearCache();
        return new DataResponse(['success' => true]);
    }

    /** Asset-Bytes für die Vorschau (Admin-Session), per Slug. */
    #[NoAdminRequired]
    public function assetBytes(string $slug): \OCP\AppFramework\Http\DataDownloadResponse {
        $row = $this->assetRowBySlug($slug);
        if ($row === null) {
            return new \OCP\AppFramework\Http\DataDownloadResponse('', $slug, 'image/png');
        }
        return new \OCP\AppFramework\Http\DataDownloadResponse((string) $row['data'], (string) $row['name'], (string) $row['mime']);
    }

    #[NoAdminRequired]
    public function setHook(): DataResponse {
        $body = $this->jsonBody();
        if (isset($body['enabled'])) {
            $this->config->setAppValue(Application::APP_ID, self::HOOK_ENABLED_KEY, ((bool) $body['enabled']) ? '1' : '0');
        }
        if (isset($body['sizeLimit'])) {
            $size = \max(0, (int) $body['sizeLimit']);
            $this->config->setAppValue(Application::APP_ID, self::SIZE_LIMIT_KEY, (string) $size);
        }
        return new DataResponse(['success' => true, 'hook' => [
            'enabled' => $this->config->getAppValue(Application::APP_ID, self::HOOK_ENABLED_KEY, '0') === '1',
            'hasSecret' => $this->config->getAppValue(Application::APP_ID, self::HOOK_SECRET_KEY, '') !== '',
            'sizeLimit' => (int) $this->config->getAppValue(Application::APP_ID, self::SIZE_LIMIT_KEY, (string) 10485760),
        ]]);
    }

    /** Neues Hook-Secret generieren. */
    #[NoAdminRequired]
    public function rotateSecret(): DataResponse {
        $secret = \bin2hex(\random_bytes(32));
        $this->config->setAppValue(Application::APP_ID, self::HOOK_SECRET_KEY, $secret);
        return new DataResponse(['success' => true, 'secret' => $secret]);
    }

    /** Live-Vorschau: finale Signatur für eine E-Mail-Adresse. */
    #[NoAdminRequired]
    public function resolveTest(string $email): DataResponse {
        $sig = $this->resolver->resolveForEmail($email);
        if ($sig === null) {
            return new DataResponse(['found' => false]);
        }
        return new DataResponse(['found' => true, 'html' => $sig['html'], 'text' => $sig['text'], 'source' => $sig['source']]);
    }

    /**
     * Stalwart-Verkabelung AUTOMATISCH anwenden (Pre-Check → Snapshot →
     * Write → Verify). Kollisionen führen zu einem Abbruch OHNE Schreiben.
     */
    #[NoAdminRequired]
    public function wireStalwart(): DataResponse {
        $hookUrl = $this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.hook_url', '');
        $secret = $this->config->getAppValue(Application::APP_ID, self::HOOK_SECRET_KEY, '');
        if ($secret === '') {
            return new DataResponse(['error' => 'Kein Hook-Secret vorhanden — erst generieren'], Http::STATUS_BAD_REQUEST);
        }
        if ($hookUrl === '') {
            // URL aus der Cloud-Instanz ableiten (Central-Basis + Pfad).
            $base = $this->request->getServerProtocol() . '://' . ($this->request->getServerHost() ?: '');
            $hookUrl = \rtrim($base, '/') . '/apps/souvera_central/signature/hook';
        }
        $result = $this->stalwartConfig->apply($hookUrl, $secret);
        return new DataResponse($result, $result['ok'] ? Http::STATUS_OK : Http::STATUS_BAD_REQUEST);
    }

    /** Rollback der letzten Stalwart-Verkabelung. */
    #[NoAdminRequired]
    public function unwireStalwart(): DataResponse {
        $result = $this->stalwartConfig->rollback();
        return new DataResponse($result, $result['ok'] ? Http::STATUS_OK : Http::STATUS_BAD_REQUEST);
    }

    /** Stalwart-Hook-Status (eigene Keys + Push-Webhook-Keys als Übersicht). */
    #[NoAdminRequired]
    public function stalwartStatus(): DataResponse {
        return new DataResponse($this->stalwartConfig->status());
    }

    // resolveSelf/logo-für-User leben in MailSettingsApiController — dieser
    // Controller hier ist per SouveraAdminMiddleware Admin-only, die
    // Webmail-Compose-Auflösung braucht aber jede angemeldete User-Session.

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return array<string, string> */
    private function getFallbacks(): array {
        $decoded = \json_decode(
            (string) $this->config->getAppValue(Application::APP_ID, self::FALLBACK_KEY, '{}'),
            true
        );
        $decoded = \is_array($decoded) ? $decoded : [];
        $out = [];
        foreach (self::FALLBACK_KEYS as $key) {
            $out[$key] = (string) ($decoded[$key] ?? '');
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function getOverrides(): array {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, scope, scope_value, html, text, priority, replace_personal, active FROM *PREFIX*souvera_central_sig_overrides ORDER BY priority ASC, id DESC'
        );
        return \array_map(static function (array $r): array {
            return [
                'id' => (int) $r['id'],
                'scope' => (string) $r['scope'],
                'scopeValue' => (string) $r['scope_value'],
                'html' => (string) ($r['html'] ?? ''),
                'text' => (string) ($r['text'] ?? ''),
                'priority' => (int) $r['priority'],
                'replacePersonal' => ((int) $r['replace_personal']) === 1,
                'active' => ((int) $r['active']) === 1,
            ];
        }, $rows);
    }

    /**
     * Alle Assets mit deterministischem CID (souvera-sig-<slug>).
     * @return list<array{name: string, slug: string, mime: string, size: int, cid: string}>
     */
    private function getAssets(): array {
        $rows = $this->db->fetchAllAssociative(
            'SELECT name, mime, LENGTH(data) AS size FROM *PREFIX*souvera_central_sig_assets ORDER BY name ASC'
        );
        $out = [];
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            $slug = self::slugForFilename($name, null);
            $out[] = [
                'name' => $name,
                'slug' => $slug,
                'mime' => (string) ($row['mime'] ?? 'image/png'),
                'size' => (int) ($row['size'] ?? 0),
                'cid' => 'souvera-sig-' . $slug,
            ];
        }
        return $out;
    }

    /** Slug aus dem Dateinamen (kleingeschrieben, nicht-alphanumerisch → Bindestrich). */
    private static function slugForFilename(string $name, ?array $taken): string {
        $base = \pathinfo($name, PATHINFO_FILENAME);
        $slug = \strtolower(\preg_replace('/[^a-z0-9]+/i', '-', $base) ?? 'bild');
        $slug = \trim($slug, '-') ?: 'bild';
        if ($taken === null || !\in_array($slug, $taken, true)) {
            return $slug;
        }
        $n = 2;
        while (\in_array($slug . '-' . $n, $taken, true)) { $n++; }
        return $slug . '-' . $n;
    }

    private function existingSlugs(): array {
        $out = [];
        foreach ($this->getAssets() as $a) {
            $out[] = $a['slug'];
        }
        return $out;
    }

    private function assetRowBySlug(string $slug): ?array {
        foreach ($this->getAssets() as $a) {
            if ($a['slug'] === $slug) {
                $row = $this->db->fetchAssociative(
                    'SELECT name, mime, data FROM *PREFIX*souvera_central_sig_assets WHERE name = ?',
                    [$a['name']]
                );
                return \is_array($row) ? $row : null;
            }
        }
        return null;
    }

    private function nameForSlug(string $slug): ?string {
        foreach ($this->getAssets() as $a) {
            if ($a['slug'] === $slug) {
                return $a['name'];
            }
        }
        return null;
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array {
        $raw = \file_get_contents('php://input') ?: '';
        $decoded = \json_decode($raw, true);
        return \is_array($decoded) ? $decoded : [];
    }
}
