<template>
  <div id="app-souvera-central">
    <!-- Sidebar Navigation -->
    <AppSidebar
      :current-route="currentRoute"
      :user-count="userCount"
      :license-total="licenseTotal"
      @navigate="handleNavigation"
    />

    <!-- Main Content Area -->
    <div class="app-main-content">
      <!-- Dashboard -->
      <Dashboard
        v-if="currentRoute === 'dashboard'"
        :user-count="userCount"
        :active-user-count="activeUserCount"
        :group-count="groupCount"
        :license-total="licenseTotal"
        :allowed-domains="allowedDomains"
        @navigate="handleNavigation"
      />

      <!-- User Management -->
      <UserManagement
        v-else-if="currentRoute === 'users'"
        :key="routeKey"
        :allowed-domains="allowedDomains"
        :license-total="licenseTotal"
        @users-loaded="updateUserCount"
      />

      <!-- Group Management -->
      <GroupManagement
        v-else-if="currentRoute === 'groups'"
      />

      <!-- Settings -->
      <Settings
        v-else-if="currentRoute === 'settings'"
        :license-total="licenseTotal"
        :allowed-domains="allowedDomains"
      />
    </div>
  </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import AppSidebar from './components/AppSidebar.vue'
import Dashboard from './modules/Dashboard/Dashboard.vue'
import UserManagement from './modules/UserManagement/UserManagement.vue'
import GroupManagement from './modules/GroupManagement/GroupManagement.vue'
import Settings from './modules/Settings/Settings.vue'

export default {
  name: 'App',

  components: {
    AppSidebar,
    Dashboard,
    UserManagement,
    GroupManagement,
    Settings
  },

  data() {
    return {
      currentRoute: 'dashboard',
      currentPath: '', // Vollständiger Pfad für routeKey
      userCount: 0,
      activeUserCount: 0,
      groupCount: 0,
      licenseTotal: 10,
      allowedDomains: []
    }
  },

  computed: {
    routeKey() {
      // Key basiert auf vollständigem Pfad, nicht nur Route
      // So wird Component auch bei /users -> /users/new neu gemountet
      return this.currentPath
    }
  },

  watch: {
    currentRoute() {
      // Update currentPath wenn Route sich ändert
      this.updateCurrentPath()
    }
  },

  mounted() {
    this.loadConfig()
    this.loadStats()
    this.initializeRouting()
  },

  beforeUnmount() {
    // Cleanup event listener
    window.removeEventListener('popstate', this.handlePopState)
  },

  methods: {
    t,

    initializeRouting() {
      // Lese initialRoute aus data-Attribut vom Backend
      const appElement = document.getElementById('app-souvera-user-management')
      const initialRoute = appElement?.getAttribute('data-initial-route') || 'dashboard'

      this.currentRoute = initialRoute
      this.updateCurrentPath()

      // Listen for browser back/forward
      window.addEventListener('popstate', this.handlePopState)
    },

    updateCurrentPath() {
      // Speichere vollständigen Pfad für routeKey
      this.currentPath = window.location.pathname
    },

    handlePopState() {
      // Extract route from URL path
      const path = window.location.pathname
      const match = path.match(/\/apps\/souvera_central\/(dashboard|users|groups|settings)/)

      if (match && match[1]) {
        this.currentRoute = match[1]
      } else {
        this.currentRoute = 'dashboard'
      }

      this.updateCurrentPath()
    },

    handleNavigation(route) {
      this.currentRoute = route

      // Update URL using history.pushState
      const url = generateUrl('/apps/souvera_central/' + route)
      window.history.pushState({ route }, '', url)

      this.updateCurrentPath()
    },

    updateUserCount(count) {
      this.userCount = count
    },

    async loadConfig() {
      try {
        const url = generateUrl('/apps/souvera_central/api/config')
        const response = await axios.get(url)
        const config = response.data.ocs?.data || response.data.data || response.data

        this.licenseTotal = config.max_licenses || 10
        this.allowedDomains = config.allowed_domains || []

        console.log('Loaded config:', config)
      } catch (error) {
        console.error('Fehler beim Laden der Config:', error)
      }
    },

    async loadStats() {
      try {
        // Hole alle Benutzer (ohne Limit) für Stats
        const url = generateUrl('/apps/souvera_central/api/users')
        const response = await axios.get(url, {
          params: {
            limit: 999999, // Alle Benutzer
            offset: 0
          }
        })

        const data = response.data.ocs?.data || response.data.data || response.data
        const users = data.users || []

        // Nutze 'total' aus der API Response (korrekte Gesamtzahl)
        this.userCount = data.total || users.length
        this.activeUserCount = users.filter(user => user.enabled).length

        // Lade Gruppen-Anzahl
        await this.loadGroupCount()
      } catch (error) {
        console.error('Fehler beim Laden der Statistiken:', error)
      }
    },

    async loadGroupCount() {
      try {
        const url = generateUrl('/apps/souvera_central/api/groups')
        const response = await axios.get(url)
        const data = response.data.ocs?.data || response.data.data || response.data
        this.groupCount = data.total || (data.groups || []).length
      } catch (error) {
        console.error('Fehler beim Laden der Gruppen-Anzahl:', error)
        this.groupCount = 0
      }
    }
  }
}
</script>

<style>
/* Global App Styles */
#app-souvera-central {
  display: flex;
  height: 100vh;
  width: 100%;
  overflow: hidden;
  background: var(--color-main-background);
}

.app-main-content {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  background: var(--color-main-background);
}

/* Nextcloud Button Overrides */
button.primary {
  background-color: var(--color-primary-element);
  border: none;
  color: var(--color-primary-element-text);
  padding: 10px 20px;
  border-radius: var(--border-radius-large);
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

button.primary:hover:not(:disabled) {
  background-color: var(--color-primary-element-hover);
}

button.primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

button.primary [class^="icon-"],
button.primary [class*=" icon-"] {
  color: inherit;
  filter: none;
}

/* Scrollbar Styling */
.app-main-content::-webkit-scrollbar {
  width: 8px;
}

.app-main-content::-webkit-scrollbar-track {
  background: var(--color-main-background);
}

.app-main-content::-webkit-scrollbar-thumb {
  background: var(--color-border-dark);
  border-radius: 4px;
}

.app-main-content::-webkit-scrollbar-thumb:hover {
  background: var(--color-text-lighter);
}
</style>
