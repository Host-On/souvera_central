<?php
/**
 * Souvera Central - Changelog-Template
 *
 * Mountpunkt für die Changelog-Vue-App (souvera_central-changelog.js).
 * Die Daten kommen aus den öffentlichen CloudManager-Endpunkten
 * (vermittelt über /api/changelogs).
 */

script('souvera_central', 'souvera_central-changelog');
style('souvera_central', 'main');
?>

<div id="souvera-changelog-app">
    <div id="app-content">
        <div class="loading-container">
            <div class="icon-loading"></div>
            <p><?php p($l->t('Loading changelog...')); ?></p>
        </div>
    </div>
</div>
