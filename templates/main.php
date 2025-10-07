<?php
/**
 * Souvera User Management - Haupt-Template
 *
 * Basis-HTML-Struktur für die Vue.js App
 */

script('souvera_user_management', 'souvera_user_management-main');
style('souvera_user_management', 'main');
?>

<div id="app-souvera-user-management">
    <div id="app-content">
        <div class="loading-container">
            <div class="icon-loading"></div>
            <p><?php p($l->t('Lade Benutzerverwaltung...')); ?></p>
        </div>
    </div>
</div>
