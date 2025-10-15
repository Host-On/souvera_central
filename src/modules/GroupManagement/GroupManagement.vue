<template>
    <div class="group-management-container">
        <!-- Group Editor (Single Page) -->
        <GroupEditor v-if="showEditor" :group="selectedGroup" @close="closeEditor" @saved="handleGroupSaved" />

        <!-- Haupt-Bereich mit Gruppen-Liste -->
        <div v-else class="groups-list-view">
            <!-- Header -->
            <div class="page-header">
                <div class="header-content">
                    <h2>{{ t('souvera_central', 'Gruppenverwaltung') }}</h2>
                    <div class="group-count-badge">
                        <span class="icon-group"></span>
                        <span class="group-count">{{ totalGroups }} {{ t('souvera_central', 'Gruppen') }}</span>
                    </div>
                </div>
                <button class="primary" @click="createNewGroup">
                    <span class="icon-add"></span>
                    {{ t('souvera_central', 'Neue Gruppe') }}
                </button>
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
                <div class="icon-loading"></div>
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
                            <tr v-for="group in groups" :key="group.id" class="group-row" @click="selectGroup(group)">
                                <td class="group-column">
                                    <div class="group-info">
                                        <span class="icon-group"></span>
                                        <span class="groupname">{{ group.id }}</span>
                                        <span
                                            v-if="group.isProtected"
                                            class="protected-badge"
                                            :title="t('souvera_central', 'Systemgruppe')"
                                        >
                                            <span class="icon-password"></span>
                                        </span>
                                    </div>
                                </td>
                                <td class="displayname-column">{{ group.displayName }}</td>
                                <td class="members-column">
                                    <div class="members-count">
                                        <span class="icon-user"></span>
                                        <span>{{ group.userCount }}</span>
                                    </div>
                                </td>
                                <td class="actions-column">
                                    <div class="group-actions">
                                        <button
                                            class="icon-rename"
                                            :title="t('souvera_central', 'Bearbeiten')"
                                            @click.stop="editGroup(group)"
                                        ></button>
                                        <button
                                            class="icon-delete"
                                            :title="
                                                group.isProtected
                                                    ? t('souvera_central', 'Systemgruppe kann nicht gelöscht werden')
                                                    : t('souvera_central', 'Löschen')
                                            "
                                            :disabled="group.isProtected"
                                            @click.stop="deleteGroup(group)"
                                        ></button>
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
                <div class="icon-group icon-large"></div>
                <h3 v-if="searchQuery">{{ t('souvera_central', 'Keine Gruppen gefunden') }}</h3>
                <h3 v-else>{{ t('souvera_central', 'Noch keine Gruppen') }}</h3>
                <p v-if="searchQuery">{{ t('souvera_central', 'Versuchen Sie einen anderen Suchbegriff.') }}</p>
                <p v-else>{{ t('souvera_central', 'Erstellen Sie Ihre erste Gruppe um zu starten.') }}</p>
            </div>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import GroupEditor from './components/GroupEditor.vue'
import SearchField from '../../components/SearchField.vue'
import Pagination from '../../components/Pagination.vue'

