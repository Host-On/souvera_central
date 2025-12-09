<template>
    <div class="shared-mailboxes-view">
        <!-- Header -->
        <div class="view-header">
            <div class="header-left">
                <h2>{{ t('souvera_central', 'Geteilte Postfächer') }}</h2>
                <span class="mailbox-count">{{ mailboxes.length }} {{ t('souvera_central', 'Postfach/Postfächer') }}</span>
            </div>
            <button class="primary-button" @click="showCreateModal = true">
                <span class="icon-add"></span>
                {{ t('souvera_central', 'Neues Postfach') }}
            </button>
        </div>

        <!-- Stalwart Status Warning -->
        <div v-if="!stalwartAvailable && !loading" class="stalwart-warning">
            <span class="icon-error"></span>
            <span>{{ t('souvera_central', 'Mail-Server nicht erreichbar. Shared Mailbox Verwaltung nicht verfügbar.') }}</span>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="loading-container">
            <span class="icon-loading"></span>
            <span>{{ t('souvera_central', 'Lade Postfächer...') }}</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="stalwartAvailable && mailboxes.length === 0" class="empty-state">
            <div class="empty-icon">
                <span class="icon-shared"></span>
            </div>
            <h3>{{ t('souvera_central', 'Keine geteilten Postfächer') }}</h3>
            <p>{{ t('souvera_central', 'Erstellen Sie ein geteiltes Postfach für Ihr Team.') }}</p>
            <button class="primary-button" @click="showCreateModal = true">
                <span class="icon-add"></span>
                {{ t('souvera_central', 'Erstes Postfach erstellen') }}
            </button>
        </div>

        <!-- Mailbox List -->
        <div v-else-if="stalwartAvailable" class="mailbox-list">
            <SharedMailboxCard
                v-for="mailbox in mailboxes"
                :key="mailbox.name"
                :mailbox="mailbox"
                @edit="editMailbox"
                @delete="confirmDelete"
                @manage-members="manageMembers"
            />
        </div>

        <!-- Create/Edit Modal -->
        <SharedMailboxModal
            v-if="showCreateModal || showEditModal"
            :mailbox="selectedMailbox"
            :allowed-domains="allowedDomains"
            :is-edit="showEditModal"
            @close="closeModal"
            @save="saveMailbox"
        />

        <!-- Members Modal -->
        <MembersModal
            v-if="showMembersModal"
            :mailbox="selectedMailbox"
            @close="showMembersModal = false"
            @updated="loadMailboxes"
        />

        <!-- Delete Confirmation -->
        <ConfirmDialog
            v-if="showDeleteConfirm"
            :title="t('souvera_central', 'Postfach löschen')"
            :message="t('souvera_central', 'Möchten Sie das Postfach \'{name}\' wirklich löschen?', { name: selectedMailbox?.description || selectedMailbox?.name })"
            :confirm-text="t('souvera_central', 'Löschen')"
            :cancel-text="t('souvera_central', 'Abbrechen')"
            danger
            @confirm="deleteMailbox"
            @cancel="showDeleteConfirm = false"
        />

        <!-- Toast Messages -->
        <div v-if="toast.show" class="toast" :class="toast.type">
            {{ toast.message }}
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import SharedMailboxCard from './components/SharedMailboxCard.vue'
import SharedMailboxModal from './components/SharedMailboxModal.vue'
import MembersModal from './components/MembersModal.vue'
import ConfirmDialog from './components/ConfirmDialog.vue'

