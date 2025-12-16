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
                                :disabled="isAdminUserInAdminGroup(user.id)"
                            />
                            <label
                                :for="'member-' + user.id"
                                :class="{ 'disabled-label': isAdminUserInAdminGroup(user.id) }"
                            >
                                <span class="member-name">{{ user.displayName }}</span>
                                <span class="member-email">{{ user.email }}</span>
                                <span
                                    v-if="isAdminUserInAdminGroup(user.id)"
                                    class="locked-badge"
                                    :title="
                                        t(
                                            'souvera_central',
                                            'Standard-Administrator kann nicht aus Admin-Gruppe entfernt werden'
                                        )
                                    "
                                >
                                    <span class="icon-password"></span>
                                </span>
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
import ConfirmationModal from '../../../components/ConfirmationModal.vue'

export default {
    name: 'GroupEditor',

    components: {
        ConfirmationModal
    },

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
            isProtected: false,
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

        /**
         * Prüft OCS-Response auf Fehler und wirft Error falls vorhanden
         * OCSController gibt oft HTTP 200 OK mit Fehler in ocs.meta zurück
         */
        checkOCSError(response) {
            const statusCode = response.data?.ocs?.meta?.statuscode
            // Message zuerst in data.error suchen (dort ist sie meist), dann in meta.message
            const message = response.data?.ocs?.data?.error ||
                response.data?.ocs?.meta?.message

            if (statusCode && statusCode >= 400) {
                throw new Error(message || 'Fehler bei der Anfrage')
            }
        },

        isAdminUserInAdminGroup(userId) {
            // Checkbox für Admin-User in "admin" Gruppe deaktivieren
            return this.formData.groupId === 'admin' && (userId === 'admin' || userId.startsWith('admin@'))
        },

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

                // Stelle sicher, dass Admin-User immer in "admin" Gruppe selektiert ist
                if (this.formData.groupId === 'admin') {
                    const adminUser = this.selectedMembers.find((id) => id === 'admin' || id.startsWith('admin@'))
                    if (!adminUser) {
                        // Falls admin oder admin@... User in allen verfügbaren Usern existiert, hinzufügen
                        const adminInAllUsers = this.allUsers.find((u) => u.id === 'admin' || u.id.startsWith('admin@'))
                        if (adminInAllUsers && !this.selectedMembers.includes(adminInAllUsers.id)) {
                            this.selectedMembers.push(adminInAllUsers.id)
                        }
                    }
                }
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

                    const updateResponse = await axios.put(url, {
                        displayName: this.formData.displayName
                    })
                    this.checkOCSError(updateResponse)

                    // Update group members
                    await this.updateGroupMembers()
                } else {
                    // Create new group
                    const url = generateUrl('/apps/souvera_central/api/groups/manage')
                    const payload = {
                        groupId: this.formData.groupId,
                        displayName: this.formData.displayName
                    }

                    const createResponse = await axios.post(url, payload)
                    this.checkOCSError(createResponse)
                }

                this.$emit('saved')
                this.$emit('close')
            } catch (error) {
                // Zeige Fehlermeldung
                console.error('GroupEditor: Error beim Speichern:', error)
                console.log('Error Response:', error.response)

                let errorMessage = this.t('souvera_central', 'Fehler beim Speichern')

                // Prüfe Error.message (von checkOCSError() oder anderen Error-Quellen)
                if (error.message && !error.message.includes('Network Error')) {
                    errorMessage = error.message
                } else if (error.response?.data?.ocs?.data?.error) {
                    errorMessage = error.response.data.ocs.data.error
                } else if (error.response?.data?.error) {
                    errorMessage = error.response.data.error
                }

                console.log('Extracted error message:', errorMessage)

                // Prüfe ob es ein Duplikat-Fehler ist und zeige ihn im GroupId-Feld
                if (errorMessage.toLowerCase().includes('bereits') || errorMessage.toLowerCase().includes('existiert')) {
                    console.log('Setting errors.groupId to:', errorMessage)
                    this.errors.groupId = errorMessage

                    // Zeige Fehler-Modal
                    this.confirmModal = {
                        isOpen: true,
                        title: this.t('souvera_central', 'Fehler beim Speichern'),
                        message: errorMessage,
                        details: this.t('souvera_central', 'Bitte verwenden Sie eine andere Gruppen-ID.'),
                        type: 'danger',
                        confirmText: this.t('souvera_central', 'OK'),
                        cancelText: '',
                        onConfirm: () => {
                            this.closeConfirmModal()
                            // Scroll zum Fehler
                            this.$nextTick(() => {
                                const groupIdField = document.getElementById('groupId')
                                groupIdField?.scrollIntoView({ behavior: 'smooth', block: 'center' })
                                groupIdField?.focus()
                            })
                        }
                    }
                } else {
                    // Zeige generischen Fehler-Modal
                    this.confirmModal = {
                        isOpen: true,
                        title: this.t('souvera_central', 'Fehler beim Speichern'),
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
            let membersToRemove = currentMembers.filter((id) => !this.selectedMembers.includes(id))

            // Verhindere Entfernung von Admin-User aus "admin" Gruppe
            if (this.formData.groupId === 'admin') {
                membersToRemove = membersToRemove.filter((id) => id !== 'admin' && !id.startsWith('admin@'))
            }

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
        },

        closeConfirmModal() {
            this.confirmModal.isOpen = false
        }
    }
}
</script>

