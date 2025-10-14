<template>
  <div class="settings-container">
    <div class="page-header">
      <h2>{{ t('souvera_central', 'Einstellungen') }}</h2>
    </div>

    <div class="settings-content">
      <div class="settings-section">
        <h3>{{ t('souvera_central', 'Lizenz-Konfiguration') }}</h3>
        <div class="setting-item">
          <label>{{ t('souvera_central', 'Maximale Lizenzen') }}:</label>
          <input type="number" :value="licenseTotal" readonly disabled />
          <p class="setting-hint">{{ t('souvera_central', 'Wird in der config.php konfiguriert') }}</p>
        </div>
      </div>

      <div class="settings-section">
        <h3>{{ t('souvera_central', 'E-Mail-Domains') }}</h3>
        <div class="setting-item">
          <label>{{ t('souvera_central', 'Erlaubte Domains') }}:</label>
          <div class="domains-list">
            <span v-for="(domain, index) in allowedDomains" :key="index" class="domain-badge">
              {{ domain }}
            </span>
            <span v-if="allowedDomains.length === 0" class="text-muted">
              {{ t('souvera_central', 'Keine Domains konfiguriert') }}
            </span>
          </div>
          <p class="setting-hint">{{ t('souvera_central', 'Wird in der config.php konfiguriert') }}</p>
        </div>
      </div>

      <div class="settings-section">
        <h3>{{ t('souvera_central', 'System-Information') }}</h3>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">{{ t('souvera_central', 'Version') }}:</span>
            <span class="info-value">0.2.0</span>
          </div>
          <div class="info-item">
            <span class="info-label">{{ t('souvera_central', 'App-ID') }}:</span>
            <span class="info-value">souvera_central</span>
          </div>
          <div class="info-item">
            <span class="info-label">{{ t('souvera_central', 'Status') }}:</span>
            <span class="info-value status-active">{{ t('souvera_central', 'Aktiv') }}</span>
          </div>
        </div>
      </div>

      <div class="coming-soon-notice">
        <span class="icon-info"></span>
        <div>
          <strong>{{ t('souvera_central', 'Weitere Einstellungen in Entwicklung') }}</strong>
          <p>{{ t('souvera_central', 'Zusätzliche Konfigurationsoptionen werden in zukünftigen Versionen verfügbar sein.') }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
  name: 'Settings',

  props: {
    licenseTotal: {
      type: Number,
      default: 10
    },
    allowedDomains: {
      type: Array,
      default: () => []
    }
  },

  methods: {
    t
  }
}
</script>

<style scoped>
.settings-container {
  padding: 30px;
  max-width: 1000px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--color-border);
}

.page-header h2 {
  margin: 0;
  font-size: 28px;
  font-weight: 600;
}

.settings-content {
  display: flex;
  flex-direction: column;
  gap: 30px;
}

.settings-section {
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  padding: 25px;
}

.settings-section h3 {
  margin: 0 0 20px;
  font-size: 18px;
  font-weight: 600;
  color: var(--color-main-text);
}

.setting-item {
  margin-bottom: 20px;
}

.setting-item:last-child {
  margin-bottom: 0;
}

.setting-item label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: var(--color-text-lighter);
}

.setting-item input[type="number"] {
  width: 100%;
  max-width: 200px;
  padding: 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  background: var(--color-background-dark);
  font-size: 14px;
}

.setting-hint {
  margin: 8px 0 0;
  font-size: 13px;
  color: var(--color-text-lighter);
  font-style: italic;
}

.domains-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 12px;
  background: var(--color-background-dark);
  border-radius: var(--border-radius);
  min-height: 50px;
  align-items: center;
}

.domain-badge {
  display: inline-block;
  padding: 6px 12px;
  background: var(--color-primary-element-light);
  color: var(--color-primary-element-text);
  border-radius: var(--border-radius);
  font-size: 14px;
  font-weight: 500;
}

.text-muted {
  color: var(--color-text-lighter);
  font-style: italic;
}

.info-grid {
  display: grid;
  gap: 15px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  background: var(--color-background-dark);
  border-radius: var(--border-radius);
}

.info-label {
  font-weight: 500;
  color: var(--color-text-lighter);
}

.info-value {
  font-weight: 600;
  color: var(--color-main-text);
}

.status-active {
  color: var(--color-success);
}

.coming-soon-notice {
  display: flex;
  align-items: flex-start;
  gap: 15px;
  padding: 20px;
  background: var(--color-background-dark);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  border-left: 4px solid var(--color-primary-element);
}

.coming-soon-notice .icon-info {
  font-size: 24px;
  color: var(--color-primary-element);
  flex-shrink: 0;
  margin-top: 2px;
}

.coming-soon-notice strong {
  display: block;
  margin-bottom: 5px;
  color: var(--color-main-text);
}

.coming-soon-notice p {
  margin: 0;
  color: var(--color-text-lighter);
  font-size: 14px;
}
</style>
