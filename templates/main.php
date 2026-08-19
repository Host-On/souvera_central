<?php
/**
 * Souvera Central - Haupt-Template
 *
 * Basis-HTML-Struktur für die Vue.js App
 */

script('souvera_central', 'souvera_central-main');
style('souvera_central', 'main');

// Initial Route und Daten vom Backend
$initialRoute = $_['initialRoute'] ?? 'dashboard';
$action = $_['action'] ?? '';
$userId = $_['userId'] ?? '';
$isSouveraAdmin = !empty($_['isSouveraAdmin']);
?>

<div id="app-souvera-user-management"
     data-initial-route="<?php p($initialRoute); ?>"
     data-action="<?php p($action); ?>"
     data-user-id="<?php p($userId); ?>"
     data-is-souvera-admin="<?php echo $isSouveraAdmin ? '1' : '0'; ?>"
     data-app-version="<?php p(\OC::$server->get(\OCP\App\IAppManager::class)->getAppVersion('souvera_central')); ?>">
    <div id="app-content">
        <div class="loading-container">
            <div class="icon-loading"></div>
            <p><?php p($l->t('Loading Souvera Central...')); ?></p>
        </div>
    </div>
</div>
