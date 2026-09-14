<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Service;

use OCA\SouveraCentral\AppInfo\Application;
use OCP\Accounts\IAccountManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

/**
 * Löst die FINALE, gerenderte Signatur für eine Absender-E-Mail-Adresse auf.
 *
 * Priorität der Templates:
 *   1. aktiver Override für den USER (höchste priority)
 *   2. aktiver Override für eine GRUPPE des Users (höchste priority)
 *   3. globales Template (AppConfig `settings.mail_signature.template`)
 *
 * Variablen-Auflösung je Wert (erste nicht-leere Quelle gewinnt):
 *   1. Admin gepflegtes Zusatzfeld (souvera_central_sig_fields)
 *   2. NC-Profil (IAccountManager: phone, organisation; IUser: displayname/email)
 *   3. Admin-Globaler Fallback (AppConfig `settings.mail_signature.fallbacks` JSON)
 *   4. leerer String
 *
 * Die Auflösung erfolgt EINMAL zentral hier — der Stalwart-Hook und die
 * Webmail-Compose-Injection konsumieren nur das Ergebnis (idiotensicher).
 */
class SignatureResolverService {
    private const CACHE_NAME = 'souvera_signature';
    private const CACHE_TTL = 300;

    /** NC-Profil-Properties, die als Signatur-Variablen genutzt werden. */
    private const PROFILE_MAP = [
        'phone' => IAccountManager::PROPERTY_PHONE,
        'company' => IAccountManager::PROPERTY_ORGANISATION,
    ];

