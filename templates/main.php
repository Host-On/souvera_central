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
?>

<div id="app-souvera-user-management"
     data-initial-route="<?php p($initialRoute); ?>"
     data-action="<?php p($action); ?>"
     data-user-id="<?php p($userId); ?>">
    <div id="app-content">
        <div class="loading-container">
            <div class="icon-loading"></div>
            <p><?php p($l->t('Lade Souvera Central...')); ?></p>
        </div>
    </div>
</div>
