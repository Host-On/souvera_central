<template>
    <div class="settings-container">
        <div class="page-header">
            <h2>{{ t('souvera_central', 'Settings') }}</h2>
            <p class="header-subtitle">{{ t('souvera_central', 'Account management settings') }}</p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
            <NcLoadingIcon :size="32" />
            <p>{{ t('souvera_central', 'Loading settings...') }}</p>
        </div>

        <!-- Settings Content -->
        <div v-else class="settings-content">
            <!-- 1. WILLKOMMENS-EMAIL SENDEN -->
            <div class="settings-section">
                <div class="section-header">
                    <Email :size="22" />
                    <h3>{{ t('souvera_central', 'Send welcome email') }}</h3>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'Automatically send welcome emails to new users') }}
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
                                    ? t('souvera_central', 'Active')
                                    : t('souvera_central', 'Inactive')
                            }}
                        </span>
                    </button>
                    <p class="setting-hint">
                        {{
                            t(
                                'souvera_central',
                                'When enabled, new users automatically receive a welcome email with their login details.'
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- 2. STANDARDEINSTELLUNGEN -->
            <div class="settings-section">
                <div class="section-header">
                    <Database :size="22" />
                    <h3>{{ t('souvera_central', 'Default settings') }}</h3>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'Default values for new users') }}
                </p>

                <div class="settings-group">
                    <label class="field-label">
                        <Database :size="16" />
                        {{ t('souvera_central', 'Default storage quota') }}
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

            <!-- 3. MAIL-SPEICHER-POOL (read-only, nur per OCC) -->
            <div class="settings-section" data-testid="mail-storage-section">
                <div class="section-header">
                    <Database :size="22" />
                    <h3>{{ t('souvera_central', 'Mail storage pool') }}</h3>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'Total email storage distributed across Souvera users and shared mailboxes. Set by the hoster via the command line (not to be confused with Nextcloud file storage).') }}
                </p>

                <div v-if="mailStorage.pool_enabled" class="settings-group">
                    <div class="pool-stats" data-testid="mail-storage-stats">
                        <div class="pool-stat">
                            <span class="pool-stat-label">{{ t('souvera_central', 'Total') }}</span>
                            <span class="pool-stat-value" data-testid="mail-storage-total">{{ formatBytes(mailStorage.max) }}</span>
                        </div>
                        <div class="pool-stat">
                            <span class="pool-stat-label">{{ t('souvera_central', 'Distributed') }}</span>
                            <span class="pool-stat-value" data-testid="mail-storage-allocated">{{ formatBytes(mailStorage.allocated) }}</span>
                        </div>
                        <div class="pool-stat">
                            <span class="pool-stat-label">{{ t('souvera_central', 'Available') }}</span>
                            <span class="pool-stat-value available" data-testid="mail-storage-available">{{ formatBytes(mailStorage.available) }}</span>
                        </div>
                    </div>
                    <div class="pool-bar-track">
                        <div
                            class="pool-bar-fill"
                            :class="poolLevelClass"
                            :style="{ width: poolPercent + '%' }"
                        ></div>
                    </div>
                    <p class="setting-hint">
                        {{ t('souvera_central', 'Changeable only via the command line: occ souvera_central:mailstorage:set 100G') }}
                    </p>
                    <p
                        v-if="mailStorage.unlimited_count > 0"
                        class="pool-unlimited-warning"
                        data-testid="mail-storage-unlimited-warning"
                    >
                        <AlertCircleOutline :size="16" />
                        {{ t('souvera_central', '{count} mailbox(es) without a limit ("Unlimited") – these cannot be accounted for in the pool and may exceed it.', { count: mailStorage.unlimited_count }) }}
                    </p>

                    <button
                        type="button"
                        class="pool-detail-toggle"
                        data-testid="mail-storage-detail-toggle"
                        @click="toggleDistribution"
                    >
                        {{ distributionOpen ? t('souvera_central', 'Hide distribution') : t('souvera_central', 'Show distribution') }}
                    </button>
                    <div v-if="distributionOpen" class="pool-detail" data-testid="mail-storage-distribution">
                        <p v-if="distributionLoading" class="pool-detail-loading">{{ t('souvera_central', 'Loading...') }}</p>
                        <table v-else class="pool-detail-table">
                            <thead>
                                <tr>
                                    <th>{{ t('souvera_central', 'Mailbox') }}</th>
                                    <th>{{ t('souvera_central', 'Type') }}</th>
                                    <th class="num">{{ t('souvera_central', 'Used') }}</th>
                                    <th class="num">{{ t('souvera_central', 'Limit') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in distributionItems"
                                    :key="item.email"
                                    data-testid="mail-storage-dist-row"
                                >
                                    <td class="mono">{{ item.email }}</td>
                                    <td>
                                        <span class="dist-badge" :class="item.type">
                                            {{ item.type === 'shared' ? t('souvera_central', 'Shared') : t('souvera_central', 'User') }}
                                        </span>
                                    </td>
                                    <td class="num">{{ formatBytes(item.used) }}</td>
                                    <td class="num">{{ item.quota > 0 ? formatBytes(item.quota) : t('souvera_central', 'Unlimited') }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="pool-detail-hint">
                            {{ t('souvera_central', 'All Souvera user and shared mailboxes counted towards the pool. Internal system mailboxes (e.g. postmaster@) are excluded.') }}
                        </p>
                    </div>
                </div>
                <div v-else class="settings-group">
                    <p class="pool-empty" data-testid="mail-storage-empty">
                        {{ t('souvera_central', 'No mail storage pool set (unlimited). The hoster can enable it via "occ souvera_central:mailstorage:set 100G".') }}
                    </p>
                </div>
            </div>

            <!-- 4. SOUVERA SHIELD -->
            <div class="settings-section" data-testid="shield-settings-section">
                <div class="section-header">
                    <ShieldCheck :size="22" />
                    <h3>{{ t('souvera_central', 'Souvera Shield') }}</h3>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'Notification settings for Souvera Shield.') }}
                </p>

                <div class="settings-group">
                    <label class="checkbox-label" data-testid="shield-desktop-label">
                        <input
                            v-model="settings.shield.desktop_notifications"
                            type="checkbox"
                            data-testid="shield-desktop-checkbox"
                            @change="saveSettings"
                        />
                        <span>{{ t('souvera_central', 'Receive desktop notifications') }}</span>
                    </label>

                    <label class="checkbox-label" data-testid="shield-summary-label">
                        <input
                            v-model="settings.shield.daily_summary"
                            type="checkbox"
                            data-testid="shield-summary-checkbox"
                            @change="saveSettings"
                        />
                        <span>{{ t('souvera_central', 'Receive a daily summary by email') }}</span>
                    </label>

                    <div v-if="settings.shield.daily_summary" class="spam-score-field" data-testid="shield-spam-score">
                        <label class="field-label">
                            {{ t('souvera_central', 'Minimum spam score for notification') }}
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
                            {{ t('souvera_central', 'Only messages at or above this spam score trigger a notification (0 = all, 10 = only very likely spam).') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Mail signature -->
            <div class="settings-section" data-testid="signature-settings-section">
                <div class="section-header">
                    <Email :size="22" />
                    <h3>{{ t('souvera_central', 'Mail signature') }}</h3>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'One global signature, managed centrally. Used by Souvera Mail and optionally enforced server-side for all SMTP clients.') }}
                </p>

                <div class="settings-group">
                    <label class="checkbox-label" data-testid="signature-enabled-label">
                        <input
                            v-model="settings.signature.enabled"
                            type="checkbox"
                            data-testid="signature-enabled-checkbox"
                            @change="saveSettings"
                        />
                        <span>{{ t('souvera_central', 'Enable global mail signature') }}</span>
                    </label>

                    <div v-if="settings.signature.enabled" class="signature-editor" data-testid="signature-editor">
                        <label class="field-label">{{ t('souvera_central', 'Signature (HTML allowed)') }}</label>
                        <textarea
                            v-model="settings.signature.template"
                            class="signature-textarea"
                            rows="7"
                            data-testid="signature-template-input"
                            :placeholder="signaturePlaceholder"
                            @blur="saveSettings"
                        ></textarea>

                        <div class="signature-variables" data-testid="signature-variables">
                            <span class="var-hint">{{ t('souvera_central', 'Available variables (click to insert):') }}</span>
                            <button
                                v-for="v in settings.signature.variables"
                                :key="v"
                                type="button"
                                class="var-chip"
                                :data-testid="'signature-var-' + v"
                                @click="insertVariable(v)"
                            >{{ v }}</button>
                        </div>

                        <div class="signature-preview-wrap">
                            <label class="field-label">{{ t('souvera_central', 'Preview') }}</label>
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <div class="signature-preview" data-testid="signature-preview" v-html="signaturePreview"></div>
                        </div>

                        <label class="checkbox-label" data-testid="signature-serverside-label">
                            <input
                                v-model="settings.signature.server_side"
                                type="checkbox"
                                data-testid="signature-serverside-checkbox"
                                @change="saveSettings"
                            />
                            <span>{{ t('souvera_central', 'Enforce server-side for all SMTP clients (Stalwart)') }}</span>
                        </label>
                        <p class="setting-hint">
                            {{ settings.signature.server_side
                                ? t('souvera_central', 'Server-side is ON: Souvera Mail will NOT add the signature (Stalwart appends it). Deploy the Sieve script via: occ souvera_central:mailsignature:sieve (requires Stalwart ≥ 0.16.6).')
                                : t('souvera_central', 'Server-side is OFF: only Souvera Mail renders the personalized signature; other SMTP clients (Thunderbird, Outlook, mobile) do not get it.') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Save Indicator -->
            <div v-if="saving" class="save-indicator">
                <NcLoadingIcon :size="16" />
                <span>{{ t('souvera_central', 'Saving...') }}</span>
            </div>

            <div v-if="saveSuccess" class="save-indicator success">
                <Check :size="16" />
                <span>{{ t('souvera_central', 'Saved') }}</span>
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
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'

export default {
    name: 'Settings',

    components: {
        NcLoadingIcon,
        Email,
        Check,
        Close,
        Database,
        InfinityIcon,
        ShieldCheck,
        AlertCircleOutline
    },

    data() {
        return {
            loading: true,
            saving: false,
            saveSuccess: false,
            customQuota: '',
            mailStorage: { max: 0, allocated: 0, available: 0, pool_enabled: false, step_bytes: 1073741824, default_quota: 0, unlimited_count: 0 },
            distributionOpen: false,
            distributionLoading: false,
            distributionItems: [],
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
                },
                signature: {
                    enabled: false,
                    template: '',
                    server_side: false,
                    variables: ['%name%', '%email%', '%first_name%', '%last_name%', '%domain%']
                }
            },
            quotaOptions: [
                { value: 'default', label: this.t('souvera_central', 'Default'), icon: 'icon-quota' },
                { value: 'none', label: this.t('souvera_central', 'Unlimited'), icon: 'icon-category-disabled' },
                { value: '1 GB', label: '1 GB', icon: 'icon-quota' },
                { value: '5 GB', label: '5 GB', icon: 'icon-quota' },
                { value: '10 GB', label: '10 GB', icon: 'icon-quota' },
                { value: '50 GB', label: '50 GB', icon: 'icon-quota' },
                { value: '100 GB', label: '100 GB', icon: 'icon-quota' }
            ]
        }
    },

    computed: {
        poolPercent() {
            if (!this.mailStorage.max || this.mailStorage.max <= 0) {
                return 0
            }
            return Math.min(100, Math.round((this.mailStorage.allocated / this.mailStorage.max) * 100))
        },

        poolLevelClass() {
            if (this.poolPercent >= 90) return 'is-full'
            if (this.poolPercent >= 75) return 'is-warn'
            return ''
        },

        signaturePlaceholder() {
            return '<p>%first_name% %last_name%<br>%email%</p>\n<p><a href="https://%domain%">%domain%</a></p>'
        },

        signaturePreview() {
            const tpl = this.settings.signature.template || ''
            const sample = {
                '%name%': 'Michael Grassegger',
                '%email%': 'michael@grassegger.eu',
                '%first_name%': 'Michael',
                '%last_name%': 'Grassegger',
                '%domain%': 'grassegger.eu'
            }
            return tpl.replace(/%name%|%email%|%first_name%|%last_name%|%domain%/g, (m) => sample[m] || m)
        }
    },

    mounted() {
        this.loadSettings()
        this.loadMailStorage()
    },

    methods: {
        t,

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
                    step_bytes: data.step_bytes || 1073741824,
                    default_quota: data.default_quota || 0,
                    unlimited_count: data.unlimited_count || 0
                }
            } catch (error) {
                this.mailStorage = { max: 0, allocated: 0, available: 0, pool_enabled: false, step_bytes: 1073741824, default_quota: 0, unlimited_count: 0 }
            }
        },

        async toggleDistribution() {
            this.distributionOpen = !this.distributionOpen
            if (this.distributionOpen && this.distributionItems.length === 0) {
                await this.loadDistribution()
            }
        },

        async loadDistribution() {
            this.distributionLoading = true
            try {
                const url = generateUrl('/apps/souvera_central/api/mail-storage/distribution')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data || {}
                this.distributionItems = Array.isArray(data.items) ? data.items : []
            } catch (error) {
                this.distributionItems = []
            } finally {
                this.distributionLoading = false
            }
        },

        formatBytes(bytes) {
            if (!bytes || bytes <= 0) {
                return this.t('souvera_central', 'Unlimited')
            }
            const TB = 1024 ** 4
            const GB = 1024 ** 3
            const MB = 1024 ** 2
            if (bytes >= TB) {
                return `${(bytes / TB).toFixed(bytes % TB === 0 ? 0 : 1)} TB`
            }
            if (bytes >= GB) {
                return `${(bytes / GB).toFixed(bytes % GB === 0 ? 0 : 1)} GB`
            }
            return `${Math.round(bytes / MB)} MB`
        },

        insertVariable(v) {
            this.settings.signature.template = (this.settings.signature.template || '') + v
            this.saveSettings()
        },

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
                        shield: data.shield || this.settings.shield,
                        signature: {
                            enabled: data.signature ? !!data.signature.enabled : this.settings.signature.enabled,
                            template: data.signature ? (data.signature.template || '') : this.settings.signature.template,
                            server_side: data.signature ? !!data.signature.server_side : this.settings.signature.server_side,
                            variables: (data.signature && Array.isArray(data.signature.variables) && data.signature.variables.length)
                                ? data.signature.variables
                                : this.settings.signature.variables
                        }
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
                alert(this.t('souvera_central', 'Error saving the settings'))
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
    max-width: none;
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
    background: #2e9e4f;
    color: #fff;
    border: 2px solid #2e9e4f;
}

