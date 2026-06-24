<template>
    <div class="alias-manager">
        <!-- Header -->
        <div class="alias-header">
            <h4>{{ t('souvera_central', 'Email-Aliase') }}</h4>
            <span class="alias-count" :class="{ 'warning': isAliasWarning, 'limit-reached': isLimitReached }">
                {{ aliases.length }} / {{ maxAliases }} {{ t('souvera_central', 'Alias(e)') }}
            </span>
        </div>

        <!-- KRITISCHES WARNING: Limit erreicht -->
        <div v-if="isLimitReached && stalwartAvailable && !loading" class="alias-critical-warning">
            <AlertCircle :size="16" />
            <span>
                {{
                    t(
                        'souvera_central',
                        'Alias-Limit erreicht! Es können keine weiteren Aliase hinzugefügt werden.'
                    )
                }}
            </span>
        </div>

        <!-- WARNING: Limit bald erreicht (80%+) -->
        <div v-else-if="isAliasWarning && stalwartAvailable && !loading" class="alias-warning-banner">
            <AlertCircle :size="16" />
            <span>
                {{
                    t(
                        'souvera_central',
                        'Alias-Limit bald erreicht ({percentage}%).',
                        { percentage: aliasPercentage }
                    )
                }}
            </span>
        </div>

        <!-- Stalwart Status Warning -->
        <div v-if="!stalwartAvailable && !loading" class="stalwart-warning">
            <AlertCircle :size="16" />
            <span>{{ t('souvera_central', 'Mail-Server nicht erreichbar. Alias-Verwaltung nicht verfügbar.') }}</span>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="alias-loading">
            <NcLoadingIcon :size="20" />
            <span>{{ t('souvera_central', 'Lade Aliase...') }}</span>
        </div>

        <!-- Alias List -->
        <div v-else-if="stalwartAvailable" class="alias-content">
            <!-- Primary Email (nicht löschbar) -->
            <div class="alias-item primary">
                <div class="alias-info">
                    <Email :size="16" />
                    <span class="alias-email">{{ primaryEmail }}</span>
                    <span class="primary-badge">{{ t('souvera_central', 'Primär') }}</span>
                </div>
            </div>

            <!-- Postfach-Speicherlimit (Quota) -->
            <div class="mailbox-quota" data-testid="mailbox-quota-section">
                <div class="quota-label-row">
                    <span class="quota-label">{{ t('souvera_central', 'Postfach-Speicherlimit') }}</span>
                    <span class="quota-current">{{ quotaDisplay }}</span>
                </div>
                <div class="quota-control-row">
                    <select
                        v-model.number="selectedQuotaBytes"
                        class="quota-select"
                        :disabled="quotaSaving"
                        data-testid="mailbox-quota-select"
                    >
                        <option v-for="opt in quotaOptions" :key="opt.bytes" :value="opt.bytes">
                            {{ opt.label }}
                        </option>
                    </select>
                    <button
                        type="button"
                        class="quota-save-button"
                        :disabled="quotaSaving || selectedQuotaBytes === currentQuotaBytes"
                        data-testid="mailbox-quota-save"
                        @click="saveQuota"
                    >
                        <NcLoadingIcon v-if="quotaSaving" :size="16" />
                        {{ quotaSaving ? t('souvera_central', 'Speichert…') : t('souvera_central', 'Limit setzen') }}
                    </button>
                </div>
                <p v-if="quotaSuccess" class="success-message" data-testid="mailbox-quota-success">{{ quotaSuccess }}</p>
                <p v-if="quotaError" class="error-message" data-testid="mailbox-quota-error">{{ quotaError }}</p>
            </div>

            <!-- Alias Liste -->
            <div v-for="alias in aliases" :key="alias" class="alias-item">
                <div class="alias-info">
                    <Email :size="16" />
                    <span class="alias-email">{{ alias }}</span>
                </div>
                <button
                    type="button"
                    class="alias-remove"
                    :disabled="removingAlias === alias"
                    :title="t('souvera_central', 'Alias entfernen')"
                    @click="removeAlias(alias)"
                >
                    <NcLoadingIcon v-if="removingAlias === alias" :size="16" />
                    <Delete v-else :size="16" />
                </button>
            </div>

            <!-- Kein Alias Hinweis -->
            <div v-if="aliases.length === 0" class="no-aliases">
                <InformationOutline :size="16" />
                <span>{{ t('souvera_central', 'Keine zusätzlichen Aliase konfiguriert.') }}</span>
            </div>

            <!-- Neuen Alias hinzufügen -->
            <div class="add-alias-form">
                <div class="add-alias-input-group">
                    <input
                        v-model="newAliasLocalPart"
                        type="text"
                        class="add-alias-input"
                        :class="{ error: aliasError }"
                        :placeholder="t('souvera_central', 'neuer-alias')"
                        :disabled="addingAlias"
                        @keyup.enter="addAlias"
                        @input="clearError"
                    />
                    <span class="email-separator">@</span>
                    <select
                        v-model="newAliasDomain"
                        class="add-alias-domain"
                        :disabled="addingAlias || allowedDomains.length <= 1"
                    >
                        <option v-for="domain in allowedDomains" :key="domain" :value="domain">
                            {{ domain }}
                        </option>
                    </select>
                    <button
                        type="button"
                        class="add-alias-button"
                        :disabled="!canAddAlias || addingAlias"
                        @click="addAlias"
                    >
                        <NcLoadingIcon v-if="addingAlias" :size="16" />
                        <Plus v-else :size="16" />
                        {{ t('souvera_central', 'Hinzufügen') }}
                    </button>
                </div>
                <p v-if="aliasError" class="error-message">{{ aliasError }}</p>
                <p v-if="aliasSuccess" class="success-message">{{ aliasSuccess }}</p>
            </div>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import Email from 'vue-material-design-icons/Email.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
    name: 'AliasManager',

    components: {
        NcLoadingIcon,
        AlertCircle,
        Email,
        InformationOutline,
        Delete,
        Plus
    },

    props: {
        userId: {
            type: String,
            required: true
        },
        primaryEmail: {
            type: String,
            required: true
        },
        allowedDomains: {
            type: Array,
            default: () => []
        }
    },

    data() {
        return {
            loading: true,
            stalwartAvailable: false,
            aliases: [],
            maxAliases: 10,
            warningThreshold: 0.8,
            newAliasLocalPart: '',
            newAliasDomain: '',
            addingAlias: false,
            removingAlias: null,
            aliasError: null,
            aliasSuccess: null,
            currentQuotaBytes: 0,
            selectedQuotaBytes: 0,
            quotaSaving: false,
            quotaSuccess: null,
            quotaError: null
        }
    },

    computed: {
        canAddAlias() {
            return this.newAliasLocalPart.trim().length > 0 && this.newAliasDomain && !this.isLimitReached
        },

        quotaOptions() {
            const GB = 1024 * 1024 * 1024
            return [
                { bytes: 0, label: this.t('souvera_central', 'Unbegrenzt') },
                { bytes: 1 * GB, label: '1 GB' },
                { bytes: 5 * GB, label: '5 GB' },
                { bytes: 10 * GB, label: '10 GB' },
                { bytes: 25 * GB, label: '25 GB' },
                { bytes: 50 * GB, label: '50 GB' }
            ]
        },

        quotaDisplay() {
            return this.formatBytes(this.currentQuotaBytes)
        },

        aliasPercentage() {
            if (this.maxAliases === 0) return 0
            return Math.round((this.aliases.length / this.maxAliases) * 100)
        },

        isLimitReached() {
            return this.aliases.length >= this.maxAliases
        },

        isAliasWarning() {
            return !this.isLimitReached &&
                this.aliases.length / this.maxAliases >= this.warningThreshold
        },

        newAliasEmail() {
            if (!this.newAliasLocalPart || !this.newAliasDomain) {
                return ''
            }
            return `${this.newAliasLocalPart.trim().toLowerCase()}@${this.newAliasDomain}`
        }
    },

    mounted() {
        this.loadAliases()

        // Default Domain setzen
        if (this.allowedDomains.length > 0) {
            // Versuche die Domain der Primary-Email zu nutzen
            const primaryDomain = this.primaryEmail.split('@')[1]
            if (this.allowedDomains.includes(primaryDomain)) {
                this.newAliasDomain = primaryDomain
            } else {
                this.newAliasDomain = this.allowedDomains[0]
            }
        }
    },

    methods: {
        t,

        async loadAliases() {
            this.loading = true

            try {
                // Zuerst Stalwart-Status prüfen
                const statusUrl = generateUrl('/apps/souvera_central/api/stalwart/status')
                const statusResponse = await axios.get(statusUrl)
                const statusData = statusResponse.data.ocs?.data || statusResponse.data.data || statusResponse.data

                this.stalwartAvailable = statusData.available === true

                if (!this.stalwartAvailable) {
                    this.loading = false
                    return
                }

                // Aliase laden
                const url = generateUrl('/apps/souvera_central/api/users/{userId}/aliases', {
                    userId: this.userId
                })
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data

                this.aliases = data.aliases || []
                this.maxAliases = data.maxAliases || 10

                // Postfach-Status (inkl. Quota) laden
                await this.loadMailboxQuota()

            } catch (error) {
                console.error('AliasManager: Fehler beim Laden', error)
                this.stalwartAvailable = false
            } finally {
                this.loading = false
            }
        },

        async loadMailboxQuota() {
            try {
                const url = generateUrl('/apps/souvera_central/api/users/{userId}/mailbox', {
                    userId: this.userId
                })
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data || {}
                this.currentQuotaBytes = data.quota || 0
                this.selectedQuotaBytes = this.currentQuotaBytes
            } catch (error) {
                this.currentQuotaBytes = 0
                this.selectedQuotaBytes = 0
            }
        },

        formatBytes(bytes) {
            if (!bytes || bytes <= 0) {
                return this.t('souvera_central', 'Unbegrenzt')
            }
            const GB = 1024 * 1024 * 1024
            const MB = 1024 * 1024
            if (bytes >= GB) {
                return `${(bytes / GB).toFixed(bytes % GB === 0 ? 0 : 1)} GB`
            }
            return `${Math.round(bytes / MB)} MB`
        },

        async saveQuota() {
            if (this.quotaSaving || this.selectedQuotaBytes === this.currentQuotaBytes) {
                return
            }
            this.quotaSaving = true
            this.quotaError = null
            this.quotaSuccess = null

            try {
                const url = generateUrl('/apps/souvera_central/api/users/{userId}/mailbox/quota', {
                    userId: this.userId
                })
                const response = await axios.post(url, { quota: this.selectedQuotaBytes })
                const data = response.data.ocs?.data || response.data.data || response.data || {}

                const statusCode = response.data?.ocs?.meta?.statuscode
                if (statusCode && statusCode >= 400) {
                    throw new Error(data.error || 'Fehler beim Setzen des Limits')
                }

                if (data.success) {
                    this.currentQuotaBytes = data.quota ?? this.selectedQuotaBytes
                    this.selectedQuotaBytes = this.currentQuotaBytes
                    this.quotaSuccess = this.t('souvera_central', 'Speicherlimit aktualisiert')
                    setTimeout(() => {
                        this.quotaSuccess = null
                    }, 3000)
                } else {
                    throw new Error(data.error || 'Unbekannter Fehler')
                }
            } catch (error) {
                this.quotaError =
                    error.response?.data?.ocs?.data?.error ||
                    error.response?.data?.error ||
                    error.message ||
                    this.t('souvera_central', 'Fehler beim Setzen des Limits')
            } finally {
                this.quotaSaving = false
            }
        },

        async addAlias() {
            if (!this.canAddAlias || this.addingAlias) {
                return
            }

            this.clearError()
            this.addingAlias = true

            try {
                const url = generateUrl('/apps/souvera_central/api/users/{userId}/aliases', {
                    userId: this.userId
                })

                const response = await axios.post(url, {
                    alias: this.newAliasEmail
                })

                const data = response.data.ocs?.data || response.data.data || response.data

                // Prüfe auf OCS-Fehler
                const statusCode = response.data?.ocs?.meta?.statuscode
                if (statusCode && statusCode >= 400) {
                    throw new Error(data.error || 'Fehler beim Hinzufügen')
                }

                if (data.success) {
                    this.aliases = data.aliases || []
                    this.newAliasLocalPart = ''
                    this.aliasSuccess = this.t('souvera_central', 'Alias erfolgreich hinzugefügt')

                    setTimeout(() => {
                        this.aliasSuccess = null
                    }, 3000)
                } else {
                    throw new Error(data.error || 'Unbekannter Fehler')
                }

            } catch (error) {
                console.error('AliasManager: Fehler beim Hinzufügen', error)

                let errorMessage = this.t('souvera_central', 'Fehler beim Hinzufügen des Alias')

                if (error.response?.data?.ocs?.data?.error) {
                    errorMessage = error.response.data.ocs.data.error
                } else if (error.response?.data?.error) {
                    errorMessage = error.response.data.error
                } else if (error.message) {
                    errorMessage = error.message
                }

                this.aliasError = errorMessage

            } finally {
                this.addingAlias = false
            }
        },

        async removeAlias(alias) {
            if (this.removingAlias) {
                return
            }

            this.clearError()
            this.removingAlias = alias

            try {
                const url = generateUrl('/apps/souvera_central/api/users/{userId}/aliases/{alias}', {
                    userId: this.userId,
                    alias: alias
                })

                const response = await axios.delete(url)
                const data = response.data.ocs?.data || response.data.data || response.data

                // Prüfe auf OCS-Fehler
                const statusCode = response.data?.ocs?.meta?.statuscode
                if (statusCode && statusCode >= 400) {
                    throw new Error(data.error || 'Fehler beim Entfernen')
                }

                if (data.success) {
                    this.aliases = data.aliases || []
                    this.aliasSuccess = this.t('souvera_central', 'Alias erfolgreich entfernt')

                    setTimeout(() => {
                        this.aliasSuccess = null
                    }, 3000)
                } else {
                    throw new Error(data.error || 'Unbekannter Fehler')
                }

            } catch (error) {
                console.error('AliasManager: Fehler beim Entfernen', error)

                let errorMessage = this.t('souvera_central', 'Fehler beim Entfernen des Alias')

                if (error.response?.data?.ocs?.data?.error) {
                    errorMessage = error.response.data.ocs.data.error
                } else if (error.response?.data?.error) {
                    errorMessage = error.response.data.error
                } else if (error.message) {
                    errorMessage = error.message
                }

                this.aliasError = errorMessage

            } finally {
                this.removingAlias = null
            }
        },

        clearError() {
            this.aliasError = null
            this.aliasSuccess = null
        }
    }
}
</script>