<style scoped>
.group-editor-page {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    background: transparent;
    padding: 30px;
}

/* Header */
.editor-header {
    padding: 0 0 20px 0;
    border-bottom: none;
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
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(6px);
    border: 1.5px solid rgba(0, 0, 0, 0.15);
    color: #000;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    padding: 10px 16px;
    border-radius: 6px;
    transition: all 0.2s;
    align-self: flex-start;
}

.back-button:hover {
    background: rgba(255, 255, 255, 0.9);
    border-color: var(--color-secondary-element);
    transform: translateX(-2px);
}

.back-button [class^='icon-'],
.back-button [class*=' icon-'] {
    font-size: 16px;
    color: #000 !important;
    opacity: 1 !important;
}

.editor-header h2 {
    margin: 0;
    font-size: 28px;
    font-weight: 600;
    color: #000;
}

/* Content */
.editor-content {
    flex: 1;
    overflow-y: auto;
    padding: 0;
    background: rgba(255, 255, 255, 0.25);
    border-radius: 6px;
    padding: 30px;
}

.group-form {
    max-width: 800px;
    margin: 0 auto;
}

/* Form Groups */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #000;
}

.form-group label.required::after {
    content: ' *';
    color: var(--color-error);
}

.member-count {
    font-weight: normal;
    color: #666;
    font-size: 14px;
}

.form-group input[type='text'],
.form-group select {
    width: 100%;
    padding: 16px 18px;
    border: 2px solid rgba(0, 0, 0, 0.2);
    border-radius: 6px;
    font-size: 16px;
    line-height: 1.5;
    height: 60px;
    box-sizing: border-box;
    transition: all 0.2s;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(6px);
    color: #000;
    font-weight: 500;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--color-secondary-element);
    box-shadow: 0 0 0 3px rgba(48, 116, 191, 0.3);
    background: #fff;
}

.form-group input.error {
    border-color: var(--color-error);
}

.form-group input:disabled {
    background: rgba(0, 0, 0, 0.05);
    cursor: not-allowed;
    opacity: 0.6;
}

/* Messages */
.help-text {
    margin: 6px 0 0;
    font-size: 14px;
    color: #666;
    font-weight: 500;
}

.error-message {
    margin: 6px 0 0;
    font-size: 14px;
    color: var(--color-error);
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
}

/* Member Search */
.member-search {
    margin-bottom: 12px;
}

.search-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid rgba(0, 0, 0, 0.2);
    border-radius: 6px;
    font-size: 15px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(6px);
    color: #000;
    font-weight: 500;
}