export default {
    name: 'SharedMailboxesView',

    components: {
        SharedMailboxCard,
        SharedMailboxModal,
        MembersModal,
        ConfirmDialog
    },

    data() {
        return {
            loading: true,
            stalwartAvailable: false,
            mailboxes: [],
            allowedDomains: [],
            selectedMailbox: null,
            showCreateModal: false,
            showEditModal: false,
            showMembersModal: false,
            showDeleteConfirm: false,
            toast: {
                show: false,
                message: '',
                type: 'success'
            }
        }
    },

    mounted() {
        this.loadConfig()
        this.checkStalwartStatus()
    },

    methods: {
        t,

        async loadConfig() {
            try {
                const url = generateUrl('/apps/souvera_central/api/config')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data
                this.allowedDomains = data.allowed_domains || data.allowedDomains || []
            } catch (error) {
                console.error('SharedMailboxesView: Fehler beim Laden der Config', error)
            }
        },

        async checkStalwartStatus() {
            try {
                const url = generateUrl('/apps/souvera_central/api/stalwart/status')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data
                this.stalwartAvailable = data.available === true

                if (this.stalwartAvailable) {
                    await this.loadMailboxes()
                }
            } catch (error) {
                console.error('SharedMailboxesView: Fehler beim Prüfen des Stalwart-Status', error)
                this.stalwartAvailable = false
            } finally {
                this.loading = false
            }
        },

        async loadMailboxes() {
            try {
                const url = generateUrl('/apps/souvera_central/api/shared-mailboxes')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data
                this.mailboxes = data.mailboxes || []
            } catch (error) {
                console.error('SharedMailboxesView: Fehler beim Laden der Postfächer', error)
                this.showToast(this.t('souvera_central', 'Fehler beim Laden der Postfächer'), 'error')
            }
        },

        editMailbox(mailbox) {
            this.selectedMailbox = mailbox
            this.showEditModal = true
        },

        manageMembers(mailbox) {
            this.selectedMailbox = mailbox
            this.showMembersModal = true
        },

        confirmDelete(mailbox) {
            this.selectedMailbox = mailbox
            this.showDeleteConfirm = true
        },

        closeModal() {
            this.showCreateModal = false
            this.showEditModal = false
            this.selectedMailbox = null
        },

        async saveMailbox(mailboxData) {
            try {
                if (this.showEditModal) {
                    // Update
                    const url = generateUrl('/apps/souvera_central/api/shared-mailboxes/{id}', {
                        id: this.selectedMailbox.name
                    })
                    await axios.put(url, mailboxData)
                    this.showToast(this.t('souvera_central', 'Postfach aktualisiert'))
                } else {
                    // Create
                    const url = generateUrl('/apps/souvera_central/api/shared-mailboxes')
                    await axios.post(url, mailboxData)
                    this.showToast(this.t('souvera_central', 'Postfach erstellt'))
                }

                this.closeModal()
                await this.loadMailboxes()

            } catch (error) {
                console.error('SharedMailboxesView: Fehler beim Speichern', error)
                const errorMsg = error.response?.data?.ocs?.data?.error ||
                    error.response?.data?.error ||
                    this.t('souvera_central', 'Fehler beim Speichern')
                this.showToast(errorMsg, 'error')
            }
        },

        async deleteMailbox() {
            try {
                const url = generateUrl('/apps/souvera_central/api/shared-mailboxes/{id}', {
                    id: this.selectedMailbox.name
                })
                await axios.delete(url)

                this.showToast(this.t('souvera_central', 'Postfach gelöscht'))
                this.showDeleteConfirm = false
                this.selectedMailbox = null
                await this.loadMailboxes()

            } catch (error) {
                console.error('SharedMailboxesView: Fehler beim Löschen', error)
                const errorMsg = error.response?.data?.ocs?.data?.error ||
                    error.response?.data?.error ||
                    this.t('souvera_central', 'Fehler beim Löschen')
                this.showToast(errorMsg, 'error')
            }
        },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type }
            setTimeout(() => {
                this.toast.show = false
            }, 3000)
        }
    }
}
</script>

<style scoped>
.shared-mailboxes-view {
    padding: 20px 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.view-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid rgba(0, 0, 0, 0.1);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.view-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: #000;
}

.mailbox-count {
    font-size: 14px;
    color: #666;
    background: rgba(0, 0, 0, 0.08);
    padding: 5px 12px;
    border-radius: 15px;
}

.primary-button {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: var(--color-primary);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.primary-button:hover {
    background: var(--color-primary-element-light);
    transform: translateY(-1px);
}

.primary-button [class^='icon-'] {
    filter: invert(1) brightness(100);
}

.stalwart-warning {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 20px;
    background: rgba(227, 56, 80, 0.1);
    border: 1px solid rgba(227, 56, 80, 0.3);
    border-radius: 8px;
    color: var(--color-error);
    font-size: 14px;
    margin-bottom: 20px;
}

.loading-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 60px 20px;
    color: #666;
    font-size: 16px;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    text-align: center;
    background: rgba(255, 255, 255, 0.5);
    border: 2px dashed rgba(0, 0, 0, 0.15);
    border-radius: 12px;
}

.empty-icon {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 50%;
    margin-bottom: 20px;
}

.empty-icon [class^='icon-'] {
    font-size: 36px;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 10px;
    font-size: 18px;
    font-weight: 600;
    color: #000;
}

.empty-state p {
    margin: 0 0 20px;
    color: #666;
    font-size: 14px;
}

.mailbox-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    padding: 14px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    z-index: 10000;
    animation: slideIn 0.3s ease;
}

.toast.success {
    background: var(--color-success);
    color: #fff;
}

.toast.error {
    background: var(--color-error);
    color: #fff;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@media (max-width: 768px) {
    .shared-mailboxes-view {
        padding: 15px;
    }

    .view-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .mailbox-list {
        grid-template-columns: 1fr;
    }
}
</style>