<style scoped>
.alias-manager {
    margin-top: 25px;
    border: 2px solid var(--color-border);
    border-radius: 6px;
    background: var(--color-main-background);
    overflow: hidden;
}

.alias-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 20px;
    background: var(--color-background-hover);
    border-bottom: 1px solid var(--color-border);
}

.alias-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: var(--color-main-text);
}

.alias-count {
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    background: var(--color-background-dark);
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 500;
}

.alias-count.warning {
    background: var(--color-warning);
    color: #fff;
    border: 1px solid var(--color-warning);
    font-weight: 600;
}

.alias-count.limit-reached {
    background: var(--color-error);
    color: #fff;
    border: 1px solid var(--color-error);
    font-weight: 600;
}

/* Alias Critical Warning */
.alias-critical-warning {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 20px;
    background: var(--color-error);
    color: #fff;
    font-size: 14px;
    font-weight: 500;
    border-radius: 0;
}

.alias-critical-warning .material-design-icon {
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

/* Alias Warning Banner */
.alias-warning-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 20px;
    background: var(--color-warning);
    color: #fff;
    font-size: 14px;
    font-weight: 500;
    border-radius: 0;
}

.alias-warning-banner .material-design-icon {
    flex-shrink: 0;
}

.stalwart-warning {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 20px;
    background: rgba(var(--color-error-rgb), 0.1);
    color: var(--color-error);
    font-size: 14px;
}

