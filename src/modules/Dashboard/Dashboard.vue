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

        <!-- Stalwart Mailserver (Admin) -->
        <div class="stalwart-section" data-testid="stalwart-section">
            <h3>{{ t('souvera_central', 'Stalwart Mailserver') }}</h3>
            <div class="stalwart-card">
                <div class="stalwart-status">
                    <ServerNetwork :size="32" class="stalwart-server-icon" />
                    <div class="stalwart-info">
                        <div class="stalwart-state">
                            <span class="status-dot" :class="stalwartDotClass"></span>
                            <span data-testid="stalwart-state-label">{{ stalwartStateLabel }}</span>
                        </div>
                        <div v-if="stalwartStatus.url" class="stalwart-url">{{ stalwartStatus.url }}</div>
                        <div v-else class="stalwart-url muted">
                            {{ t('souvera_central', 'Keine Verbindungsdaten in config.php hinterlegt') }}
                        </div>
                    </div>
                </div>
                <div class="stalwart-actions">
                    <button
                        class="sync-button"
                        :disabled="syncing || !stalwartStatus.available"
                        data-testid="stalwart-sync-button"
                        @click="syncMailboxes">
                        <Sync :size="18" :class="{ spinning: syncing }" />
                        {{ syncing ? t('souvera_central', 'Synchronisiere…') : t('souvera_central', 'Postfächer synchronisieren') }}
                    </button>
                </div>
            </div>
            <div
                v-if="syncResult"
                class="sync-result"
                :class="{ 'has-errors': syncResult.errors > 0 }"
                data-testid="stalwart-sync-result">
                <CheckCircle v-if="syncResult.errors === 0" :size="18" />
                <AlertCircle v-else :size="18" />
                <span>
                    {{
                        t(
                            'souvera_central',
                            '{created} angelegt · {skipped} übersprungen · {noMail} ohne Mail · {errors} Fehler',
                            syncResult
                        )
                    }}
                </span>
            </div>
            <div v-if="syncError" class="sync-result has-errors" data-testid="stalwart-sync-error">
                <AlertCircle :size="18" />
                <span>{{ syncError }}</span>
            </div>

            <!-- Mail-Gruppe: steuert die Sichtbarkeit der smail-App -->
            <div v-if="mailGroup.enabled" class="mailgroup-info" data-testid="stalwart-mailgroup">
                <AccountGroup :size="20" class="mailgroup-icon" />
                <div class="mailgroup-text">
                    <div class="mailgroup-headline">
                        <span class="mailgroup-name" data-testid="stalwart-mailgroup-name">{{ mailGroup.displayName || mailGroup.id }}</span>
                        <span class="mailgroup-badge" :class="{ 'badge-warn': !mailGroup.exists }">
                            {{ mailGroup.members }} {{ t('souvera_central', 'Mitglied(er)') }}
                        </span>
                    </div>
                    <p class="mailgroup-hint">
                        {{
                            t(
                                'souvera_central',
                                'Benutzer mit Postfach werden automatisch dieser Gruppe zugeordnet. Beschränken Sie die Mail-App (smail) in den App-Einstellungen auf diese Gruppe, damit Benutzer ohne Postfach sie nicht sehen.'
                            )
                        }}
                    </p>
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="system-info">
            <h3>{{ t('souvera_central', 'System-Information') }}</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">{{ t('souvera_central', 'Version') }}:</span>
                    <span class="info-value">0.22.1</span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ t('souvera_central', 'Erlaubte Domains') }}:</span>
                    <span class="info-value">{{ allowedDomains.length }} {{ t('souvera_central', 'Domain(s)') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ t('souvera_central', 'Verfügbare Lizenzen') }}:</span>
                    <span class="info-value">{{ availableLicenses }} {{ t('souvera_central', 'von') }} {{ maxLicenses }}</span>
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
import ServerNetwork from 'vue-material-design-icons/ServerNetwork.vue'
import Sync from 'vue-material-design-icons/Sync.vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'

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
        AlertOctagon,
        ServerNetwork,
        Sync,
        CheckCircle
    },

    props: {
        totalUsers: { type: Number, default: 0 },
        usedLicenses: { type: Number, default: 0 },
        groupCount: { type: Number, default: 0 },
        sharedMailboxCount: { type: Number, default: 0 },
        maxLicenses: { type: Number, default: 10 },
        maxGroups: { type: Number, default: 20 },
        maxSharedMailboxes: { type: Number, default: 10 },
        warningThreshold: { type: Number, default: 0.8 },
        allowedDomains: { type: Array, default: () => [] }
    },

    emits: ['navigate'],

    data() {
        return {
            resellerInfo: { support_url: null, url: null, name: null },
            stalwartStatus: { configured: false, available: false, url: null },
            mailGroup: { id: 'souvera-users', displayName: 'Souvera Users', exists: false, members: 0, enabled: true },
            syncing: false,
            syncResult: null,
            syncError: null
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
        availableLicenses() {
            return Math.max(0, this.maxLicenses - this.usedLicenses)
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
            if (this.resellerInfo.support_url) return this.resellerInfo.support_url
            if (this.resellerInfo.url) return this.resellerInfo.url
            return 'https://www.souvera.eu'
        },
        stalwartDotClass() {
            if (!this.stalwartStatus.configured) return 'dot-muted'
            return this.stalwartStatus.available ? 'dot-ok' : 'dot-error'
        },
        stalwartStateLabel() {
            if (!this.stalwartStatus.configured) return t('souvera_central', 'Nicht konfiguriert')
            return this.stalwartStatus.available
                ? t('souvera_central', 'Verbunden')
                : t('souvera_central', 'Nicht erreichbar')
        }
    },

    mounted() {
        this.loadResellerInfo()
        this.loadStalwartStatus()
        this.loadMailGroup()
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
                // Fallback ist in contactUrl implementiert
            }
        },

        async loadMailGroup() {
            try {
                const url = generateUrl('/apps/souvera_central/api/stalwart/mailgroup')
                const response = await axios.get(url)
                const data = response.data?.ocs?.data || response.data?.data || response.data || {}
                this.mailGroup = {
                    id: data.id || 'souvera-users',
                    displayName: data.displayName || data.id || 'Souvera Users',
                    exists: !!data.exists,
                    members: data.members || 0,
                    enabled: data.enabled !== false
                }
            } catch (error) {
                this.mailGroup = { id: 'souvera-users', displayName: 'Souvera Users', exists: false, members: 0, enabled: true }
            }
        },

        async loadStalwartStatus() {
            try {
                const url = generateUrl('/apps/souvera_central/api/stalwart/status')
                const response = await axios.get(url)
                const data = response.data?.ocs?.data || response.data?.data || response.data || {}
                this.stalwartStatus = {
                    configured: !!data.configured,
                    available: !!data.available,
                    url: data.url || null
                }
            } catch (error) {
                this.stalwartStatus = { configured: false, available: false, url: null }
            }
        },

        async syncMailboxes() {
            this.syncing = true
            this.syncResult = null
            this.syncError = null
            try {
                const url = generateUrl('/apps/souvera_central/api/stalwart/sync-mailboxes')
                const response = await axios.post(url)
                const data = response.data?.ocs?.data || response.data?.data || response.data || {}
                this.syncResult = {
                    created: data.created || 0,
                    skipped: data.skipped || 0,
                    noMail: data.noMail || 0,
                    errors: data.errors || 0
                }
                if (data.mailGroup) {
                    this.mailGroup = {
                        id: data.mailGroup.id || this.mailGroup.id,
                        displayName: data.mailGroup.displayName || this.mailGroup.displayName || data.mailGroup.id,
                        exists: !!data.mailGroup.exists,
                        members: data.mailGroup.members || 0,
                        enabled: data.mailGroup.enabled !== false
                    }
                } else {
                    this.loadMailGroup()
                }
            } catch (error) {
                this.syncError =
                    error.response?.data?.error ||
                    t('souvera_central', 'Synchronisierung fehlgeschlagen. Ist Stalwart erreichbar?')
            } finally {
                this.syncing = false
            }
        }
    }
}
</script>

