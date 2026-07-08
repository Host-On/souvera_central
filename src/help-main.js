/**
 * Souvera Central - Hilfe-Seite Entry Point
 *
 * Eigener Webpack-Entry (souvera_central-help.js). Die Hilfe ist - anders als
 * die Verwaltung - für Souvera-User UND Souvera-Admins zugänglich, nutzt aber
 * exakt dasselbe Central-Layout (NcContent + NcAppNavigation + NcAppContent).
 */

import { createApp } from 'vue'
import HelpApp from './modules/Help/HelpApp.vue'
import './styles/forms.css'

createApp(HelpApp).mount('#souvera-help-app')
