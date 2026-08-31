<template>
    <div class="settings-section" data-testid="domains-section">
        <div class="section-header">
            <Earth :size="22" />
            <h3>{{ t('souvera_central', 'Mail domains') }}</h3>
            <span class="domain-count">{{ t('souvera_central', '{count} domains', { count: domains.length }) }}</span>
        </div>
        <p class="section-description">
            {{
                t(
                    'souvera_central',
                    'Every domain here is a mail domain: new users can be created with it (dropdown in the user editor) and aliases can be assigned. DNS records (MX, SPF, DKIM, DMARC) are provided by the CloudManager, as with the primary domain.'
                )
            }}
        </p>

        <div v-if="stalwartAvailable === false" class="domains-warning">
            {{ t('souvera_central', 'Stalwart is not reachable — domain status and usage counts are unavailable.') }}
        </div>

        <table class="domains-table" data-testid="domains-table">
            <thead>
                <tr>
                    <th>{{ t('souvera_central', 'Domain') }}</th>
                    <th>{{ t('souvera_central', 'Stalwart') }}</th>
                    <th>{{ t('souvera_central', 'Mailboxes') }}</th>
                    <th>{{ t('souvera_central', 'Aliases') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="d in domains" :key="d.domain" :class="{ 'domain-disabled': !d.allowed }">
                    <td>
                        <strong>{{ d.domain }}</strong>
                        <span v-if="!d.allowed" class="domain-tag">{{ t('souvera_central', 'not allowed') }}</span>
                    </td>
                    <td>
                        <span class="status-dot" :class="d.in_stalwart ? 'ok' : 'missing'"></span>
                        {{ d.in_stalwart ? t('souvera_central', 'created') : t('souvera_central', 'missing') }}
                    </td>
                    <td data-testid="domain-accounts">{{ d.accounts }}</td>
                    <td data-testid="domain-aliases">{{ d.aliases }}</td>
                    <td class="domain-actions">
                        <button
                            v-if="d.allowed"
                            class="danger-button"
                            :disabled="saving"
                            @click="armRemove(d.domain)"
                        >
                            {{ deleteArm === d.domain ? t('souvera_central', 'Really remove?') : t('souvera_central', 'Remove') }}
                        </button>
                        <span v-if="deleteArm === d.domain" class="confirm-cancel" @click="deleteArm = null">
                            {{ t('souvera_central', 'Cancel') }}
                        </span>
                    </td>
                </tr>
                <tr v-if="domains.length === 0">
                    <td colspan="5" class="domains-empty">{{ t('souvera_central', 'No domains configured.') }}</td>
                </tr>
            </tbody>
        </table>

        <div v-if="error" class="domain-error" data-testid="domains-error">{{ error }}</div>

        <div class="domain-add" data-testid="domain-add">
            <input
                v-model="newDomain"
                class="domain-input"
                type="text"
                :placeholder="t('souvera_central', 'example.com')"
                @keyup.enter="addDomain"
            >
            <button class="primary-button" :disabled="saving || newDomain.trim() === ''" @click="addDomain">
                {{ t('souvera_central', 'Add domain') }}
            </button>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import Earth from 'vue-material-design-icons/Earth.vue'

export default {
    name: 'DomainsSection',

    components: { Earth },

    data() {
        return {
            domains: [],
            stalwartAvailable: true,
            newDomain: '',
            saving: false,
            deleteArm: null,
            error: '',
        }
    },

    mounted() {
        this.load()
    },

    methods: {
        t,

        async load() {
            try {
                const response = await axios.get(generateUrl('/apps/souvera_central/api/domains'))
                const data = response.data.ocs?.data || response.data
                this.domains = data.domains || []
                this.stalwartAvailable = data.stalwart_available !== false
            } catch (error) {
                console.error('[SouveraCentral] domains list failed:', error?.response?.status)
            }
        },

        async addDomain() {
            if (this.saving || this.newDomain.trim() === '') {
                return
            }
            this.saving = true
            this.error = ''
            try {
                await axios.post(generateUrl('/apps/souvera_central/api/domains'), {
                    domain: this.newDomain.trim(),
                })
                this.newDomain = ''
                await this.load()
            } catch (error) {
                this.error = this.extractError(error) || 'Adding the domain failed.'
            } finally {
                this.saving = false
            }
        },

        async armRemove(domain) {
            if (this.deleteArm !== domain) {
                this.deleteArm = domain
                return
            }
            this.deleteArm = null
            this.saving = true
            this.error = ''
            try {
                await axios.delete(generateUrl('/apps/souvera_central/api/domains/' + encodeURIComponent(domain)))
                await this.load()
            } catch (error) {
                this.error = this.extractError(error) || 'Removing the domain failed.'
            } finally {
                this.saving = false
            }
        },

        extractError(error) {
            return error?.response?.data?.ocs?.data?.message
                || error?.response?.data?.data?.message
                || error?.response?.data?.message
                || ''
        },
    },
}
</script>

<style scoped>
.domain-count {
    margin-left: auto;
    color: var(--color-text-maxcontrast);
    font-size: 13px;
}

.domains-warning {
    color: var(--color-warning-text, var(--color-warning));
    font-size: 13px;
    margin-bottom: 12px;
}

.domains-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 16px;
}

.domains-table th,
.domains-table td {
    text-align: left;
    padding: 8px 10px;
    border-bottom: 1px solid var(--color-border);
    font-size: 13px;
}

.domain-disabled {
    opacity: 0.65;
}

.domain-tag {
    margin-left: 8px;
    font-size: 11px;
    padding: 1px 6px;
    border-radius: 8px;
    background: var(--color-background-dark);
    color: var(--color-text-maxcontrast);
}

.status-dot {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    margin-right: 6px;
}

.status-dot.ok {
    background: var(--color-success);
}

.status-dot.missing {
    background: var(--color-error);
}

.domain-actions {
    text-align: right;
}

.domains-empty {
    color: var(--color-text-maxcontrast);
}

.domain-error {
    color: var(--color-error-text, var(--color-error));
    font-size: 13px;
    margin-bottom: 12px;
}

.domain-add {
    display: flex;
    gap: 8px;
    align-items: center;
}

.domain-input {
    flex: 0 0 280px;
    padding: 8px 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-element, 8px);
    background: var(--color-main-background);
    color: var(--color-main-text);
}

.primary-button,
.danger-button {
    padding: 8px 14px;
    border-radius: var(--border-radius-element, 8px);
    border: 1px solid var(--color-border);
    cursor: pointer;
    font-size: 13px;
}

.primary-button {
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
    border-color: var(--color-primary-element);
}

.primary-button:disabled {
    opacity: 0.5;
    cursor: default;
}

.danger-button {
    background: transparent;
    color: var(--color-error-text, var(--color-error));
    border-color: var(--color-error);
}

.confirm-cancel {
    color: var(--color-text-maxcontrast);
    cursor: pointer;
    font-size: 13px;
}
</style>