<style scoped>
.dashboard-container {
    padding: 30px;
    max-width: none;
    margin: 0 auto;
}

/* KRITISCHES WARNING BANNER (100%) */
.critical-warning {
    position: relative;
    margin-bottom: 30px;
    padding: 22px 26px;
    background: rgba(var(--color-error-rgb), 0.1);
    border: 1px solid rgba(var(--color-error-rgb), 0.35);
    border-left: 4px solid var(--color-error);
    border-radius: var(--border-radius-large);
}

.critical-warning .warning-content {
    display: flex;
    align-items: center;
    gap: 18px;
}

.critical-warning .warning-icon {
    flex-shrink: 0;
    color: var(--color-error);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.55;
    }
}

.critical-warning .warning-text {
    flex: 1;
    min-width: 0;
}

.critical-warning h3 {
    margin: 0 0 6px;
    font-size: 19px;
    font-weight: 700;
    color: var(--color-error-text);
}

.critical-warning p {
    margin: 0;
    font-size: 15px;
    line-height: 1.5;
    color: var(--color-main-text);
}

.contact-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 22px;
    background: var(--color-error);
    color: #fff;
    border: none;
    border-radius: var(--border-radius-element);
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    white-space: nowrap;
    transition: filter 0.2s, transform 0.2s, box-shadow 0.2s;
}

