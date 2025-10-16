<template>
    <div class="settings-container">
        <div class="page-header">
            <h2>{{ t('souvera_central', 'Einstellungen') }}</h2>
            <p class="header-subtitle">{{ t('souvera_central', 'Kontoverwaltungseinstellungen') }}</p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
            <div class="icon-loading"></div>
            <p>{{ t('souvera_central', 'Lade Einstellungen...') }}</p>
        </div>

        <!-- Settings Content -->
        <div v-else class="settings-content">
            <!-- 1. E-MAIL SENDEN -->
            <div class="settings-section">
                <div class="section-header">
                    <span class="icon-mail"></span>
                    <h3>{{ t('souvera_central', 'E-Mail senden') }}</h3>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'Automatisches Versenden von Willkommens-Emails an neue Benutzer') }}
                </p>

                <div class="settings-group">
                    <button
                        class="toggle-button"
                        :class="settings.email.send_to_new_users ? 'toggle-active' : 'toggle-inactive'"
                        @click="toggleEmailSending"
                    >
                        <span class="toggle-icon" :class="settings.email.send_to_new_users ? 'icon-checkmark' : 'icon-close'"></span>
                        <span class="toggle-text">
                            {{ settings.email.send_to_new_users ? t('souvera_central', 'Aktiv') : t('souvera_central', 'Inaktiv') }}
                        </span>
                    </button>
                    <p class="setting-hint">
                        {{
                            t(
                                'souvera_central',
                                'Wenn aktiviert, erhalten neue Benutzer automatisch eine Willkommens-Email mit ihren Login-Daten.'
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- 2. STANDARDEINSTELLUNGEN -->
            <div class="settings-section">
                <div class="section-header">
                    <span class="icon-quota"></span>
                    <h3>{{ t('souvera_central', 'Standardeinstellungen') }}</h3>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'Standard-Werte für neue Benutzer') }}
                </p>

                <div class="settings-group">
                    <label class="field-label">
                        <span class="icon-quota"></span>
                        {{ t('souvera_central', 'Standard Speicherkontingent') }}
                    </label>

                    <!-- Quota Grid -->
                    <div class="quota-grid">
                        <button
                            v-for="option in quotaOptions"
                            :key="option.value"
                            class="quota-option"
                            :class="{ 'quota-selected': settings.defaults.quota === option.value }"
                            @click="selectQuota(option.value)"
                        >
                            <span class="quota-icon" :class="option.icon"></span>
                            <span class="quota-label">{{ option.label }}</span>
                            <span v-if="settings.defaults.quota === option.value" class="selected-indicator icon-checkmark"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Save Indicator -->
            <div v-if="saving" class="save-indicator">
                <div class="icon-loading-small"></div>
                <span>{{ t('souvera_central', 'Speichere...') }}</span>
            </div>

            <div v-if="saveSuccess" class="save-indicator success">
                <span class="icon-checkmark"></span>
                <span>{{ t('souvera_central', 'Gespeichert') }}</span>
            </div>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
    name: 'Settings',

    data() {
        return {
            loading: true,
            saving: false,
            saveSuccess: false,
            customQuota: '',
            settings: {
                email: {
                    send_to_new_users: false
                },
                defaults: {
                    quota: 'default'
                }
            },
            quotaOptions: [
                { value: 'default', label: this.t('souvera_central', 'Standard'), icon: 'icon-quota' },
                { value: 'none', label: this.t('souvera_central', 'Unbegrenzt'), icon: 'icon-category-disabled' },
                { value: '1 GB', label: '1 GB', icon: 'icon-quota' },
                { value: '5 GB', label: '5 GB', icon: 'icon-quota' },
                { value: '10 GB', label: '10 GB', icon: 'icon-quota' },
                { value: '50 GB', label: '50 GB', icon: 'icon-quota' },
                { value: '100 GB', label: '100 GB', icon: 'icon-quota' }
            ]
        }
    },

    mounted() {
        this.loadSettings()
    },

    methods: {
        t,

        async loadSettings() {
            try {
                this.loading = true
                const url = generateUrl('/apps/souvera_central/api/settings')
                const response = await axios.get(url)

                // OCS Response Format
                const data = response.data.ocs?.data || response.data.data || response.data

                if (data) {
                    this.settings = {
                        email: data.email || this.settings.email,
                        defaults: data.defaults || this.settings.defaults
                    }
                }
            } catch (error) {
                // Error handling
            } finally {
                this.loading = false
            }
        },

        async saveSettings() {
            try {
                this.saving = true
                this.saveSuccess = false

                const url = generateUrl('/apps/souvera_central/api/settings')
                await axios.put(url, this.settings)

                // Success-Feedback anzeigen
                this.saveSuccess = true
                setTimeout(() => {
                    this.saveSuccess = false
                }, 2000)

                // Emit event für andere Komponenten
                this.$emit('settings-updated', this.settings)
            } catch (error) {
                alert(this.t('souvera_central', 'Fehler beim Speichern der Einstellungen'))
            } finally {
                this.saving = false
            }
        },

        saveCustomQuota() {
            const value = this.customQuota.trim()
            if (value) {
                this.settings.defaults.quota = value
                this.saveSettings()
            }
        },

        toggleEmailSending() {
            this.settings.email.send_to_new_users = !this.settings.email.send_to_new_users
            this.saveSettings()
        },

        selectQuota(value) {
            this.settings.defaults.quota = value
            this.saveSettings()
        }
    }
}
</script>

