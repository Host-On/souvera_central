<template>
    <div class="settings-container">
        <div class="page-header">
            <h2>{{ t('souvera_central', 'Einstellungen') }}</h2>
            <p class="header-subtitle">{{ t('souvera_central', 'Kontoverwaltungseinstellungen') }}</p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
            <NcLoadingIcon :size="32" />
            <p>{{ t('souvera_central', 'Lade Einstellungen...') }}</p>
        </div>

        <!-- Settings Content -->
        <div v-else class="settings-content">
            <!-- 1. WILLKOMMENS-EMAIL SENDEN -->
            <div class="settings-section">
                <div class="section-header">
                    <Email :size="22" />
                    <h3>{{ t('souvera_central', 'Willkommens-Email senden') }}</h3>
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
                        <Check v-if="settings.email.send_to_new_users" :size="18" class="toggle-icon" />
                        <Close v-else :size="18" class="toggle-icon" />
                        <span class="toggle-text">
                            {{
                                settings.email.send_to_new_users
                                    ? t('souvera_central', 'Aktiv')
                                    : t('souvera_central', 'Inaktiv')
                            }}
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
                    <Database :size="22" />
                    <h3>{{ t('souvera_central', 'Standardeinstellungen') }}</h3>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'Standard-Werte für neue Benutzer') }}
                </p>

                <div class="settings-group">
                    <label class="field-label">
                        <Database :size="16" />
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
                            <component :is="option.value === 'none' ? 'InfinityIcon' : 'Database'" :size="32" class="quota-icon" />
                            <span class="quota-label">{{ option.label }}</span>
                            <Check
                                v-if="settings.defaults.quota === option.value"
                                :size="16"
                                class="selected-indicator"
                            />
                        </button>
                    </div>
                </div>
            </div>

            <!-- 3. SOUVERA SHIELD -->
            <div class="settings-section" data-testid="shield-settings-section">
                <div class="section-header">
                    <ShieldCheck :size="22" />
                    <h3>{{ t('souvera_central', 'Souvera Shield') }}</h3>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'Benachrichtigungs-Einstellungen für Souvera Shield.') }}
                </p>

                <div class="settings-group">
                    <label class="checkbox-label" data-testid="shield-desktop-label">
                        <input
                            v-model="settings.shield.desktop_notifications"
                            type="checkbox"
                            data-testid="shield-desktop-checkbox"
                            @change="saveSettings"
                        />
                        <span>{{ t('souvera_central', 'Desktop-Benachrichtigungen erhalten') }}</span>
                    </label>

                    <label class="checkbox-label" data-testid="shield-summary-label">
                        <input
                            v-model="settings.shield.daily_summary"
                            type="checkbox"
                            data-testid="shield-summary-checkbox"
                            @change="saveSettings"
                        />
                        <span>{{ t('souvera_central', 'Tägliche Zusammenfassung per E-Mail erhalten') }}</span>
                    </label>

                    <div v-if="settings.shield.daily_summary" class="spam-score-field" data-testid="shield-spam-score">
                        <label class="field-label">
                            {{ t('souvera_central', 'Mindest-Spam-Score für Benachrichtigung') }}
                        </label>
                        <div class="slider-row">
                            <input
                                type="range"
                                min="0"
                                max="10"
                                step="0.5"
                                :value="settings.shield.min_spam_score"
                                class="spam-slider"
                                data-testid="shield-spam-slider"
                                @input="settings.shield.min_spam_score = parseFloat($event.target.value)"
                                @change="saveSettings"
                            />
                            <span class="slider-value" data-testid="shield-spam-value">{{ formatScore(settings.shield.min_spam_score) }}</span>
                        </div>
                        <div class="slider-scale">
                            <span>0</span>
                            <span>2,5</span>
                            <span>5</span>
                            <span>7,5</span>
                            <span>10</span>
                        </div>
                        <p class="setting-hint">
                            {{ t('souvera_central', 'Nur Nachrichten ab diesem Spam-Score lösen eine Benachrichtigung aus (0 = alle, 10 = nur sehr wahrscheinlicher Spam).') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Save Indicator -->
            <div v-if="saving" class="save-indicator">
                <NcLoadingIcon :size="16" />
                <span>{{ t('souvera_central', 'Speichere...') }}</span>
            </div>

            <div v-if="saveSuccess" class="save-indicator success">
                <Check :size="16" />
                <span>{{ t('souvera_central', 'Gespeichert') }}</span>
            </div>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import Email from 'vue-material-design-icons/Email.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Database from 'vue-material-design-icons/Database.vue'
import InfinityIcon from 'vue-material-design-icons/Infinity.vue'
import ShieldCheck from 'vue-material-design-icons/ShieldCheck.vue'

export default {
    name: 'Settings',

    components: {
        NcLoadingIcon,
        Email,
        Check,
        Close,
        Database,
        InfinityIcon,
        ShieldCheck
    },

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
                },
                shield: {
                    desktop_notifications: false,
                    daily_summary: false,
                    min_spam_score: 2.5
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
                        defaults: data.defaults || this.settings.defaults,
                        shield: data.shield || this.settings.shield
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
        },

        formatScore(value) {
            const n = Number(value)
            return (Number.isFinite(n) ? n : 0).toFixed(1).replace('.', ',')
        }
    }
}
</script>