.contact-button:hover {
    filter: brightness(1.08);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--color-error-rgb), 0.35);
}

/* WARNING BANNER (80%+) */
.warning-banner {
    position: relative;
    margin-bottom: 30px;
    padding: 20px 24px;
    background: rgba(var(--color-warning-rgb), 0.12);
    border: 1px solid rgba(var(--color-warning-rgb), 0.35);
    border-left: 4px solid var(--color-warning);
    border-radius: var(--border-radius-large);
}

.warning-banner .warning-content {
    display: flex;
    align-items: center;
    gap: 18px;
}

.warning-banner .warning-icon {
    flex-shrink: 0;
    color: var(--color-warning);
}

.warning-banner .warning-text {
    flex: 1;
    min-width: 0;
}

.warning-banner h3 {
    margin: 0 0 5px;
    font-size: 17px;
    font-weight: 700;
    color: var(--color-warning-text);
}

.warning-banner p {
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
    color: var(--color-main-text);
    font-weight: 500;
}

.contact-button.secondary {
    background: var(--color-warning);
    color: #3d2c00;
}

.contact-button.secondary:hover {
    filter: brightness(1.06);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(var(--color-warning-rgb), 0.3);
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

/* Stalwart Mailserver */
.stalwart-section {
    margin-bottom: 40px;
}

.stalwart-section h3 {
    margin: 0 0 20px;
    font-size: 20px;
    font-weight: 600;
}

.stalwart-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    padding: 20px 24px;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
}

.stalwart-status {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
}

.stalwart-server-icon {
    color: var(--color-primary-element);
    flex-shrink: 0;
}

.stalwart-info {
    min-width: 0;
}

.stalwart-state {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 15px;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.status-dot.dot-ok {
    background: var(--color-success);
    box-shadow: 0 0 0 3px rgba(var(--color-success-rgb), 0.2);
}

.status-dot.dot-error {
    background: var(--color-error);
    box-shadow: 0 0 0 3px rgba(var(--color-error-rgb), 0.2);
}

.status-dot.dot-muted {
    background: var(--color-text-maxcontrast);
}

.stalwart-url {
    margin-top: 4px;
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    word-break: break-all;
}

.stalwart-url.muted {
    font-style: italic;
}

.sync-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
    border: none;
    border-radius: var(--border-radius-element);
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.15s;
    white-space: nowrap;
}

.sync-button:hover:not(:disabled) {
    background: var(--color-primary-element-hover);
}

.sync-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.sync-button .spinning {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.sync-result {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding: 10px 14px;
    border-radius: var(--border-radius-element);
    font-size: 14px;
    font-weight: 500;
    background: rgba(var(--color-success-rgb), 0.12);
    color: var(--color-success-text);
}

.sync-result.has-errors {
    background: rgba(var(--color-error-rgb), 0.12);
    color: var(--color-error-text);
}

/* Mail-Gruppe Info */
.mailgroup-info {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-top: 12px;
    padding: 14px 16px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-element);
    background: var(--color-background-dark);
}

.mailgroup-icon {
    color: var(--color-primary-element);
    flex-shrink: 0;
    margin-top: 2px;
}

.mailgroup-text {
    min-width: 0;
}

.mailgroup-headline {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.mailgroup-name {
    font-weight: 600;
    font-size: 15px;
    color: var(--color-main-text);
}

.mailgroup-badge {
    font-size: 12px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: var(--border-radius-pill);
    background: rgba(var(--color-success-rgb), 0.15);
    color: var(--color-success-text);
}

.mailgroup-badge.badge-warn {
    background: rgba(var(--color-warning-rgb), 0.15);
    color: var(--color-warning-text);
}

.mailgroup-hint {
    margin: 6px 0 0;
    font-size: 13px;
    line-height: 1.5;
    color: var(--color-text-maxcontrast);
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

    .stalwart-card {
        flex-direction: column;
        align-items: flex-start;
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
