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
                    <span
                        class="status-badge"
                        :class="settings.email.send_to_new_users ? 'status-active' : 'status-inactive'"
                    >
                        {{ settings.email.send_to_new_users ? t('souvera_central', 'Aktiv') : t('souvera_central', 'Inaktiv') }}
                    </span>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'Automatisches Versenden von Willkommens-Emails an neue Benutzer') }}
                </p>

                <div class="settings-group">
                    <label class="checkbox-label">
                        <input
                            type="checkbox"
                            v-model="settings.email.send_to_new_users"
                            @change="saveSettings"
                            class="checkbox"
                        />
                        <span>{{ t('souvera_central', 'E-Mail an neue Benutzer senden') }}</span>
                    </label>
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
                    <label class="field-label">{{ t('souvera_central', 'Standard Speicherkontingent') }}</label>

                    <select v-model="settings.defaults.quota" @change="saveSettings" class="quota-select">
                        <option value="default">{{ t('souvera_central', 'Standard') }}</option>
                        <option value="none">{{ t('souvera_central', 'Unbegrenzt') }}</option>
                        <option value="1 GB">1 GB</option>
                        <option value="5 GB">5 GB</option>
                        <option value="10 GB">10 GB</option>
                        <option value="50 GB">50 GB</option>
                        <option value="100 GB">100 GB</option>
                        <option value="custom">{{ t('souvera_central', 'Benutzerdefiniert') }}</option>
                    </select>

                    <!-- Custom Quota Input -->
                    <div v-if="settings.defaults.quota === 'custom'" class="custom-quota-input">
                        <input
                            type="text"
                            v-model="customQuota"
                            @blur="saveCustomQuota"
                            @keyup.enter="saveCustomQuota"
                            :placeholder="t('souvera_central', 'z.B. 25 GB, 500 MB')"
                            class="input-field"
                        />
                        <p class="setting-hint">
                            {{ t('souvera_central', 'Verwenden Sie Abkürzungen wie MB, GB, TB (z.B. "25 GB")') }}
                        </p>
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
            }
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

                    console.log('Einstellungen geladen:', this.settings)
                }
            } catch (error) {
                console.error('Fehler beim Laden der Einstellungen:', error)
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

                console.log('Einstellungen gespeichert:', this.settings)

                // Emit event für andere Komponenten
                this.$emit('settings-updated', this.settings)
            } catch (error) {
                console.error('Fehler beim Speichern der Einstellungen:', error)
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

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: var(--border-radius-large);
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s;
}

.status-badge.status-active {
    background: var(--color-success);
    color: white;
}

.status-badge.status-inactive {
    background: var(--color-background-dark);
    color: var(--color-text-lighter);
    border: 1px solid var(--color-border);
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
    display: block;
    font-weight: 600;
    color: var(--color-main-text);
    margin-bottom: 8px;
    font-size: 14px;
}

/* Quota Select */
.quota-select {
    width: 100%;
    max-width: 300px;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background: var(--color-main-background);
    font-size: 14px;
    cursor: pointer;
    transition: border-color 0.2s;
}

.quota-select:hover {
    border-color: var(--color-primary);
}

.quota-select:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 2px var(--color-primary-element-light);
}

/* Custom Quota Input */
.custom-quota-input {
    margin-top: 10px;
}

.input-field {
    width: 100%;
    max-width: 300px;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    font-size: 14px;
    background: var(--color-main-background);
}

.input-field:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 2px var(--color-primary-element-light);
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
