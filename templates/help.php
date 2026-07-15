<?php
/**
 * Souvera Central - Hilfe-Template
 *
 * Mountpunkt für die Hilfe-Vue-App (souvera_central-help.js).
 */

script('souvera_central', 'souvera_central-help');
style('souvera_central', 'main');
?>

<div id="souvera-help-app">
    <div id="app-content">
        <div class="loading-container">
            <div class="icon-loading"></div>
            <p><?php p($l->t('Loading help...')); ?></p>
        </div>
    </div>
</div>
