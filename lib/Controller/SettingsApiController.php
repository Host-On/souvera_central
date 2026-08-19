<?php
/**
 * Souvera Central - Settings API Controller
 *
 * API-Endpunkte für App-Einstellungen
 */

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\MailSignatureDeployService;
use OCP\IRequest;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class SettingsApiController extends OCSController {
    private $config;
    private $logger;

    public function __construct(
        string $appName,
        IRequest $request,
        IConfig $config,
        LoggerInterface $logger,
        private MailSignatureDeployService $signatureDeploy,
    ) {
        parent::__construct($appName, $request);
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Einstellungen abrufen
     */
    #[NoAdminRequired]
    public function getSettings(): DataResponse {
        try {
            $settings = [
                'visibility' => [
                    'manager' => (bool) $this->config->getAppValue('souvera_central', 'settings.visibility.manager', '1'),
                    'groups' => (bool) $this->config->getAppValue('souvera_central', 'settings.visibility.groups', '1'),
                    'storage_location' => (bool) $this->config->getAppValue('souvera_central', 'settings.visibility.storage_location', '0'),
                    'last_login' => (bool) $this->config->getAppValue('souvera_central', 'settings.visibility.last_login', '1'),
                    'email' => (bool) $this->config->getAppValue('souvera_central', 'settings.visibility.email', '1'),
                    'backend' => (bool) $this->config->getAppValue('souvera_central', 'settings.visibility.backend', '0'),
                ],
                'sorting' => [
                    'groups' => $this->config->getAppValue('souvera_central', 'settings.sorting.groups', 'displayName'), // 'id', 'displayName', 'userCount'
                ],
                'email' => [
                    'send_to_new_users' => (bool) $this->config->getAppValue('souvera_central', 'settings.email.send_to_new_users', '0'),
                ],
                'defaults' => [
                    'quota' => $this->config->getAppValue('souvera_central', 'settings.defaults.quota', 'default'),
                ],
                // Souvera Shield: global, wird von der Shield-App via AppConfig ausgelesen
                'shield' => [
                    'desktop_notifications' => (bool) $this->config->getAppValue('souvera_central', 'settings.shield.desktop_notifications', '0'),
                    'daily_summary' => (bool) $this->config->getAppValue('souvera_central', 'settings.shield.daily_summary', '0'),
                    'min_spam_score' => (float) $this->config->getAppValue('souvera_central', 'settings.shield.min_spam_score', '2.5'),
                    'report_hour' => (int) $this->config->getAppValue('souvera_central', 'settings.shield.report_hour', '6'),
                    'pmg_report_disable' => (bool) $this->config->getAppValue('souvera_central', 'settings.shield.pmg_report_disable', '1'),
                    'suspicious_login' => [
                        'detection_enabled' => (bool) $this->config->getAppValue('souvera_central', 'settings.shield.suspicious_login.detection_enabled', '1'),
                        'grace_period_days' => (int) $this->config->getAppValue('souvera_central', 'settings.shield.suspicious_login.grace_period_days', '14'),
                        'score_threshold' => (int) $this->config->getAppValue('souvera_central', 'settings.shield.suspicious_login.score_threshold', '20'),
                        'notify_high_severity' => (bool) $this->config->getAppValue('souvera_central', 'settings.shield.suspicious_login.notify_high_severity', '1'),
                        'notify_critical_severity' => (bool) $this->config->getAppValue('souvera_central', 'settings.shield.suspicious_login.notify_critical_severity', '1'),
                        'retention_days' => (int) $this->config->getAppValue('souvera_central', 'settings.shield.suspicious_login.retention_days', '90'),
                        'retention_resolved_days' => (int) $this->config->getAppValue('souvera_central', 'settings.shield.suspicious_login.retention_resolved_days', '30'),
                        'auto_resolve_after_days' => (int) $this->config->getAppValue('souvera_central', 'settings.shield.suspicious_login.auto_resolve_after_days', '30'),
                        'max_events_per_hour' => (int) $this->config->getAppValue('souvera_central', 'settings.shield.suspicious_login.max_events_per_hour', '10'),
                    ],
                ],
                // Globale Mail-Signatur: EINE Vorlage, von souvera_mail via /api/mail-settings
                // abgefragt; optional serverseitig via Stalwart Sieve erzwungen.
                'signature' => [
                    'enabled' => (bool) $this->config->getAppValue('souvera_central', 'settings.mail_signature.enabled', '0'),
                    'template' => $this->config->getAppValue('souvera_central', 'settings.mail_signature.template', ''),
                    'server_side' => (bool) $this->config->getAppValue('souvera_central', 'settings.mail_signature.server_side', '0'),
                    'variables' => ConfigService::SIGNATURE_VARIABLES,
                ],
                'archive' => [
                    'enabled' => (bool) $this->config->getAppValue('souvera_central', 'archive.enabled', '0'),
                    'retention_years' => (int) $this->config->getAppValue('souvera_central', 'archive.retention_years', '10'),
                    'auto_delete' => (bool) $this->config->getAppValue('souvera_central', 'archive.auto_delete', '0'),
                ],
            ];

            return new DataResponse($settings);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Einstellungen speichern
     */
    #[NoAdminRequired]
    public function updateSettings(array $visibility = null, array $sorting = null, array $email = null, array $defaults = null, array $shield = null, array $signature = null, array $archive = null): DataResponse {
        try {
            // Visibility Settings
            if ($visibility !== null) {
                if (isset($visibility['manager'])) {
                    $this->config->setAppValue('souvera_central', 'settings.visibility.manager', $visibility['manager'] ? '1' : '0');
                }
                if (isset($visibility['groups'])) {
                    $this->config->setAppValue('souvera_central', 'settings.visibility.groups', $visibility['groups'] ? '1' : '0');
                }
                if (isset($visibility['storage_location'])) {
                    $this->config->setAppValue('souvera_central', 'settings.visibility.storage_location', $visibility['storage_location'] ? '1' : '0');
                }
                if (isset($visibility['last_login'])) {
                    $this->config->setAppValue('souvera_central', 'settings.visibility.last_login', $visibility['last_login'] ? '1' : '0');
                }
                if (isset($visibility['email'])) {
                    $this->config->setAppValue('souvera_central', 'settings.visibility.email', $visibility['email'] ? '1' : '0');
                }
                if (isset($visibility['backend'])) {
                    $this->config->setAppValue('souvera_central', 'settings.visibility.backend', $visibility['backend'] ? '1' : '0');
                }
            }

            // Sorting Settings
            if ($sorting !== null) {
                if (isset($sorting['groups'])) {
                    $allowedValues = ['id', 'displayName', 'userCount'];
                    if (in_array($sorting['groups'], $allowedValues)) {
                        $this->config->setAppValue('souvera_central', 'settings.sorting.groups', $sorting['groups']);
                    } else {
                        return new DataResponse(
                            ['error' => 'Ungültiger Sortierungswert'],
                            Http::STATUS_BAD_REQUEST
                        );
                    }
                }
            }

            // Email Settings
            if ($email !== null) {
                if (isset($email['send_to_new_users'])) {
                    $this->config->setAppValue('souvera_central', 'settings.email.send_to_new_users', $email['send_to_new_users'] ? '1' : '0');
                }
            }

            // Default Settings
            if ($defaults !== null) {
                if (isset($defaults['quota'])) {
                    // Validierung des Quota-Werts
                    $quota = trim($defaults['quota']);
                    if ($this->isValidQuota($quota)) {
                        $this->config->setAppValue('souvera_central', 'settings.defaults.quota', $quota);
                    } else {
                        return new DataResponse(
                            ['error' => 'Ungültiger Quota-Wert. Erlaubte Formate: "5 GB", "500 MB", "default", "none"'],
                            Http::STATUS_BAD_REQUEST
                        );
                    }
                }
                // Hinweis: Das Standard-Postfach-Speicherlimit (Stalwart) wird bewusst NICHT über die
                // UI gesetzt, sondern ausschließlich vom Hoster per occ (souvera_central:quota:*).
            }

            // Souvera Shield Settings (global; werden von der Shield-App via AppConfig ausgelesen)
            if ($shield !== null) {
                if (isset($shield['desktop_notifications'])) {
                    $this->config->setAppValue('souvera_central', 'settings.shield.desktop_notifications', $shield['desktop_notifications'] ? '1' : '0');
                }
                if (isset($shield['daily_summary'])) {
                    $this->config->setAppValue('souvera_central', 'settings.shield.daily_summary', $shield['daily_summary'] ? '1' : '0');
                }
                if (array_key_exists('min_spam_score', $shield)) {
                    $score = $this->normalizeSpamScore($shield['min_spam_score']);
                    if ($score === null) {
                        return new DataResponse(
                            ['error' => 'Ungültiger Spam-Score. Erlaubt: 0 bis 10 in 0,5-Schritten.'],
                            Http::STATUS_BAD_REQUEST
                        );
                    }
                    $this->config->setAppValue('souvera_central', 'settings.shield.min_spam_score', (string) $score);
                }
                if (array_key_exists('report_hour', $shield)) {
                    $hour = (int) $shield['report_hour'];
                    if ($hour < 0 || $hour > 23) {
                        return new DataResponse(
                            ['error' => 'Ungültige Sendezeit. Erlaubt: 0 bis 23.'],
                            Http::STATUS_BAD_REQUEST
                        );
                    }
                    $this->config->setAppValue('souvera_central', 'settings.shield.report_hour', (string) $hour);
                }
                if (isset($shield['pmg_report_disable'])) {
                    $this->config->setAppValue('souvera_central', 'settings.shield.pmg_report_disable', $shield['pmg_report_disable'] ? '1' : '0');
                }

                // Suspicious Login Detection settings
                if (isset($shield['suspicious_login']) && is_array($shield['suspicious_login'])) {
                    $sl = $shield['suspicious_login'];
                    if (isset($sl['detection_enabled'])) {
                        $this->config->setAppValue('souvera_central', 'settings.shield.suspicious_login.detection_enabled', $sl['detection_enabled'] ? '1' : '0');
                    }
                    if (isset($sl['grace_period_days'])) {
                        $days = max(1, min(90, (int)$sl['grace_period_days']));
                        $this->config->setAppValue('souvera_central', 'settings.shield.suspicious_login.grace_period_days', (string)$days);
                    }
                    if (isset($sl['score_threshold'])) {
                        $threshold = max(0, min(100, (int)$sl['score_threshold']));
                        $this->config->setAppValue('souvera_central', 'settings.shield.suspicious_login.score_threshold', (string)$threshold);
                    }
                    if (isset($sl['notify_high_severity'])) {
                        $this->config->setAppValue('souvera_central', 'settings.shield.suspicious_login.notify_high_severity', $sl['notify_high_severity'] ? '1' : '0');
                    }
                    if (isset($sl['notify_critical_severity'])) {
                        $this->config->setAppValue('souvera_central', 'settings.shield.suspicious_login.notify_critical_severity', $sl['notify_critical_severity'] ? '1' : '0');
                    }
                    if (isset($sl['retention_days'])) {
                        $days = max(7, min(365, (int)$sl['retention_days']));
                        $this->config->setAppValue('souvera_central', 'settings.shield.suspicious_login.retention_days', (string)$days);
                    }
                    if (isset($sl['retention_resolved_days'])) {
                        $days = max(1, min(365, (int)$sl['retention_resolved_days']));
                        $this->config->setAppValue('souvera_central', 'settings.shield.suspicious_login.retention_resolved_days', (string)$days);
                    }
                    if (isset($sl['auto_resolve_after_days'])) {
                        $days = max(1, min(90, (int)$sl['auto_resolve_after_days']));
                        $this->config->setAppValue('souvera_central', 'settings.shield.suspicious_login.auto_resolve_after_days', (string)$days);
                    }
                    if (isset($sl['max_events_per_hour'])) {
                        $max = max(1, min(100, (int)$sl['max_events_per_hour']));
                        $this->config->setAppValue('souvera_central', 'settings.shield.suspicious_login.max_events_per_hour', (string)$max);
                    }
                }
            }

            // Archive Settings (§2.2 ARCHIVE_PLAN)
            if ($archive !== null) {
                if (isset($archive['enabled'])) {
                    $this->config->setAppValue('souvera_central', 'archive.enabled', $archive['enabled'] ? '1' : '0');
                }
                if (isset($archive['retention_years'])) {
                    $years = max(6, min(15, (int)$archive['retention_years']));
                    $this->config->setAppValue('souvera_central', 'archive.retention_years', (string)$years);
                }
                if (isset($archive['auto_delete'])) {
                    $this->config->setAppValue('souvera_central', 'archive.auto_delete', $archive['auto_delete'] ? '1' : '0');
                }
            }

            // Instanzweite App-Umbenennung (Talk -> "Link", Office/Collabora -> "Desk")
            // ist bewusst FEST und nicht über die UI editierbar (siehe ConfigService).

            // Globale Mail-Signatur (EINE Vorlage; wird von souvera_mail und optional
            // serverseitig via Stalwart Sieve verwendet)
            $signatureDeploy = null;
            if ($signature !== null) {
                if (isset($signature['enabled'])) {
                    $this->config->setAppValue('souvera_central', 'settings.mail_signature.enabled', $signature['enabled'] ? '1' : '0');
                }
                if (array_key_exists('template', $signature)) {
                    $this->config->setAppValue('souvera_central', 'settings.mail_signature.template', (string) $signature['template']);
                }
                if (isset($signature['server_side'])) {
                    $this->config->setAppValue('souvera_central', 'settings.mail_signature.server_side', $signature['server_side'] ? '1' : '0');
                }

                // Serverseitige Signatur automatisch mit Stalwart abgleichen (deploy/remove).
                // Nicht blockierend: Fehler werden geloggt + im Response gemeldet, sperren
                // aber nicht das Speichern der Einstellungen.
                try {
                    $signatureDeploy = $this->signatureDeploy->sync();
                } catch (\Throwable $e) {
                    $this->logger->error('SettingsApiController: Signatur-Deployment fehlgeschlagen', ['exception' => $e->getMessage()]);
                    $signatureDeploy = ['action' => 'sync', 'ok' => false, 'error' => $e->getMessage()];
                }
            }

            // Aktualisierte Einstellungen (+ optionalen Deploy-Status) zurückgeben
            $response = $this->getSettings();
            if ($signatureDeploy !== null) {
                $data = $response->getData();
                if (is_array($data)) {
                    $data['signature_deploy'] = $signatureDeploy;
                    $response->setData($data);
                }
            }
            return $response;
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Validiert Quota-Wert
     */
    private function isValidQuota(string $quota): bool {
        // Erlaube "default", "none"
        if (in_array(strtolower($quota), ['default', 'none'])) {
            return true;
        }

        // Erlaube Zahlen mit Einheiten: "5 GB", "500 MB", "1 TB"
        if (preg_match('/^\d+(\.\d+)?\s?(B|KB|MB|GB|TB)$/i', $quota)) {
            return true;
        }

        // Erlaube reine Zahlen (Bytes)
        if (is_numeric($quota)) {
            return true;
        }

        return false;
    }

    /**
     * Validiert/normalisiert den Spam-Score (0..10, 0,5-Schritte). Liefert null bei ungültig.
     */
    private function normalizeSpamScore($value): ?float {
        if (!is_numeric($value)) {
            return null;
        }
        $score = (float) $value;
        if ($score < 0 || $score > 10) {
            return null;
        }
        // Auf nächste 0,5 runden
        return round($score * 2) / 2;
    }
}
