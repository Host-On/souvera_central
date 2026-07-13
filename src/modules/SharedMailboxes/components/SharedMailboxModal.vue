<template>
    <div class="modal-overlay" @click.self="$emit('close')">
        <div class="modal-content">
            <div class="modal-header">
                <h3>{{ isEdit ? t('souvera_central', 'Postfach bearbeiten') : t('souvera_central', 'Neues Postfach erstellen') }}</h3>
                <button class="close-button" @click="$emit('close')">
                    <Close :size="20" />
                </button>
            </div>

            <form class="modal-body" @submit.prevent="save">
                <!-- Name -->
                <div class="form-group">
                    <label for="mailbox-name">{{ t('souvera_central', 'Name') }} *</label>
                    <input
                        id="mailbox-name"
                        v-model="form.name"
                        type="text"
                        :placeholder="t('souvera_central', 'z.B. Support Team')"
                        :disabled="isEdit"
                        required
                    />
                    <p v-if="isEdit" class="hint">{{ t('souvera_central', 'Der Name kann nach Erstellung nicht mehr geändert werden.') }}</p>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label>{{ t('souvera_central', 'Email-Adresse') }} *</label>
                    <div class="email-input-group">
                        <input
                            v-model="form.emailLocal"
                            type="text"
                            :placeholder="t('souvera_central', 'support')"
                            :disabled="isEdit"
                            required
                        />
                        <span class="email-separator">@</span>
                        <select
                            v-model="form.emailDomain"
                            :disabled="isEdit || allowedDomains.length <= 1"
                        >
                            <option v-for="domain in allowedDomains" :key="domain" :value="domain">
                                {{ domain }}
                            </option>
                        </select>
                    </div>
                    <p v-if="isEdit" class="hint">{{ t('souvera_central', 'Die Email-Adresse kann nach Erstellung nicht mehr geändert werden.') }}</p>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="mailbox-description">{{ t('souvera_central', 'Beschreibung') }}</label>
                    <textarea
                        id="mailbox-description"
                        v-model="form.description"
                        :placeholder="t('souvera_central', 'Optionale Beschreibung für das Postfach...')"
                        rows="3"
                    ></textarea>
                </div>

                <!-- Speicherlimit (Mail-Speicher-Pool) -->
                <div class="form-group" data-testid="shared-quota-group">
                    <label>{{ t('souvera_central', 'Speicherlimit') }}</label>
                    <div class="quota-row">
                        <input
                            v-model.number="form.quotaGB"
                            type="number"
                            min="1"
                            step="1"
                            :max="poolEnabled ? maxGb : null"
                            :disabled="form.unlimited"
                            data-testid="shared-quota-input"
                        />
                        <span class="quota-unit">{{ t('souvera_central', 'GB') }}</span>
                        <label v-if="!poolEnabled" class="unlimited-label" data-testid="shared-quota-unlimited-label">
                            <input v-model="form.unlimited" type="checkbox" data-testid="shared-quota-unlimited" />
                            <span>{{ t('souvera_central', 'Unbegrenzt') }}</span>
                        </label>
                    </div>
                    <p v-if="poolEnabled" class="hint" data-testid="shared-quota-pool-hint">
                        {{ t('souvera_central', 'Im Mail-Speicher-Pool verfügbar: {available}', { available: formatBytes(poolAvailableForThis) }) }}
                    </p>
                </div>

                <!-- Error -->
                <p v-if="error" class="error-message">{{ error }}</p>

                <!-- Actions -->
                <div class="modal-actions">
                    <button type="button" class="cancel-button" @click="$emit('close')">
                        {{ t('souvera_central', 'Abbrechen') }}
                    </button>
                    <button type="submit" class="save-button" :disabled="!canSave || saving">
                        <NcLoadingIcon v-if="saving" :size="18" />
                        {{ isEdit ? t('souvera_central', 'Speichern') : t('souvera_central', 'Erstellen') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import Close from 'vue-material-design-icons/Close.vue'

export default {
    name: 'SharedMailboxModal',

    components: {
        NcLoadingIcon,
        Close
    },

    props: {
        mailbox: {
            type: Object,
            default: null
        },
        allowedDomains: {
            type: Array,
            default: () => []
        },
        isEdit: {
            type: Boolean,
            default: false
        },
        mailStorage: {
            type: Object,
            default: () => ({ max: 0, allocated: 0, available: 0, pool_enabled: false, step_bytes: 1073741824 })
        }
    },

    emits: ['close', 'save'],

    data() {
        return {
            form: {
                name: '',
                emailLocal: '',
                emailDomain: '',
                description: '',
                quotaGB: 1,
                unlimited: false
            },
            currentQuotaBytes: 0,
            saving: false,
            error: null
        }
    },

    computed: {
        GiB() {
            return 1024 * 1024 * 1024
        },

        poolEnabled() {
            return !!this.mailStorage.pool_enabled
        },

        poolAvailableForThis() {
            return (this.mailStorage.available || 0) + Math.max(0, this.currentQuotaBytes)
        },

        maxGb() {
            return Math.max(1, Math.floor(this.poolAvailableForThis / this.GiB))
        },

        quotaBytes() {
            if (this.form.unlimited) {
                return 0
            }
            const gb = Number(this.form.quotaGB)
            return Number.isFinite(gb) && gb > 0 ? Math.round(gb) * this.GiB : 0
        },

        canSave() {
            if (this.isEdit) {
                return true // Beschreibung/Limit kann immer gespeichert werden
            }
            return this.form.name.trim() && this.form.emailLocal.trim() && this.form.emailDomain
        },

        fullEmail() {
            if (!this.form.emailLocal || !this.form.emailDomain) {
                return ''
            }
            return `${this.form.emailLocal.trim().toLowerCase()}@${this.form.emailDomain}`
        }
    },

    mounted() {
        if (this.isEdit && this.mailbox) {
            this.form.name = this.mailbox.name || ''
            this.form.description = this.mailbox.description || ''

            const email = this.mailbox.emails?.[0] || ''
            if (email.includes('@')) {
                const [local, domain] = email.split('@')
                this.form.emailLocal = local
                this.form.emailDomain = domain
            }
            this.currentQuotaBytes = this.mailbox.quota || 0
        } else if (this.allowedDomains.length > 0) {
            this.form.emailDomain = this.allowedDomains[0]
        }
        // Stepper/Unbegrenzt aus aktuellem Limit ableiten
        if (this.currentQuotaBytes > 0) {
            this.form.unlimited = false
            this.form.quotaGB = Math.max(1, Math.round(this.currentQuotaBytes / this.GiB))
        } else {
            this.form.unlimited = !this.poolEnabled
            this.form.quotaGB = 1
        }
    },

    methods: {
        t,

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

        save() {
            if (!this.canSave || this.saving) {
                return
            }

            // Client-seitige Pool-Vorabprüfung (Server validiert erneut)
            if (this.poolEnabled) {
                if (this.quotaBytes <= 0) {
                    this.error = this.t('souvera_central', 'Bei aktivem Mail-Speicher-Pool ist „Unbegrenzt" nicht erlaubt.')
                    return
                }
                if (this.quotaBytes > this.poolAvailableForThis) {
                    this.error = this.t('souvera_central', 'Nicht genügend Mail-Speicher im Pool verfügbar.')
                    return
                }
            }

            this.error = null
            this.saving = true

            const data = {
                description: this.form.description.trim(),
                quota: this.quotaBytes
            }

            if (!this.isEdit) {
                data.name = this.form.name.trim()
                data.email = this.fullEmail
            }

            this.$emit('save', data)
            this.saving = false
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
    overflow: auto;
    box-shadow: 0 8px 32px var(--color-box-shadow);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 25px;
    border-bottom: 1px solid var(--color-border);
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--color-main-text);
}

.close-button {
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
    padding: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--color-main-text);
}

.form-group input,
.form-group select {
    width: 100%;
    height: var(--sc-control-height);
    padding: var(--sc-control-padding-y) var(--sc-control-padding-x);
    border: var(--sc-control-border-width) solid var(--color-border);
    border-radius: var(--sc-control-radius);
    font-size: 14px;
    line-height: 1.4;
    background: var(--color-main-background);
    color: var(--color-main-text);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    box-sizing: border-box;
}

.form-group textarea {
    width: 100%;
    padding: var(--sc-control-padding-y) var(--sc-control-padding-x);
    border: var(--sc-control-border-width) solid var(--color-border);
    border-radius: var(--sc-control-radius);
    font-size: 14px;
    line-height: 1.5;
    background: var(--color-main-background);
    color: var(--color-main-text);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    box-sizing: border-box;
    resize: vertical;
    min-height: 88px;
}

.form-group select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    padding-right: 40px;
    background-image: var(--sc-caret);
    background-repeat: no-repeat;
    background-position: right 16px center;
    cursor: pointer;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--color-primary-element);
    box-shadow: var(--sc-focus-ring);
}