.stalwart-warning .material-design-icon {
    flex-shrink: 0;
}

.alias-loading {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px;
    color: var(--color-text-maxcontrast);
    font-size: 14px;
}

.alias-content {
    padding: 15px 20px;
}

.alias-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 15px;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    margin-bottom: 10px;
}

.alias-item.primary {
    background: rgba(var(--color-primary-element-rgb), 0.1);
    border-color: rgba(var(--color-primary-element-rgb), 0.3);
}

/* Postfach-Speicherlimit (Quota) */
.mailbox-quota {
    padding: 14px 15px;
    margin-bottom: 12px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    background: var(--color-background-dark);
}

.quota-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.quota-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-main-text);
}

.quota-current {
    font-size: 13px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 12px;
    background: var(--color-primary-element-light);
    color: var(--color-primary-element-light-text);
}

.quota-control-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.quota-select {
    flex: 1;
    min-width: 0;
    height: 44px;
    padding: 0 12px;
    border: 2px solid var(--color-border);
    border-radius: 6px;
    font-size: 14px;
    background: var(--color-main-background);
    color: var(--color-main-text);
    cursor: pointer;
}

.quota-select:focus {
    outline: none;
    border-color: var(--color-primary-element);
}

.quota-select:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.quota-save-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 18px;
    height: 44px;
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.15s;
    white-space: nowrap;
}

