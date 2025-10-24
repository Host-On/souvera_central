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
            // Hard navigation: Update URL with pushState
            window.history.pushState({ route }, '', url)

            // Emit navigation event to parent (App.vue)
            // Parent will handle route change
            this.$emit('navigate', route)

            // Dispatch custom event for child components to react
            window.dispatchEvent(new CustomEvent('route-changed', { detail: { route, url } }))
        }
    }
}
</script>

<style scoped>
.app-sidebar {
    width: 260px;
    padding: 20px 15px;
    height: 100vh;
    background: transparent;
    border-top-left-radius: 6px;
    border-bottom-left-radius: 6px;
    margin-right: 10px;
    box-shadow: 0 8px 20px rgba(6, 11, 20, 0.08);
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
}

/* Header */
.sidebar-header {
    margin: 0 0 20px 0;
    background: transparent;
    border-bottom: none;
    padding-bottom: 0;
}

.sidebar-header h1 {
    margin: 0 0 12px 0;
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: 0.2px;
    color: #3074BF !important;
}

.subtitle {
    margin: 0;
    font-size: 0.85rem;
    color: var(--color-main-text-souvera);
    opacity: 0.7;
}

/* Navigation */
.sidebar-nav {
    flex: 1;
    padding: 0;
    overflow-y: auto;
    overflow-x: hidden;
}

.nav-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    margin-bottom: 8px;
    background: transparent;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background 160ms ease, transform 120ms ease;
    text-align: left;
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--color-primary-text);
    white-space: nowrap;
    text-decoration: none;
    box-sizing: border-box;
}

.nav-item:hover {
    background: rgba(0, 0, 0, 0.05);
    transform: translateX(2px);
}

.nav-item.active {
    background: var(--color-primary-element);
    color: var(--color-primary-text);
    font-weight: 600;
    box-shadow: 0 6px 18px rgba(14, 165, 233, 0.16);
}

.nav-item .icon {
    width: 18px;
    height: 18px;
    opacity: 0.7;
    flex-shrink: 0;
}

.nav-item.active .icon {
    opacity: 1;
}

.nav-item:hover .icon {
    opacity: 0.9;
}

.nav-label {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .app-sidebar {
        width: 100%;
        height: auto;
        padding: 15px;
        margin-right: 0;
        margin-bottom: 10px;
        border-radius: 6px;
    }

    .sidebar-nav {
        display: flex;
        flex-direction: row;
        overflow-x: auto;
        overflow-y: hidden;
        gap: 8px;
        padding-bottom: 5px;
    }

    .nav-item {
        margin-bottom: 0;
        white-space: nowrap;
        min-width: auto;
    }

    .sidebar-header {
        margin-bottom: 15px;
    }
}

@media (max-width: 768px) {
    .app-sidebar {
        padding: 10px;
    }

    .sidebar-header h1 {
        font-size: 1rem;
    }

    .subtitle {
        font-size: 0.75rem;
    }

    .nav-item {
        padding: 8px 12px;
        font-size: 0.85rem;
    }

    .nav-item .icon {
        width: 16px;
        height: 16px;
    }
}

@media (max-width: 480px) {
    .sidebar-nav {
        gap: 5px;
    }

    .nav-item {
        padding: 6px 10px;
        font-size: 0.8rem;
    }

    .nav-label {
        display: none;
    }

    .nav-item .icon {
        margin: 0;
    }
}
</style>
