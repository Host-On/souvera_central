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
            <span class="icon-error"></span>
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
            <span class="icon-error"></span>
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
            <span class="icon-error"></span>
            <span>{{ t('souvera_central', 'Mail-Server nicht erreichbar. Alias-Verwaltung nicht verfügbar.') }}</span>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="alias-loading">
            <span class="icon-loading-small"></span>
            <span>{{ t('souvera_central', 'Lade Aliase...') }}</span>
        </div>

        <!-- Alias List -->
        <div v-else-if="stalwartAvailable" class="alias-content">
            <!-- Primary Email (nicht löschbar) -->
            <div class="alias-item primary">
                <div class="alias-info">
                    <span class="icon-mail"></span>
                    <span class="alias-email">{{ primaryEmail }}</span>
                    <span class="primary-badge">{{ t('souvera_central', 'Primär') }}</span>
                </div>
            </div>

            <!-- Alias Liste -->
            <div v-for="alias in aliases" :key="alias" class="alias-item">
                <div class="alias-info">
                    <span class="icon-mail"></span>
                    <span class="alias-email">{{ alias }}</span>
                </div>
                <button
                    type="button"
                    class="alias-remove"
                    :disabled="removingAlias === alias"
                    :title="t('souvera_central', 'Alias entfernen')"
                    @click="removeAlias(alias)"
                >
                    <span v-if="removingAlias === alias" class="icon-loading-small"></span>
                    <span v-else class="icon-delete"></span>
                </button>
            </div>

            <!-- Kein Alias Hinweis -->
            <div v-if="aliases.length === 0" class="no-aliases">
                <span class="icon-info"></span>
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
                        <span v-if="addingAlias" class="icon-loading-small"></span>
                        <span v-else class="icon-add"></span>
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

export default {
    name: 'AliasManager',

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
            aliasSuccess: null
        }
    },

    computed: {
        canAddAlias() {
            return this.newAliasLocalPart.trim().length > 0 && this.newAliasDomain && !this.isLimitReached
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

            } catch (error) {
                console.error('AliasManager: Fehler beim Laden', error)
                this.stalwartAvailable = false
            } finally {
                this.loading = false
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
    background: rgba(0, 0, 0, 0.03);
    border-bottom: 1px solid var(--color-border);
}

.alias-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #000;
}

.alias-count {
    font-size: 13px;
    color: #666;
    background: rgba(0, 0, 0, 0.08);
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

.alias-critical-warning .icon-error {
    flex-shrink: 0;
    font-size: 24px;
    filter: brightness(0) invert(1);
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

.alias-warning-banner .icon-error {
    flex-shrink: 0;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>');
    background-size: 24px 24px;
    background-repeat: no-repeat;
    background-position: center;
    width: 24px;
    height: 24px;
    display: inline-block;
}

.alias-warning-banner .icon-error::before {
    content: '';
    display: none;
}

.stalwart-warning {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 20px;
    background: rgba(227, 56, 80, 0.1);
    color: var(--color-error);
    font-size: 14px;
}

.stalwart-warning .icon-error {
    flex-shrink: 0;
}

.alias-loading {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px;
    color: #666;
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
    background: rgba(48, 116, 191, 0.1);
    border-color: rgba(48, 116, 191, 0.3);
}

.alias-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.alias-info .icon-mail {
    color: #666;
    font-size: 16px;
}

.alias-email {
    font-size: 14px;
    font-weight: 500;
    color: #000;
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
    background: rgba(227, 56, 80, 0.1);
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
    background: rgba(0, 0, 0, 0.03);
    border-radius: 6px;
    color: #666;
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
    color: #000;
}

.add-alias-input:focus {
    outline: none;
    border-color: var(--color-primary-element);
    box-shadow: 0 0 0 3px rgba(48, 116, 191, 0.2);
}

.add-alias-input.error {
    border-color: var(--color-error);
}

.add-alias-input:disabled {
    background: rgba(0, 0, 0, 0.05);
    cursor: not-allowed;
}

.email-separator {
    font-weight: 700;
    color: #000;
    font-size: 16px;
}

.add-alias-domain {
    min-width: 280px;
    padding: 0 12px;
    border: 2px solid var(--color-border);
    border-radius: 6px;
    font-size: 14px;
    background: var(--color-main-background);
    color: #000;
    cursor: pointer;
    height: 44px;
}

.add-alias-domain:focus {
    outline: none;
    border-color: var(--color-primary-element);
}

.add-alias-domain:disabled {
    background: rgba(0, 0, 0, 0.05);
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
    background: var(--color-primary-element-light);
    transform: translateY(-1px);
}

.add-alias-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.add-alias-button [class^='icon-'] {
    filter: invert(1) brightness(100);
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
