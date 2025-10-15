<template>
    <div class="dashboard-container">
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
                <a href="https://www.host-on.de/de/kontakt" target="_blank" class="contact-button">
                    <span class="icon-external"></span>
                    {{ t('souvera_central', 'Lizenzen erweitern') }}
                </a>
            </div>
        </div>

        <!-- WARNING: Lizenzlimit bald erreicht (80%+) -->
        <div v-else-if="isLicenseWarning" class="warning-banner">
            <div class="warning-content">
                <span class="icon-alert warning-icon"></span>
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
                <a href="https://www.host-on.de/de/kontakt" target="_blank" class="contact-button secondary">
                    <span class="icon-external"></span>
                    {{ t('souvera_central', 'Kontakt') }}
                </a>
            </div>
        </div>

        <div class="dashboard-header">
            <h2>{{ t('souvera_central', 'Dashboard') }}</h2>
            <p class="subtitle">{{ t('souvera_central', 'Übersicht Ihrer Souvera Central Installation') }}</p>
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
                <button class="action-card" @click="$emit('navigate', 'users')" :disabled="isLicenseLimitReached">
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
                    <span class="info-value">0.2.0</span>
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
        }
    },

    methods: {
        t
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
    box-shadow: 0 4px 12px var(--color-box-shadow);
}

.critical-warning .warning-content {
    display: flex;
    align-items: center;
    gap: 20px;
    color: var(--color-primary-element-text);
}

.critical-warning .warning-icon {
    font-size: 48px;
    flex-shrink: 0;
    animation: pulse 2s infinite;
    color: var(--color-primary-element-text);
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
    color: var(--color-primary-element-text);
}

.critical-warning p {
    margin: 0;
    font-size: 15px;
    line-height: 1.5;
    color: var(--color-primary-element-text);
    opacity: 0.95;
}

.contact-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: var(--color-main-background);
    color: var(--color-error);
    border: 2px solid var(--color-error);
    border-radius: var(--border-radius-large);
    font-weight: 700;
    font-size: 15px;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s;
    box-shadow: 0 2px 8px var(--color-box-shadow);
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
    color: var(--color-main-text);
}

.warning-banner .warning-icon {
    font-size: 36px;
    flex-shrink: 0;
    color: var(--color-main-text);
    opacity: 0.8;
}

.warning-banner .warning-text {
    flex: 1;
}

.warning-banner h3 {
    margin: 0 0 5px;
    font-size: 18px;
    font-weight: 600;
    color: var(--color-main-text);
}

.warning-banner p {
    margin: 0;
    font-size: 14px;
    color: var(--color-main-text);
}

.contact-button.secondary {
    background: var(--color-main-background);
    color: var(--color-main-text);
    border: 2px solid var(--color-main-text);
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
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    box-shadow: 0 2px 4px var(--color-box-shadow);
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px var(--color-box-shadow);
}

.stat-card.stat-warning {
    border-color: var(--color-warning);
    background: var(--color-background-dark);
}

.stat-card.stat-critical {
    border-color: var(--color-error);
    background: var(--color-background-dark);
}

.stat-icon {
    font-size: 40px;
    opacity: 0.7;
    color: var(--color-primary-element);
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

.stat-label {
    font-size: 14px;
    color: var(--color-text-lighter);
    font-weight: 500;
}

.stat-warning-text {
    margin-top: 5px;
    font-size: 12px;
    color: var(--color-warning);
    font-weight: 600;
}

.stat-critical-text {
    margin-top: 5px;
    font-size: 12px;
    color: var(--color-error);
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
    background: var(--color-background-dark);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

.action-card:hover:not(:disabled) {
    background: var(--color-primary-element-light);
    border-color: var(--color-primary-element);
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
    background: var(--color-background-dark);
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
    color: var(--color-text-lighter);
}

.info-value {
    font-weight: 600;
    color: var(--color-main-text);
}
</style>
