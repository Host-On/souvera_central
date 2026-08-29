<template>
    <NcContent app-name="souvera_central">
        <NcAppNavigation data-testid="souvera-navigation" :aria-label="t('souvera_central', 'Souvera Central Navigation')">
            <template #list>
                <NcAppNavigationItem
                    v-for="item in visibleNavigationItems"
                    :key="item.id"
                    :name="item.label"
                    :active="currentRoute === item.id"
                    :data-testid="`nav-${item.id}`"
                    @click="handleNavigation(item.id, item.url)">
                    <template #icon>
                        <component :is="item.icon" :size="20" />
                    </template>
                </NcAppNavigationItem>
            </template>
        </NcAppNavigation>

        <NcAppContent>
            <div class="souvera-content" data-testid="souvera-content">
                <!-- Dashboard -->
                <Dashboard
                    v-if="currentRoute === 'dashboard'"
                    :total-users="totalUsers"
                    :used-licenses="usedLicenses"
                    :group-count="groupCount"
                    :shared-mailbox-count="sharedMailboxCount"
                    :max-licenses="maxLicenses"
                    :max-groups="maxGroups"
                    :max-shared-mailboxes="maxSharedMailboxes"
                    :warning-threshold="warningThreshold"
                    :allowed-domains="allowedDomains"
                    @navigate="handleNavigation" />

                <!-- User Management -->
                <UserManagement
                    v-else-if="currentRoute === 'users'"
                    :key="routeKey"
                    :allowed-domains="allowedDomains"
                    :max-licenses="maxLicenses"
                    :warning-threshold="warningThreshold"
                    @users-loaded="updateUserStats" />

                <!-- Group Management -->
                <GroupManagement
                    v-else-if="currentRoute === 'groups'"
                    :key="routeKey"
                    @groups-loaded="updateGroupCount" />

                <!-- Shared Mailboxes -->
                <SharedMailboxesView v-else-if="currentRoute === 'shared-mailboxes'" :key="routeKey" />

                <!-- Settings -->
                <Settings
                    v-else-if="currentRoute === 'settings'"
                    :max-licenses="maxLicenses"
                    :allowed-domains="allowedDomains" />

                <!-- Changelog -->
                <ChangelogView v-else-if="currentRoute === 'changelogs'" :key="routeKey" />

                <!-- Souvera AI -->
                <AiView v-else-if="currentRoute === 'ai'" :key="routeKey" />
            </div>
        </NcAppContent>
    </NcContent>
</template>

<script>
import { markRaw } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'

import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import EmailMultiple from 'vue-material-design-icons/EmailMultiple.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import History from 'vue-material-design-icons/History.vue'
import Robot from 'vue-material-design-icons/Robot.vue'

import Dashboard from './modules/Dashboard/Dashboard.vue'
import UserManagement from './modules/UserManagement/UserManagement.vue'
import GroupManagement from './modules/GroupManagement/GroupManagement.vue'
import SharedMailboxesView from './modules/SharedMailboxes/SharedMailboxesView.vue'
import Settings from './modules/Settings/Settings.vue'
import ChangelogView from './modules/Changelog/ChangelogView.vue'
import AiView from './modules/Ai/Ai.vue'

