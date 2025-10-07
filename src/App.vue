<template>
  <div id="app-content-wrapper">
    <!-- User Editor (Single Page) -->
    <UserEditor
      v-if="showEditor"
      :user="selectedUser"
      @close="closeEditor"
      @saved="handleUserSaved"
    />

    <!-- Haupt-Bereich mit User-Liste -->
    <div v-else id="app-main-content" class="users-list-container">
      <!-- Header mit Lizenz-Info -->
      <div class="page-header">
        <div class="header-content">
          <h2>{{ t('souvera_user_management', 'Benutzer') }}</h2>
          <div class="license-status">
            <span class="icon-quota"></span>
            <span class="license-info">{{ users.length }} von {{ licenseTotal }} Lizenzen genutzt</span>
          </div>
        </div>
        <button class="primary" @click="createNewUser">
          <span class="icon-add"></span>
          {{ t('souvera_user_management', 'Neuer Benutzer') }}
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="icon-loading"></div>
        <p>{{ t('souvera_user_management', 'Lade Benutzer...') }}</p>
      </div>

      <!-- User Table -->
      <div v-else-if="users.length > 0" class="users-table-wrapper">
        <table class="users-table">
          <thead>
            <tr>
              <th class="user-column">{{ t('souvera_user_management', 'Benutzername') }}</th>
              <th class="displayname-column">{{ t('souvera_user_management', 'Anzeigename') }}</th>
              <th class="email-column">{{ t('souvera_user_management', 'E-Mail') }}</th>
              <th class="groups-column">{{ t('souvera_user_management', 'Gruppen') }}</th>
              <th class="quota-column">{{ t('souvera_user_management', 'Speicherplatz') }}</th>
              <th class="status-column">{{ t('souvera_user_management', 'Status') }}</th>
              <th class="actions-column">{{ t('souvera_user_management', 'Aktionen') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id" class="user-row" @click="selectUser(user)">
              <td class="user-column">
                <div class="user-info">
                  <span class="icon-user"></span>
                  <span class="username">{{ user.id }}</span>
                </div>
              </td>
              <td class="displayname-column">{{ user.displayName }}</td>
              <td class="email-column">{{ user.email || '-' }}</td>
              <td class="groups-column">
                <div class="groups-list">
                  <span v-for="group in user.groups" :key="group.id" class="group-badge">
                    {{ group.displayName }}
                  </span>
                  <span v-if="user.groups.length === 0" class="text-muted">-</span>
                </div>
              </td>
              <td class="quota-column">{{ user.quota.quota }}</td>
              <td class="status-column">
                <span :class="['status-badge', user.enabled ? 'status-enabled' : 'status-disabled']">
                  {{ user.enabled ? t('souvera_user_management', 'Aktiv') : t('souvera_user_management', 'Deaktiviert') }}
                </span>
              </td>
              <td class="actions-column">
                <div class="user-actions">
                  <button class="icon-rename" :title="t('souvera_user_management', 'Bearbeiten')" @click.stop="editUser(user)"></button>
                  <button class="icon-delete" :title="t('souvera_user_management', 'Löschen')" @click.stop="deleteUser(user)"></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty State -->
      <div v-else class="empty-state">
        <div class="icon-user icon-large"></div>
        <h3>{{ t('souvera_user_management', 'Noch keine Benutzer') }}</h3>
        <p>{{ t('souvera_user_management', 'Erstellen Sie Ihren ersten Benutzer um zu starten.') }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import UserEditor from './components/UserEditor.vue'

export default {
  name: 'App',

  components: {
    UserEditor
  },

  data() {
    return {
      users: [],
      loading: true,
      licenseTotal: 10,
      selectedUser: null,
      showEditor: false
    }
  },

  mounted() {
    this.loadUsers()
  },

  methods: {
    t,

    async loadUsers() {
      try {
        this.loading = true
        const url = generateUrl('/apps/souvera_user_management/api/users')
        console.log('Lade Benutzer von:', url)
        const response = await axios.get(url)
        console.log('API Response:', response.data)

        // OCS Response Format: response.data.ocs.data.users
        const users = response.data.ocs?.data?.users || response.data.data?.users || response.data.users || []
        console.log('Raw users:', users)

        // Fix groups: Convert object to array
        this.users = users.map(user => ({
          ...user,
          groups: Array.isArray(user.groups)
            ? user.groups
            : Object.values(user.groups || {})
        }))

        console.log('Geladene Benutzer:', this.users.length)
      } catch (error) {
        console.error('Fehler beim Laden der Benutzer:', error)
        console.error('Error details:', error.response)
        if (error.response) {
          console.error('Status:', error.response.status)
          console.error('Data:', error.response.data)
        }
        // TODO: Fehlerbehandlung UI
      } finally {
        this.loading = false
      }
    },

    selectUser(user) {
      this.selectedUser = user
      this.showEditor = true
    },

    createNewUser() {
      this.selectedUser = null
      this.showEditor = true
    },

    editUser(user) {
      this.selectedUser = user
      this.showEditor = true
    },

    closeEditor() {
      this.showEditor = false
      this.selectedUser = null
    },

    async handleUserSaved() {
      this.showEditor = false
      this.selectedUser = null
      await this.loadUsers()
    },

    async deleteUser(user) {
      if (!confirm(this.t('souvera_user_management', 'Möchten Sie den Benutzer "{user}" wirklich löschen?', { user: user.displayName }))) {
        return
      }

      try {
        const url = generateUrl('/apps/souvera_user_management/api/users/{id}', { id: user.id })
        await axios.delete(url)
        console.log('User deleted:', user.id)
        await this.loadUsers()
      } catch (error) {
        console.error('Fehler beim Löschen:', error)

        let errorMessage = this.t('souvera_user_management', 'Fehler beim Löschen')
        if (error.response?.data?.ocs?.data?.error) {
          errorMessage = error.response.data.ocs.data.error
        } else if (error.response?.data?.error) {
          errorMessage = error.response.data.error
        }

        alert(errorMessage)
      }
    }
  }
}
</script>

<style scoped>
#app-content-wrapper {
  height: 100%;
  background: var(--color-main-background);
}

.users-list-container {
  padding: 30px;
  max-width: 1400px;
  margin: 0 auto;
}

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
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

/* User Table */
.users-table-wrapper {
  background: var(--color-main-background);
  border-radius: var(--border-radius-large);
  overflow: hidden;
  box-shadow: 0 0 3px var(--color-box-shadow);
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
  cursor: pointer;
}

.users-table tbody tr:hover {
  background: var(--color-background-hover);
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

.groups-column {
  width: 200px;
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

/* Groups */
.groups-list {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.group-badge {
  display: inline-block;
  padding: 4px 8px;
  background: var(--color-primary-element-light);
  color: var(--color-primary-element-text);
  border-radius: var(--border-radius);
  font-size: 12px;
  font-weight: 500;
}

.text-muted {
  color: var(--color-text-lighter);
}

/* Status Badge */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--border-radius-large);
  font-size: 12px;
  font-weight: 500;
}

.status-enabled {
  background: #d4edda;
  color: #155724;
}

.status-disabled {
  background: #f8d7da;
  color: #721c24;
}

/* Actions */
.user-actions {
  display: flex;
  gap: 5px;
  justify-content: flex-end;
}

.user-actions button {
  opacity: 0.6;
  transition: opacity 0.2s;
  background: none;
  border: none;
  cursor: pointer;
  padding: 8px;
}

.user-actions button:hover {
  opacity: 1;
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
</style>
