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
                        <span class="icon-quota"></span>
                        <span class="license-info">{{ totalUsers }} von {{ licenseTotal }} Lizenzen genutzt</span>
                    </div>
                </div>
                <button
                    class="primary"
                    @click="createNewUser"
                    :disabled="isLicenseLimitReached"
                    :title="isLicenseLimitReached ? t('souvera_central', 'Lizenzlimit erreicht') : ''"
                >
                    <span class="icon-add"></span>
                    {{ t('souvera_central', 'Neuer Benutzer') }}
                </button>
            </div>

            <!-- KRITISCHES WARNING: Lizenzlimit erreicht -->
            <div v-if="isLicenseLimitReached" class="critical-warning">
                <div class="warning-content">
                    <span class="icon-error warning-icon"></span>
                    <div class="warning-text">
                        <h3>{{ t('souvera_central', 'Lizenzlimit erreicht!') }}</h3>
                        <p>
                            {{
                                t(
                                    'souvera_central',
                                    'Sie haben {count} von {total} Lizenzen genutzt. Es können keine weiteren Benutzer erstellt werden.',
                                    { count: totalUsers, total: licenseTotal }
                                )
                            }}
                        </p>
                    </div>
                    <a href="https://www.host-on.de/de/kontakt" target="_blank" class="contact-button">
                        <span class="icon-external"></span>
                        {{ t('souvera_central', 'Lizenzen erweitern') }}
                    </a>
                </div>
            </div>

            <!-- WARNING: Lizenzlimit bald erreicht (80%+) -->
            <div v-else-if="isLicenseWarning" class="warning-banner">
                <div class="warning-content">
                    <span class="icon-alert warning-icon"></span>
                    <div class="warning-text">
                        <h3>{{ t('souvera_central', 'Lizenzlimit bald erreicht') }}</h3>
                        <p>
                            {{
                                t(
                                    'souvera_central',
                                    'Sie haben {count} von {total} Lizenzen genutzt ({percentage}%). Erweitern Sie rechtzeitig Ihre Lizenzen.',
                                    { count: totalUsers, total: licenseTotal, percentage: licensePercentage }
                                )
                            }}
                        </p>
                    </div>
                    <a href="https://www.host-on.de/de/kontakt" target="_blank" class="contact-button secondary">
                        <span class="icon-external"></span>
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
                <div class="icon-loading"></div>
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
                                <th class="email-column">{{ t('souvera_central', 'E-Mail') }}</th>
                                <th class="quota-column">{{ t('souvera_central', 'Speicherplatz') }}</th>
                                <th class="status-column">{{ t('souvera_central', 'Status') }}</th>
                                <th class="actions-column">{{ t('souvera_central', 'Aktionen') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users" :key="user.id" class="user-row">
                                <td class="user-column">
                                    <div class="user-info">
                                        <span class="icon-user"></span>
                                        <span class="username">{{ user.id }}</span>
                                    </div>
                                </td>
                                <td class="displayname-column">{{ user.displayName }}</td>
                                <td class="email-column">{{ user.email || '-' }}</td>
                                <td class="quota-column">{{ user.quota.quota }}</td>
                                <td class="status-column">
                                    <div class="status-indicator">
                                        <span
                                            :class="['status-icon', user.enabled ? 'icon-checkmark-color' : 'icon-close']"
                                            :title="user.enabled ? t('souvera_central', 'Aktiv') : t('souvera_central', 'Inaktiv')"
                                        ></span>
                                    </div>
                                </td>
                                <td class="actions-column">
                                    <div class="user-actions">
                                        <button
                                            :title="t('souvera_central', 'Bearbeiten')"
                                            @click.stop="editUser(user)"
                                        >
                                            <span class="icon-rename"></span>
                                        </button>
                                        <button
                                            v-if="user.id !== currentUserId"
                                            :title="t('souvera_central', 'Löschen')"
                                            @click.stop="deleteUser(user)"
                                        >
                                            <span class="icon-delete"></span>
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
                <div class="icon-user icon-large"></div>
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

export default {
    name: 'UserManagement',

    components: {
        UserEditor,
        SearchField,
        Pagination,
        ConfirmationModal
    },

    props: {
        allowedDomains: {
            type: Array,
            default: () => []
        },
        licenseTotal: {
            type: Number,
            default: 10
        }
    },

    computed: {
        isLicenseLimitReached() {
            return this.totalUsers >= this.licenseTotal
        },

        licensePercentage() {
            if (this.licenseTotal === 0) return 0
            return Math.round((this.totalUsers / this.licenseTotal) * 100)
        },

        isLicenseWarning() {
            return !this.isLicenseLimitReached && this.totalUsers / this.licenseTotal >= 0.8
        }
    },

    data() {
        return {
            users: [],
            totalUsers: 0,
            loading: true,
            selectedUser: null,
            showEditor: false,
            searchQuery: '',
            currentPage: 1,
            perPage: 20,
            currentUserId: null, // ID des aktuell angemeldeten Benutzers
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

    mounted() {
        this.loadCurrentUser()
        this.loadUsers()
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
                console.log('Aktueller Benutzer geladen:', this.currentUserId)
            } catch (error) {
                console.error('Fehler beim Laden des aktuellen Benutzers:', error)
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
            console.log('URL changed to:', path)

            // Wenn URL zu /users (ohne /new oder /edit/:id) geht → Editor schließen
            if (path.endsWith('/users') || path === generateUrl('/apps/souvera_central/users')) {
                if (this.showEditor) {
                    console.log('Closing editor due to URL change')
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
                console.error('Fehler beim Laden des Benutzers:', error)
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

                // Fix groups: Convert object to array
                this.users = users.map((user) => ({
                    ...user,
                    groups: Array.isArray(user.groups) ? user.groups : Object.values(user.groups || {})
                }))

                // Emit total user count to parent (für Dashboard)
                this.$emit('users-loaded', this.totalUsers)

                console.log('Geladene Benutzer:', this.users.length, 'von', this.totalUsers)
            } catch (error) {
                console.error('Fehler beim Laden der Benutzer:', error)
            } finally {
                this.loading = false
            }
        },

        handleSearch(query) {
            console.log('Suche:', query)
            this.searchQuery = query
            this.currentPage = 1 // Zurück zur ersten Seite bei neuer Suche
            this.loadUsers()
        },

        handlePageChange(page) {
            console.log('Seite wechseln:', page)
            this.currentPage = page
            this.loadUsers()
        },

        handlePerPageChange(perPage) {
            console.log('Pro-Seite ändern:', perPage)
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
            window.history.pushState({}, '', url)

            this.showEditor = false
            this.selectedUser = null
        },

        async handleUserSaved() {
            // Navigate zurück zu /users
            const url = generateUrl('/apps/souvera_central/users')
            window.history.pushState({}, '', url)

            this.showEditor = false
            this.selectedUser = null
            await this.loadUsers()
        },

        deleteUser(user) {
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
                            details: this.t(
                                'souvera_central',
                                '"{user}" wurde dauerhaft entfernt.',
                                { user: user.displayName }
                            ),
                            type: 'success',
                            confirmText: this.t('souvera_central', 'OK'),
                            cancelText: '',
                            onConfirm: () => {
                                this.closeConfirmModal()
                                this.loadUsers()
                            }
                        }
                    } catch (error) {
                        console.error('Fehler beim Löschen:', error)

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
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--color-border);
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
    border-radius: var(--border-radius-large);
    font-size: 14px;
    font-weight: 500;
}

.license-status .icon-quota {
    opacity: 0.7;
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
    border-radius: var(--border-radius-large);
    overflow: hidden;
    box-shadow: 0 0 3px var(--color-box-shadow);
}

/* User Table */
.users-table-wrapper {
    overflow-x: auto;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
}

.users-table thead {
    background: var(--color-background-dark);
    border-bottom: 1px solid var(--color-border);
}

.users-table th {
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: var(--color-text-lighter);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.users-table tbody tr {
    border-bottom: 1px solid var(--color-border);
    transition: background-color 0.2s;
}

.users-table tbody tr:last-child {
    border-bottom: none;
}

.users-table td {
    padding: 16px 12px;
    vertical-align: middle;
}

/* Columns */
.user-column {
    width: 180px;
}

.displayname-column {
    width: 200px;
}

.email-column {
    width: 250px;
}

.quota-column {
    width: 120px;
}

.status-column {
    width: 100px;
}

.actions-column {
    width: 100px;
    text-align: right;
}

/* User Info */
.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.username {
    font-weight: 500;
}

/* Status Indicator */
.status-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
}

.status-indicator .status-icon {
    font-size: 24px;
    opacity: 1;
}

.status-indicator .icon-checkmark-color {
    color: var(--color-success);
}

.status-indicator .icon-close {
    color: var(--color-error);
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
    background: var(--color-background-dark);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    cursor: pointer;
    padding: 0;
    transition: all 0.2s;
    color: var(--color-main-text);
    opacity: 1;
}

.user-actions button:hover {
    background: var(--color-primary-element-light);
    border-color: var(--color-primary-element);
    transform: scale(1.1);
}

.user-actions button.icon-delete:hover {
    background: var(--color-error);
    border-color: var(--color-error);
    color: white;
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
    border-radius: var(--border-radius-large);
    box-shadow: 0 4px 12px var(--color-box-shadow);
}

.critical-warning .warning-content {
    display: flex;
    align-items: center;
    gap: 20px;
    color: var(--color-primary-element-text);
}

.critical-warning .warning-icon {
    font-size: 48px;
    flex-shrink: 0;
    animation: pulse 2s infinite;
    color: var(--color-primary-element-text);
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
    color: var(--color-primary-element-text);
}

.critical-warning p {
    margin: 0;
    font-size: 15px;
    line-height: 1.5;
    color: var(--color-primary-element-text);
    opacity: 0.95;
}

.contact-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: var(--color-main-background);
    color: var(--color-error);
    border: 2px solid var(--color-error);
    border-radius: var(--border-radius-large);
    font-weight: 700;
    font-size: 15px;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s;
    box-shadow: 0 2px 8px var(--color-box-shadow);
}

.contact-button:hover {
    background: var(--color-background-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px var(--color-box-shadow);
}

/* WARNING BANNER (80%+) */
.warning-banner {
    margin-bottom: 30px;
    padding: 20px 25px;
    background: var(--color-warning);
    border: 2px solid var(--color-warning);
    border-radius: var(--border-radius-large);
    box-shadow: 0 2px 8px var(--color-box-shadow);
}

.warning-banner .warning-content {
    display: flex;
    align-items: center;
    gap: 20px;
    color: var(--color-main-text);
}

.warning-banner .warning-icon {
    font-size: 36px;
    flex-shrink: 0;
    color: var(--color-main-text);
    opacity: 0.8;
}

.warning-banner .warning-text {
    flex: 1;
}

.warning-banner h3 {
    margin: 0 0 5px;
    font-size: 18px;
    font-weight: 600;
    color: var(--color-main-text);
}

.warning-banner p {
    margin: 0;
    font-size: 14px;
    color: var(--color-main-text);
}

.contact-button.secondary {
    background: var(--color-main-background);
    color: var(--color-main-text);
    border: 2px solid var(--color-main-text);
    box-shadow: 0 2px 6px var(--color-box-shadow);
}

.contact-button.secondary:hover {
    background: var(--color-background-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px var(--color-box-shadow);
}

/* License Status Badge Colors */
.license-status.license-warning {
    background: var(--color-warning);
    color: var(--color-main-text);
    border: 1px solid var(--color-warning);
}

.license-status.license-critical {
    background: var(--color-error);
    color: var(--color-primary-element-text);
    border: 1px solid var(--color-error);
}
</style>
