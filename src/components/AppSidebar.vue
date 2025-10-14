<template>
  <div class="app-sidebar">
    <div class="sidebar-header">
      <h1>Souvera Central</h1>
      <p class="subtitle">{{ t('souvera_central', 'Management-Zentrale') }}</p>
    </div>

    <nav class="sidebar-nav">
      <button
        v-for="item in navigationItems"
        :key="item.id"
        :class="['nav-item', { active: currentRoute === item.id }]"
        @click="navigateTo(item.id)"
      >
        <span :class="['icon', item.icon]"></span>
        <span class="nav-label">{{ item.label }}</span>
      </button>
    </nav>

    <div class="sidebar-footer">
      <div class="license-info">
        <span class="icon-quota"></span>
        <div class="license-details">
          <span class="license-count">{{ userCount }} / {{ licenseTotal }}</span>
          <span class="license-label">{{ t('souvera_central', 'Lizenzen') }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
  name: 'AppSidebar',

  props: {
    currentRoute: {
      type: String,
      required: true
    },
    userCount: {
      type: Number,
      default: 0
    },
    licenseTotal: {
      type: Number,
      default: 10
    }
  },

  data() {
    return {
      navigationItems: [
        {
          id: 'dashboard',
          label: this.t('souvera_central', 'Dashboard'),
          icon: 'icon-home'
        },
        {
          id: 'users',
          label: this.t('souvera_central', 'Benutzerverwaltung'),
          icon: 'icon-user'
        },
        {
          id: 'groups',
          label: this.t('souvera_central', 'Gruppenverwaltung'),
          icon: 'icon-group'
        },
        {
          id: 'settings',
          label: this.t('souvera_central', 'Einstellungen'),
          icon: 'icon-settings'
        }
      ]
    }
  },

  methods: {
    t,

    navigateTo(route) {
      this.$emit('navigate', route)
    }
  }
}
</script>

<style scoped>
.app-sidebar {
  width: 280px;
  height: 100%;
  background: var(--color-main-background);
  border-right: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
}

/* Header */
.sidebar-header {
  padding: 30px 20px 20px;
  border-bottom: 1px solid var(--color-border);
}

.sidebar-header h1 {
  margin: 0;
  font-size: 24px;
  font-weight: 700;
  color: var(--color-primary-element);
}

.subtitle {
  margin: 5px 0 0;
  font-size: 13px;
  color: var(--color-text-lighter);
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 20px 10px;
  overflow-y: auto;
}

.nav-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 15px;
  margin-bottom: 4px;
  background: transparent;
  border: none;
  border-radius: var(--border-radius-large);
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: left;
  font-size: 15px;
  font-weight: 500;
  color: var(--color-main-text);
}

.nav-item:hover {
  background: var(--color-background-hover);
}

.nav-item.active {
  background: var(--color-primary-element-light);
  color: var(--color-primary-element-text);
}

.nav-item .icon {
  width: 20px;
  height: 20px;
  opacity: 0.7;
  flex-shrink: 0;
}

.nav-item.active .icon {
  opacity: 1;
}

.nav-label {
  flex: 1;
}

/* Footer */
.sidebar-footer {
  padding: 20px;
  border-top: 1px solid var(--color-border);
}

.license-info {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: var(--color-background-dark);
  border-radius: var(--border-radius-large);
}

.license-info .icon-quota {
  font-size: 20px;
  opacity: 0.7;
}

.license-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.license-count {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-main-text);
}

.license-label {
  font-size: 12px;
  color: var(--color-text-lighter);
}
</style>
