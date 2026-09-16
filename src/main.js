/**
 * Souvera User Management - Main Entry Point
 *
 * Vue.js 3 Einstiegspunkt für die App
 */

import { createApp } from 'vue'
import App from './App.vue'
import './styles/forms.css'
import { translate, translatePlural } from '@nextcloud/l10n'

// Initialisiere Vue App
const app = createApp(App)

// l10n: globale t()-Funktion für ALLE Templates (Options-API-Komponenten
// lösen Template-Expressions über _ctx auf — ohne diesen Eintrag crasht
// jedes t() mit "e.t is not a function").
app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural

// Mount auf #app-souvera-user-management
app.mount('#app-souvera-user-management')