.search-input:focus {
    outline: none;
    border-color: #3074BF;
    box-shadow: 0 0 0 3px rgba(48, 116, 191, 0.2);
    background: #fff;
}

/* Members Selector */
.members-loading {
    padding: 20px;
    text-align: center;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-weight: 600;
}

.members-selector {
    border: 2px solid rgba(0, 0, 0, 0.2);
    border-radius: 6px;
    padding: 12px;
    max-height: 300px;
    overflow-y: auto;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(6px);
}

.member-checkbox {
    display: flex;
    align-items: center;
    padding: 12px;
    border-radius: 6px;
    transition: all 0.2s;
}

.member-checkbox:hover {
    background: rgba(48, 116, 191, 0.1);
}

.member-checkbox input[type='checkbox'] {
    margin: 0 12px 0 0;
    flex-shrink: 0;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.member-checkbox label {
    margin: 0;
    cursor: pointer;
    font-weight: normal;
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}

.member-name {
    font-weight: 700;
    font-size: 15px;
    color: #000;
}

.member-email {
    font-size: 13px;
    color: #666;
    font-weight: 500;
}

.no-users-found {
    padding: 20px;
    text-align: center;
    color: #666;
    font-weight: 600;
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
    background: #ff6600;
    border: 1px solid #ff6600;
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
    border-top: 2px solid rgba(0, 0, 0, 0.1);
}

.form-actions button {
    padding: 14px 28px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-actions button.primary {
    background: #28a745;
    color: #fff;
    border: 2px solid #28a745;
    box-shadow: 0 4px 16px rgba(40, 167, 69, 0.4);
}

.form-actions button.primary:hover:not(:disabled) {
    background: #34d058;
    border-color: #34d058;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.6);
}

.form-actions button.primary:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: #ccc;
    border-color: #ccc;
    box-shadow: none;
}

.form-actions button.secondary {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(6px);
    border: 2px solid rgba(0, 0, 0, 0.2);
    color: #000;
    font-weight: 600;
}

.form-actions button.secondary:hover {
    background: rgba(255, 255, 255, 0.95);
    border-color: #3074BF;
    transform: translateY(-1px);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .group-editor-page {
        padding: 20px;
    }

    .editor-content {
        padding: 20px;
    }

    .group-form {
        max-width: 100%;
    }
}

@media (max-width: 768px) {
    .group-editor-page {
        padding: 15px;
    }

    .editor-header h2 {
        font-size: 24px;
    }

    .editor-content {
        padding: 15px;
    }

    .form-group input[type='text'],
    .form-group select {
        height: 52px;
        padding: 14px 16px;
        font-size: 15px;
    }

    .search-input {
        padding: 10px 14px;
        font-size: 14px;
    }

    .members-selector {
        max-height: 250px;
    }

    .member-checkbox {
        padding: 10px;
    }

    .form-actions {
        flex-direction: column-reverse;
        gap: 10px;
    }

    .form-actions button {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .group-editor-page {
        padding: 10px;
    }

    .back-button {
        font-size: 13px;
        padding: 8px 14px;
    }

    .editor-header h2 {
        font-size: 20px;
    }

    .editor-content {
        padding: 10px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        font-size: 14px;
    }

    .form-group input[type='text'],
    .form-group select {
        height: 48px;
        padding: 12px 14px;
        font-size: 14px;
    }

    .search-input {
        padding: 8px 12px;
        font-size: 13px;
    }

    .members-selector {
        max-height: 200px;
    }

    .member-checkbox {
        padding: 8px;
    }

    .member-name {
        font-size: 14px;
    }

    .member-email {
        font-size: 12px;
    }

    .form-actions button {
        padding: 12px 24px;
        font-size: 14px;
    }

    .info-box {
        padding: 12px 15px;
    }

    .info-box .icon-password {
        font-size: 20px;
    }

    .info-content strong {
        font-size: 14px;
    }

    .info-content p {
        font-size: 13px;
    }
}
</style>
