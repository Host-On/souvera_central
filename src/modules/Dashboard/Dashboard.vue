<template>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h2>{{ t('souvera_central', 'Dashboard') }}</h2>
            <p class="subtitle">{{ t('souvera_central', 'Übersicht Ihrer Souvera Central Installation') }}</p>
        </div>

        <!-- KRITISCHES WARNING: Lizenzlimit erreicht -->
        <div v-if="isLicenseLimitReached" class="critical-warning">
            <div class="warning-content">
                <span class="icon-error warning-icon"></span>
                <div class="warning-text">
                    <h3>{{ t('souvera_central', 'Lizenzlimit erreicht!') }}</h3>
                    <p>
                        {{
                            t(
                                'souvera_central',
                                'Sie haben {count} von {total} Lizenzen genutzt. Es können keine weiteren Benutzer erstellt werden.',
                                { count: userCount, total: licenseTotal }
                            )
                        }}
                    </p>
                </div>
                <a :href="contactUrl" target="_blank" class="contact-button">
                    <span class="icon-external"></span>
                    {{ t('souvera_central', 'Lizenzen erweitern') }}
                </a>
            </div>
        </div>

        <!-- WARNING: Lizenzlimit bald erreicht (80%+) -->
        <div v-else-if="isLicenseWarning" class="warning-banner">
            <div class="warning-content">
                <span class="icon-error warning-icon"></span>
                <div class="warning-text">
                    <h3>{{ t('souvera_central', 'Lizenzlimit bald erreicht') }}</h3>
                    <p>
                        {{
                            t(
                                'souvera_central',
                                'Sie haben {count} von {total} Lizenzen genutzt ({percentage}%). Erweitern Sie rechtzeitig Ihre Lizenzen.',
                                { count: userCount, total: licenseTotal, percentage: licensePercentage }
                            )
                        }}
                    </p>
                </div>
                <a :href="contactUrl" target="_blank" class="contact-button secondary">
                    <span class="icon-external"></span>
                    {{ t('souvera_central', 'Kontakt') }}
                </a>
            </div>
        </div>

        <!-- Statistik-Karten -->
        <div class="stats-grid">
            <!-- Benutzer -->
            <div class="stat-card">
                <div class="stat-icon icon-user"></div>
                <div class="stat-content">
                    <div class="stat-value">{{ userCount }}</div>
                    <div class="stat-label">{{ t('souvera_central', 'Benutzer') }}</div>
                </div>
            </div>

            <!-- Gruppen -->
            <div class="stat-card">
                <div class="stat-icon icon-group"></div>
                <div class="stat-content">
                    <div class="stat-value">{{ groupCount }}</div>
                    <div class="stat-label">{{ t('souvera_central', 'Gruppen') }}</div>
                </div>
            </div>

            <!-- Lizenzen -->
            <div
                class="stat-card"
                :class="{ 'stat-warning': isLicenseWarning, 'stat-critical': isLicenseLimitReached }"
            >
                <div class="stat-icon icon-quota"></div>
                <div class="stat-content">
                    <div class="stat-value">{{ userCount }} / {{ licenseTotal }}</div>
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
            <div class="stat-card">
                <div class="stat-icon icon-checkmark"></div>
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
                <button class="action-card" :disabled="isLicenseLimitReached" @click="$emit('navigate', 'users')">
                    <span class="icon-add"></span>
                    <span class="action-label">{{ t('souvera_central', 'Benutzer erstellen') }}</span>
                </button>

                <button class="action-card" @click="$emit('navigate', 'groups')">
                    <span class="icon-add"></span>
                    <span class="action-label">{{ t('souvera_central', 'Gruppe erstellen') }}</span>
                </button>

                <button class="action-card" @click="$emit('navigate', 'users')">
                    <span class="icon-user"></span>
                    <span class="action-label">{{ t('souvera_central', 'Benutzer verwalten') }}</span>
                </button>

                <button class="action-card" @click="$emit('navigate', 'settings')">
                    <span class="icon-settings"></span>
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
                    <span class="info-value">0.4.0</span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ t('souvera_central', 'Erlaubte Domains') }}:</span>
                    <span class="info-value">{{ allowedDomains.length }} {{ t('souvera_central', 'Domain(s)') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ t('souvera_central', 'Lizenz-Limit') }}:</span>
                    <span class="info-value">{{ licenseTotal }} {{ t('souvera_central', 'Lizenzen') }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
    name: 'Dashboard',

    props: {
        userCount: {
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
        licenseTotal: {
            type: Number,
            default: 10
        },
        allowedDomains: {
            type: Array,
            default: () => []
        }
    },

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
            if (this.licenseTotal === 0) return 0
            return Math.round((this.userCount / this.licenseTotal) * 100)
        },

        isLicenseLimitReached() {
            return this.userCount >= this.licenseTotal
        },

        isLicenseWarning() {
            return !this.isLicenseLimitReached && this.userCount / this.licenseTotal >= 0.8
        },

        contactUrl() {
            // Fallback-Logik: support_url → url → www.souvera.eu
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
                console.error('Failed to load reseller info:', error)
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
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.critical-warning .warning-content {
    display: flex;
    align-items: center;
    gap: 20px;
    color: #fff;
}

.critical-warning .warning-icon {
    font-size: 64px;
    flex-shrink: 0;
    animation: pulse 2s infinite;
    color: #fff !important;
    filter: brightness(0) invert(1);
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
    border-radius: 6px;
    font-weight: 700;
    font-size: 15px;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
    background: #ff6600;
    border: 2px solid #ff6600;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.warning-banner .warning-content {
    display: flex;
    align-items: center;
    gap: 20px;
    color: #fff;
}

.warning-banner .warning-icon {
    font-size: 48px;
    flex-shrink: 0;
    opacity: 1;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>');
    background-size: 48px 48px;
    background-repeat: no-repeat;
    background-position: center;
    width: 48px;
    height: 48px;
    display: inline-block;
}

.warning-banner .warning-icon::before {
    content: '';
    display: none;
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
    background: #fff;
    color: #ff6600;
    border: none;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.contact-button.secondary .icon-external {
    color: #ff6600 !important;
    opacity: 1;
}

.contact-button.secondary:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
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
    color: var(--color-text-lighter);
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
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(6px);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition:
        transform 0.2s,
        box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-card.stat-warning {
    border-color: #ff6600;
    background: rgba(255, 246, 219, 0.9);
}

.stat-card.stat-critical {
    border-color: var(--color-error);
    border-width: 2px;
    background: rgba(227, 56, 80, 0.95);
    color: #fff;
}

.stat-icon {
    font-size: 40px;
    opacity: 0.7;
    color: var(--color-primary-element);
}

.stat-card.stat-critical .stat-icon {
    color: #fff;
    opacity: 1;
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
    color: var(--color-text-lighter);
    font-weight: 500;
}

.stat-card.stat-critical .stat-label {
    color: #fff;
}

.stat-warning-text {
    margin-top: 5px;
    font-size: 12px;
    color: #ff6600;
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
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(6px);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

.action-card:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.8);
    border-color: var(--color-secondary-element);
    transform: translateY(-2px);
}

.action-card:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.action-card span.icon-add,
.action-card span.icon-user,
.action-card span.icon-settings {
    font-size: 24px;
    opacity: 0.7;
}

.action-card:hover:not(:disabled) span {
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
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(6px);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 6px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
}

.info-label {
    font-weight: 500;
    color: var(--color-text-lighter);
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

    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 24px;
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

    .warning-icon {
        font-size: 36px !important;
        background-size: 36px 36px !important;
        width: 36px !important;
        height: 36px !important;
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

    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 20px;
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