<style scoped>
.settings-container {
    padding: 30px;
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.page-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: none;
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
    border-radius: 6px;
    padding: 25px;
    transition: box-shadow 0.2s;
}

.settings-section:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.section-header .material-design-icon {
    color: var(--color-primary-element);
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
    border-radius: 6px;
    font-size: 16px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    min-width: 180px;
    justify-content: center;
}

.toggle-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.toggle-button:active {
    transform: translateY(0);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.toggle-button.toggle-active {
    background: var(--color-success);
    color: #fff;
    border: 2px solid var(--color-success);
}

.toggle-button.toggle-active:hover {
    background: var(--color-success-hover);
}

.toggle-button.toggle-active .toggle-icon {
    color: #fff;
}

.toggle-button.toggle-inactive {
    background: var(--color-error);
    color: #fff;
    border: 2px solid var(--color-error);
}

.toggle-button.toggle-inactive:hover {
    background: var(--color-error-hover);
}

.toggle-button.toggle-inactive .toggle-icon {
    color: #fff;
}

.toggle-icon {
    opacity: 1;
}

.toggle-text {
    font-size: 16px;
    font-weight: 700;
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

.field-label .material-design-icon {
    opacity: 0.8;
}

/* Souvera Shield - Spam-Score Slider */
.spam-score-field {
    margin-top: 8px;
    padding: 16px;
    background: var(--color-background-hover);
    border-radius: var(--border-radius-large, 12px);
    animation: slideDown 0.25s ease-out;
}

.slider-row {
    display: flex;
    align-items: center;
    gap: 16px;
}

.spam-slider {
    flex: 1;
    accent-color: var(--color-primary-element);
    cursor: pointer;
    height: 6px;
}

.slider-value {
    min-width: 48px;
    text-align: center;
    font-size: 16px;
    font-weight: 700;
    color: var(--color-primary-element);
    background: var(--color-main-background);
    border: 2px solid var(--color-primary-element);
    border-radius: var(--border-radius-pill, 16px);
    padding: 4px 10px;
}

.slider-scale {
    display: flex;
    justify-content: space-between;
    margin-top: 6px;
    padding-right: 64px;
    font-size: 12px;
    color: var(--color-text-maxcontrast);
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
    background: var(--color-main-background);
    border: 2px solid var(--color-border);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: 100px;
}

.quota-option:hover {
    background: var(--color-main-background);
    border-color: var(--color-secondary-element);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.quota-option.quota-selected {
    background: var(--color-primary-element-light);
    border-color: var(--color-primary-element);
    border-width: 3px;
    box-shadow: 0 4px 16px rgba(var(--color-primary-element-rgb), 0.3);
}

.quota-icon {
    opacity: 0.8;
    color: var(--color-primary-element);
}

.quota-option.quota-selected .quota-icon {
    opacity: 1;
    color: var(--color-primary-element);
}

.quota-label {
    font-size: 15px;
    font-weight: 700;
    color: var(--color-main-text);
    text-align: center;
}

.selected-indicator {
    position: absolute;
    top: 8px;
    right: 8px;
    font-size: 20px;
    color: #fff;
    background: var(--color-success);
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(30, 214, 122, 0.4);
    font-weight: bold;
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
    padding: var(--sc-control-padding-y) var(--sc-control-padding-x);
    background: var(--color-main-background);
    border: var(--sc-control-border-width) solid var(--color-primary-element);
    border-radius: var(--sc-control-radius);
    min-height: var(--sc-control-height);
    box-sizing: border-box;
    transition: box-shadow 0.2s ease;
}

.custom-quota-field:focus-within {
    box-shadow: var(--sc-focus-ring);
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
    border-radius: 6px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
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

/* Responsive Design */
@media (max-width: 1024px) {
    .settings-container {
        padding: 20px;
        max-width: 100%;
    }

    .quota-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .settings-container {
        padding: 15px;
    }

    .page-header h2 {
        font-size: 24px;
    }

    .settings-section {
        padding: 20px;
    }

    .section-header h3 {
        font-size: 18px;
    }

    .quota-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .quota-option {
        padding: 15px 12px;
        min-height: 80px;
    }

    .quota-icon {
        font-size: 28px;
    }

    .toggle-button {
        padding: 12px 24px;
        font-size: 15px;
    }
}

@media (max-width: 480px) {
    .settings-container {
        padding: 10px;
    }

    .page-header {
        padding-bottom: 15px;
    }

    .page-header h2 {
        font-size: 20px;
    }

    .header-subtitle {
        font-size: 12px;
    }

    .settings-section {
        padding: 15px;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .section-header h3 {
        font-size: 16px;
    }

    .quota-grid {
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .quota-option {
        padding: 12px 10px;
        min-height: 70px;
    }

    .toggle-button {
        padding: 10px 20px;
        font-size: 14px;
        min-width: 140px;
    }

    .save-indicator {
        bottom: 15px;
        right: 15px;
        padding: 10px 16px;
        font-size: 13px;
    }
}
</style>
