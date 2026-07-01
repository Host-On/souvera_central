<template>
    <div class="group-management-container">
        <!-- Group Editor (Single Page) -->
        <GroupEditor
            v-if="showEditor"
            :group="selectedGroup"
            @close="closeEditor"
            @saved="handleGroupSaved" />

        <!-- Haupt-Bereich mit Gruppen-Liste -->
        <div v-else class="groups-list-view">
            <!-- Header -->
            <div class="page-header">
                <div class="header-content">
                    <h2>{{ t('souvera_central', 'Gruppenverwaltung') }}</h2>
                    <div
                        class="license-status"
                        :class="{ 'license-warning': isGroupWarning, 'license-critical': isGroupLimitReached }"
                    >
                        <AccountGroup :size="18" />
                        <span class="license-info">{{ totalGroups }} von {{ maxGroups }} Gruppen</span>
                    </div>
                </div>
                <button
                    class="primary"
                    :disabled="isGroupLimitReached"
                    :title="isGroupLimitReached ? t('souvera_central', 'Limit erreicht') : ''"
                    @click="createNewGroup"
                >
                    <Plus :size="20" />
                    {{ t('souvera_central', 'Neue Gruppe') }}
                </button>
            </div>

            <!-- KRITISCHES WARNING: Limit erreicht -->
            <div v-if="isGroupLimitReached" class="critical-warning">
                <div class="warning-content">
                    <AlertOctagon class="warning-icon" :size="48" />
                    <div class="warning-text">
                        <h3>{{ t('souvera_central', 'Gruppenlimit erreicht!') }}</h3>
                        <p>
                            {{
                                t(
                                    'souvera_central',
                                    'Sie haben {count} von {total} Gruppen erstellt. Es können keine weiteren Gruppen erstellt werden.',
                                    { count: totalGroups, total: maxGroups }
                                )
                            }}
                        </p>
                    </div>
                    <a :href="contactUrl" target="_blank" class="contact-button">
                        <OpenInNew :size="18" />
                        {{ t('souvera_central', 'Limit erweitern') }}
                    </a>
                </div>
            </div>

            <!-- WARNING: Limit bald erreicht -->
            <div v-else-if="isGroupWarning" class="warning-banner">
                <div class="warning-content">
                    <AlertCircle class="warning-icon" :size="40" />
                    <div class="warning-text">
                        <h3>{{ t('souvera_central', 'Gruppenlimit bald erreicht') }}</h3>
                        <p>
                            {{
                                t(
                                    'souvera_central',
                                    'Sie haben {count} von {total} Gruppen erstellt ({percentage}%).',
                                    { count: totalGroups, total: maxGroups, percentage: groupPercentage }
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
                    :placeholder="t('souvera_central', 'Suche nach Gruppenname...')"
                    @search="handleSearch"
                />
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading-state">
                <NcLoadingIcon :size="32" />
                <p>{{ t('souvera_central', 'Lade Gruppen...') }}</p>
            </div>

            <!-- Groups Table -->
            <div v-else-if="groups.length > 0" class="table-container">
                <div class="groups-table-wrapper">
                    <table class="groups-table">
                        <thead>
                            <tr>
                                <th class="group-column">{{ t('souvera_central', 'Gruppenname') }}</th>
                                <th class="displayname-column">{{ t('souvera_central', 'Anzeigename') }}</th>
                                <th class="members-column">{{ t('souvera_central', 'Mitglieder') }}</th>
                                <th class="actions-column">{{ t('souvera_central', 'Aktionen') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="group in groups"
                                :key="group.id"
                                class="group-row">
                                <td class="group-column">
                                    <div class="group-info">
                                        <AccountGroup class="row-group-icon" :size="20" />
                                        <span class="groupname">{{ group.id }}</span>
                                        <span
                                            v-if="group.isProtected"
                                            class="protected-badge"
                                            :title="t('souvera_central', 'Systemgruppe')"
                                        >
                                            <ShieldLock :size="14" />
                                        </span>
                                    </div>
                                </td>
                                <td class="displayname-column">{{ group.displayName }}</td>
                                <td class="members-column">
                                    <div class="members-count">
                                        <Account :size="18" />
                                        <span>{{ group.userCount }}</span>
                                    </div>
                                </td>
                                <td class="actions-column">
                                    <div class="group-actions">
                                        <button
                                            class="action-edit"
                                            :title="t('souvera_central', 'Bearbeiten')"
                                            @click="editGroup(group)"
                                        >
                                            <Pencil :size="18" />
                                        </button>
                                        <button
                                            class="action-delete"
                                            :title="
                                                group.isProtected
                                                    ? t('souvera_central', 'Systemgruppe kann nicht gelöscht werden')
                                                    : t('souvera_central', 'Löschen')
                                            "
                                            :disabled="group.isProtected"
                                            @click="deleteGroup(group)"
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
                    :total="totalGroups"
                    @page-change="handlePageChange"
                    @per-page-change="handlePerPageChange"
                />
            </div>

            <!-- Empty State -->
            <div v-else class="empty-state">
                <AccountGroup class="empty-icon" :size="64" />
                <h3 v-if="searchQuery">{{ t('souvera_central', 'Keine Gruppen gefunden') }}</h3>
                <h3 v-else>{{ t('souvera_central', 'Noch keine Gruppen') }}</h3>
                <p v-if="searchQuery">{{ t('souvera_central', 'Versuchen Sie einen anderen Suchbegriff.') }}</p>
                <p v-else>{{ t('souvera_central', 'Erstellen Sie Ihre erste Gruppe um zu starten.') }}</p>
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
import GroupEditor from './components/GroupEditor.vue'
import SearchField from '../../components/SearchField.vue'
import Pagination from '../../components/Pagination.vue'
import ConfirmationModal from '../../components/ConfirmationModal.vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import Account from 'vue-material-design-icons/Account.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import ShieldLock from 'vue-material-design-icons/ShieldLock.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import AlertOctagon from 'vue-material-design-icons/AlertOctagon.vue'

export default {
    name: 'GroupManagement',

    components: {
        GroupEditor,
        SearchField,
        Pagination,
        ConfirmationModal,
        NcLoadingIcon,
        Account,
        AccountGroup,
        ShieldLock,
        Pencil,
        Delete,
        Plus,
        OpenInNew,
        AlertCircle,
        AlertOctagon
    },

    data() {
        return {
            groups: [],
            totalGroups: 0,
            maxGroups: 20,
            warningThreshold: 0.8,
            loading: true,
            selectedGroup: null,
            showEditor: false,
            searchQuery: '',
            currentPage: 1,
            perPage: 20,
            settings: {
                sorting: {
                    groups: 'displayName'
                }
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
            },
            resellerInfo: {
                support_url: null,
                url: null,
                name: null
            }
        }
    },

    computed: {
        groupPercentage() {
            if (this.maxGroups === 0) return 0
            return Math.round((this.totalGroups / this.maxGroups) * 100)
        },

        isGroupLimitReached() {
            return this.totalGroups >= this.maxGroups
        },

        isGroupWarning() {
            return !this.isGroupLimitReached &&
                this.totalGroups / this.maxGroups >= this.warningThreshold
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
        this.loadSettings()
        this.loadGroups()
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
            // Prüfe ob wir von /groups/new oder /groups/edit/:id kommen
            const appElement = document.getElementById('app-souvera-user-management')
            const action = appElement?.getAttribute('data-action') || ''
            const groupId = appElement?.getAttribute('data-group-id') || ''

            if (action === 'new') {
                this.createNewGroup()
            } else if (action === 'edit' && groupId) {
                // Lade Gruppe und öffne Editor
                this.loadAndEditGroup(groupId)
            }
        },

        handleUrlChange() {
            // Reagiere auf URL-Änderungen (Browser Back/Forward, Sidebar-Klicks)
            const path = window.location.pathname

            // Check für /groups/new
            if (path.includes('/groups/new')) {
                if (!this.showEditor || this.selectedGroup !== null) {
                    this.selectedGroup = null
                    this.showEditor = true
                }
                return
            }

            // Check für /groups/edit/:id
            const editMatch = path.match(/\/groups\/edit\/([^/]+)/)
            if (editMatch && editMatch[1]) {
                const groupId = decodeURIComponent(editMatch[1])
                // Nur neu laden wenn andere Gruppe oder Editor geschlossen
                if (!this.showEditor || this.selectedGroup?.id !== groupId) {
                    this.loadAndEditGroup(groupId)
                }
                return
            }

            // Wenn URL zu /groups (ohne /new oder /edit/:id) geht → Editor schließen
            if (path.endsWith('/groups') || path === generateUrl('/apps/souvera_central/groups')) {
                if (this.showEditor) {
                    this.showEditor = false
                    this.selectedGroup = null
                }
            }
        },

        async loadAndEditGroup(groupId) {
            try {
                const url = generateUrl('/apps/souvera_central/api/groups/manage/{id}', { id: groupId })
                const response = await axios.get(url)

                const data = response.data.ocs?.data || response.data.data || response.data
                this.selectedGroup = data
                this.showEditor = true
            } catch (error) {
                this.showError(error, this.t('souvera_central', 'Fehler beim Laden der Gruppe'))
            }
        },

        async loadSettings() {
            try {
                const url = generateUrl('/apps/souvera_central/api/settings')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data

                if (data && data.sorting) {
                    this.settings.sorting = data.sorting
                }
            } catch (error) {
                // Fallback auf Default-Sortierung
            }
        },

        async loadGroups() {
            try {
                this.loading = true
                const offset = (this.currentPage - 1) * this.perPage

                const url = generateUrl('/apps/souvera_central/api/groups/manage')
                const response = await axios.get(url, {
                    params: {
                        search: this.searchQuery,
                        limit: this.perPage,
                        offset: offset
                    }
                })

                const data = response.data.ocs?.data || response.data.data || response.data
                let groups = data.groups || []

                // Sortierung anwenden (nur auf aktuell geladene Gruppen)
                groups = this.sortGroups(groups)

                this.groups = groups
                this.totalGroups = data.total || 0

                // Limits aus API-Response übernehmen
                if (data.maxGroups !== undefined) {
                    this.maxGroups = data.maxGroups
                }
                if (data.warningThreshold !== undefined) {
                    this.warningThreshold = data.warningThreshold
                }

                // Emit total group count to parent (für Dashboard)
                this.$emit('groups-loaded', this.totalGroups)
            } catch (error) {
                this.showError(error, this.t('souvera_central', 'Fehler beim Laden der Gruppen'))
            } finally {
                this.loading = false
            }
        },

        sortGroups(groups) {
            const sortMode = this.settings.sorting.groups || 'displayName'

            return [...groups].sort((a, b) => {
                if (sortMode === 'id') {
                    return a.id.localeCompare(b.id)
                } else if (sortMode === 'userCount') {
                    return (b.userCount || 0) - (a.userCount || 0) // Absteigende Sortierung
                } else {
                    // displayName (default)
                    return (a.displayName || a.id).localeCompare(b.displayName || b.id)
                }
            })
        },

        handleSearch(query) {
            this.searchQuery = query
            this.currentPage = 1 // Zurück zur ersten Seite bei neuer Suche
            this.loadGroups()
        },

        handlePageChange(page) {
            this.currentPage = page
            this.loadGroups()
        },

        handlePerPageChange(perPage) {
            this.perPage = perPage
            this.currentPage = 1 // Zurück zur ersten Seite
            this.loadGroups()
        },

        createNewGroup() {
            // Navigate to /groups/new
            const url = generateUrl('/apps/souvera_central/groups/new')
            window.history.pushState({}, '', url)

            this.selectedGroup = null
            this.showEditor = true
        },

        editGroup(group) {
            // Navigate to /groups/edit/:id
            const url = generateUrl('/apps/souvera_central/groups/edit/{id}', { id: group.id })
            window.history.pushState({}, '', url)

            this.selectedGroup = group
            this.showEditor = true
        },

        closeEditor() {
            // Navigate zurück zu /groups
            const url = generateUrl('/apps/souvera_central/groups')
            window.history.pushState({ route: 'groups' }, '', url)

            // Editor schließen (OHNE Liste neu zu laden - nur bei Abbruch)
            this.showEditor = false
            this.selectedGroup = null

            // Dispatch event damit andere Komponenten reagieren können
            window.dispatchEvent(new CustomEvent('route-changed', { detail: { route: 'groups', url } }))
        },

        async handleGroupSaved() {
            // Navigate zurück zu /groups
            const url = generateUrl('/apps/souvera_central/groups')
            window.history.pushState({ route: 'groups' }, '', url)

            // Editor schließen
            this.showEditor = false
            this.selectedGroup = null

            // Liste komplett neu laden (NUR bei erfolgreichem Speichern)
            await this.loadGroups()

            // Dispatch event damit andere Komponenten reagieren können
            window.dispatchEvent(new CustomEvent('route-changed', { detail: { route: 'groups', url } }))
        },

        deleteGroup(group) {
            if (group.isProtected) {
                this.confirmModal = {
                    isOpen: true,
                    title: this.t('souvera_central', 'Systemgruppe'),
                    message: this.t('souvera_central', 'Systemgruppen können nicht gelöscht werden'),
                    details: this.t('souvera_central', 'Die Gruppe "{group}" ist eine geschützte Systemgruppe.', {
                        group: group.displayName
                    }),
                    type: 'warning',
                    confirmText: this.t('souvera_central', 'OK'),
                    cancelText: '',
                    onConfirm: () => {
                        this.closeConfirmModal()
                    }
                }
                return
            }

            this.confirmModal = {
                isOpen: true,
                title: this.t('souvera_central', 'Gruppe löschen?'),
                message: this.t('souvera_central', 'Möchten Sie die Gruppe "{group}" wirklich löschen?', {
                    group: group.displayName
                }),
                details: this.t('souvera_central', 'WARNUNG: Diese Aktion kann nicht rückgängig gemacht werden!'),
                type: 'danger',
                confirmText: this.t('souvera_central', 'Ja, Gruppe löschen'),
                cancelText: this.t('souvera_central', 'Abbrechen'),
                onConfirm: async () => {
                    try {
                        const url = generateUrl('/apps/souvera_central/api/groups/manage/{id}', { id: group.id })
                        await axios.delete(url)

                        // Success Modal
                        this.confirmModal = {
                            isOpen: true,
                            title: this.t('souvera_central', 'Gruppe gelöscht!'),
                            message: this.t('souvera_central', 'Die Gruppe wurde erfolgreich gelöscht.'),
                            details: this.t('souvera_central', '"{group}" wurde entfernt.', {
                                group: group.displayName
                            }),
                            type: 'success',
                            confirmText: this.t('souvera_central', 'OK'),
                            cancelText: '',
                            onConfirm: () => {
                                this.closeConfirmModal()
                                this.loadGroups()
                            }
                        }
                    } catch (error) {
                        const errorMessage = this.getErrorMessage(
                            error,
                            this.t('souvera_central', 'Fehler beim Löschen')
                        )

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
        },

        getErrorMessage(error, defaultMessage) {
            if (error.response?.data?.ocs?.data?.error) {
                return error.response.data.ocs.data.error
            } else if (error.response?.data?.error) {
                return error.response.data.error
            }
            return defaultMessage
        },

        showError(error, defaultMessage) {
            const errorMessage = this.getErrorMessage(error, defaultMessage)
            alert(errorMessage)
        }
    }
}
</script>

<style scoped>
.group-management-container {
    height: 100%;
}

.groups-list-view {
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

/* Contact Button */
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
    overflow: hidden;
    margin-bottom: 20px;
}

/* Groups Table */
.groups-table-wrapper {
    overflow-x: auto;
}

.groups-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
    color: var(--color-main-text);
    table-layout: auto;
}

.groups-table thead th {
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

.groups-table tbody tr {
    border-bottom: 1px solid var(--color-border);
    transition: background-color 0.15s ease;
}

.groups-table tbody tr:hover {
    background: var(--color-background-hover);
}

.groups-table tbody tr:last-child {
    border-bottom: none;
}

.groups-table td {
    padding: 12px 16px;
    vertical-align: middle;
    text-align: left;
    word-break: break-word;
    white-space: normal;
}

/* Columns */
.group-column {
    width: 300px;
}

.displayname-column {
    width: 350px;
}

.members-column {
    width: 150px;
}

.actions-column {
    width: 120px;
    text-align: right;
}

/* Group Info */
.group-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.row-group-icon {
    color: var(--color-text-maxcontrast);
}

.groupname {
    font-weight: 500;
}

.protected-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 7px;
    background: rgba(var(--color-warning-rgb), 0.15);
    color: var(--color-warning-text);
    border-radius: var(--border-radius);
    font-size: 12px;
}

/* Members Count */
.members-count {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-text-maxcontrast);
}

/* Actions */
.group-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.group-actions button {
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

.group-actions button:hover:not(:disabled) {
    background: var(--color-primary-element-light);
    border-color: var(--color-primary-element);
    color: var(--color-primary-element-light-text);
}

.group-actions button.action-delete:hover:not(:disabled) {
    background: var(--color-error);
    border-color: var(--color-error);
    color: #fff;
}

.group-actions button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
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

/* Responsive Design */
@media (max-width: 768px) {
    .groups-list-view {
        padding: 15px;
    }

    .page-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }

    .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .groups-table-wrapper {
        overflow-x: scroll;
    }

    .group-column {
        min-width: 200px;
    }

    .displayname-column {
        min-width: 200px;
    }

    .page-header button.primary {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .group-management-container {
        padding: 15px;
    }

    .page-header {
        padding: 15px;
        flex-direction: column;
        gap: 15px;
    }

    .page-header h2 {
        font-size: 24px;
    }

    .table-container {
        padding: 12px;
    }

    .groups-table {
        font-size: 0.85rem;
    }

    .groups-table th,
    .groups-table td {
        padding: 8px 10px;
    }

    .group-actions {
        gap: 6px;
    }

    .group-actions button {
        padding: 6px;
        min-width: 32px;
        height: 32px;
    }
}

@media (max-width: 480px) {
    .group-management-container {
        padding: 10px;
    }

    .page-header {
        padding: 10px;
    }

    .page-header h2 {
        font-size: 20px;
    }

    .group-count-badge {
        font-size: 12px;
        padding: 6px 12px;
    }

    .groups-table {
        font-size: 0.8rem;
    }

    .groups-table th,
    .groups-table td {
        padding: 6px 8px;
    }

    .group-info .icon-group {
        display: none;
    }

    .group-actions button {
        padding: 4px;
        min-width: 28px;
        height: 28px;
    }

    .protected-badge {
        display: none;
    }
}
</style>
