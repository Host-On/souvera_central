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
    ) {
        parent::__construct($appName, $request);
    }

    /** Übersicht für die Admin-UI. */
    #[NoAdminRequired]
    public function overview(): DataResponse {
        return new DataResponse([
            'globalEnabled' => $this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.enabled', '0') === '1',
            'globalTemplate' => (string) $this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.template', ''),
            'variables' => ['%name%', '%first_name%', '%last_name%', '%email%', '%domain%', '%title%', '%department%', '%phone%', '%company%'],
            'fallbacks' => $this->getFallbacks(),
            'overrides' => $this->getOverrides(),
            'logo' => $this->getLogoInfo(),
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

    /** Logo hochladen (multipart/form-data, Feld "logo"). */
    #[NoAdminRequired]
    public function uploadLogo(): DataResponse {
        $file = $this->request->getUploadedFile('logo');
        if ($file === null || !isset($file['tmp_name']) || !\is_uploaded_file($file['tmp_name'])) {
            return new DataResponse(['error' => 'Keine Datei übertragen'], Http::STATUS_BAD_REQUEST);
        }
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg'];
        $finfo = \function_exists('finfo_open')
            ? \finfo_buffer(\finfo_open(FILEINFO_MIME_TYPE), (string) \file_get_contents($file['tmp_name']))
            : (string) ($file['type'] ?? '');
        if ($finfo === false || !isset($allowed[$finfo])) {
            return new DataResponse(['error' => 'Nur PNG/JPG/SVG erlaubt (erkannt: ' . $finfo . ')'], Http::STATUS_BAD_REQUEST);
        }
        $data = (string) \file_get_contents($file['tmp_name']);
        if (\strlen($data) > 512 * 1024) {
            return new DataResponse(['error' => 'Logo zu groß (max. 512 KB)'], Http::STATUS_BAD_REQUEST);
        }
        $name = \basename((string) ($file['name'] ?? 'logo.png'));
        $this->db->executeStatement(
            'DELETE FROM *PREFIX*souvera_central_sig_assets'
        );
        $this->db->executeStatement(
            'INSERT INTO *PREFIX*souvera_central_sig_assets (name, mime, data) VALUES (?, ?, ?)',
            [$name, $finfo, $data]
        );
        $this->resolver->clearCache();
        return new DataResponse(['success' => true, 'logo' => $this->getLogoInfo()]);
    }

    #[NoAdminRequired]
    public function deleteLogo(): DataResponse {
        $this->db->executeStatement('DELETE FROM *PREFIX*souvera_central_sig_assets');
        $this->resolver->clearCache();
        return new DataResponse(['success' => true]);
    }

    /** Logo-Bytes für die Admin-Vorschau (Admin-Session). */
    #[NoAdminRequired]
    public function logoBytes(): \OCP\AppFramework\Http\DataDownloadResponse {
        $row = $this->db->fetchAssociative('SELECT name, mime, data FROM *PREFIX*souvera_central_sig_assets LIMIT 1');
        if (!\is_array($row) || !isset($row['data'])) {
            return new \OCP\AppFramework\Http\DataDownloadResponse('', 'logo.png', 'image/png');
        }
        return new \OCP\AppFramework\Http\DataDownloadResponse((string) $row['data'], (string) ($row['name'] ?? 'logo.png'), (string) ($row['mime'] ?? 'image/png'));
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

    /** @return array{name: string, mime: string, size: int}|null */
    private function getLogoInfo(): ?array {
        $row = $this->db->fetchAssociative('SELECT name, mime, LENGTH(data) AS size FROM *PREFIX*souvera_central_sig_assets LIMIT 1');
        if (!\is_array($row)) {
            return null;
        }
        return ['name' => (string) ($row['name'] ?? ''), 'mime' => (string) ($row['mime'] ?? ''), 'size' => (int) ($row['size'] ?? 0)];
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array {
        $raw = \file_get_contents('php://input') ?: '';
        $decoded = \json_decode($raw, true);
        return \is_array($decoded) ? $decoded : [];
    }
}
