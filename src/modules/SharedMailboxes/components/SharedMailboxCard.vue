<template>
    <div class="mailbox-card">
        <div class="card-header">
            <div class="mailbox-icon">
                <EmailMultiple :size="24" />
            </div>
            <div class="mailbox-info">
                <h3 class="mailbox-name">{{ mailbox.name }}</h3>
                <span class="mailbox-email">{{ primaryEmail }}</span>
                <span v-if="mailbox.description" class="mailbox-description">{{ mailbox.description }}</span>
            </div>
        </div>

        <div class="card-body">
            <div class="stat-row">
                <span class="stat-label">{{ t('souvera_central', 'Mitglieder') }}</span>
                <span class="stat-value">{{ memberCount }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">{{ t('souvera_central', 'Speicherlimit') }}</span>
                <span class="stat-value" data-testid="shared-card-quota">{{ quotaDisplay }}</span>
            </div>
        </div>

        <div class="card-actions">
            <button
                class="action-button"
                :title="t('souvera_central', 'Mitglieder verwalten')"
                @click="$emit('manage-members', mailbox)"
            >
                <AccountMultiple :size="16" />
                {{ t('souvera_central', 'Mitglieder') }}
            </button>
            <button
                class="action-button"
                :title="t('souvera_central', 'Bearbeiten')"
                @click="$emit('edit', mailbox)"
            >
                <Pencil :size="16" />
            </button>
            <button
                class="action-button danger"
                :title="t('souvera_central', 'Löschen')"
                @click="$emit('delete', mailbox)"
            >
                <Delete :size="16" />
            </button>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import EmailMultiple from 'vue-material-design-icons/EmailMultiple.vue'
import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

export default {
    name: 'SharedMailboxCard',

    components: {
        EmailMultiple,
        AccountMultiple,
        Pencil,
        Delete
    },

    props: {
        mailbox: {
            type: Object,
            required: true
        }
    },

    emits: ['edit', 'delete', 'manage-members'],

    computed: {
        primaryEmail() {
            const emails = this.mailbox.emails || []
            return emails[0] || this.mailbox.name
        },

        memberCount() {
            if (typeof this.mailbox.memberCount === 'number') {
                return this.mailbox.memberCount
            }
            const members = this.mailbox.members || []
            return members.length
        },

        quotaDisplay() {
            const bytes = this.mailbox.quota || 0
            if (!bytes || bytes <= 0) {
                return this.t('souvera_central', 'Unbegrenzt')
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
    },

    methods: {
        t
    }
}
</script>

<style scoped>
.mailbox-card {
    background: var(--color-main-background);
    border: 2px solid var(--color-border);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.2s;
}

.mailbox-card:hover {
    border-color: var(--color-primary-element);
    box-shadow: 0 4px 12px var(--color-box-shadow);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: var(--color-background-hover);
    border-bottom: 1px solid var(--color-border);
}

.mailbox-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-primary-element);
    border-radius: 10px;
    color: var(--color-primary-element-text);
}

.mailbox-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.mailbox-name {
    display: block;
    margin: 0 0 4px;
    padding: 0;
    font-size: 16px;
    font-weight: 600;
    color: var(--color-main-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}

.mailbox-email {
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mailbox-description {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    font-style: italic;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}

.card-body {
    padding: 15px 20px;
}

.stat-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-label {
    font-size: 13px;
    color: var(--color-text-maxcontrast);
}

.stat-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-main-text);
    background: var(--color-background-dark);
    padding: 3px 10px;
    border-radius: 10px;
}

.card-actions {
    display: flex;
    gap: 8px;
    padding: 15px 20px;
    border-top: 1px solid var(--color-border);
    background: var(--color-background-hover);
}

.action-button {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    background: var(--color-main-background);
    border-radius: 6px;
    font-size: 13px;
    color: var(--color-main-text);
    cursor: pointer;
    transition: background-color 0.2s, border-color 0.2s, color 0.2s;
}

.action-button:hover {
    background: var(--color-background-hover);
    border-color: var(--color-border-maxcontrast);
}

.action-button.danger {
    color: var(--color-error);
    border-color: rgba(var(--color-error-rgb), 0.3);
}

.action-button.danger:hover {
    background: rgba(var(--color-error-rgb), 0.1);
    border-color: var(--color-error);
}

.action-button:first-child {
    flex: 1;
}
</style>
