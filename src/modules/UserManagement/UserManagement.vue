<template>
    <div class="user-management-container">
        <!-- User Editor (Single Page) -->
        <UserEditor
            v-if="showEditor"
            :user="selectedUser"
            :allowed-domains="allowedDomains"
            @close="closeEditor"
            @saved="handleUserSaved"
        />

        <!-- Haupt-Bereich mit User-Liste -->
        <div v-else class="users-list-view">
            <!-- Header mit Lizenz-Info -->
            <div class="page-header">
                <div class="header-content">
                    <h2>{{ t('souvera_central', 'Benutzerverwaltung') }}</h2>
                    <div
                        class="license-status"
                        :class="{ 'license-warning': isLicenseWarning, 'license-critical': isLicenseLimitReached }"
                    >
                        <KeyVariant :size="18" />
                        <span class="license-info">{{ usedLicenses }} von {{ maxLicenses }} Lizenzen genutzt</span>
                    </div>
                </div>
                <button
                    class="primary"
                    :disabled="isLicenseLimitReached"
                    :title="isLicenseLimitReached ? t('souvera_central', 'Lizenzlimit erreicht') : ''"
                    @click="createNewUser"
                >
                    <Plus :size="20" />
                    {{ t('souvera_central', 'Neuer Benutzer') }}
                </button>
            </div>

            <!-- KRITISCHES WARNING: Lizenzlimit erreicht -->
            <div v-if="isLicenseLimitReached" class="critical-warning">
                <div class="warning-content">
                    <AlertOctagon class="warning-icon" :size="48" />
                    <div class="warning-text">
                        <h3>{{ t('souvera_central', 'Lizenzlimit erreicht!') }}</h3>
                        <p>
                            {{
                                t(
                                    'souvera_central',
                                    'Sie haben {count} von {total} Lizenzen genutzt. Es können keine weiteren Benutzer erstellt werden.',
                                    { count: usedLicenses, total: maxLicenses }
                                )
                            }}
                        </p>
                    </div>
                    <a :href="contactUrl" target="_blank" class="contact-button">
                        <OpenInNew :size="18" />
                        {{ t('souvera_central', 'Lizenzen erweitern') }}
                    </a>
                </div>
            </div>

            <!-- WARNING: Lizenzlimit bald erreicht (80%+) -->
            <div v-else-if="isLicenseWarning" class="warning-banner">
                <div class="warning-content">
                    <AlertCircle class="warning-icon" :size="40" />
                    <div class="warning-text">
                        <h3>{{ t('souvera_central', 'Lizenzlimit bald erreicht') }}</h3>
                        <p>
                            {{
                                t(
                                    'souvera_central',
                                    'Sie haben {count} von {total} Lizenzen genutzt ({percentage}%). Erweitern Sie rechtzeitig Ihre Lizenzen.',
                                    { count: usedLicenses, total: maxLicenses, percentage: licensePercentage }
                                )
                            }}
                        </p>
                    </div>
                    <a :href="contactUrl" target="_blank" class="contact-button secondary">
                        <OpenInNew :size="18" />
                        {{ t('souvera_central', 'Kontakt') }}
                    </a>
                </div>
            </div>

            <!-- Suchfeld -->
            <div class="search-bar">
                <SearchField
                    v-model="searchQuery"
                    :placeholder="t('souvera_central', 'Suche nach Benutzername, Name oder E-Mail...')"
                    @search="handleSearch"
                />
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading-state">
                <NcLoadingIcon :size="32" />
                <p>{{ t('souvera_central', 'Lade Benutzer...') }}</p>
            </div>

            <!-- User Table -->
            <div v-else-if="users.length > 0" class="table-container">
                <div class="users-table-wrapper">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th class="user-column">{{ t('souvera_central', 'Benutzername') }}</th>
                                <th class="displayname-column">{{ t('souvera_central', 'Anzeigename') }}</th>
                                <th class="type-column">{{ t('souvera_central', 'Typ') }}</th>
                                <th class="quota-column">{{ t('souvera_central', 'NC-Speicher') }}</th>
                                <th class="mailbox-usage-column">{{ t('souvera_central', 'Postfach (E-Mail)') }}</th>
                                <th class="status-column">{{ t('souvera_central', 'Status') }}</th>
                                <th class="actions-column">{{ t('souvera_central', 'Aktionen') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users" :key="user.id" class="user-row">
                                <td class="user-column">
                                    <div class="user-info">
                                        <Account class="row-user-icon" :size="20" />
                                        <span class="username" :title="user.id">{{ user.id }}</span>
                                        <EmailCheck
                                            v-if="stalwartConfigured && hasMailbox(user)"
                                            class="mailbox-indicator has-mailbox"
                                            :size="16"
                                            :title="t('souvera_central', 'Stalwart-Postfach vorhanden')"
                                        />
                                        <EmailRemoveOutline
                                            v-else-if="stalwartConfigured"
                                            class="mailbox-indicator no-mailbox"
                                            :size="16"
                                            :title="t('souvera_central', 'Kein Stalwart-Postfach')"
                                        />
                                    </div>
                                </td>
                                <td class="displayname-column">
                                    <span class="cell-ellipsis" :title="user.displayName">{{ user.displayName }}</span>
                                </td>
                                <td class="type-column">
                                    <div class="type-cell">
                                        <span
                                            class="type-badge"
                                            :class="isSouveraUser(user) ? 'type-souvera' : 'type-nextcloud'"
                                            :data-testid="'user-type-badge-' + user.id"
                                        >
                                            {{ isSouveraUser(user) ? t('souvera_central', 'Souvera User') : t('souvera_central', 'Nextcloud User') }}
                                        </span>
                                        <span
                                            v-if="isSouveraAdmin(user)"
                                            class="type-badge type-admin"
                                            :data-testid="'user-admin-badge-' + user.id"
                                        >
                                            <ShieldCrown :size="13" />
                                            {{ t('souvera_central', 'Admin') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="quota-column">{{ user.quota.quota }}</td>
                                <td class="mailbox-usage-column">
                                    <div
                                        v-if="stalwartConfigured && hasMailbox(user)"
                                        class="mb-usage"
                                        :data-testid="'mailbox-usage-' + user.id"
                                    >
                                        <div v-if="usageFor(user).quota > 0" class="mb-usage-bar">
                                            <div
                                                class="mb-usage-fill"
                                                :class="usageLevelClass(user)"
                                                :style="{ width: usagePercent(user) + '%' }"
                                            ></div>
                                        </div>
                                        <span class="mb-usage-text">
                                            {{ formatBytes(usageFor(user).used) }} /
                                            {{ usageFor(user).quota > 0 ? formatBytes(usageFor(user).quota) : '∞' }}
                                        </span>
                                    </div>
                                    <span v-else-if="stalwartConfigured" class="mb-usage-none">—</span>
                                </td>
                                <td class="status-column">
                                    <div class="status-badge" :class="user.enabled ? 'status-active' : 'status-inactive'">
                                        <CheckCircle v-if="user.enabled" :size="16" />
                                        <CloseCircle v-else :size="16" />
                                        <span class="status-text">
                                            {{ user.enabled ? t('souvera_central', 'Aktiv') : t('souvera_central', 'Inaktiv') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="actions-column">
                                    <div class="user-actions">
                                        <button
                                            v-if="!isSouveraUser(user)"
                                            class="action-mailbox"
                                            :title="t('souvera_central', 'Zum Souvera User machen')"
                                            :disabled="upgradingUser === user.id"
                                            :data-testid="'upgrade-souvera-' + user.id"
                                            @click.stop="upgradeToSouveraUser(user)"
                                        >
                                            <AccountArrowUp :size="18" />
                                        </button>
                                        <button
                                            v-if="isSouveraUser(user) && !isSouveraAdmin(user)"
                                            class="action-makeadmin"
                                            :title="t('souvera_central', 'Zum Souvera-Admin machen')"
                                            :disabled="updatingAdmin === user.id"
                                            :data-testid="'make-admin-' + user.id"
                                            @click.stop="makeAdmin(user)"
                                        >
                                            <ShieldCrown :size="18" />
                                        </button>
                                        <button
                                            v-if="isSouveraAdmin(user) && currentUserId && user.id !== currentUserId"
                                            class="action-removeadmin"
                                            :title="t('souvera_central', 'Souvera-Admin-Rechte entfernen')"
                                            :disabled="updatingAdmin === user.id"
                                            :data-testid="'remove-admin-' + user.id"
                                            @click.stop="removeAdmin(user)"
                                        >
                                            <ShieldRemove :size="18" />
                                        </button>
                                        <button
                                            class="action-edit"
                                            :title="t('souvera_central', 'Bearbeiten')"
                                            :data-testid="'edit-user-' + user.id"
                                            @click.stop="editUser(user)"
                                        >
                                            <Pencil :size="18" />
                                        </button>
                                        <button
                                            v-if="user.id !== currentUserId && user.id !== 'admin' && !user.id.startsWith('admin@') && !user.isProtected"
                                            class="action-delete"
                                            :title="t('souvera_central', 'Löschen')"
                                            @click.stop="deleteUser(user)"
                                        >
                                            <Delete :size="18" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <Pagination
                    :current-page="currentPage"
                    :per-page="perPage"
                    :total="totalUsers"
                    @page-change="handlePageChange"
                    @per-page-change="handlePerPageChange"
                />
            </div>

            <!-- Empty State -->
            <div v-else class="empty-state">
                <Account class="empty-icon" :size="64" />
                <h3 v-if="searchQuery">{{ t('souvera_central', 'Keine Benutzer gefunden') }}</h3>
                <h3 v-else>{{ t('souvera_central', 'Noch keine Benutzer') }}</h3>
                <p v-if="searchQuery">{{ t('souvera_central', 'Versuchen Sie einen anderen Suchbegriff.') }}</p>
                <p v-else>{{ t('souvera_central', 'Erstellen Sie Ihren ersten Benutzer um zu starten.') }}</p>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <ConfirmationModal
            :is-open="confirmModal.isOpen"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :details="confirmModal.details"
            :type="confirmModal.type"
            :confirm-text="confirmModal.confirmText"
            :cancel-text="confirmModal.cancelText"
            @confirm="confirmModal.onConfirm"
            @close="closeConfirmModal"
        />
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import UserEditor from './components/UserEditor.vue'
import SearchField from '../../components/SearchField.vue'
import Pagination from '../../components/Pagination.vue'
import ConfirmationModal from '../../components/ConfirmationModal.vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import Account from 'vue-material-design-icons/Account.vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import CloseCircle from 'vue-material-design-icons/CloseCircle.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import KeyVariant from 'vue-material-design-icons/KeyVariant.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import AlertOctagon from 'vue-material-design-icons/AlertOctagon.vue'
import EmailCheck from 'vue-material-design-icons/EmailCheck.vue'
import EmailRemoveOutline from 'vue-material-design-icons/EmailRemoveOutline.vue'
import AccountArrowUp from 'vue-material-design-icons/AccountArrowUp.vue'
import ShieldCrown from 'vue-material-design-icons/ShieldCrown.vue'
import ShieldRemove from 'vue-material-design-icons/ShieldRemove.vue'

export default {
    name: 'UserManagement',

    components: {
        UserEditor,
        SearchField,
        Pagination,
        ConfirmationModal,
        NcLoadingIcon,
        Account,
        CheckCircle,
        CloseCircle,
        Pencil,
        Delete,
        Plus,
        KeyVariant,
        OpenInNew,
        AlertCircle,
        AlertOctagon,
        EmailCheck,
        EmailRemoveOutline,
        AccountArrowUp,
        ShieldCrown,
        ShieldRemove
    },

    props: {
        allowedDomains: {
            type: Array,
            default: () => []
        },
        maxLicenses: {
            type: Number,
            default: 10
        },
        warningThreshold: {
            type: Number,
            default: 0.8
        }
    },

    data() {
        return {
            users: [],
            totalUsers: 0,
            usedLicenses: 0,
            loading: true,
            selectedUser: null,
            showEditor: false,
            searchQuery: '',
            currentPage: 1,
            perPage: 20,
            currentUserId: null, // ID des aktuell angemeldeten Benutzers
            mailboxes: [], // Stalwart Principal-Namen (UIDs) mit Postfach
            mailboxUsage: {}, // { email: { used, quota } } Belegung je Postfach
            stalwartConfigured: false,
            upgradingUser: null, // UID, während ein User zum Souvera User gemacht wird
            updatingAdmin: null, // UID, während Souvera-Admin-Rechte gesetzt/entfernt werden
            resellerInfo: {
                support_url: null,
                url: null,
                name: null
            },
            confirmModal: {
                isOpen: false,
                title: '',
                message: '',
                details: '',
                type: 'info',
                confirmText: 'Bestätigen',
                cancelText: 'Abbrechen',
                onConfirm: () => {}
            }
        }
    },

    computed: {
        isLicenseLimitReached() {
            return this.usedLicenses >= this.maxLicenses
        },

        licensePercentage() {
            if (this.maxLicenses === 0) return 0
            return Math.round((this.usedLicenses / this.maxLicenses) * 100)
        },

        isLicenseWarning() {
            return !this.isLicenseLimitReached && this.usedLicenses / this.maxLicenses >= this.warningThreshold
        },

        contactUrl() {
            // Fallback-Logik: support_url → url → www.souvera.eu
            if (this.resellerInfo.support_url) {
                return this.resellerInfo.support_url
            }
            if (this.resellerInfo.url) {
                return this.resellerInfo.url
            }
            return 'https://www.souvera.eu'
        }
    },

    mounted() {
        this.loadCurrentUser()
        this.loadUsers()
        this.loadMailboxes()
        this.loadResellerInfo()
        this.checkInitialAction()

        // Event-Listener für URL-Änderungen
        window.addEventListener('popstate', this.handleUrlChange) // Browser Back/Forward
        window.addEventListener('route-changed', this.handleUrlChange) // Sidebar-Navigation
    },

    beforeUnmount() {
        // Cleanup event listeners
        window.removeEventListener('popstate', this.handleUrlChange)
        window.removeEventListener('route-changed', this.handleUrlChange)
    },

    methods: {
        t,

        async loadCurrentUser() {
            try {
                const url = generateUrl('/apps/souvera_central/api/users/current')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data

                this.currentUserId = data.id
            } catch (error) {
                // Error handling
            }
        },

        async loadResellerInfo() {
            try {
                const url = generateUrl('/apps/souvera_central/api/reseller')
                const response = await axios.get(url)

                if (response.data?.ocs?.data) {
                    this.resellerInfo = response.data.ocs.data
                } else if (response.data) {
                    this.resellerInfo = response.data
                }
            } catch (error) {
                console.error('Failed to load reseller info:', error)
                // Fallback ist bereits in contactUrl implementiert
            }
        },

        checkInitialAction() {
            // Prüfe ob wir von /users/new oder /users/edit/:id kommen
            const appElement = document.getElementById('app-souvera-user-management')
            const action = appElement?.getAttribute('data-action') || ''
            const userId = appElement?.getAttribute('data-user-id') || ''

            if (action === 'new') {
                this.createNewUser()
            } else if (action === 'edit' && userId) {
                // Lade User und öffne Editor
                this.loadAndEditUser(userId)
            }
        },

        handleUrlChange() {
            // Reagiere auf URL-Änderungen (Browser Back/Forward, Sidebar-Klicks)
            const path = window.location.pathname

            // Check für /users/new
            if (path.includes('/users/new')) {
                if (!this.showEditor || this.selectedUser !== null) {
                    this.selectedUser = null
                    this.showEditor = true
                }
                return
            }

            // Check für /users/edit/:id
            const editMatch = path.match(/\/users\/edit\/([^/]+)/)
            if (editMatch && editMatch[1]) {
                const userId = editMatch[1]
                // Nur neu laden wenn anderer User oder Editor geschlossen
                if (!this.showEditor || this.selectedUser?.id !== userId) {
                    this.loadAndEditUser(userId)
                }
                return
            }

            // Wenn URL zu /users (ohne /new oder /edit/:id) geht → Editor schließen
            if (path.endsWith('/users') || path === generateUrl('/apps/souvera_central/users')) {
                if (this.showEditor) {
                    this.showEditor = false
                    this.selectedUser = null
                }
            }
        },

        async loadAndEditUser(userId) {
            try {
                const url = generateUrl('/apps/souvera_central/api/users/{id}', { id: userId })
                const response = await axios.get(url)

                // Check OCS statuscode
                const statusCode = response.data?.ocs?.meta?.statuscode
                if (statusCode === 100) {
                    const user = response.data.ocs.data
                    // Convert groups
                    user.groups = Array.isArray(user.groups) ? user.groups : Object.values(user.groups || {})

                    this.selectedUser = user
                    this.showEditor = true
                }
            } catch (error) {
                // Error handling
            }
        },

        async loadUsers() {
            try {
                this.loading = true
                const offset = (this.currentPage - 1) * this.perPage

                const url = generateUrl('/apps/souvera_central/api/users')
                const response = await axios.get(url, {
                    params: {
                        search: this.searchQuery,
                        limit: this.perPage,
                        offset: offset
                    }
                })

                const data = response.data.ocs?.data || response.data.data || response.data
                const users = data.users || []
                this.totalUsers = data.total || 0
                // Genutzte Lizenzen = lizenzierte Souvera User (Backend-Wert, ohne scadmin/hidden)
                this.usedLicenses = typeof data.usedLicenses === 'number'
                    ? data.usedLicenses
                    : Math.max(0, this.totalUsers - 1)

                // Fix groups: Convert object to array
                this.users = users.map((user) => ({
                    ...user,
                    groups: Array.isArray(user.groups) ? user.groups : Object.values(user.groups || {})
                }))

                // Emit user stats to parent (für Dashboard)
                this.$emit('users-loaded', {
                    total: this.totalUsers,
                    used: this.usedLicenses
                })
            } catch (error) {
                // Error handling
            } finally {
                this.loading = false
            }
        },

        async loadMailboxes() {
            try {
                const url = generateUrl('/apps/souvera_central/api/stalwart/mailboxes')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data || {}
                this.stalwartConfigured = !!data.configured
                this.mailboxes = data.mailboxes || []
                this.mailboxUsage = data.usage || {}
            } catch (error) {
                this.stalwartConfigured = false
                this.mailboxes = []
                this.mailboxUsage = {}
            }
        },

        hasMailbox(user) {
            const list = this.mailboxes.map((m) => String(m).toLowerCase())
            const candidates = [user.email, user.id]
                .filter(Boolean)
                .map((s) => String(s).toLowerCase())
            return candidates.some((c) => list.includes(c))
        },

        usageFor(user) {
            const k1 = String(user.email || '').toLowerCase()
            const k2 = String(user.id || '').toLowerCase()
            return this.mailboxUsage[k1] || this.mailboxUsage[k2] || { used: 0, quota: 0 }
        },

        usagePercent(user) {
            const u = this.usageFor(user)
            if (!u.quota || u.quota <= 0) {
                return 0
            }
            return Math.min(100, Math.round((u.used / u.quota) * 100))
        },

        usageLevelClass(user) {
            const p = this.usagePercent(user)
            if (p >= 90) {
                return 'is-full'
            }
            if (p >= 75) {
                return 'is-warn'
            }
            return ''
        },

        formatBytes(bytes) {
            const b = Number(bytes) || 0
            if (b <= 0) {
                return '0 B'
            }
            const units = ['B', 'KB', 'MB', 'GB', 'TB']
            let v = b
            let i = 0
            while (v >= 1024 && i < units.length - 1) {
                v /= 1024
                i++
            }
            const s = v === Math.floor(v) ? String(v) : v.toFixed(1)
            return s + ' ' + units[i]
        },

        isSouveraUser(user) {
            return user.isSouveraUser === true || user.type === 'souvera'
        },

        isSouveraAdmin(user) {
            if (user.isSouveraAdmin === true) {
                return true
            }
            const groups = Array.isArray(user.groups) ? user.groups : []
            return groups.some((g) => (typeof g === 'string' ? g : g.id) === 'souvera-admins')
        },

        async makeAdmin(user) {
            this.updatingAdmin = user.id
            try {
                const url = generateUrl(
                    '/apps/souvera_central/api/users/' + encodeURIComponent(user.id) + '/make-admin'
                )
                await axios.post(url)
                await this.loadUsers()
            } catch (error) {
                const msg = error.response?.data?.ocs?.data?.error
                    || error.response?.data?.error
                    || error.response?.data?.message
                this.confirmModal = {
                    isOpen: true,
                    title: this.t('souvera_central', 'Aktion nicht möglich'),
                    message: msg || this.t('souvera_central', 'Der Benutzer konnte nicht zum Souvera-Admin gemacht werden.'),
                    details: '',
                    type: 'warning',
                    confirmText: this.t('souvera_central', 'OK'),
                    cancelText: '',
                    onConfirm: () => this.closeConfirmModal()
                }
            } finally {
                this.updatingAdmin = null
            }
        },

        removeAdmin(user) {
            this.confirmModal = {
                isOpen: true,
                title: this.t('souvera_central', 'Admin-Rechte entfernen?'),
                message: this.t(
                    'souvera_central',
                    'Möchten Sie "{user}" die Souvera-Administrator-Rechte wirklich entziehen?',
                    { user: user.displayName }
                ),
                details: this.t('souvera_central', 'Der Souvera-User-Status (Postfach) bleibt erhalten.'),
                type: 'warning',
                confirmText: this.t('souvera_central', 'Ja, Rechte entfernen'),
                cancelText: this.t('souvera_central', 'Abbrechen'),
                onConfirm: async () => {
                    this.updatingAdmin = user.id
                    try {
                        const url = generateUrl(
                            '/apps/souvera_central/api/users/' + encodeURIComponent(user.id) + '/remove-admin'
                        )
                        await axios.post(url)
                        this.closeConfirmModal()
                        await this.loadUsers()
                    } catch (error) {
                        const msg = error.response?.data?.ocs?.data?.error
                            || error.response?.data?.error
                            || error.response?.data?.message
                        this.confirmModal = {
                            isOpen: true,
                            title: this.t('souvera_central', 'Aktion nicht möglich'),
                            message: msg || this.t('souvera_central', 'Die Administrator-Rechte konnten nicht entfernt werden.'),
                            details: '',
                            type: 'danger',
                            confirmText: this.t('souvera_central', 'OK'),
                            cancelText: '',
                            onConfirm: () => this.closeConfirmModal()
                        }
                    } finally {
                        this.updatingAdmin = null
                    }
                }
            }
        },

        async upgradeToSouveraUser(user) {
            this.upgradingUser = user.id
            try {
                const url = generateUrl(
                    '/apps/souvera_central/api/users/' + encodeURIComponent(user.id)
                )
                await axios.put(url, { isSouveraUser: true })
                await this.loadUsers()
                await this.loadMailboxes()
            } catch (error) {
                const status = error.response?.status
                const msg = error.response?.data?.ocs?.data?.error
                    || error.response?.data?.error
                    || error.response?.data?.message
                this.confirmModal = {
                    isOpen: true,
                    title: this.t('souvera_central', 'Upgrade nicht möglich'),
                    message: status === 409
                        ? (msg || this.t('souvera_central', 'Lizenzlimit erreicht.'))
                        : this.t('souvera_central', 'Der Benutzer konnte nicht zum Souvera User gemacht werden.'),
                    details: '',
                    type: 'warning',
                    confirmText: this.t('souvera_central', 'OK'),
                    cancelText: '',
                    onConfirm: () => this.closeConfirmModal()
                }
            } finally {
                this.upgradingUser = null
            }
        },

        handleSearch(query) {
            this.searchQuery = query
            this.currentPage = 1 // Zurück zur ersten Seite bei neuer Suche
            this.loadUsers()
        },

        handlePageChange(page) {
            this.currentPage = page
            this.loadUsers()
        },

        handlePerPageChange(perPage) {
            this.perPage = perPage
            this.currentPage = 1 // Zurück zur ersten Seite
            this.loadUsers()
        },

        createNewUser() {
            if (this.isLicenseLimitReached) {
                alert(
                    this.t(
                        'souvera_central',
                        'Lizenzlimit erreicht. Es können keine weiteren Benutzer erstellt werden.'
                    )
                )
                return
            }

            // Navigate to /users/new
            const url = generateUrl('/apps/souvera_central/users/new')
            window.history.pushState({}, '', url)

            this.selectedUser = null
            this.showEditor = true
        },

        editUser(user) {
            // Navigate to /users/edit/:id
            const url = generateUrl('/apps/souvera_central/users/edit/{id}', { id: user.id })
            window.history.pushState({}, '', url)

            this.selectedUser = user
            this.showEditor = true
        },

        closeEditor() {
            // Navigate zurück zu /users
            const url = generateUrl('/apps/souvera_central/users')
            window.history.pushState({ route: 'users' }, '', url)

            // Editor schließen (OHNE Liste neu zu laden - nur bei Abbruch)
            this.showEditor = false
            this.selectedUser = null

            // Dispatch event damit andere Komponenten reagieren können
            window.dispatchEvent(new CustomEvent('route-changed', { detail: { route: 'users', url } }))
        },

        async handleUserSaved() {
            // Navigate zurück zu /users
            const url = generateUrl('/apps/souvera_central/users')
            window.history.pushState({ route: 'users' }, '', url)

            // Editor schließen
            this.showEditor = false
            this.selectedUser = null

            // Liste komplett neu laden (NUR bei erfolgreichem Speichern)
            await this.loadUsers()

            // Dispatch event damit andere Komponenten reagieren können
            window.dispatchEvent(new CustomEvent('route-changed', { detail: { route: 'users', url } }))
        },

        deleteUser(user) {
            // Verhindere Löschen des Admin-Accounts
            if (user.id === 'admin' || user.id.startsWith('admin@')) {
                this.confirmModal = {
                    isOpen: true,
                    title: this.t('souvera_central', 'Aktion nicht möglich'),
                    message: this.t('souvera_central', 'Der Administrator-Account kann nicht gelöscht werden.'),
                    type: 'warning',
                    confirmText: this.t('souvera_central', 'OK'),
                    onConfirm: () => {
                        this.confirmModal.isOpen = false
                    }
                }
                return
            }

            this.confirmModal = {
                isOpen: true,
                title: this.t('souvera_central', 'Benutzer löschen?'),
                message: this.t(
                    'souvera_central',
                    'Möchten Sie den Benutzer "{user}" wirklich unwiderruflich löschen?',
                    { user: user.displayName }
                ),
                details: this.t(
                    'souvera_central',
                    'WARNUNG: Diese Aktion kann nicht rückgängig gemacht werden! Alle Daten des Benutzers werden dauerhaft gelöscht.'
                ),
                type: 'danger',
                confirmText: this.t('souvera_central', 'Ja, Benutzer löschen'),
                cancelText: this.t('souvera_central', 'Abbrechen'),
                onConfirm: async () => {
                    try {
                        const url = generateUrl('/apps/souvera_central/api/users/{id}', { id: user.id })
                        await axios.delete(url)

                        // Success Modal
                        this.confirmModal = {
                            isOpen: true,
                            title: this.t('souvera_central', 'Benutzer gelöscht!'),
                            message: this.t('souvera_central', 'Der Benutzer wurde erfolgreich gelöscht.'),
                            details: this.t('souvera_central', '"{user}" wurde dauerhaft entfernt.', {
                                user: user.displayName
                            }),
                            type: 'success',
                            confirmText: this.t('souvera_central', 'OK'),
                            cancelText: '',
                            onConfirm: () => {
                                this.closeConfirmModal()
                                this.loadUsers()
                            }
                        }
                    } catch (error) {
                        let errorMessage = this.t('souvera_central', 'Fehler beim Löschen')
                        if (error.response?.data?.ocs?.data?.error) {
                            errorMessage = error.response.data.ocs.data.error
                        } else if (error.response?.data?.error) {
                            errorMessage = error.response.data.error
                        }

                        // Error Modal
                        this.confirmModal = {
                            isOpen: true,
                            title: this.t('souvera_central', 'Fehler beim Löschen'),
                            message: errorMessage,
                            details: '',
                            type: 'danger',
                            confirmText: this.t('souvera_central', 'OK'),
                            cancelText: '',
                            onConfirm: () => {
                                this.closeConfirmModal()
                            }
                        }
                    }
                }
            }
        },

        closeConfirmModal() {
            this.confirmModal.isOpen = false
        }
    }
}
</script>

<style scoped>
.user-management-container {
    height: 100%;
}

.users-list-view {
    padding: 30px;
    max-width: none;
    margin: 0 auto;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: none;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.header-content h2 {
    margin: 0;
    font-size: 28px;
    font-weight: 600;
}

.license-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--color-background-dark);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    font-size: 14px;
    font-weight: 500;
    color: var(--color-main-text);
}

.license-status .material-design-icon {
    color: var(--color-text-maxcontrast);
}

/* Search Bar */
.search-bar {
    margin-bottom: 20px;
}

/* Loading State */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px;
    gap: 15px;
}

.loading-state p {
    color: var(--color-text-lighter);
}

/* Table Container */
.table-container {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    margin-bottom: 20px;
    overflow: hidden;
}

/* User Table */
.users-table-wrapper {
    overflow-x: auto;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
    color: var(--color-main-text);
    table-layout: auto;
}

.users-table thead th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-maxcontrast);
    background: var(--color-background-dark);
    border-bottom: 1px solid var(--color-border);
}

.users-table tbody tr {
    border-bottom: 1px solid var(--color-border);
    transition: background-color 0.15s ease;
}

.users-table tbody tr:hover {
    background: var(--color-background-hover);
    cursor: pointer;
}

.users-table tbody tr:last-child {
    border-bottom: none;
}

.users-table td {
    padding: 12px 16px;
    vertical-align: middle;
    text-align: left;
    word-break: break-word;
    white-space: normal;
}

/* Columns */
.user-column {
    width: 250px;
}

.displayname-column {
    width: 300px;
}

.quota-column {
    width: 150px;
    color: var(--color-text-maxcontrast);
}

.mailbox-usage-column {
    min-width: 160px;
}

.mb-usage {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.mb-usage-bar {
    height: 6px;
    width: 100%;
    max-width: 140px;
    border-radius: var(--border-radius-pill, 100px);
    background: var(--color-background-dark);
    overflow: hidden;
}

.mb-usage-fill {
    height: 100%;
    border-radius: inherit;
    background: var(--color-primary-element);
    transition: width 0.3s ease, background-color 0.2s ease;
}

.mb-usage-fill.is-warn {
    background: var(--color-warning);
}

.mb-usage-fill.is-full {
    background: var(--color-error);
}

.mb-usage-text {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    white-space: nowrap;
}

.mb-usage-none {
    color: var(--color-text-maxcontrast);
}

.status-column {
    width: 120px;
    text-align: left;
}

.actions-column {
    width: 120px;
    text-align: right;
}

/* User Info */
.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.row-user-icon {
    color: var(--color-text-maxcontrast);
    flex-shrink: 0;
}

.username {
    font-weight: 500;
    display: inline-block;
    max-width: 185px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: middle;
}

/* Generisch: Zellinhalt einzeilig kürzen + voller Text via title-Tooltip */
.cell-ellipsis {
    display: inline-block;
    max-width: 280px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: middle;
}

.mailbox-indicator {
    flex-shrink: 0;
}

.mailbox-indicator.has-mailbox {
    color: var(--color-success);
}

.mailbox-indicator.no-mailbox {
    color: var(--color-text-maxcontrast);
    opacity: 0.6;
}

.user-actions button.action-mailbox:hover:not(:disabled) {
    background: rgba(var(--color-success-rgb), 0.12);
    border-color: var(--color-success);
    color: var(--color-success-text);
}

.user-actions button.action-makeadmin:hover:not(:disabled) {
    background: rgba(var(--color-warning-rgb, 230, 160, 0), 0.15);
    border-color: var(--color-warning);
    color: var(--color-main-text);
}

.user-actions button.action-removeadmin:hover:not(:disabled) {
    background: rgba(var(--color-error-rgb), 0.12);
    border-color: var(--color-error);
    color: var(--color-error-text);
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: var(--border-radius-pill);
    font-size: 13px;
    font-weight: 600;
    line-height: 1.4;
}

.status-badge.status-active {
    background: rgba(var(--color-success-rgb), 0.12);
    color: var(--color-success-text);
    border: 1px solid rgba(var(--color-success-rgb), 0.35);
}

.status-badge.status-inactive {
    background: rgba(var(--color-error-rgb), 0.12);
    color: var(--color-error-text);
    border: 1px solid rgba(var(--color-error-rgb), 0.35);
}

.status-text {
    font-weight: 600;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: var(--border-radius-pill);
    font-size: 13px;
    font-weight: 600;
    line-height: 1.4;
    white-space: nowrap;
}

.type-badge.type-souvera {
    background: rgba(var(--color-primary-element-rgb, 0, 130, 201), 0.12);
    color: var(--color-primary-element);
    border: 1px solid rgba(var(--color-primary-element-rgb, 0, 130, 201), 0.35);
}

.type-badge.type-nextcloud {
    background: var(--color-background-dark);
    color: var(--color-text-maxcontrast);
    border: 1px solid var(--color-border);
}

.type-cell {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.type-badge.type-admin {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(var(--color-warning-rgb, 230, 160, 0), 0.15);
    color: var(--color-main-text);
    border: 1px solid var(--color-warning);
}


/* Actions */
.user-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.user-actions button {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    cursor: pointer;
    padding: 0;
    transition: background-color 0.15s, border-color 0.15s, color 0.15s;
    color: var(--color-main-text);
}

.user-actions button:hover {
    background: var(--color-primary-element-light);
    border-color: var(--color-primary-element);
    color: var(--color-primary-element-light-text);
}

.user-actions button.action-delete:hover {
    background: var(--color-error);
    border-color: var(--color-error);
    color: #fff;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: var(--color-text-lighter);
}

.empty-state .icon-large {
    font-size: 64px;
    opacity: 0.3;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--color-text-light);
}

.empty-state p {
    margin: 0;
}

/* KRITISCHES WARNING BANNER */
.critical-warning {
    margin-bottom: 30px;
    padding: 25px 30px;
    background: var(--color-error);
    border: 2px solid var(--color-error);
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.critical-warning .warning-content {
    display: flex;
    align-items: center;
    gap: 20px;
    color: #fff;
}

.critical-warning .warning-icon {
    flex-shrink: 0;
    animation: pulse 2s infinite;
    color: #fff;
}

@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.6;
    }
}

.critical-warning .warning-text {
    flex: 1;
}

.critical-warning h3 {
    margin: 0 0 8px;
    font-size: 20px;
    font-weight: 700;
    color: #fff;
}

.critical-warning p {
    margin: 0;
    font-size: 15px;
    line-height: 1.5;
    color: #fff;
}

.contact-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: #fff;
    color: var(--color-error);
    border: 2px solid #fff;
    border-radius: 6px;
    font-weight: 700;
    font-size: 15px;
    text-decoration: none;
    white-space: nowrap;
    transition: background-color 0.2s, transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 8px var(--color-box-shadow);
}

.contact-button:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px var(--color-box-shadow);
}