.toggle-button.toggle-active:hover {
    background: #278a44;
}

.toggle-button.toggle-active .toggle-icon {
    color: #fff;
}

.toggle-button.toggle-inactive {
    background: #d32f2f;
    color: #fff;
    border: 2px solid #d32f2f;
}

.toggle-button.toggle-inactive:hover {
    background: #b71c1c;
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

/* Mail-Speicher-Pool */
.pool-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 14px;
}

.pool-stat {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px 16px;
    background: var(--color-background-dark);
    border-radius: 8px;
}

.pool-stat-label {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    font-weight: 600;
}

.pool-stat-value {
    font-size: 20px;
    font-weight: 700;
    color: var(--color-main-text);
}

.pool-stat-value.available {
    color: #2e9e4f;
}

.pool-bar-track {
    height: 10px;
    border-radius: 100px;
    background: var(--color-background-dark);
    overflow: hidden;
}

.pool-bar-fill {
    height: 100%;
    border-radius: inherit;
    background: var(--color-primary-element);
    transition: width 0.3s ease;
}

.pool-bar-fill.is-warn {
    background: #d18700;
}

.pool-bar-fill.is-full {
    background: #d32f2f;
}

.pool-empty {
    margin: 0;
    padding: 16px;
    background: var(--color-background-hover);
    border-radius: 8px;
    font-size: 14px;
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
    background: #2e9e4f;
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

.pool-unlimited-warning {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin: 12px 0 0;
    padding: 10px 12px;
    border-radius: 8px;
    background: var(--color-warning, #e9a13b);
    background: color-mix(in srgb, var(--color-warning, #e9a13b) 14%, transparent);
    border: 1px solid color-mix(in srgb, var(--color-warning, #e9a13b) 45%, transparent);
    color: var(--color-main-text);
    font-size: 13px;
    font-weight: 500;
    line-height: 1.4;
}

.pool-unlimited-warning :deep(svg) {
    flex: 0 0 auto;
    margin-top: 1px;
    color: var(--color-warning, #e9a13b);
}

.pool-detail-toggle {
    margin-top: 12px;
    background: transparent;
    border: none;
    padding: 4px 0;
    color: var(--color-primary-element);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.pool-detail-toggle:hover {
    opacity: 0.8;
}

.pool-detail {
    margin-top: 8px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    overflow: hidden;
}

.pool-detail-loading {
    margin: 0;
    padding: 12px;
    font-size: 13px;
    color: var(--color-text-maxcontrast);
}

.pool-detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.pool-detail-table th,
.pool-detail-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}

.pool-detail-table th {
    background: var(--color-background-dark);
    font-weight: 600;
    color: var(--color-text-maxcontrast);
}

.pool-detail-table tr:last-child td {
    border-bottom: none;
}

.pool-detail-table .num {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.pool-detail-table .mono {
    font-family: var(--font-face-monospace, monospace);
    word-break: break-all;
}

.dist-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    background: var(--color-primary-element-light, rgba(0, 130, 201, 0.12));
    color: var(--color-primary-element);
}

.dist-badge.shared {
    background: color-mix(in srgb, var(--color-success, #46ba61) 16%, transparent);
    color: var(--color-success, #2d7d3f);
}

.pool-detail-hint {
    margin: 0;
    padding: 10px 12px;
    font-size: 12px;
    font-style: italic;
    color: var(--color-text-maxcontrast);
    background: var(--color-background-hover);
}

/* Mail-Signatur-Editor */
.signature-editor {
    margin-top: 14px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.signature-textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-family: var(--font-face-monospace, monospace);
    font-size: 13px;
    line-height: 1.5;
    resize: vertical;
}

.signature-textarea:focus {
    outline: none;
    border-color: var(--color-primary-element);
    box-shadow: var(--sc-focus-ring, 0 0 0 2px rgba(0, 130, 201, 0.25));
}

.signature-variables {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}

.var-hint {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    margin-right: 4px;
}

.var-chip {
    padding: 3px 10px;
    border: 1px solid var(--color-border);
    border-radius: 999px;
    background: var(--color-background-dark);
    color: var(--color-primary-element);
    font-family: var(--font-face-monospace, monospace);
    font-size: 12px;
    cursor: pointer;
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.var-chip:hover {
    background: var(--color-primary-element-light, rgba(0, 130, 201, 0.12));
    border-color: var(--color-primary-element);
}

.signature-preview-wrap {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.signature-preview {
    min-height: 40px;
    padding: 12px;
    border: 1px dashed var(--color-border);
    border-radius: 8px;
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 14px;
    overflow-wrap: anywhere;
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
    border-color: #2e9e4f;
    background: #2e9e4f;
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
