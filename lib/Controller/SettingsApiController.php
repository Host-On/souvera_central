<?php
/**
 * Souvera Central - Settings API Controller
 *
 * API-Endpunkte für App-Einstellungen
 */

namespace OCA\SouveraCentral\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
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
        LoggerInterface $logger
    ) {
        parent::__construct($appName, $request);
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Einstellungen abrufen
     */
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
    public function updateSettings(array $visibility = null, array $sorting = null, array $email = null, array $defaults = null): DataResponse {
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
            }

            // Aktualisierte Einstellungen zurückgeben
            return $this->getSettings();

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
}
