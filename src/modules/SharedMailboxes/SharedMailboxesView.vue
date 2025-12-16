<template>
    <div class="shared-mailboxes-view">
        <!-- Header -->
        <div class="view-header">
            <div class="header-left">
                <h2>{{ t('souvera_central', 'Geteilte Postfächer') }}</h2>
                <div
                    class="license-status"
                    :class="{ 'license-warning': isMailboxWarning, 'license-critical': isMailboxLimitReached }"
                >
                    <span class="icon-shared"></span>
                    <span class="license-info">{{ mailboxes.length }} von {{ maxMailboxes }} Postfächer</span>
                </div>
            </div>
            <button
                class="primary"
                :disabled="isMailboxLimitReached"
                :title="isMailboxLimitReached ? t('souvera_central', 'Limit erreicht') : ''"
                @click="showCreateModal = true"
            >
                <span class="icon-add"></span>
                {{ t('souvera_central', 'Neues Postfach') }}
            </button>
        </div>

        <!-- KRITISCHES WARNING: Limit erreicht -->
        <div v-if="isMailboxLimitReached" class="critical-warning">
            <div class="warning-content">
                <span class="icon-error warning-icon"></span>
                <div class="warning-text">
                    <h3>{{ t('souvera_central', 'Limit erreicht!') }}</h3>
                    <p>
                        {{
                            t(
                                'souvera_central',
                                'Sie haben {count} von {total} geteilten Postfächern erstellt. Es können keine weiteren Postfächer erstellt werden.',
                                { count: mailboxes.length, total: maxMailboxes }
                            )
                        }}
                    </p>
                </div>
                <a :href="contactUrl" target="_blank" class="contact-button">
                    <span class="icon-external"></span>
                    {{ t('souvera_central', 'Limit erweitern') }}
                </a>
            </div>
        </div>

        <!-- WARNING: Limit bald erreicht -->
        <div v-else-if="isMailboxWarning" class="warning-banner">
            <div class="warning-content">
                <span class="icon-error warning-icon"></span>
                <div class="warning-text">
                    <h3>{{ t('souvera_central', 'Limit bald erreicht') }}</h3>
                    <p>
                        {{
                            t(
                                'souvera_central',
                                'Sie haben {count} von {total} geteilten Postfächern erstellt ({percentage}%).',
                                { count: mailboxes.length, total: maxMailboxes, percentage: mailboxPercentage }
                            )
                        }}
                    </p>
                </div>
                <a :href="contactUrl" target="_blank" class="contact-button secondary">
                    <span class="icon-external"></span>
                    {{ t('souvera_central', 'Kontakt') }}
                </a>
            </div>
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
            <button class="primary" @click="showCreateModal = true">
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
            maxMailboxes: 10,
            warningThreshold: 0.8,
            selectedMailbox: null,
            showCreateModal: false,
            showEditModal: false,
            showMembersModal: false,
            showDeleteConfirm: false,
            toast: {
                show: false,
                message: '',
                type: 'success'
            },
            resellerInfo: {
                support_url: null,
                url: null,
                name: null
            }
        }
    },

    computed: {
        mailboxPercentage() {
            if (this.maxMailboxes === 0) return 0
            return Math.round((this.mailboxes.length / this.maxMailboxes) * 100)
        },

        isMailboxLimitReached() {
            return this.mailboxes.length >= this.maxMailboxes
        },

        isMailboxWarning() {
            return !this.isMailboxLimitReached &&
                this.mailboxes.length / this.maxMailboxes >= this.warningThreshold
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
        this.loadConfig()
        this.loadResellerInfo()
        this.checkStalwartStatus()
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
                // Limits aus API-Response übernehmen
                if (data.maxMailboxes !== undefined) {
                    this.maxMailboxes = data.maxMailboxes
                }
                if (data.warningThreshold !== undefined) {
                    this.warningThreshold = data.warningThreshold
                }
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
    padding: 30px;
    max-width: 1400px;
    margin: 0 auto;
}

.view-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 20px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 20px;
}

.view-header h2 {
    margin: 0;
    font-size: 28px;
    font-weight: 600;
}

.license-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
}

.license-status .icon-shared {
    opacity: 0.7;
}

.license-status.license-warning {
    background: #ff6600;
    color: #fff;
    border: 1px solid #ff6600;
    font-weight: 600;
}

.license-status.license-warning .icon-shared {
    color: #fff !important;
    opacity: 1;
    filter: brightness(0) invert(1);
}

.license-status.license-critical {
    background: var(--color-error);
    color: #fff;
    border: 1px solid var(--color-error);
}

.license-status.license-critical .icon-shared {
    color: #fff;
    opacity: 1;
    filter: brightness(0) invert(1);
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
    font-size: 64px;
    flex-shrink: 0;
    animation: pulse 2s infinite;
    color: #fff !important;
    filter: brightness(0) invert(1);
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
    background: #ff6600;
    border: 2px solid #ff6600;
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
    font-size: 48px;
    flex-shrink: 0;
    opacity: 1;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>');
    background-size: 48px 48px;
    background-repeat: no-repeat;
    background-position: center;
    width: 48px;
    height: 48px;
    display: inline-block;
}

.warning-banner .warning-icon::before {
    content: '';
    display: none;
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
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.contact-button:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.contact-button.secondary {
    background: #fff;
    color: #ff6600;
    border: none;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.contact-button.secondary .icon-external {
    color: #ff6600 !important;
    opacity: 1;
}

.contact-button.secondary:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
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

    .header-left {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .view-header h2 {
        font-size: 24px;
    }

    .mailbox-count {
        font-size: 12px;
        padding: 6px 12px;
    }

    .mailbox-list {
        grid-template-columns: 1fr;
    }

    .view-header button.primary {
        width: 100%;
    }
}
</style>