.quota-save-button:hover:not(:disabled) {
    background: var(--color-primary-element-hover);
}

.quota-save-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.alias-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.alias-info .material-design-icon {
    color: var(--color-text-maxcontrast);
}

.alias-email {
    font-size: 14px;
    font-weight: 500;
    color: var(--color-main-text);
}

.primary-badge {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    background: var(--color-primary-element);
    color: #fff;
    padding: 3px 8px;
    border-radius: 10px;
}

.alias-remove {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: var(--color-error);
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.2s;
}

.alias-remove:hover:not(:disabled) {
    background: rgba(var(--color-error-rgb), 0.1);
}

.alias-remove:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.no-aliases {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    background: var(--color-background-hover);
    border-radius: 6px;
    color: var(--color-text-maxcontrast);
    font-size: 14px;
    margin-bottom: 15px;
}

.add-alias-form {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--color-border);
}

.add-alias-input-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.add-alias-input {
    flex: 1;
    min-width: 0;
    padding: 12px 14px;
    border: 2px solid var(--color-border);
    border-radius: 6px;
    font-size: 14px;
    background: var(--color-main-background);
    color: var(--color-main-text);
}

.add-alias-input:focus {
    outline: none;
    border-color: var(--color-primary-element);
    box-shadow: 0 0 0 3px rgba(var(--color-primary-element-rgb), 0.2);
}