/* WARNING BANNER (80%+) */
.warning-banner {
    margin-bottom: 30px;
    padding: 20px 25px;
    background: var(--color-warning);
    border: 2px solid var(--color-warning);
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.warning-banner .warning-content {
    display: flex;
    align-items: center;
    gap: 20px;
    color: #fff;
}

.warning-banner .warning-icon {
    flex-shrink: 0;
    color: #fff;
}

.warning-banner .warning-text {
    flex: 1;
}

.warning-banner h3 {
    margin: 0 0 5px;
    font-size: 18px;
    font-weight: 700;
    color: #fff;
}

.warning-banner p {
    margin: 0;
    font-size: 14px;
    color: #fff;
    font-weight: 500;
}

.contact-button.secondary {
    background: #fff;
    color: var(--color-warning);
    border: none;
    box-shadow: 0 2px 6px var(--color-box-shadow);
}

.contact-button.secondary:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px var(--color-box-shadow);
}

/* License Status Badge Colors */
.license-status.license-warning {
    background: var(--color-warning);
    color: #fff;
    border: 1px solid var(--color-warning);
    font-weight: 600;
}

.license-status.license-warning .material-design-icon,
.license-status.license-critical .material-design-icon {
    color: #fff;
}

.license-status.license-critical {
    background: var(--color-error);
    color: #fff;
    border: 1px solid var(--color-error);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .user-management-container {
        padding: 20px;
    }

    .page-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }

    .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .page-header button.primary {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .user-management-container {
        padding: 15px;
    }

    .page-header h2 {
        font-size: 24px;
    }

    .table-container {
        padding: 12px;
        overflow-x: auto;
    }

    .users-table {
        font-size: 0.85rem;
        min-width: 600px;
    }

    .users-table th,
    .users-table td {
        padding: 8px 10px;
    }

    .user-actions {
        gap: 6px;
    }

    .user-actions button {
        padding: 6px;
        min-width: 32px;
        height: 32px;
    }

    .status-badge {
        font-size: 12px;
        padding: 4px 10px;
    }

    .warning-banner,
    .critical-warning {
        padding: 15px 20px;
    }

    .warning-banner .warning-content,
    .critical-warning .warning-content {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }

    .warning-icon {
        font-size: 36px !important;
        background-size: 36px 36px !important;
        width: 36px !important;
        height: 36px !important;
    }
}

@media (max-width: 480px) {
    .user-management-container {
        padding: 10px;
    }

    .users-list-view {
        padding: 0;
    }

    .page-header {
        padding: 0;
        margin-bottom: 15px;
    }

    .page-header h2 {
        font-size: 20px;
    }

    .license-status {
        font-size: 12px;
        padding: 6px 12px;
    }

    .users-table {
        font-size: 0.8rem;
    }

    .users-table th,
    .users-table td {
        padding: 6px 8px;
    }

    .user-info {
        gap: 6px;
    }

    .user-info .icon-user {
        display: none;
    }

    .user-actions button {
        padding: 4px;
        min-width: 28px;
        height: 28px;
    }

    .status-badge {
        font-size: 11px;
        padding: 3px 8px;
    }

    .status-badge .icon-checkmark,
    .status-badge .icon-close {
        display: none;
    }

    .warning-banner h3,
    .critical-warning h3 {
        font-size: 16px;
    }

    .warning-banner p,
    .critical-warning p {
        font-size: 13px;
    }

    .contact-button,
    .contact-button.secondary {
        padding: 10px 20px;
        font-size: 14px;
    }
}
</style>
