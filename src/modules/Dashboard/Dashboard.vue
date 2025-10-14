<template>
  <div class="dashboard-container">
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
      <div class="stat-card" :class="{ 'stat-warning': isLicenseWarning }">
        <div class="stat-icon icon-quota"></div>
        <div class="stat-content">
          <div class="stat-value">{{ userCount }} / {{ licenseTotal }}</div>
          <div class="stat-label">{{ t('souvera_central', 'Lizenzen genutzt') }}</div>
          <div v-if="isLicenseWarning" class="stat-warning-text">
            {{ t('souvera_central', 'Lizenzlimit bald erreicht') }}
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
        <button class="action-card" @click="$emit('navigate', 'users')">
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
    isLicenseWarning() {
      return (this.userCount / this.licenseTotal) >= 0.8
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
  background: rgba(255, 165, 0, 0.05);
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

.action-card:hover {
  background: var(--color-primary-element-light);
  border-color: var(--color-primary-element);
  transform: translateY(-2px);
}

.action-card span.icon-add,
.action-card span.icon-user,
.action-card span.icon-settings {
  font-size: 24px;
  opacity: 0.7;
}

.action-card:hover span {
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
