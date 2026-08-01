/**
 * Souvera Central - Changelog-Seite Entry Point
 *
 * Eigener Webpack-Entry (souvera_central-changelog.js). Die Daten werden
 * vom internen Endpunkt /api/changelogs geladen, der die öffentlichen
 * CloudManager-Endpunkte spiegelt (10-min-Cache).
 */

import { createApp } from 'vue'
import ChangelogApp from './modules/Changelog/ChangelogApp.vue'
import './styles/forms.css'

createApp(ChangelogApp).mount('#souvera-changelog-app')
