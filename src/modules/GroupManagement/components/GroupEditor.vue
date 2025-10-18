<template>
    <div class="group-editor-page">
        <!-- Header -->
        <div class="editor-header">
            <div class="header-left">
                <button class="back-button" @click="$emit('close')">
                    <span class="icon-history"></span>
                    {{ t('souvera_central', 'Zurück zur Übersicht') }}
                </button>
                <h2>
                    {{ isEditMode ? t('souvera_central', 'Gruppe bearbeiten') : t('souvera_central', 'Neue Gruppe') }}
                </h2>
            </div>
        </div>

        <!-- Form -->
        <div class="editor-content">
            <form class="group-form" @submit.prevent="saveGroup">
                <!-- Gruppen-ID -->
                <div class="form-group">
                    <label for="groupId" class="required">
                        {{ t('souvera_central', 'Gruppen-ID') }}
                    </label>
                    <input
                        id="groupId"
                        v-model="formData.groupId"
                        type="text"
                        :disabled="isEditMode"
                        :class="{ error: errors.groupId }"
                        required
                        @input="validateGroupId"
                    />
                    <p v-if="errors.groupId" class="error-message">{{ errors.groupId }}</p>
                    <p v-else class="help-text">
                        {{
                            t(
                                'souvera_central',
                                'Eindeutige Gruppen-ID, kann später nicht geändert werden. Nur Buchstaben, Zahlen, _ und - erlaubt.'
                            )
                        }}
                    </p>
                </div>

                <!-- Anzeigename -->
                <div class="form-group">
                    <label for="displayName" class="required">
                        {{ t('souvera_central', 'Anzeigename') }}
                    </label>
                    <input
                        id="displayName"
                        v-model="formData.displayName"
                        type="text"
                        :class="{ error: errors.displayName }"
                        required
                        @input="validateDisplayName"
                    />
                    <p v-if="errors.displayName" class="error-message">{{ errors.displayName }}</p>
                    <p v-else class="help-text">
                        {{
                            t(
                                'souvera_central',
                                'Freundlicher Name der Gruppe, wie er in der Benutzeroberfläche angezeigt wird'
                            )
                        }}
                    </p>
                </div>

                <!-- Mitglieder-Verwaltung (nur im Edit-Modus) -->
                <div v-if="isEditMode" class="form-group">
                    <label for="members">
                        {{ t('souvera_central', 'Mitglieder') }}
                        <span class="member-count">({{ selectedMembers.length }})</span>
                    </label>
                    <p class="help-text">{{ t('souvera_central', 'Benutzer dieser Gruppe zuweisen') }}</p>

                    <!-- Search für Mitglieder -->
                    <div class="member-search">
                        <input
                            v-model="memberSearchQuery"
                            type="text"
                            :placeholder="t('souvera_central', 'Benutzer suchen...')"
                            class="search-input"
                        />
                    </div>

                    <!-- Loading State -->
                    <div v-if="loadingUsers" class="members-loading">
                        <span class="icon-loading-small"></span>
                        {{ t('souvera_central', 'Lade Benutzer...') }}
                    </div>

                    <!-- Members Selector -->
                    <div v-else class="members-selector">
                        <div v-for="user in filteredUsers" :key="user.id" class="member-checkbox">
                            <input
                                :id="'member-' + user.id"
                                v-model="selectedMembers"
                                type="checkbox"
                                :value="user.id"
                            />
                            <label :for="'member-' + user.id">
                                <span class="member-name">{{ user.displayName }}</span>
                                <span class="member-email">{{ user.email }}</span>
                            </label>
                        </div>
                        <div v-if="filteredUsers.length === 0" class="no-users-found">
                            {{ t('souvera_central', 'Keine Benutzer gefunden') }}
                        </div>
                    </div>
                </div>

                <!-- Info-Box für geschützte Gruppen -->
                <div v-if="isProtected" class="info-box warning">
                    <span class="icon-password"></span>
                    <div class="info-content">
                        <strong>{{ t('souvera_central', 'Systemgruppe') }}</strong>
                        <p>
                            {{
                                t(
                                    'souvera_central',
                                    'Diese Gruppe ist eine Systemgruppe und kann nicht gelöscht werden.'
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="secondary" @click="$emit('close')">
                        {{ t('souvera_central', 'Abbrechen') }}
                    </button>
                    <button type="submit" class="primary" :disabled="!isFormValid || saving">
                        <span v-if="saving" class="icon-loading-small"></span>
                        <template v-if="isEditMode">
                            {{ saving ? t('souvera_central', 'Speichert...') : t('souvera_central', 'Speichern') }}
                        </template>
                        <template v-else>
                            {{
                                saving ? t('souvera_central', 'Erstellt...') : t('souvera_central', 'Gruppe erstellen')
                            }}
                        </template>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
    name: 'GroupEditor',

    props: {
        group: {
            type: Object,
            default: null
        }
    },

    emits: ['close', 'saved'],

    data() {
        return {
            formData: {
                groupId: '',
                displayName: ''
            },
            errors: {
                groupId: null,
                displayName: null
            },
            selectedMembers: [],
            availableUsers: [],
            memberSearchQuery: '',
            loadingUsers: false,
            saving: false,
            isProtected: false
        }
    },

    computed: {
        isEditMode() {
            return this.group !== null
        },

        isFormValid() {
            return (
                this.formData.groupId && this.formData.displayName && !this.errors.groupId && !this.errors.displayName
            )
        },

        filteredUsers() {
            if (!this.memberSearchQuery) {
                return this.availableUsers
            }

            const query = this.memberSearchQuery.toLowerCase()
            return this.availableUsers.filter((user) => {
                return (
                    user.displayName.toLowerCase().includes(query) ||
                    user.id.toLowerCase().includes(query) ||
                    (user.email && user.email.toLowerCase().includes(query))
                )
            })
        }
    },

    mounted() {
        if (this.isEditMode) {
            this.formData = {
                groupId: this.group.id,
                displayName: this.group.displayName
            }
            this.isProtected = this.group.isProtected || false

            // Lade Members und alle Users
            this.loadGroupMembers()
            this.loadAllUsers()
        }
    },

    methods: {
        t,

        validateGroupId() {
            this.errors.groupId = null

            if (!this.formData.groupId) {
                this.errors.groupId = this.t('souvera_central', 'Gruppen-ID ist erforderlich')
                return
            }

            if (this.formData.groupId.length < 3) {
                this.errors.groupId = this.t('souvera_central', 'Gruppen-ID muss mindestens 3 Zeichen lang sein')
                return
            }

            if (!/^[a-zA-Z0-9_-]+$/.test(this.formData.groupId)) {
                this.errors.groupId = this.t('souvera_central', 'Nur Buchstaben, Zahlen, _ und - erlaubt')
            }
        },

        validateDisplayName() {
            this.errors.displayName = null

            if (!this.formData.displayName) {
                this.errors.displayName = this.t('souvera_central', 'Anzeigename ist erforderlich')
            }
        },

        async loadGroupMembers() {
            try {
                const url = generateUrl('/apps/souvera_central/api/groups/manage/{id}/members', {
                    id: this.formData.groupId
                })
                const response = await axios.get(url)

                const data = response.data.ocs?.data || response.data.data || response.data
                const members = data.members || []

                this.selectedMembers = members.map((m) => m.id)
            } catch (error) {
                // Error loading group members
            }
        },

        async loadAllUsers() {
            try {
                this.loadingUsers = true
                const url = generateUrl('/apps/souvera_central/api/users')
                const response = await axios.get(url, {
                    params: {
                        limit: 1000 // Alle User laden für Selektor
                    }
                })

                const data = response.data.ocs?.data || response.data.data || response.data
                const users = data.users || []

                this.availableUsers = users.map((user) => ({
                    id: user.id,
                    displayName: user.displayName,
                    email: user.email || ''
                }))
            } catch (error) {
                // Error loading users
                this.availableUsers = []
            } finally {
                this.loadingUsers = false
            }
        },

        async saveGroup() {
            // Validate all fields
            this.validateGroupId()
            this.validateDisplayName()

            if (!this.isFormValid) {
                return
            }

            this.saving = true

            try {
                if (this.isEditMode) {
                    // Update existing group
                    const url = generateUrl('/apps/souvera_central/api/groups/manage/{id}', {
                        id: this.formData.groupId
                    })

                    await axios.put(url, {
                        displayName: this.formData.displayName
                    })

                    // Update group members
                    await this.updateGroupMembers()
                } else {
                    // Create new group
                    const url = generateUrl('/apps/souvera_central/api/groups/manage')
                    const payload = {
                        groupId: this.formData.groupId,
                        displayName: this.formData.displayName
                    }

                    await axios.post(url, payload)
                }

                this.$emit('saved')
                this.$emit('close')
            } catch (error) {
                // Zeige Fehlermeldung
                let errorMessage = this.t('souvera_central', 'Fehler beim Speichern')

                if (error.response?.data?.ocs?.data?.error) {
                    errorMessage = error.response.data.ocs.data.error
                } else if (error.response?.data?.error) {
                    errorMessage = error.response.data.error
                }

                alert(errorMessage)
            } finally {
                this.saving = false
            }
        },

        async updateGroupMembers() {
            // Lade aktuelle Mitglieder
            const url = generateUrl('/apps/souvera_central/api/groups/manage/{id}/members', {
                id: this.formData.groupId
            })
            const response = await axios.get(url)

            const data = response.data.ocs?.data || response.data.data || response.data
            const currentMembers = (data.members || []).map((m) => m.id)

            // Finde hinzuzufügende und zu entfernende Mitglieder
            const membersToAdd = this.selectedMembers.filter((id) => !currentMembers.includes(id))
            const membersToRemove = currentMembers.filter((id) => !this.selectedMembers.includes(id))

            // Füge neue Mitglieder hinzu
            for (const userId of membersToAdd) {
                try {
                    const addUrl = generateUrl('/apps/souvera_central/api/groups/manage/{id}/members', {
                        id: this.formData.groupId
                    })
                    await axios.post(addUrl, { userId })
                } catch (error) {
                    // Error adding user to group
                }
            }

            // Entferne Mitglieder
            for (const userId of membersToRemove) {
                try {
                    const removeUrl = generateUrl('/apps/souvera_central/api/groups/manage/{id}/members/{userId}', {
                        id: this.formData.groupId,
                        userId
                    })
                    await axios.delete(removeUrl)
                } catch (error) {
                    // Error removing user from group
                }
            }
        }
    }
}
</script>

<style scoped>
.group-editor-page {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    background: var(--color-main-background);
}

/* Header */
.editor-header {
    padding: 20px 30px;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: center;
}

.header-left {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--color-background-dark);
    border: 1px solid var(--color-border);
    color: var(--color-primary);
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    padding: 10px 16px;
    border-radius: var(--border-radius);
    transition: all 0.2s;
    align-self: flex-start;
}

.back-button:hover {
    background: var(--color-primary-element-light);
    border-color: var(--color-primary);
    transform: translateX(-2px);
}

.back-button [class^='icon-'],
.back-button [class*=' icon-'] {
    font-size: 16px;
    color: var(--color-primary) !important;
    opacity: 1 !important;
}

.editor-header h2 {
    margin: 0;
    font-size: 28px;
    font-weight: 600;
}

/* Content */
.editor-content {
    flex: 1;
    overflow-y: auto;
    padding: 30px;
}

.group-form {
    max-width: 700px;
    margin: 0 auto;
}

/* Form Groups */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 8px;
    color: var(--color-text-light);
}

