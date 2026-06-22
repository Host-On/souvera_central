<template>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h2>{{ t('souvera_central', 'Dashboard') }}</h2>
            <p class="subtitle">{{ t('souvera_central', 'Übersicht Ihrer Souvera Central Installation') }}</p>
        </div>

        <!-- KRITISCHES WARNING: Lizenzlimit erreicht -->
        <div v-if="isLicenseLimitReached" class="critical-warning" data-testid="license-critical-banner">
            <div class="warning-content">
                <AlertOctagon class="warning-icon" :size="52" />
                <div class="warning-text">
                    <h3>{{ t('souvera_central', 'Lizenzlimit erreicht!') }}</h3>
                    <p>
                        {{
                            t(
                                'souvera_central',
                                'Sie haben {count} von {total} Lizenzen genutzt. Es können keine weiteren Benutzer erstellt werden.',
                                { count: usedLicenses, total: maxLicenses }
                            )
                        }}
                    </p>
                </div>
                <a :href="contactUrl" target="_blank" class="contact-button">
                    <OpenInNew :size="18" />
                    {{ t('souvera_central', 'Lizenzen erweitern') }}
                </a>
            </div>
        </div>

        <!-- WARNING: Lizenzlimit bald erreicht (80%+) -->
        <div v-else-if="isLicenseWarning" class="warning-banner" data-testid="license-warning-banner">
            <div class="warning-content">
                <AlertCircle class="warning-icon" :size="40" />
                <div class="warning-text">
                    <h3>{{ t('souvera_central', 'Lizenzlimit bald erreicht') }}</h3>
                    <p>
                        {{
                            t(
                                'souvera_central',
                                'Sie haben {count} von {total} Lizenzen genutzt ({percentage}%). Erweitern Sie rechtzeitig Ihre Lizenzen.',
                                { count: usedLicenses, total: maxLicenses, percentage: licensePercentage }
                            )
                        }}
                    </p>
                </div>
                <a :href="contactUrl" target="_blank" class="contact-button secondary">
                    <OpenInNew :size="18" />
                    {{ t('souvera_central', 'Kontakt') }}
                </a>
            </div>
        </div>

        <!-- Statistik-Karten -->
        <div class="stats-grid">
            <!-- Benutzer -->
            <div class="stat-card" data-testid="stat-users">
                <div class="stat-icon"><AccountMultiple :size="40" /></div>
                <div class="stat-content">
                    <div class="stat-value">{{ totalUsers }}</div>
                    <div class="stat-label">{{ t('souvera_central', 'Benutzer') }}</div>
                </div>
            </div>

            <!-- Gruppen -->
            <div
                class="stat-card"
                :class="{ 'stat-warning': isGroupWarning, 'stat-critical': isGroupLimitReached }"
                data-testid="stat-groups">
                <div class="stat-icon"><AccountGroup :size="40" /></div>
                <div class="stat-content">
                    <div class="stat-value">{{ groupCount }} / {{ maxGroups }}</div>
                    <div class="stat-label">{{ t('souvera_central', 'Gruppen') }}</div>
                    <div v-if="isGroupLimitReached" class="stat-critical-text">
                        {{ t('souvera_central', 'Limit erreicht!') }}
                    </div>
                    <div v-else-if="isGroupWarning" class="stat-warning-text">
                        {{ t('souvera_central', 'Bald erreicht ({percentage}%)', { percentage: groupPercentage }) }}
                    </div>
                </div>
            </div>

            <!-- Geteilte Postfächer -->
            <div
                class="stat-card"
                :class="{ 'stat-warning': isMailboxWarning, 'stat-critical': isMailboxLimitReached }"
                data-testid="stat-mailboxes">
                <div class="stat-icon"><EmailMultiple :size="40" /></div>
                <div class="stat-content">
                    <div class="stat-value">{{ sharedMailboxCount }} / {{ maxSharedMailboxes }}</div>
                    <div class="stat-label">{{ t('souvera_central', 'Geteilte Postfächer') }}</div>
                    <div v-if="isMailboxLimitReached" class="stat-critical-text">
                        {{ t('souvera_central', 'Limit erreicht!') }}
                    </div>
                    <div v-else-if="isMailboxWarning" class="stat-warning-text">
                        {{ t('souvera_central', 'Bald erreicht ({percentage}%)', { percentage: mailboxPercentage }) }}
                    </div>
                </div>
            </div>

            <!-- Lizenzen -->
            <div
                class="stat-card"
                :class="{ 'stat-warning': isLicenseWarning, 'stat-critical': isLicenseLimitReached }"
                data-testid="stat-licenses">
                <div class="stat-icon"><KeyVariant :size="40" /></div>
                <div class="stat-content">
                    <div class="stat-value">{{ usedLicenses }} / {{ maxLicenses }}</div>
                    <div class="stat-label">{{ t('souvera_central', 'Lizenzen genutzt') }}</div>
                    <div v-if="isLicenseLimitReached" class="stat-critical-text">
                        {{ t('souvera_central', 'Limit erreicht!') }}
                    </div>
                    <div v-else-if="isLicenseWarning" class="stat-warning-text">
                        {{ t('souvera_central', 'Bald erreicht ({percentage}%)', { percentage: licensePercentage }) }}
                    </div>
                </div>
            </div>

            <!-- Aktive Benutzer -->
            <div class="stat-card" data-testid="stat-active-users">
                <div class="stat-icon"><AccountCheck :size="40" /></div>
                <div class="stat-content">
                    <div class="stat-value">{{ activeUserCount }}</div>
                    <div class="stat-label">{{ t('souvera_central', 'Aktive Benutzer') }}</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3>{{ t('souvera_central', 'Schnellaktionen') }}</h3>
            <div class="actions-grid">
                <button
                    class="action-card"
                    :disabled="isLicenseLimitReached"
                    data-testid="action-create-user"
                    @click="$emit('navigate', 'users')">
                    <Plus :size="22" />
                    <span class="action-label">{{ t('souvera_central', 'Benutzer erstellen') }}</span>
                </button>

                <button class="action-card" data-testid="action-create-group" @click="$emit('navigate', 'groups')">
                    <Plus :size="22" />
                    <span class="action-label">{{ t('souvera_central', 'Gruppe erstellen') }}</span>
                </button>

                <button class="action-card" data-testid="action-manage-users" @click="$emit('navigate', 'users')">
                    <AccountMultiple :size="22" />
                    <span class="action-label">{{ t('souvera_central', 'Benutzer verwalten') }}</span>
                </button>

                <button class="action-card" data-testid="action-settings" @click="$emit('navigate', 'settings')">
                    <Cog :size="22" />
                    <span class="action-label">{{ t('souvera_central', 'Einstellungen') }}</span>
                </button>
            </div>
        </div>

        <!-- System Info -->
        <div class="system-info">
            <h3>{{ t('souvera_central', 'System-Information') }}</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">{{ t('souvera_central', 'Version') }}:</span>
                    <span class="info-value">0.7.0</span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ t('souvera_central', 'Erlaubte Domains') }}:</span>
                    <span class="info-value">{{ allowedDomains.length }} {{ t('souvera_central', 'Domain(s)') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ t('souvera_central', 'Verfügbare Lizenzen') }}:</span>
                    <span class="info-value">{{ maxLicenses }} {{ t('souvera_central', 'Lizenzen') }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import EmailMultiple from 'vue-material-design-icons/EmailMultiple.vue'
import KeyVariant from 'vue-material-design-icons/KeyVariant.vue'
import AccountCheck from 'vue-material-design-icons/AccountCheck.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import AlertOctagon from 'vue-material-design-icons/AlertOctagon.vue'

export default {
    name: 'Dashboard',

    components: {
        AccountMultiple,
        AccountGroup,
        EmailMultiple,
        KeyVariant,
        AccountCheck,
        Plus,
        Cog,
        OpenInNew,
        AlertCircle,
        AlertOctagon
    },

    props: {
        totalUsers: {
            type: Number,
            default: 0
        },
        usedLicenses: {
            type: Number,
            default: 0
        },
        activeUserCount: {
            type: Number,
            default: 0
        },
        groupCount: {
            type: Number,
            default: 0
        },
        sharedMailboxCount: {
            type: Number,
            default: 0
        },
        maxLicenses: {
            type: Number,
            default: 10
        },
        maxGroups: {
            type: Number,
            default: 20
        },
        maxSharedMailboxes: {
            type: Number,
            default: 10
        },
        warningThreshold: {
            type: Number,
            default: 0.8
        },
        allowedDomains: {
            type: Array,
            default: () => []
        }
    },

    emits: ['navigate'],

    data() {
        return {
            resellerInfo: {
                support_url: null,
                url: null,
                name: null
            }
        }
    },

    computed: {
        licensePercentage() {
            if (this.maxLicenses === 0) return 0
            return Math.round((this.usedLicenses / this.maxLicenses) * 100)
        },

        isLicenseLimitReached() {
            return this.usedLicenses >= this.maxLicenses
        },

        isLicenseWarning() {
            return !this.isLicenseLimitReached && this.usedLicenses / this.maxLicenses >= this.warningThreshold
        },

        groupPercentage() {
            if (this.maxGroups === 0) return 0
            return Math.round((this.groupCount / this.maxGroups) * 100)
        },

        isGroupLimitReached() {
            return this.groupCount >= this.maxGroups
        },

        isGroupWarning() {
            return !this.isGroupLimitReached && this.groupCount / this.maxGroups >= this.warningThreshold
        },

        mailboxPercentage() {
            if (this.maxSharedMailboxes === 0) return 0
            return Math.round((this.sharedMailboxCount / this.maxSharedMailboxes) * 100)
        },

        isMailboxLimitReached() {
            return this.sharedMailboxCount >= this.maxSharedMailboxes
        },

        isMailboxWarning() {
            return !this.isMailboxLimitReached && this.sharedMailboxCount / this.maxSharedMailboxes >= this.warningThreshold
        },

        contactUrl() {
            if (this.resellerInfo.support_url) {
                return this.resellerInfo.support_url
            }
            if (this.resellerInfo.url) {
                return this.resellerInfo.url
            }
            return 'https://www.souvera.eu'
        }
    },

    mounted() {
        this.loadResellerInfo()
    },

    methods: {
        t,

        async loadResellerInfo() {
            try {
                const url = generateUrl('/apps/souvera_central/api/reseller')
                const response = await axios.get(url)

                if (response.data?.ocs?.data) {
                    this.resellerInfo = response.data.ocs.data
                } else if (response.data) {
                    this.resellerInfo = response.data
                }
            } catch (error) {
                // Fallback ist bereits in contactUrl implementiert
            }
        }
    }
}
</script>

<style scoped>
.dashboard-container {
    padding: 30px;
    max-width: 1400px;
    margin: 0 auto;
}

/* KRITISCHES WARNING BANNER */
.critical-warning {
    margin-bottom: 30px;
    padding: 25px 30px;
    background: var(--color-error);
    border: 2px solid var(--color-error);
    border-radius: var(--border-radius-large);
    box-shadow: 0 2px 8px var(--color-box-shadow);
}

.critical-warning .warning-content {
    display: flex;
    align-items: center;
    gap: 20px;
    color: #fff;
}

.critical-warning .warning-icon {
    flex-shrink: 0;
    animation: pulse 2s infinite;
    color: #fff;
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

.critical-warning .warning-text {
    flex: 1;
}

.critical-warning h3 {
    margin: 0 0 8px;
    font-size: 20px;
    font-weight: 700;
    color: #fff;
}

.critical-warning p {
    margin: 0;
    font-size: 15px;
    line-height: 1.5;
    color: #fff;
}

.contact-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: var(--color-main-background);
    color: var(--color-error);
    border: 2px solid var(--color-error);
    border-radius: var(--border-radius-element);
    font-weight: 700;
    font-size: 15px;
    text-decoration: none;
    white-space: nowrap;
    transition: background-color 0.2s, transform 0.2s, box-shadow 0.2s;
}

.contact-button:hover {
    background: var(--color-background-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px var(--color-box-shadow);
}

/* WARNING BANNER (80%+) */
.warning-banner {
    margin-bottom: 30px;
    padding: 20px 25px;
    background: var(--color-warning);
    border: 2px solid var(--color-warning);
    border-radius: var(--border-radius-large);
    box-shadow: 0 2px 8px var(--color-box-shadow);
}

.warning-banner .warning-content {
    display: flex;
    align-items: center;
    gap: 20px;
    color: #fff;
}

.warning-banner .warning-icon {
    flex-shrink: 0;
    color: #fff;
}

.warning-banner .warning-text {
    flex: 1;
}

.warning-banner h3 {
    margin: 0 0 5px;
    font-size: 18px;
    font-weight: 700;
    color: #fff;
}

.warning-banner p {
    margin: 0;
    font-size: 14px;
    color: #fff;
    font-weight: 500;
}

.contact-button.secondary {
    background: var(--color-main-background);
    color: var(--color-warning-text);
    border: none;
    box-shadow: 0 2px 6px var(--color-box-shadow);
}

.contact-button.secondary:hover {
    background: var(--color-background-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px var(--color-box-shadow);
}

/* Header */
.dashboard-header {
    margin-bottom: 30px;
}

.dashboard-header h2 {
    margin: 0 0 5px;
    font-size: 28px;
    font-weight: 600;
}

.subtitle {
    margin: 0;
    font-size: 14px;
    color: var(--color-text-maxcontrast);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 25px;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    transition:
        border-color 0.2s,
        box-shadow 0.2s,
        transform 0.2s;
}

.stat-card:hover {
    border-color: var(--color-border-maxcontrast);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px var(--color-box-shadow);
}

.stat-card.stat-warning {
    border-color: var(--color-warning);
    background: rgba(var(--color-warning-rgb), 0.08);
}

.stat-card.stat-critical {
    border-color: var(--color-error);
    border-width: 2px;
    background: var(--color-error);
    color: #fff;
}

.stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-primary-element);
}

.stat-card.stat-critical .stat-icon {
    color: #fff;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--color-main-text);
    line-height: 1;
    margin-bottom: 5px;
}

.stat-card.stat-critical .stat-value {
    color: #fff;
}

.stat-label {
    font-size: 14px;
    color: var(--color-text-maxcontrast);
    font-weight: 500;
}

.stat-card.stat-critical .stat-label {
    color: #fff;
}

.stat-warning-text {
    margin-top: 5px;
    font-size: 12px;
    color: var(--color-warning-text);
    font-weight: 600;
}

.stat-critical-text {
    margin-top: 5px;
    font-size: 12px;
    color: #fff;
    font-weight: 700;
    text-transform: uppercase;
}

/* Quick Actions */
.quick-actions {
    margin-bottom: 40px;
}

.quick-actions h3 {
    margin: 0 0 20px;
    font-size: 20px;
    font-weight: 600;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.action-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    cursor: pointer;
    transition: background-color 0.2s, border-color 0.2s, transform 0.2s;
    text-align: left;
    color: var(--color-main-text);
}

.action-card:hover:not(:disabled) {
    background: var(--color-background-hover);
    border-color: var(--color-primary-element);
    transform: translateY(-2px);
}

.action-card:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.action-card .material-design-icon {
    color: var(--color-primary-element);
    opacity: 0.85;
}

.action-card:hover:not(:disabled) .material-design-icon {
    opacity: 1;
}

.action-label {
    font-size: 15px;
    font-weight: 500;
}

/* System Info */
.system-info h3 {
    margin: 0 0 20px;
    font-size: 20px;
    font-weight: 600;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
    padding: 20px;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
}

.info-label {
    font-weight: 500;
    color: var(--color-text-maxcontrast);
}

.info-value {
    font-weight: 600;
    color: var(--color-main-text);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .dashboard-container {
        padding: 20px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 15px;
    }

    .dashboard-header h2 {
        font-size: 24px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .stat-card {
        padding: 20px;
    }

    .stat-value {
        font-size: 28px;
    }

    .quick-actions {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .info-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .warning-banner,
    .critical-warning {
        padding: 15px 20px;
    }

    .warning-banner .warning-content,
    .critical-warning .warning-content {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 10px;
    }

    .dashboard-header h2 {
        font-size: 20px;
    }

    .subtitle {
        font-size: 12px;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-value {
        font-size: 24px;
    }

    .stat-label {
        font-size: 12px;
    }

    .action-card {
        padding: 15px;
    }

    .warning-banner h3,
    .critical-warning h3 {
        font-size: 16px;
    }

    .warning-banner p,
    .critical-warning p {
        font-size: 13px;
    }

    .contact-button,
    .contact-button.secondary {
        padding: 10px 20px;
        font-size: 14px;
    }
}
</style>
