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
            <!-- Alias-Auslastung -->
            <div class="alias-usage" data-testid="alias-quota-bar">
                <div class="alias-usage-track">
                    <div
                        class="alias-usage-fill"
                        :class="usageClass"
                        :style="{ width: Math.min(aliasPercentage, 100) + '%' }"
                    ></div>
                </div>
                <span class="alias-usage-label">
                    {{ aliases.length }} / {{ maxAliases }} {{ t('souvera_central', 'Aliase') }}
                </span>
            </div>

            <!-- Primary Email (nicht löschbar) -->
            <div class="alias-item primary" data-testid="alias-row-primary">
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
                    <div class="quota-stepper">
                        <input
                            v-model.number="selectedQuotaGB"
                            type="number"
                            min="1"
                            step="1"
                            :max="poolEnabled ? maxGbForThis : null"
                            class="quota-input"
                            :disabled="quotaSaving || unlimited"
                            data-testid="mailbox-quota-input"
                        />
                        <span class="quota-unit">{{ t('souvera_central', 'GB') }}</span>
                    </div>
                    <label
                        v-if="!poolEnabled"
                        class="quota-unlimited-label"
                        data-testid="mailbox-quota-unlimited-label"
                    >
                        <input
                            v-model="unlimited"
                            type="checkbox"
                            :disabled="quotaSaving"
                            data-testid="mailbox-quota-unlimited"
                        />
                        <span>{{ t('souvera_central', 'Unbegrenzt') }}</span>
                    </label>
                    <button
                        type="button"
                        class="quota-save-button"
                        :disabled="quotaSaving || !quotaChanged"
                        data-testid="mailbox-quota-save"
                        @click="saveQuota"
                    >
                        <NcLoadingIcon v-if="quotaSaving" :size="16" />
                        {{ quotaSaving ? t('souvera_central', 'Speichert…') : t('souvera_central', 'Limit setzen') }}
                    </button>
                </div>
                <p v-if="poolEnabled" class="quota-pool-hint" data-testid="mailbox-quota-pool-hint">
                    {{ t('souvera_central', 'Für dieses Postfach im Pool verfügbar: {available}', { available: formatBytes(poolAvailableForThis) }) }}
                </p>
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
                        data-testid="alias-add-input-local"
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
                        data-testid="alias-add-submit"
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
            selectedQuotaGB: 1,
            unlimited: false,
            mailStorage: { max: 0, allocated: 0, available: 0, pool_enabled: false, step_bytes: 1073741824 },
            quotaSaving: false,
            quotaSuccess: null,
            quotaError: null
        }
    },

    computed: {
        canAddAlias() {
            return this.newAliasLocalPart.trim().length > 0 && this.newAliasDomain && !this.isLimitReached
        },

        GiB() {
            return 1024 * 1024 * 1024
        },

        poolEnabled() {
            return !!this.mailStorage.pool_enabled
        },

        // Bytes, die dieses Postfach maximal belegen darf (Rest im Pool + eigenes bisheriges Limit)
        poolAvailableForThis() {
            return (this.mailStorage.available || 0) + Math.max(0, this.currentQuotaBytes)
        },

        maxGbForThis() {
            return Math.max(1, Math.floor(this.poolAvailableForThis / this.GiB))
        },

        selectedQuotaBytes() {
            if (this.unlimited) {
                return 0
            }
            const gb = Number(this.selectedQuotaGB)
            return Number.isFinite(gb) && gb > 0 ? Math.round(gb) * this.GiB : 0
        },

        quotaChanged() {
            return this.selectedQuotaBytes !== this.currentQuotaBytes
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

        usageClass() {
            if (this.isLimitReached) return 'is-full'
            if (this.isAliasWarning) return 'is-warn'
            return ''
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
            const primaryDomain = (this.primaryEmail || '').split('@')[1]
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
                await this.loadMailStorage()

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
                this.syncQuotaControls()
            } catch (error) {
                this.currentQuotaBytes = 0
                this.syncQuotaControls()
            }
        },

        async loadMailStorage() {
            try {
                const url = generateUrl('/apps/souvera_central/api/mail-storage')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data || {}
                this.mailStorage = {
                    max: data.max || 0,
                    allocated: data.allocated || 0,
                    available: data.available || 0,
                    pool_enabled: !!data.pool_enabled,
                    step_bytes: data.step_bytes || 1073741824
                }
            } catch (error) {
                this.mailStorage = { max: 0, allocated: 0, available: 0, pool_enabled: false, step_bytes: 1073741824 }
            } finally {
                this.syncQuotaControls()
            }
        },

        // Stepper + Unbegrenzt-Checkbox aus dem aktuellen Limit ableiten
        syncQuotaControls() {
            const bytes = this.currentQuotaBytes
            if (bytes > 0) {
                this.unlimited = false
                this.selectedQuotaGB = Math.max(1, Math.round(bytes / this.GiB))
            } else {
                // 0 = unbegrenzt. Bei aktivem Pool ist unbegrenzt nicht erlaubt → 1 GB vorschlagen.
                this.unlimited = !this.poolEnabled
                this.selectedQuotaGB = 1
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
            if (this.quotaSaving || !this.quotaChanged) {
                return
            }
            const targetBytes = this.selectedQuotaBytes

            // Client-seitige Vorabprüfung gegen den Pool (Server validiert erneut)
            if (this.poolEnabled) {
                if (targetBytes <= 0) {
                    this.quotaError = this.t('souvera_central', 'Bei aktivem Mail-Speicher-Pool ist „Unbegrenzt" nicht erlaubt.')
                    return
                }
                if (targetBytes > this.poolAvailableForThis) {
                    this.quotaError = this.t('souvera_central', 'Nicht genügend Mail-Speicher im Pool verfügbar.')
                    return
                }
            }

            this.quotaSaving = true
            this.quotaError = null
            this.quotaSuccess = null

            try {
                const url = generateUrl('/apps/souvera_central/api/users/{userId}/mailbox/quota', {
                    userId: this.userId
                })
                const response = await axios.post(url, { quota: targetBytes })
                const data = response.data.ocs?.data || response.data.data || response.data || {}

                const statusCode = response.data?.ocs?.meta?.statuscode
                if (statusCode && statusCode >= 400) {
                    throw new Error(data.error || 'Fehler beim Setzen des Limits')
                }

                if (data.success) {
                    this.currentQuotaBytes = data.quota ?? targetBytes
                    this.syncQuotaControls()
                    this.quotaSuccess = this.t('souvera_central', 'Speicherlimit aktualisiert')
                    // Pool-Anzeige aktualisieren
                    await this.loadMailStorage()
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
    margin-top: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large, 12px);
    background: var(--color-main-background);
    overflow: hidden;
}

.alias-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px 14px;
    background: transparent;
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

/* Alias-Auslastung (Progress) */
.alias-usage {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.alias-usage-track {
    flex: 1;
    height: 8px;
    border-radius: var(--border-radius-pill, 100px);
    background: var(--color-background-dark);
    overflow: hidden;
}

.alias-usage-fill {
    height: 100%;
    border-radius: inherit;
    background: var(--color-primary-element);
    transition: width 0.3s ease, background-color 0.2s ease;
}

.alias-usage-fill.is-warn {
    background: var(--color-warning);
}

.alias-usage-fill.is-full {
    background: var(--color-error);
}

.alias-usage-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    white-space: nowrap;
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
    flex-wrap: wrap;
}

.quota-stepper {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.quota-input {
    width: 110px;
    height: 44px;
    padding: 0 12px;
    border: 2px solid var(--color-border);
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    background: var(--color-main-background);
    color: var(--color-main-text);
}

.quota-input:focus {
    outline: none;
    border-color: var(--color-primary-element);
}

.quota-input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.quota-unit {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-main-text);
}

.quota-unlimited-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--color-main-text);
    cursor: pointer;
}

.quota-unlimited-label input {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

.quota-pool-hint {
    margin: 10px 0 0;
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    font-weight: 500;
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
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    background: var(--color-success);
    color: var(--color-main-background);
    padding: 3px 10px;
    border-radius: var(--border-radius-pill, 100px);
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
    display: grid;
    grid-template-columns: 2fr auto 1.5fr auto;
    align-items: center;
    gap: 10px;
}

.add-alias-input {
    min-width: 0;
    padding: 0 14px;
    height: var(--sc-control-height, 44px);
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
    min-width: 0;
    padding: 0 12px;
    border: 2px solid var(--color-border);
    border-radius: 6px;
    font-size: 14px;
    background: var(--color-main-background);
    color: var(--color-main-text);
    cursor: pointer;
    height: var(--sc-control-height, 44px);
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
    justify-content: center;
    gap: 8px;
    padding: 0 18px;
    height: var(--sc-control-height, 44px);
    background: var(--color-primary-element);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.15s ease;
    white-space: nowrap;
}

.add-alias-button:hover:not(:disabled) {
    background: var(--color-primary-element-hover);
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
        grid-template-columns: 1fr;
    }

    .email-separator {
        display: none;
    }
}
</style>
