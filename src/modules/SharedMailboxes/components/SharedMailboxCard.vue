<template>
    <div class="mailbox-card">
        <div class="card-header">
            <div class="mailbox-icon">
                <span class="icon-shared"></span>
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
        </div>

        <div class="card-actions">
            <button
                class="action-button"
                :title="t('souvera_central', 'Mitglieder verwalten')"
                @click="$emit('manage-members', mailbox)"
            >
                <span class="icon-user"></span>
                {{ t('souvera_central', 'Mitglieder') }}
            </button>
            <button
                class="action-button"
                :title="t('souvera_central', 'Bearbeiten')"
                @click="$emit('edit', mailbox)"
            >
                <span class="icon-rename"></span>
            </button>
            <button
                class="action-button danger"
                :title="t('souvera_central', 'Löschen')"
                @click="$emit('delete', mailbox)"
            >
                <span class="icon-delete"></span>
            </button>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
    name: 'SharedMailboxCard',

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
            const members = this.mailbox.members || []
            return members.length
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
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: rgba(0, 0, 0, 0.03);
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
}

.mailbox-icon [class^='icon-'] {
    filter: invert(1) brightness(100);
    font-size: 24px;
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
    color: #000 !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}

.mailbox-email {
    font-size: 13px;
    color: #666;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mailbox-description {
    font-size: 12px;
    color: #888;
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
    color: #666;
}

.stat-value {
    font-size: 14px;
    font-weight: 600;
    color: #000;
    background: rgba(0, 0, 0, 0.08);
    padding: 3px 10px;
    border-radius: 10px;
}

.card-actions {
    display: flex;
    gap: 8px;
    padding: 15px 20px;
    border-top: 1px solid var(--color-border);
    background: rgba(0, 0, 0, 0.02);
}

.action-button {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    background: #fff;
    border-radius: 6px;
    font-size: 13px;
    color: #333;
    cursor: pointer;
    transition: all 0.2s;
}

.action-button:hover {
    background: rgba(0, 0, 0, 0.05);
    border-color: rgba(0, 0, 0, 0.25);
}

.action-button.danger {
    color: var(--color-error);
    border-color: rgba(227, 56, 80, 0.3);
}

.action-button.danger:hover {
    background: rgba(227, 56, 80, 0.1);
    border-color: var(--color-error);
}

.action-button:first-child {
    flex: 1;
}
</style>
