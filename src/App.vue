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
      userCount: 0,
      activeUserCount: 0,
      groupCount: 0,
      licenseTotal: 10,
      allowedDomains: []
    }
  },

  mounted() {
    this.loadConfig()
    this.loadStats()
  },

  methods: {
    t,

    handleNavigation(route) {
      this.currentRoute = route
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
        const url = generateUrl('/apps/souvera_central/api/users')
        const response = await axios.get(url)

        const users = response.data.ocs?.data?.users || response.data.data?.users || response.data.users || []

        this.userCount = users.length
        this.activeUserCount = users.filter(user => user.enabled).length

        // TODO: Load actual group count from API when available
        this.groupCount = 0
      } catch (error) {
        console.error('Fehler beim Laden der Statistiken:', error)
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

button.primary .icon-add {
  font-size: 16px;
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
