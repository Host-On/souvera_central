<template>
    <div class="modal-overlay" @click.self="$emit('close')">
        <div class="modal-content">
            <div class="modal-header">
                <h3>{{ isEdit ? t('souvera_central', 'Postfach bearbeiten') : t('souvera_central', 'Neues Postfach erstellen') }}</h3>
                <button class="close-button" @click="$emit('close')">
                    <span class="icon-close"></span>
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

                <!-- Error -->
                <p v-if="error" class="error-message">{{ error }}</p>

                <!-- Actions -->
                <div class="modal-actions">
                    <button type="button" class="cancel-button" @click="$emit('close')">
                        {{ t('souvera_central', 'Abbrechen') }}
                    </button>
                    <button type="submit" class="save-button" :disabled="!canSave || saving">
                        <span v-if="saving" class="icon-loading-small"></span>
                        {{ isEdit ? t('souvera_central', 'Speichern') : t('souvera_central', 'Erstellen') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
    name: 'SharedMailboxModal',

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
        }
    },

    emits: ['close', 'save'],

    data() {
        return {
            form: {
                name: '',
                emailLocal: '',
                emailDomain: '',
                description: ''
            },
            saving: false,
            error: null
        }
    },

    computed: {
        canSave() {
            if (this.isEdit) {
                return true // Beschreibung kann immer gespeichert werden
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
        } else if (this.allowedDomains.length > 0) {
            this.form.emailDomain = this.allowedDomains[0]
        }
    },

    methods: {
        t,

        save() {
            if (!this.canSave || this.saving) {
                return
            }

            this.error = null
            this.saving = true

            const data = {
                description: this.form.description.trim()
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
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.modal-content {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 25px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #000;
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
    background: rgba(0, 0, 0, 0.08);
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
    color: #000;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid rgba(0, 0, 0, 0.15);
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
    color: #000;
    transition: all 0.2s;
    box-sizing: border-box;
}

.form-group input[type="text"] {
    min-width: 200px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(48, 116, 191, 0.15);
}

.form-group input:disabled,
.form-group select:disabled {
    background: rgba(0, 0, 0, 0.05);
    cursor: not-allowed;
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.email-input-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.email-input-group input {
    flex: 1;
}

.email-input-group select {
    min-width: 150px;
}

.email-separator {
    font-weight: 700;
    color: #000;
    font-size: 16px;
}

.hint {
    margin: 8px 0 0;
    font-size: 12px;
    color: #666;
    font-style: italic;
}

.error-message {
    margin: 0 0 15px;
    padding: 12px 15px;
    background: rgba(227, 56, 80, 0.1);
    border: 1px solid rgba(227, 56, 80, 0.3);
    border-radius: 6px;
    color: var(--color-error);
    font-size: 13px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 10px;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
    margin-top: 20px;
}

.cancel-button,
.save-button {
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.cancel-button {
    background: transparent;
    border: 2px solid rgba(0, 0, 0, 0.2);
    color: #333;
}

.cancel-button:hover {
    background: rgba(0, 0, 0, 0.05);
    border-color: rgba(0, 0, 0, 0.3);
}

.save-button {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--color-primary);
    border: none;
    color: #fff;
}

.save-button:hover:not(:disabled) {
    background: var(--color-primary-element-light);
}

.save-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.save-button [class^='icon-'] {
    filter: invert(1) brightness(100);
}
</style>