.form-group label.required::after {
    content: ' *';
    color: var(--color-error);
}

.member-count {
    font-weight: normal;
    color: var(--color-text-lighter);
    font-size: 14px;
}

.form-group input[type='text'],
.form-group select {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    font-size: 15px;
    line-height: 1.5;
    height: 46px;
    box-sizing: border-box;
    transition: border-color 0.2s;
    background: var(--color-main-background);
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--color-primary);
}

.form-group input.error {
    border-color: var(--color-error);
}

.form-group input:disabled {
    background: var(--color-background-dark);
    cursor: not-allowed;
}

/* Messages */
.help-text {
    margin: 6px 0 0;
    font-size: 13px;
    color: var(--color-text-lighter);
}

.error-message {
    margin: 6px 0 0;
    font-size: 13px;
    color: var(--color-error);
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Member Search */
.member-search {
    margin-bottom: 12px;
}

.search-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    font-size: 14px;
    background: var(--color-main-background);
}

.search-input:focus {
    outline: none;
    border-color: var(--color-primary);
}

/* Members Selector */
.members-loading {
    padding: 20px;
    text-align: center;
    color: var(--color-text-lighter);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.members-selector {
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    padding: 12px;
    max-height: 300px;
    overflow-y: auto;
    background: var(--color-main-background);
}

.member-checkbox {
    display: flex;
    align-items: center;
    padding: 10px;
    border-radius: var(--border-radius);
    transition: background 0.2s;
}

.member-checkbox:hover {
    background: var(--color-background-hover);
}

.member-checkbox input[type='checkbox'] {
    margin: 0 12px 0 0;
    flex-shrink: 0;
}

.member-checkbox label {
    margin: 0;
    cursor: pointer;
    font-weight: normal;
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
}

.member-name {
    font-weight: 500;
    color: var(--color-main-text);
}

.member-email {
    font-size: 13px;
    color: var(--color-text-lighter);
}

.no-users-found {
    padding: 20px;
    text-align: center;
    color: var(--color-text-lighter);
}

/* Info Box */
.info-box {
    display: flex;
    gap: 15px;
    padding: 15px 20px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
}

.info-box.warning {
    background: var(--color-warning);
    border: 1px solid var(--color-warning);
}

.info-box .icon-password {
    font-size: 24px;
    flex-shrink: 0;
    opacity: 0.8;
}

.info-content {
    flex: 1;
}

.info-content strong {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.info-content p {
    margin: 0;
    font-size: 14px;
    opacity: 0.9;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--color-border);
}

.form-actions button {
    padding: 10px 24px;
    border-radius: var(--border-radius);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-actions button.primary {
    background: var(--color-primary);
    color: white;
    border: none;
}

.form-actions button.primary:hover:not(:disabled) {
    background: var(--color-primary-element-light);
}

.form-actions button.primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.form-actions button.secondary {
    background: transparent;
    border: 1px solid var(--color-border);
    color: var(--color-text-light);
}

.form-actions button.secondary:hover {
    background: var(--color-background-hover);
}

/* Responsive Design */
@media (max-width: 768px) {
    .editor-content {
        padding: 20px 15px;
    }

    .group-form {
        max-width: 100%;
    }
}
</style>
