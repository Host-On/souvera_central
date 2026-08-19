/**
 * Souvera User Management - Main Entry Point
 *
 * Vue.js 3 Einstiegspunkt für die App
 */

import { createApp } from 'vue'
import App from './App.vue'
import './styles/forms.css'

// Initialisiere Vue App
const app = createApp(App)

// Mount auf #app-souvera-user-management
app.mount('#app-souvera-user-management')