.add-alias-input.error {
    border-color: var(--color-error);
}

.add-alias-input:disabled {
    background: var(--color-background-dark);
    cursor: not-allowed;
}

.email-separator {
    font-weight: 700;
    color: var(--color-main-text);
    font-size: 16px;
}

.add-alias-domain {
    min-width: 280px;
    padding: 0 12px;
    border: 2px solid var(--color-border);
    border-radius: 6px;
    font-size: 14px;
    background: var(--color-main-background);
    color: var(--color-main-text);
    cursor: pointer;
    height: 44px;
}

.add-alias-domain:focus {
    outline: none;
    border-color: var(--color-primary-element);
}

.add-alias-domain:disabled {
    background: var(--color-background-dark);
    cursor: not-allowed;
}

.add-alias-button {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 18px;
    background: var(--color-primary-element);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.add-alias-button:hover:not(:disabled) {
    background: var(--color-primary-element-hover);
    transform: translateY(-1px);
}

.add-alias-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.error-message {
    margin: 10px 0 0;
    font-size: 13px;
    color: var(--color-error);
    font-weight: 500;
}

.success-message {
    margin: 10px 0 0;
    font-size: 13px;
    color: var(--color-success);
    font-weight: 500;
}

/* Responsive */
@media (max-width: 768px) {
    .add-alias-input-group {
        flex-wrap: wrap;
    }

    .add-alias-input {
        flex: 1 1 100%;
        min-width: 100%;
    }

    .email-separator {
        display: none;
    }

    .add-alias-domain {
        flex: 1;
    }

    .add-alias-button {
        flex: 1;
    }
}
</style>
