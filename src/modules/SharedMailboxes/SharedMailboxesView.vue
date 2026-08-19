<template>
    <div class="shared-mailboxes-view">
        <!-- Header -->
        <div class="view-header">
            <div class="header-left">
                <h2>{{ t('souvera_central', 'Shared mailboxes') }}</h2>
                <div
                    class="license-status"
                    :class="{ 'license-warning': isMailboxWarning, 'license-critical': isMailboxLimitReached }"
                >
                    <EmailMultiple :size="16" />
                    <span class="license-info">{{ mailboxes.length }} von {{ maxMailboxes }} Postfächer</span>
                </div>
                <div
                    v-if="mailStorage.pool_enabled"
                    class="pool-status"
                    data-testid="shared-pool-status"
                >
                    <Database :size="16" />
                    <span>{{ t('souvera_central', 'Mail storage: {available} of {max} free', { available: formatBytes(mailStorage.available), max: formatBytes(mailStorage.max) }) }}</span>
                </div>
            </div>
            <button
                class="primary"
                :disabled="isMailboxLimitReached"
                :title="isMailboxLimitReached ? t('souvera_central', 'Limit reached') : ''"
                @click="showCreateModal = true"
            >
                <Plus :size="18" />
                {{ t('souvera_central', 'New mailbox') }}
            </button>
        </div>

        <!-- KRITISCHES WARNING: Limit erreicht -->
        <div v-if="isMailboxLimitReached" class="critical-warning">
            <div class="warning-content">
                <AlertCircle :size="36" class="warning-icon" />
                <div class="warning-text">
                    <h3>{{ t('souvera_central', 'Limit reached!') }}</h3>
                    <p>
                        {{
                            t(
                                'souvera_central',
                                'You have created {count} of {total} shared mailboxes. No further mailboxes can be created.',
                                { count: mailboxes.length, total: maxMailboxes }
                            )
                        }}
                    </p>
                </div>
                <a :href="contactUrl" target="_blank" class="contact-button">
                    <OpenInNew :size="16" />
                    {{ t('souvera_central', 'Extend limit') }}
                </a>
            </div>
        </div>

        <!-- WARNING: Limit bald erreicht -->
        <div v-else-if="isMailboxWarning" class="warning-banner">
            <div class="warning-content">
                <AlertCircle :size="36" class="warning-icon" />
                <div class="warning-text">
                    <h3>{{ t('souvera_central', 'Limit almost reached') }}</h3>
                    <p>
                        {{
                            t(
                                'souvera_central',
                                'You have created {count} of {total} shared mailboxes ({percentage}%).',
                                { count: mailboxes.length, total: maxMailboxes, percentage: mailboxPercentage }
                            )
                        }}
                    </p>
                </div>
                <a :href="contactUrl" target="_blank" class="contact-button secondary">
                    <OpenInNew :size="16" />
                    {{ t('souvera_central', 'Contact') }}
                </a>
            </div>
        </div>

        <!-- Stalwart Status Warning -->
        <div v-if="!stalwartAvailable && !loading" class="stalwart-warning">
            <AlertCircle :size="18" />
            <span>{{ t('souvera_central', 'Mail server not reachable. Shared mailbox management not available.') }}</span>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="loading-container">
            <NcLoadingIcon :size="32" />
            <span>{{ t('souvera_central', 'Loading mailboxes...') }}</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="stalwartAvailable && mailboxes.length === 0" class="empty-state">
            <div class="empty-icon">
                <EmailMultiple :size="48" />
            </div>
            <h3>{{ t('souvera_central', 'No shared mailboxes') }}</h3>
            <p>{{ t('souvera_central', 'Create a shared mailbox for your team.') }}</p>
            <button class="primary" @click="showCreateModal = true">
                <Plus :size="18" />
                {{ t('souvera_central', 'Create first mailbox') }}
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
            :mail-storage="mailStorage"
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
            :title="t('souvera_central', 'Delete mailbox')"
            :message="t('souvera_central', 'Do you really want to delete the mailbox \'{name}\'?', { name: selectedMailbox?.description || selectedMailbox?.name })"
            :confirm-text="t('souvera_central', 'Delete')"
            :cancel-text="t('souvera_central', 'Cancel')"
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
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import EmailMultiple from 'vue-material-design-icons/EmailMultiple.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import Database from 'vue-material-design-icons/Database.vue'

