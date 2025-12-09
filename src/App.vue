<template>
    <div id="app-souvera-central">
        <!-- Sidebar Navigation -->
        <AppSidebar
            :current-route="currentRoute"
            @navigate="handleNavigation"
        />

        <!-- Main Content Area -->
        <div class="app-main-content">
            <!-- Dashboard -->
            <Dashboard
                v-if="currentRoute === 'dashboard'"
                :total-users="totalUsers"
                :used-licenses="usedLicenses"
                :active-user-count="activeUserCount"
                :group-count="groupCount"
                :max-licenses="maxLicenses"
                :allowed-domains="allowedDomains"
                @navigate="handleNavigation"
            />

            <!-- User Management -->
            <UserManagement
                v-else-if="currentRoute === 'users'"
                :key="routeKey"
                :allowed-domains="allowedDomains"
                :max-licenses="maxLicenses"
                @users-loaded="updateUserStats"
            />

            <!-- Group Management -->
            <GroupManagement v-else-if="currentRoute === 'groups'" :key="routeKey" @groups-loaded="updateGroupCount" />

            <!-- Shared Mailboxes -->
            <SharedMailboxesView v-else-if="currentRoute === 'shared-mailboxes'" :key="routeKey" />

            <!-- Settings -->
            <Settings
                v-else-if="currentRoute === 'settings'"
                :max-licenses="maxLicenses"
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
import SharedMailboxesView from './modules/SharedMailboxes/SharedMailboxesView.vue'
import Settings from './modules/Settings/Settings.vue'

export default {
    name: 'App',

    components: {
        AppSidebar,
        Dashboard,
        UserManagement,
        GroupManagement,
        SharedMailboxesView,
        Settings
    },

    data() {
        return {
            currentRoute: 'dashboard',
            currentPath: '', // Vollständiger Pfad für routeKey
            totalUsers: 0,
            usedLicenses: 0,
            activeUserCount: 0,
            groupCount: 0,
            maxLicenses: 10,
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

        // Listen to route-changed events from child components
        window.addEventListener('route-changed', this.handleRouteChanged)
    },

    beforeUnmount() {
        // Cleanup event listener
        window.removeEventListener('popstate', this.handlePopState)
        window.removeEventListener('route-changed', this.handleRouteChanged)
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
            const match = path.match(/\/apps\/souvera_central\/(dashboard|users|groups|shared-mailboxes|settings)/)

            if (match && match[1]) {
                this.currentRoute = match[1]
            } else {
                this.currentRoute = 'dashboard'
            }

            this.updateCurrentPath()
        },

        handleNavigation(route) {
            this.currentRoute = route
            this.updateCurrentPath()

            // URL und custom event werden bereits von AppSidebar.navigateTo() gesetzt
            // Kein doppeltes pushState nötig
        },

        handleRouteChanged() {
            // Reagiere auf route-changed Events von Child-Komponenten
            // Dies wird gefeuert wenn z.B. von /users/edit/123 -> /users navigiert wird
            this.updateCurrentPath()
        },

        updateUserStats(stats) {
            this.totalUsers = stats.total
            this.usedLicenses = stats.used
        },

        updateGroupCount(count) {
            this.groupCount = count
        },

        async loadConfig() {
            try {
                const url = generateUrl('/apps/souvera_central/api/config')
                const response = await axios.get(url)
                const config = response.data.ocs?.data || response.data.data || response.data

                this.totalUsers = config.total_users || 0
                this.usedLicenses = config.used_licenses || 0
                this.maxLicenses = config.max_licenses || 10
                this.allowedDomains = config.allowed_domains || []
            } catch (error) {
                // Error handling
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

                // Nutze 'total' aus der API Response (tatsächliche Benutzeranzahl)
                this.totalUsers = data.total || users.length
                this.usedLicenses = Math.max(0, this.totalUsers - 1)
                this.activeUserCount = users.filter((user) => user.enabled).length

                // Lade Gruppen-Anzahl
                await this.loadGroupCount()
            } catch (error) {
                // Error handling
            }
        },

        async loadGroupCount() {
            try {
                const url = generateUrl('/apps/souvera_central/api/groups')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data
                this.groupCount = data.total || (data.groups || []).length
            } catch (error) {
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
    min-height: 80vh;
    height: 100%;
    width: 100%;
    background: rgba(255, 255, 255, 0.25);
    border-radius: 6px;
}

.app-main-content {
    flex: 1;
    padding: 30px 50px 30px 50px;
    overflow-y: auto;
    overflow-x: hidden;
    background: transparent;
    min-width: 0;
}

/* Responsive Design */
@media (max-width: 1024px) {
    #app-souvera-central {
        flex-direction: column;
    }

    .app-main-content {
        padding: 20px 30px;
    }
}

@media (max-width: 768px) {
    .app-main-content {
        padding: 15px 20px;
    }
}

@media (max-width: 480px) {
    #app-souvera-central {
        border-radius: 0;
    }

    .app-main-content {
        padding: 0;
    }
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

button.primary [class^='icon-'],
button.primary [class*=' icon-'] {
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