    public function __construct(
        private IConfig $config,
        private IDBConnection $db,
        private IUserManager $userManager,
        private IGroupManager $groupManager,
        private IAccountManager $accountManager,
        private ICacheFactory $cacheFactory,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Finale Signatur für eine Absender-Adresse — oder null, wenn keine
     * aktive Signatur existiert (Hook/Composer lassen die Mail dann
     * unverändert).
     *
     * @return array{html: string, text: string, source: string}|null
     */
    public function resolveForEmail(string $email): ?array {
        $email = \strtolower(\trim($email));
        if ($email === '' || !\str_contains($email, '@')) {
            return null;
        }

        $cache = $this->cache();
        $cacheKey = 'resolve_' . \md5($email);
        if ($cache !== null) {
            $cached = $cache->get($cacheKey);
            if (\is_string($cached) && $cached !== '') {
                $decoded = \json_decode($cached, true);
                if (\is_array($decoded)) {
                    return $decoded['html'] !== null ? $decoded : null;
                }
            }
        }

        $resolved = $this->resolveUncached($email);

        if ($cache !== null) {
            $cache->set($cacheKey, \json_encode(['html' => $resolved?->html, 'text' => $resolved?->text, 'source' => $resolved?->source], JSON_UNESCAPED_UNICODE), self::CACHE_TTL);
        }
        return $resolved;
    }

    private function resolveUncached(string $email): ?array {
        $matches = $this->userManager->getByEmail($email);
        $user = $matches[0] ?? null;
        if ($user === null) {
            return null;
        }

        $template = $this->resolveTemplate($user);
        if ($template === null || \trim((string) ($template['html'] ?? '')) === '') {
            return null;
        }

        $vars = $this->buildVariables($user, $email);

        $html = \strtr((string) $template['html'], $vars);
        $text = (string) ($template['text'] ?? '');
        if (\trim($text) !== '') {
            $text = \strtr($text, $vars);
        } else {
            $text = $this->htmlToText($html);
        }

        return ['html' => $html, 'text' => $text, 'source' => (string) ($template['source'] ?? 'global')];
    }

    /**
     * Template-Priorität: User-Override → Gruppen-Override → global.
     * @return array{html: string, text: string, source: string}|null
     */
    private function resolveTemplate(IUser $user): ?array {
        try {
            $row = $this->db->executeQuery(
                'SELECT html, text FROM *PREFIX*souvera_central_sig_overrides'
                . ' WHERE active = 1 AND scope = ? AND scope_value = ?'
                . ' ORDER BY priority ASC, id DESC LIMIT 1',
                ['user', $user->getUID()]
            )->fetchAssociative();
            if (\is_array($row) && \trim((string) ($row['html'] ?? '')) !== '') {
                return ['html' => (string) $row['html'], 'text' => (string) ($row['text'] ?? ''), 'source' => 'user:' . $user->getUID()];
            }

            $gids = \array_values(\array_diff($this->groupManager->getUserGroupIds($user), ['admin', 'users']));
            if ($gids !== []) {
                $placeholders = \implode(',', \array_fill(0, \count($gids), '?'));
                $row = $this->db->executeQuery(
                    'SELECT html, text, scope_value FROM *PREFIX*souvera_central_sig_overrides'
                    . ' WHERE active = 1 AND scope = ? AND scope_value IN (' . $placeholders . ')'
                    . ' ORDER BY priority ASC, id DESC LIMIT 1',
                    \array_merge(['group'], $gids)
                )->fetchAssociative();
                if (\is_array($row) && \trim((string) ($row['html'] ?? '')) !== '') {
                    return ['html' => (string) $row['html'], 'text' => (string) ($row['text'] ?? ''), 'source' => 'group:' . (string) $row['scope_value']];
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Souvera signature: override lookup failed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
        }

        if ($this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.enabled', '0') === '1') {
            $global = (string) $this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.template', '');
            if (\trim($global) !== '') {
                return ['html' => $global, 'text' => '', 'source' => 'global'];
            }
        }
        return null;
    }

    /**
     * Variablen-Tabelle für strtr(). Erste nicht-leere Quelle pro Variable:
     * Zusatzfeld → NC-Profil → Admin-Fallback.
     * @return array<string, string>
     */
    private function buildVariables(IUser $user, string $email): array {
        $uid = $user->getUID();
        $displayName = (string) $user->getDisplayName();
        $first = '';
        $last = '';
        $parts = \preg_split('/\s+/u', \trim($displayName)) ?: [];
        if (\count($parts) >= 2) {
            $first = (string) \array_shift($parts);
            $last = \implode(' ', $parts);
        } elseif (\count($parts) === 1) {
            $last = $parts[0];
        }

        $domain = \substr($email, \strrpos($email, '@') + 1);

        // Admin-Fallbacks (JSON aus AppConfig).
        $fallbacks = \json_decode(
            (string) $this->config->getAppValue(Application::APP_ID, 'settings.mail_signature.fallbacks', '{}'),
            true
        );
        $fallbacks = \is_array($fallbacks) ? $fallbacks : [];

        // Admin gepflegte Zusatzfelder.
        $custom = [];
        try {
            $rows = $this->db->executeQuery(
                'SELECT field_key, field_value FROM *PREFIX*souvera_central_sig_fields WHERE uid = ?',
                [$uid]
            )->fetchAllAssociative();
            foreach ($rows as $row) {
                $custom[(string) $row['field_key']] = (string) ($row['field_value'] ?? '');
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Souvera signature: custom fields lookup failed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
        }

        // NC-Profil (IAccountManager).
        $profile = [];
        try {
            $account = $this->accountManager->getAccount($user);
            foreach (self::PROFILE_MAP as $var => $property) {
                $profile[$var] = \trim($account->getProperty($property)->getValue());
            }
        } catch (\Throwable $e) {
            $this->logger->debug('Souvera signature: profile lookup failed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
        }

        $pick = static function (string $key) use ($custom, $profile, $fallbacks): string {
            foreach ([$custom[$key] ?? '', $profile[$key] ?? '', $fallbacks[$key] ?? ''] as $v) {
                $v = \trim((string) $v);
                if ($v !== '') {
                    return $v;
                }
            }
            return '';
        };

        return [
            '%name%' => $displayName,
            '%first_name%' => $first,
            '%last_name%' => $last,
            '%email%' => $email,
            '%domain%' => $domain,
            '%title%' => $pick('title'),
            '%department%' => $pick('department'),
            '%phone%' => $pick('phone'),
            '%company%' => $pick('company'),
        ];
    }

    /** Grobe HTML→Text-Fallback-Konvertierung (keine Vollständigkeit nötig). */
    private function htmlToText(string $html): string {
        $html = \preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = \preg_replace('/<\/(p|div|tr|h[1-6])>/i', "\n", $html) ?? $html;
        $text = \strip_tags($html);
        $text = \html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = \preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = \preg_replace('/\n{3,}/', "\n\n", \trim($text)) ?? \trim($text);
        return $text;
    }

    /** Cache leeren (nach Admin-Änderungen an Templates/Overrides/Fallbacks). */
    public function clearCache(): void {
        $this->cache()?->clear();
    }

    private function cache(): ?ICache {
        try {
            return $this->cacheFactory->createLocal(self::CACHE_NAME);
        } catch (\Throwable $e) {
            $this->logger->debug('Souvera signature: cache unavailable: ' . $e->getMessage(), ['app' => Application::APP_ID]);
            return null;
        }
    }
}
