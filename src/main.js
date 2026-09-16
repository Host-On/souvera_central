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

// l10n: globale t()/n()-Funktionen für ALLE Templates und Komponenten.
// Ohne diese Provision crasht jedes t() in Templates/Methods
// ("e.t is not a function" — Central hat anders als souvera_mail
// keinen globalen t-Mixin). Die Übersetzungen kommen aus den
// NC-L10N-Katalogen (l10n/de.js etc. der App).
app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural

// Mount auf #app-souvera-user-management
app.mount('#app-souvera-user-management')