export default {
    name: 'GroupManagement',

    components: {
        GroupEditor,
        SearchField,
        Pagination
    },

    data() {
        return {
            groups: [],
            totalGroups: 0,
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
            }
        }
    },

    mounted() {
        this.loadSettings()
        this.loadGroups()
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
            console.log('URL changed to:', path)

            // Wenn URL zu /groups (ohne /new oder /edit/:id) geht → Editor schließen
            if (path.endsWith('/groups') || path === generateUrl('/apps/souvera_central/groups')) {
                if (this.showEditor) {
                    console.log('Closing editor due to URL change')
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
                console.error('Fehler beim Laden der Gruppe:', error)
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
                console.error('Fehler beim Laden der Einstellungen:', error)
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

                // Emit total group count to parent (für Dashboard)
                this.$emit('groups-loaded', this.totalGroups)

                console.log('Geladene Gruppen:', this.groups.length, 'von', this.totalGroups, '| Sortierung:', this.settings.sorting.groups)
            } catch (error) {
                console.error('Fehler beim Laden der Gruppen:', error)
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
            console.log('Suche:', query)
            this.searchQuery = query
            this.currentPage = 1 // Zurück zur ersten Seite bei neuer Suche
            this.loadGroups()
        },

        handlePageChange(page) {
            console.log('Seite wechseln:', page)
            this.currentPage = page
            this.loadGroups()
        },

        handlePerPageChange(perPage) {
            console.log('Pro-Seite ändern:', perPage)
            this.perPage = perPage
            this.currentPage = 1 // Zurück zur ersten Seite
            this.loadGroups()
        },

        selectGroup(group) {
            // Navigate to /groups/edit/:id
            const url = generateUrl('/apps/souvera_central/groups/edit/{id}', { id: group.id })
            window.history.pushState({}, '', url)

            this.selectedGroup = group
            this.showEditor = true
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
            window.history.pushState({}, '', url)

            this.showEditor = false
            this.selectedGroup = null
        },

        async handleGroupSaved() {
            // Navigate zurück zu /groups
            const url = generateUrl('/apps/souvera_central/groups')
            window.history.pushState({}, '', url)

            this.showEditor = false
            this.selectedGroup = null
            await this.loadGroups()
        },

        async deleteGroup(group) {
            if (group.isProtected) {
                alert(this.t('souvera_central', 'Systemgruppen können nicht gelöscht werden'))
                return
            }

            if (
                !confirm(
                    this.t('souvera_central', 'Möchten Sie die Gruppe "{group}" wirklich löschen?', {
                        group: group.displayName
                    })
                )
            ) {
                return
            }

            try {
                const url = generateUrl('/apps/souvera_central/api/groups/manage/{id}', { id: group.id })
                await axios.delete(url)
                await this.loadGroups()
            } catch (error) {
                console.error('Fehler beim Löschen:', error)
                this.showError(error, this.t('souvera_central', 'Fehler beim Löschen'))
            }
        },

        showError(error, defaultMessage) {
            let errorMessage = defaultMessage
            if (error.response?.data?.ocs?.data?.error) {
                errorMessage = error.response.data.ocs.data.error
            } else if (error.response?.data?.error) {
                errorMessage = error.response.data.error
            }
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

.group-count-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--color-background-dark);
    border-radius: var(--border-radius-large);
    font-size: 14px;
    font-weight: 500;
}

.group-count-badge .icon-group {
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

/* Groups Table */
.groups-table-wrapper {
    overflow-x: auto;
}

.groups-table {
    width: 100%;
    border-collapse: collapse;
}

.groups-table thead {
    background: var(--color-background-dark);
    border-bottom: 1px solid var(--color-border);
}

.groups-table th {
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: var(--color-text-lighter);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.groups-table tbody tr {
    border-bottom: 1px solid var(--color-border);
    transition: background-color 0.2s;
    cursor: pointer;
}

.groups-table tbody tr:hover {
    background: var(--color-background-hover);
}

.groups-table tbody tr:last-child {
    border-bottom: none;
}

.groups-table td {
    padding: 16px 12px;
    vertical-align: middle;
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

.groupname {
    font-weight: 500;
}

.protected-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    background: var(--color-warning);
    border-radius: var(--border-radius);
    font-size: 12px;
    color: var(--color-main-text);
}

/* Members Count */
.members-count {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-text-lighter);
}

.members-count .icon-user {
    opacity: 0.6;
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
    background: var(--color-background-dark);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    cursor: pointer;
    padding: 0;
    transition: all 0.2s;
    color: var(--color-main-text);
    opacity: 1;
}

.group-actions button:hover:not(:disabled) {
    background: var(--color-primary-element-light);
    border-color: var(--color-primary-element);
    transform: scale(1.1);
}

.group-actions button.icon-delete:hover:not(:disabled) {
    background: var(--color-error);
    border-color: var(--color-error);
    color: white;
}

.group-actions button:disabled {
    opacity: 0.3;
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
}
</style>
