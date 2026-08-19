<?php
/**
 * Souvera Central - Module Registry
 *
 * Verwaltet verfügbare Module und deren Feature-Toggles
 */

namespace OCA\SouveraCentral\Service;

use OCP\IConfig;

class ModuleRegistry {
    private $config;
    private $modules = [];

    public function __construct(IConfig $config) {
        $this->config = $config;
        $this->discoverModules();
    }

    /**
     * Entdeckt alle verfügbaren Module
     */
    private function discoverModules(): void {
        $modulesPath = __DIR__ . '/../Modules';

        if (!is_dir($modulesPath)) {
            return;
        }

        $moduleDirs = scandir($modulesPath);

        foreach ($moduleDirs as $moduleDir) {
            if ($moduleDir === '.' || $moduleDir === '..') {
                continue;
            }

            $moduleJsonPath = $modulesPath . '/' . $moduleDir . '/module.json';

            if (file_exists($moduleJsonPath)) {
                $moduleData = json_decode(file_get_contents($moduleJsonPath), true);
                if ($moduleData) {
                    $this->modules[$moduleData['id']] = $moduleData;
                }
            }
        }
    }

    /**
     * Gibt alle verfügbaren Module zurück
     *
     * @return array
     */
    public function getAllModules(): array {
        return $this->modules;
    }

    /**
     * Gibt alle aktivierten Module zurück
     *
     * @return array
     */
    public function getEnabledModules(): array {
        return array_filter($this->modules, function($module) {
            return $this->isModuleEnabled($module['id']);
        });
    }

    /**
     * Prüft ob ein Modul aktiviert ist
     *
     * @param string $moduleId
     * @return bool
     */
    public function isModuleEnabled(string $moduleId): bool {
        // Standard: Modul ist aktiviert, außer explizit deaktiviert
        $enabled = $this->config->getSystemValue(
            'souvera_central.modules.' . $moduleId . '.enabled',
            true
        );

        return (bool) $enabled;
    }

    /**
     * Holt Modul-spezifische Konfiguration
     *
     * @param string $moduleId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getModuleConfig(string $moduleId, string $key, $default = null) {
        return $this->config->getSystemValue(
            'souvera_central.modules.' . $moduleId . '.' . $key,
            $default
        );
    }

    /**
     * Gibt Modul-Metadaten zurück
     *
     * @param string $moduleId
     * @return array|null
     */
    public function getModule(string $moduleId): ?array {
        return $this->modules[$moduleId] ?? null;
    }
}