export default {
    name: 'App',

    components: {
        NcContent,
        NcAppNavigation,
        NcAppNavigationItem,
        NcAppContent,
        Dashboard,
        UserManagement,
        GroupManagement,
        SharedMailboxesView,
        Settings,
        ChangelogView,
        AiView
    },

    data() {
        return {
            currentRoute: '',
            currentPath: '',
            isSouveraAdmin: false,
            totalUsers: 0,
            usedLicenses: 0,
            groupCount: 0,
            sharedMailboxCount: 0,
            maxLicenses: 10,
            maxGroups: 20,
            maxSharedMailboxes: 10,
            warningThreshold: 0.8,
            allowedDomains: [],
            statsLoaded: false,
            navigationItems: [
                {
                    id: 'dashboard',
                    label: t('souvera_central', 'Dashboard'),
                    icon: markRaw(ViewDashboard),
                    url: generateUrl('/apps/souvera_central/dashboard'),
                    adminOnly: true
                },
                {
                    id: 'users',
                    label: t('souvera_central', 'User management'),
                    icon: markRaw(AccountMultiple),
                    url: generateUrl('/apps/souvera_central/users'),
                    adminOnly: true
                },
                {
                    id: 'groups',
                    label: t('souvera_central', 'Group management'),
                    icon: markRaw(AccountGroup),
                    url: generateUrl('/apps/souvera_central/groups'),
                    adminOnly: true
                },
                {
                    id: 'shared-mailboxes',
                    label: t('souvera_central', 'Shared mailboxes'),
                    icon: markRaw(EmailMultiple),
                    url: generateUrl('/apps/souvera_central/shared-mailboxes'),
                    adminOnly: true
                },
                {
                    id: 'settings',
                    label: t('souvera_central', 'Settings'),
                    icon: markRaw(Cog),
                    url: generateUrl('/apps/souvera_central/settings'),
                    adminOnly: true
                },
                {
                    id: 'ai',
                    label: t('souvera_central', 'Souvera AI'),
                    icon: markRaw(Robot),
                    url: generateUrl('/apps/souvera_central/ai'),
                    adminOnly: true
                },
                {
                    id: 'changelogs',
                    label: t('souvera_central', 'Changelog'),
                    icon: markRaw(History),
                    url: generateUrl('/apps/souvera_central/changelogs')
                }
            ]
        }
    },

    computed: {
        routeKey() {
            return this.currentPath
        },
        visibleNavigationItems() {
            if (this.isSouveraAdmin) {
                return this.navigationItems
            }
            return this.navigationItems.filter((item) => !item.adminOnly)
        }
    },

    watch: {
        currentRoute(route) {
            this.updateCurrentPath()
            // Self-healing: Stats neu laden, sobald das Dashboard (wieder)
            // angezeigt wird — deckt z. B. fehlgeschlagene Erstladungen ab.
            if (route === 'dashboard' && this.isSouveraAdmin && this.statsLoaded) {
                this.loadStats()
            }
        }
    },

    mounted() {
        this.initializeRouting()

        // Admin-only data loads — never fetch them for non-admins (the
        // mount of admin views is prevented by the route guard).
        if (this.isSouveraAdmin) {
            this.loadConfig()
            this.loadStats()
        }

        window.addEventListener('route-changed', this.handleRouteChanged)
    },

    beforeUnmount() {
        window.removeEventListener('popstate', this.handlePopState)
        window.removeEventListener('route-changed', this.handleRouteChanged)
    },

    methods: {
        t,

        initializeRouting() {
            const appElement = document.getElementById('app-souvera-user-management')
            const initialRoute = appElement?.getAttribute('data-initial-route') || 'dashboard'

            // Server-derived admin flag (data-is-souvera-admin). Non-admins
            // only get the changelog view — admin routes/APIs stay 403 server-side.
            this.isSouveraAdmin = appElement?.getAttribute('data-is-souvera-admin') === '1'

            this.currentRoute = this.authorizeRoute(initialRoute)
            this.updateCurrentPath()

            window.addEventListener('popstate', this.handlePopState)
        },

        /**
         * Central route authorization: non-admins are restricted to the
         * changelog view. Used by initializeRouting, handleNavigation and
         * handlePopState so no path (click, URL, Back/Forward) can mount
         * an admin view client-side.
         */
        authorizeRoute(route) {
            if (this.isSouveraAdmin) {
                return route
            }
            const adminOnlyRoutes = ['dashboard', 'users', 'groups', 'shared-mailboxes', 'settings', 'ai']
            return adminOnlyRoutes.indexOf(route) !== -1 ? 'changelogs' : route
        },

        updateCurrentPath() {
            this.currentPath = window.location.pathname
        },

        handlePopState() {
            const path = window.location.pathname
            const match = path.match(/\/apps\/souvera_central\/(dashboard|users|groups|shared-mailboxes|settings|ai|changelogs)/)

            this.currentRoute = this.authorizeRoute(match && match[1] ? match[1] : 'dashboard')
            this.updateCurrentPath()
        },

        handleNavigation(route, url) {
            route = this.authorizeRoute(route)
            if (!this.isSouveraAdmin && url) {
                url = generateUrl('/apps/souvera_central/' + route)
            }

            this.currentRoute = route
            this.updateCurrentPath()

            // Update browser URL when navigating via the sidebar
            const targetUrl = url || generateUrl('/apps/souvera_central/' + route)
            window.history.pushState({ route }, '', targetUrl)
            window.dispatchEvent(new CustomEvent('route-changed', { detail: { route, url: targetUrl } }))
        },

        handleRouteChanged() {
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
                this.maxGroups = config.max_groups || 20
                this.maxSharedMailboxes = config.max_shared_mailboxes || 10
                this.warningThreshold = config.warning_threshold || 0.8
                this.allowedDomains = config.allowed_domains || []
            } catch (error) {
                console.error('[SouveraCentral] loadConfig failed:', error?.response?.status, error?.response?.data || error)
            }
        },

        async loadStats() {
            // Jede Zähler-Quelle unabhängig laden: schlägt der User-Endpoint
            // fehl, dürfen Gruppen und Postfächer trotzdem geladen werden.
            try {
                const url = generateUrl('/apps/souvera_central/api/users')
                const response = await axios.get(url, {
                    params: {
                        limit: 999999,
                        offset: 0
                    }
                })

                const data = response.data.ocs?.data || response.data.data || response.data
                const users = data.users || []

                this.totalUsers = data.total || users.length
                // Lizenzzahlen kommen ausschließlich aus /api/config (LicenseService),
                // hier NICHT überschreiben/schätzen.
            } catch (error) {
                console.error('[SouveraCentral] loadStats users failed:', error?.response?.status, error?.response?.data || error)
            }

            await this.loadGroupCount()
            await this.loadSharedMailboxCount()
            this.statsLoaded = true
        },

        async loadGroupCount() {
            try {
                const url = generateUrl('/apps/souvera_central/api/groups/manage')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data
                this.groupCount = data.total || (data.groups || []).length
            } catch (error) {
                console.error('[SouveraCentral] loadGroupCount failed:', error?.response?.status, error?.response?.data || error)
                this.groupCount = 0
            }
        },

        async loadSharedMailboxCount() {
            try {
                const url = generateUrl('/apps/souvera_central/api/shared-mailboxes')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data
                this.sharedMailboxCount = data.total || (data.mailboxes || []).length
            } catch (error) {
                console.error('[SouveraCentral] loadSharedMailboxCount failed:', error?.response?.status, error?.response?.data || error)
                this.sharedMailboxCount = 0
            }
        }
    }
}
</script>

<style scoped>
.souvera-content {
    width: 100%;
    min-height: 100%;
    box-sizing: border-box;
}

/* Native nav items should never be underlined */
:deep(.app-navigation-entry-link),
:deep(.app-navigation-entry__title),
:deep(.app-navigation a) {
    text-decoration: none;
}
</style>