.form-group input:disabled,
.form-group select:disabled {
    background: var(--color-background-dark);
    cursor: not-allowed;
}

.email-input-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.email-input-group input {
    flex: 1 1 auto;
    min-width: 0;
}

.email-input-group select {
    flex: 0 0 auto;
    width: auto;
    max-width: 45%;
}

.email-separator {
    font-weight: 700;
    color: var(--color-main-text);
    font-size: 16px;
}

.hint {
    margin: 8px 0 0;
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    font-style: italic;
}

.quota-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.quota-row input[type='number'] {
    width: 120px;
    flex: 0 0 auto;
}

.quota-unit {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-main-text);
}

.unlimited-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--color-main-text);
    cursor: pointer;
    margin: 0;
}

.unlimited-label input[type='checkbox'] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

.error-message {
    margin: 0 0 15px;
    padding: 12px 15px;
    background: rgba(var(--color-error-rgb), 0.1);
    border: 1px solid rgba(var(--color-error-rgb), 0.3);
    border-radius: 6px;
    color: var(--color-error);
    font-size: 13px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 10px;
    border-top: 1px solid var(--color-border);
    margin-top: 20px;
}

.cancel-button,
.save-button {
    height: var(--sc-control-height);
    padding: 0 24px;
    border-radius: var(--sc-control-radius);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease, border-color 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.cancel-button {
    background: transparent;
    border: 2px solid var(--color-border);
    color: var(--color-main-text);
}

.cancel-button:hover {
    background: var(--color-background-dark);
    border-color: var(--color-border-maxcontrast);
}

.save-button {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--color-primary-element);
    border: none;
    color: #fff;
}

.save-button:hover:not(:disabled) {
    background: var(--color-primary-element-hover);
}

.save-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