<style scoped>
.settings-container {
    padding: 30px;
    max-width: 900px;
    margin: 0 auto;
}

/* Header */
.page-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--color-border);
}

.page-header h2 {
    margin: 0 0 5px;
    font-size: 28px;
    font-weight: 600;
}

.header-subtitle {
    margin: 0;
    color: var(--color-text-lighter);
    font-size: 14px;
}

/* Loading State */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px;
    gap: 15px;
}

.loading-state p {
    color: var(--color-text-lighter);
}

/* Settings Content */
.settings-content {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

/* Settings Section */
.settings-section {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    padding: 25px;
    transition: box-shadow 0.2s;
}

.settings-section:hover {
    box-shadow: 0 2px 8px var(--color-box-shadow);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.section-header span[class^='icon-'] {
    font-size: 24px;
    color: var(--color-primary);
}

.section-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--color-main-text);
    flex: 1;
}

/* Toggle Button */
.toggle-button {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 14px 28px;
    border-radius: var(--border-radius-large);
    font-size: 16px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px var(--color-box-shadow);
    min-width: 180px;
    justify-content: center;
}

.toggle-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px var(--color-box-shadow);
}

.toggle-button:active {
    transform: translateY(0);
    box-shadow: 0 2px 6px var(--color-box-shadow);
}

.toggle-button.toggle-active {
    background: var(--color-success);
    color: white;
}

.toggle-button.toggle-active:hover {
    background: #46a049;
}

.toggle-button.toggle-inactive {
    background: var(--color-error);
    color: white;
}

.toggle-button.toggle-inactive:hover {
    background: #c9302c;
}

.toggle-icon {
    font-size: 20px;
    opacity: 0.95;
}

.toggle-text {
    font-size: 16px;
}

.section-description {
    margin: 0 0 20px;
    color: var(--color-text-lighter);
    font-size: 14px;
}

/* Settings Group */
.settings-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Checkbox Label */
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: background 0.2s;
}

.checkbox-label:hover {
    background: var(--color-background-hover);
}

.checkbox-label input[type='checkbox'] {
    cursor: pointer;
    width: 18px;
    height: 18px;
}

.checkbox-label span {
    font-size: 14px;
    color: var(--color-main-text);
}

/* Field Label */
.field-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: var(--color-main-text);
    margin-bottom: 16px;
    font-size: 15px;
}

.field-label .icon-quota {
    font-size: 18px;
    opacity: 0.8;
}

/* Quota Grid */
.quota-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.quota-option {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 20px 16px;
    background: var(--color-background-dark);
    border: 2px solid var(--color-border);
    border-radius: var(--border-radius-large);
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: 100px;
}

.quota-option:hover {
    background: var(--color-background-hover);
    border-color: var(--color-primary-element);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px var(--color-box-shadow);
}

.quota-option.quota-selected {
    background: var(--color-primary-element-light);
    border-color: var(--color-primary-element);
    box-shadow: 0 0 0 3px var(--color-primary-element-light);
}

.quota-icon {
    font-size: 32px;
    opacity: 0.7;
    color: var(--color-primary-element);
}

.quota-option.quota-selected .quota-icon {
    opacity: 1;
    color: var(--color-primary-element);
}

.quota-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-main-text);
    text-align: center;
}

.selected-indicator {
    position: absolute;
    top: 8px;
    right: 8px;
    font-size: 18px;
    color: var(--color-success);
    background: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Custom Quota Input */
.custom-quota-input {
    margin-top: 8px;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.custom-quota-field {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--color-background-dark);
    border: 2px solid var(--color-primary-element);
    border-radius: var(--border-radius-large);
    transition: all 0.2s;
}

.custom-quota-field:focus-within {
    box-shadow: 0 0 0 3px var(--color-primary-element-light);
}

.custom-quota-field .icon-edit {
    font-size: 20px;
    color: var(--color-primary-element);
    flex-shrink: 0;
}

.input-field {
    flex: 1;
    padding: 8px 0;
    border: none;
    background: transparent;
    font-size: 15px;
    color: var(--color-main-text);
    font-weight: 500;
}

.input-field:focus {
    outline: none;
}

/* Setting Hint */
.setting-hint {
    margin: 8px 0 0;
    font-size: 13px;
    color: var(--color-text-lighter);
    font-style: italic;
}

/* Save Indicator */
.save-indicator {
    position: fixed;
    bottom: 30px;
    right: 30px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    box-shadow: 0 4px 16px var(--color-box-shadow);
    z-index: 1000;
    animation: slideIn 0.3s ease-out;
}

.save-indicator.success {
    border-color: var(--color-success);
    background: var(--color-success);
    color: white;
}

.save-indicator .icon-loading-small {
    width: 20px;
    height: 20px;
}

.save-indicator .icon-checkmark {
    font-size: 20px;
}

@keyframes slideIn {
    from {
        transform: translateX(100px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>
