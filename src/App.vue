<template>
    <NcContent app-name="souvera_central">
        <NcAppNavigation data-testid="souvera-navigation" :aria-label="t('souvera_central', 'Souvera Central Navigation')">
            <template #list>
                <NcAppNavigationItem
                    v-for="item in navigationItems"
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
                    :active-user-count="activeUserCount"
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

import Dashboard from './modules/Dashboard/Dashboard.vue'
import UserManagement from './modules/UserManagement/UserManagement.vue'
import GroupManagement from './modules/GroupManagement/GroupManagement.vue'
import SharedMailboxesView from './modules/SharedMailboxes/SharedMailboxesView.vue'
import Settings from './modules/Settings/Settings.vue'

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
        Settings
    },

    data() {
        return {
            currentRoute: 'dashboard',
            currentPath: '',
            totalUsers: 0,
            usedLicenses: 0,
            activeUserCount: 0,
            groupCount: 0,
            sharedMailboxCount: 0,
            maxLicenses: 10,
            maxGroups: 20,
            maxSharedMailboxes: 10,
            warningThreshold: 0.8,
            allowedDomains: [],
            navigationItems: [
                {
                    id: 'dashboard',
                    label: t('souvera_central', 'Dashboard'),
                    icon: markRaw(ViewDashboard),
                    url: generateUrl('/apps/souvera_central/dashboard')
                },
                {
                    id: 'users',
                    label: t('souvera_central', 'Benutzerverwaltung'),
                    icon: markRaw(AccountMultiple),
                    url: generateUrl('/apps/souvera_central/users')
                },
                {
                    id: 'groups',
                    label: t('souvera_central', 'Gruppenverwaltung'),
                    icon: markRaw(AccountGroup),
                    url: generateUrl('/apps/souvera_central/groups')
                },
                {
                    id: 'shared-mailboxes',
                    label: t('souvera_central', 'Geteilte Postfächer'),
                    icon: markRaw(EmailMultiple),
                    url: generateUrl('/apps/souvera_central/shared-mailboxes')
                },
                {
                    id: 'settings',
                    label: t('souvera_central', 'Einstellungen'),
                    icon: markRaw(Cog),
                    url: generateUrl('/apps/souvera_central/settings')
                }
            ]
        }
    },

    computed: {
        routeKey() {
            return this.currentPath
        }
    },

    watch: {
        currentRoute() {
            this.updateCurrentPath()
        }
    },

    mounted() {
        this.loadConfig()
        this.loadStats()
        this.initializeRouting()

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

            this.currentRoute = initialRoute
            this.updateCurrentPath()

            window.addEventListener('popstate', this.handlePopState)
        },

        updateCurrentPath() {
            this.currentPath = window.location.pathname
        },

        handlePopState() {
            const path = window.location.pathname
            const match = path.match(/\/apps\/souvera_central\/(dashboard|users|groups|shared-mailboxes|settings)/)

            this.currentRoute = match && match[1] ? match[1] : 'dashboard'
            this.updateCurrentPath()
        },

        handleNavigation(route, url) {
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
                // Error handling
            }
        },

        async loadStats() {
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
                this.usedLicenses = Math.max(0, this.totalUsers - 1)
                this.activeUserCount = users.filter((user) => user.enabled).length

                await this.loadGroupCount()
                await this.loadSharedMailboxCount()
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
        },

        async loadSharedMailboxCount() {
            try {
                const url = generateUrl('/apps/souvera_central/api/shared-mailboxes')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data
                this.sharedMailboxCount = data.total || (data.mailboxes || []).length
            } catch (error) {
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
