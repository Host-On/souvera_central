<template>
    <div class="modal-overlay" @click.self="$emit('close')">
        <div class="modal-content">
            <div class="modal-header">
                <h3>{{ t('souvera_central', 'Mitglieder verwalten') }}</h3>
                <span class="mailbox-name">{{ mailbox.description || mailbox.name }}</span>
                <button class="close-button" @click="$emit('close')">
                    <Close :size="20" />
                </button>
            </div>

            <div class="modal-body">
                <!-- Add Member -->
                <div class="add-member-section">
                    <label>{{ t('souvera_central', 'Mitglied hinzufügen') }}</label>
                    <div class="add-member-form">
                        <div class="search-container">
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="t('souvera_central', 'Benutzer suchen...')"
                                @input="searchUsers"
                            />
                            <!-- Search Results Dropdown -->
                            <div v-if="searchResults.length > 0" class="search-results">
                                <div
                                    v-for="user in searchResults"
                                    :key="user.id"
                                    class="search-result-item"
                                    @click="selectUser(user)"
                                >
                                    <span class="user-name">{{ user.displayName || user.id }}</span>
                                    <span class="user-email">{{ user.id }}</span>
                                </div>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="add-button"
                            :disabled="!selectedUser || adding"
                            @click="addMember"
                        >
                            <NcLoadingIcon v-if="adding" :size="18" />
                            <Plus v-else :size="18" />
                        </button>
                    </div>
                    <p v-if="searchQuery.length >= 2 && searchResults.length === 0 && !adding" class="hint-text">
                        {{ t('souvera_central', 'Keine Benutzer gefunden') }}
                    </p>
                    <p v-else-if="searchQuery.length > 0 && searchQuery.length < 2" class="hint-text">
                        {{ t('souvera_central', 'Mind. 2 Zeichen eingeben...') }}
                    </p>
                    <p v-if="error" class="error-message">{{ error }}</p>
                </div>

                <!-- Members List -->
                <div class="members-section">
                    <label>{{ t('souvera_central', 'Aktuelle Mitglieder') }} ({{ members.length }})</label>

                    <div class="send-as-hint" data-testid="send-as-hint">
                        <InformationOutline :size="16" />
                        <span>{{ t('souvera_central', 'Mitglieder können E-Mails dieses Postfachs empfangen UND im Namen des Postfachs senden („Senden als").') }}</span>
                    </div>

                    <div v-if="loading" class="loading">
                        <NcLoadingIcon :size="24" />
                    </div>

                    <div v-else-if="members.length === 0" class="no-members">
                        {{ t('souvera_central', 'Keine Mitglieder') }}
                    </div>

                    <div v-else class="members-list">
                        <div v-for="member in members" :key="member" class="member-item">
                            <div class="member-info">
                                <Account :size="18" />
                                <span class="member-email">{{ member }}</span>
                            </div>
                            <button
                                class="remove-button"
                                :disabled="removingMember === member"
                                :title="t('souvera_central', 'Entfernen')"
                                @click="removeMember(member)"
                            >
                                <NcLoadingIcon v-if="removingMember === member" :size="16" />
                                <Delete v-else :size="16" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="done-button" @click="$emit('close')">
                    {{ t('souvera_central', 'Fertig') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import Close from 'vue-material-design-icons/Close.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Account from 'vue-material-design-icons/Account.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'

export default {
    name: 'MembersModal',

    components: {
        NcLoadingIcon,
        Close,
        Plus,
        Account,
        Delete,
        InformationOutline
    },

    props: {
        mailbox: {
            type: Object,
            required: true
        }
    },

    emits: ['close', 'updated'],

    data() {
        return {
            loading: true,
            members: [],
            searchQuery: '',
            searchResults: [],
            selectedUser: null,
            adding: false,
            removingMember: null,
            error: null,
            searchTimeout: null
        }
    },

    mounted() {
        this.loadMembers()
    },

    methods: {
        t,

        async loadMembers() {
            this.loading = true
            try {
                const url = generateUrl('/apps/souvera_central/api/shared-mailboxes/{id}/members', {
                    id: this.mailbox.name
                })
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data
                this.members = data.members || []
            } catch (error) {
                console.error('MembersModal: Fehler beim Laden der Mitglieder', error)
            } finally {
                this.loading = false
            }
        },

        searchUsers() {
            this.selectedUser = null
            this.error = null

            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout)
            }

            if (this.searchQuery.length < 2) {
                this.searchResults = []
                return
            }

            this.searchTimeout = setTimeout(async () => {
                try {
                    const url = generateUrl('/apps/souvera_central/api/users/search')
                    const response = await axios.get(url, {
                        params: { query: this.searchQuery }
                    })
                    const data = response.data.ocs?.data || response.data.data || response.data
                    // Filter out already members
                    this.searchResults = (data.users || []).filter(
                        (user) => !this.members.includes(user.id)
                    )
                } catch (error) {
                    console.error('MembersModal: Fehler bei der Suche', error)
                    this.searchResults = []
                }
            }, 300)
        },

        selectUser(user) {
            // Direkt hinzufügen wenn User ausgewählt wird
            this.addMemberDirect(user)
        },

        async addMember() {
            if (!this.selectedUser || this.adding) {
                return
            }
            this.addMemberDirect(this.selectedUser)
        },

        async addMemberDirect(user) {
            if (this.adding || !user) {
                return
            }

            this.adding = true
            this.error = null
            this.searchResults = []

            try {
                const url = generateUrl('/apps/souvera_central/api/shared-mailboxes/{id}/members', {
                    id: this.mailbox.name
                })
                await axios.post(url, { userId: user.id })

                this.members.push(user.id)
                this.selectedUser = null
                this.searchQuery = ''
                this.$emit('updated')

            } catch (error) {
                console.error('MembersModal: Fehler beim Hinzufügen', error)
                this.error = error.response?.data?.ocs?.data?.error ||
                    error.response?.data?.error ||
                    this.t('souvera_central', 'Fehler beim Hinzufügen')
            } finally {
                this.adding = false
            }
        },

        async removeMember(member) {
            if (this.removingMember) {
                return
            }

            this.removingMember = member
            this.error = null

            try {
                const url = generateUrl('/apps/souvera_central/api/shared-mailboxes/{id}/members/{userId}', {
                    id: this.mailbox.name,
                    userId: member
                })
                await axios.delete(url)

                this.members = this.members.filter((m) => m !== member)
                this.$emit('updated')

            } catch (error) {
                console.error('MembersModal: Fehler beim Entfernen', error)
                this.error = error.response?.data?.ocs?.data?.error ||
                    error.response?.data?.error ||
                    this.t('souvera_central', 'Fehler beim Entfernen')
            } finally {
                this.removingMember = null
            }
        }
    }
}
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.modal-content {
    background: var(--color-main-background);
    border-radius: 12px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 8px 32px var(--color-box-shadow);
}