export default {
    name: 'SharedMailboxesView',

    components: {
        SharedMailboxCard,
        SharedMailboxModal,
        MembersModal,
        ConfirmDialog,
        NcLoadingIcon,
        EmailMultiple,
        Plus,
        AlertCircle,
        OpenInNew,
        Database
    },

    data() {
        return {
            loading: true,
            stalwartAvailable: false,
            mailboxes: [],
            allowedDomains: [],
            maxMailboxes: 10,
            warningThreshold: 0.8,
            mailStorage: { max: 0, allocated: 0, available: 0, pool_enabled: false, step_bytes: 1073741824 },
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
                if (data.mailStorage) {
                    this.mailStorage = {
                        max: data.mailStorage.max || 0,
                        allocated: data.mailStorage.allocated || 0,
                        available: data.mailStorage.available || 0,
                        pool_enabled: !!data.mailStorage.pool_enabled,
                        step_bytes: data.mailStorage.step_bytes || 1073741824
                    }
                }
            } catch (error) {
                console.error('SharedMailboxesView: Fehler beim Laden der Postfächer', error)
                this.showToast(this.t('souvera_central', 'Error loading the mailboxes'), 'error')
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
                    this.showToast(this.t('souvera_central', 'Mailbox updated'))
                } else {
                    // Create
                    const url = generateUrl('/apps/souvera_central/api/shared-mailboxes')
                    await axios.post(url, mailboxData)
                    this.showToast(this.t('souvera_central', 'Mailbox created'))
                }

                this.closeModal()
                await this.loadMailboxes()

            } catch (error) {
                console.error('SharedMailboxesView: Fehler beim Speichern', error)
                const errorMsg = error.response?.data?.ocs?.data?.error ||
                    error.response?.data?.error ||
                    this.t('souvera_central', 'Error while saving')
                this.showToast(errorMsg, 'error')
            }
        },

        async deleteMailbox() {
            try {
                const url = generateUrl('/apps/souvera_central/api/shared-mailboxes/{id}', {
                    id: this.selectedMailbox.name
                })
                await axios.delete(url)

                this.showToast(this.t('souvera_central', 'Mailbox deleted'))
                this.showDeleteConfirm = false
                this.selectedMailbox = null
                await this.loadMailboxes()

            } catch (error) {
                console.error('SharedMailboxesView: Fehler beim Löschen', error)
                const errorMsg = error.response?.data?.ocs?.data?.error ||
                    error.response?.data?.error ||
                    this.t('souvera_central', 'Error while deleting')
                this.showToast(errorMsg, 'error')
            }
        },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type }
            setTimeout(() => {
                this.toast.show = false
            }, 3000)
        },

        formatBytes(bytes) {
            if (!bytes || bytes <= 0) {
                return this.t('souvera_central', 'Unlimited')
            }
            const TB = 1024 ** 4
            const GB = 1024 ** 3
            const MB = 1024 ** 2
            if (bytes >= TB) {
                return `${(bytes / TB).toFixed(bytes % TB === 0 ? 0 : 1)} TB`
            }
            if (bytes >= GB) {
                return `${(bytes / GB).toFixed(bytes % GB === 0 ? 0 : 1)} GB`
            }
            return `${Math.round(bytes / MB)} MB`
        }
    }
}
</script>

<style scoped>
.shared-mailboxes-view {
    padding: 30px;
    max-width: none;
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
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
}

.license-status .material-design-icon {
    opacity: 0.8;
}

.pool-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: rgba(var(--color-primary-element-rgb), 0.1);
    border: 1px solid rgba(var(--color-primary-element-rgb), 0.3);
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    color: var(--color-main-text);
}

.pool-status .material-design-icon {
    color: var(--color-primary-element);
}

.license-status.license-warning {
    background: var(--color-warning);
    color: #fff;
    border: 1px solid var(--color-warning);
    font-weight: 600;
}

.license-status.license-warning .material-design-icon,
.license-status.license-critical .material-design-icon {
    opacity: 1;
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
    box-shadow: 0 4px 12px var(--color-box-shadow);
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
    box-shadow: 0 2px 8px var(--color-box-shadow);
}

.warning-banner .warning-content {
    display: flex;
    align-items: center;
    gap: 20px;
    color: #fff;
}

.warning-banner .warning-icon {
    flex-shrink: 0;
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
    background: var(--color-main-background);
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
    box-shadow: 0 4px 12px var(--color-box-shadow);
}

.contact-button.secondary {
    background: var(--color-main-background);
    color: var(--color-warning);
    border: none;
    box-shadow: 0 2px 6px var(--color-box-shadow);
}

.contact-button.secondary:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px var(--color-box-shadow);
}

.stalwart-warning {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 20px;
    background: rgba(var(--color-error-rgb), 0.1);
    border: 1px solid rgba(var(--color-error-rgb), 0.3);
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
    color: var(--color-text-maxcontrast);
    font-size: 16px;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    text-align: center;
    background: var(--color-main-background);
    border: 2px dashed var(--color-border);
    border-radius: 12px;
}

.empty-icon {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-background-dark);
    border-radius: 50%;
    margin-bottom: 20px;
    color: var(--color-text-maxcontrast);
}

.empty-icon .material-design-icon {
    opacity: 0.6;
}

.empty-state h3 {
    margin: 0 0 10px;
    font-size: 18px;
    font-weight: 600;
    color: var(--color-main-text);
}

.empty-state p {
    margin: 0 0 20px;
    color: var(--color-text-maxcontrast);
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
