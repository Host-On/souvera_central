<template>
    <div class="app-sidebar">
        <div class="sidebar-header">
            <h1>Souvera Central</h1>
            <p class="subtitle">{{ t('souvera_central', 'Management-Zentrale') }}</p>
        </div>

        <nav class="sidebar-nav">
            <a
                v-for="item in navigationItems"
                :key="item.id"
                :href="item.url"
                :class="['nav-item', { active: currentRoute === item.id }]"
                @click.prevent="navigateTo(item.id, item.url)"
            >
                <span :class="['icon', item.icon]"></span>
                <span class="nav-label">{{ item.label }}</span>
            </a>
        </nav>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

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
                    icon: 'icon-home',
                    url: generateUrl('/apps/souvera_central/dashboard')
                },
                {
                    id: 'users',
                    label: this.t('souvera_central', 'Benutzerverwaltung'),
                    icon: 'icon-user',
                    url: generateUrl('/apps/souvera_central/users')
                },
                {
                    id: 'groups',
                    label: this.t('souvera_central', 'Gruppenverwaltung'),
                    icon: 'icon-group',
                    url: generateUrl('/apps/souvera_central/groups')
                },
                {
                    id: 'settings',
                    label: this.t('souvera_central', 'Einstellungen'),
                    icon: 'icon-settings',
                    url: generateUrl('/apps/souvera_central/settings')
                }
            ]
        }
    },

    methods: {
        t,

        navigateTo(route, url) {
            // Emit navigation event to parent (App.vue)
            // Parent will handle pushState and route change
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
    background: var(--color-main-background);
}

.sidebar-header h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: var(--color-main-text);
}

.subtitle {
    margin: 5px 0 0;
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    opacity: 0.7;
}

/* Navigation */
.sidebar-nav {
    flex: 1;
    padding: 20px 10px;
    overflow-y: auto;
    overflow-x: hidden;
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
    white-space: nowrap;
    text-decoration: none;
    box-sizing: border-box;
}

.nav-item:hover {
    background: var(--color-background-hover);
    color: var(--color-main-text);
}

.nav-item.active {
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
    font-weight: 600;
}

.nav-item .icon {
    width: 20px;
    height: 20px;
    opacity: 0.7;
    flex-shrink: 0;
}

.nav-item.active .icon {
    opacity: 1;
    filter: brightness(0) invert(1);
}

.nav-item:hover .icon {
    opacity: 0.9;
}

.nav-label {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