.modal-header {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 20px 25px;
    border-bottom: 1px solid var(--color-border);
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--color-main-text);
}

.mailbox-name {
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    background: var(--color-background-dark);
    padding: 4px 10px;
    border-radius: 10px;
}

.close-button {
    margin-left: auto;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.close-button:hover {
    background: var(--color-background-dark);
}

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 25px;
}

.add-member-section {
    margin-bottom: 25px;
}

.add-member-section label,
.members-section label {
    display: block;
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 600;
    color: var(--color-main-text);
}

.add-member-form {
    display: flex;
    gap: 10px;
}

.search-container {
    flex: 1;
    position: relative;
}

.search-container input {
    width: 100%;
    height: var(--sc-control-height);
    padding: var(--sc-control-padding-y) var(--sc-control-padding-x);
    border: var(--sc-control-border-width) solid var(--color-border);
    border-radius: var(--sc-control-radius);
    font-size: 14px;
    box-sizing: border-box;
    background: var(--color-main-background);
    color: var(--color-main-text);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.search-container input:focus {
    outline: none;
    border-color: var(--color-primary-element);
    box-shadow: var(--sc-focus-ring);
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    box-shadow: 0 4px 12px var(--color-box-shadow);
    z-index: 100;
    max-height: 200px;
    overflow-y: auto;
}

.search-result-item {
    display: flex;
    flex-direction: column;
    padding: 12px 14px;
    cursor: pointer;
    transition: all 0.2s;
    border-bottom: 1px solid var(--color-border);
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item:hover {
    background: var(--color-primary-element);
    color: #fff;
}

.search-result-item:hover .user-email {
    color: rgba(255, 255, 255, 0.8);
}

.search-result-item .user-name {
    font-size: 14px;
    font-weight: 500;
    color: var(--color-main-text);
}

.search-result-item .user-email {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
}

.add-button {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-primary-element);
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.add-button:hover:not(:disabled) {
    background: var(--color-primary-element-hover);
}

.add-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.selected-user {
    margin: 10px 0 0;
    font-size: 13px;
    color: var(--color-primary-element);
}

.hint-text {
    margin: 8px 0 0;
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    font-style: italic;
}

.error-message {
    margin: 10px 0 0;
    font-size: 13px;
    color: var(--color-error);
}

.loading {
    display: flex;
    justify-content: center;
    padding: 20px;
}

.no-members {
    padding: 20px;
    text-align: center;
    color: var(--color-text-maxcontrast);
    background: var(--color-background-hover);
    border-radius: 6px;
}

.send-as-hint {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 12px;
    padding: 10px 12px;
    font-size: 12.5px;
    line-height: 1.4;
    color: var(--color-text-maxcontrast);
    background: var(--color-background-hover);
    border: 1px solid var(--color-border);
    border-radius: 8px;
}

.send-as-hint .material-design-icon {
    flex-shrink: 0;
    color: var(--color-primary-element);
    margin-top: 1px;
}

.members-list {
    border: 1px solid var(--color-border);
    border-radius: 6px;
    overflow: hidden;
}

.member-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 15px;
    border-bottom: 1px solid var(--color-border);
}

.member-item:last-child {
    border-bottom: none;
}

.member-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.member-email {
    font-size: 14px;
    color: var(--color-main-text);
}

.remove-button {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: var(--color-error);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.remove-button:hover:not(:disabled) {
    background: rgba(var(--color-error-rgb), 0.1);
}

.remove-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.modal-footer {
    padding: 15px 25px;
    border-top: 1px solid var(--color-border);
    display: flex;
    justify-content: flex-end;
}

.done-button {
    padding: 12px 24px;
    background: var(--color-primary-element);
    border: none;
    border-radius: 6px;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.done-button:hover {
    background: var(--color-primary-element-hover);
}
</style>
