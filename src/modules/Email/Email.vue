<template>
    <div class="email-container" data-testid="email-module">
        <div class="page-header">
            <h2>{{ t('souvera_central', 'E-Mail') }}</h2>
            <p class="header-subtitle">{{ t('souvera_central', 'Zentrale E-Mail-Funktionen für deine Domains') }}</p>
        </div>

        <div class="email-tabs" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                class="email-tab"
                :class="{ active: activeTab === tab.id }"
                :data-testid="`email-tab-${tab.id}`"
                role="tab"
                @click="activeTab = tab.id">
                <component :is="tab.icon" :size="18" />
                <span>{{ tab.label }}</span>
            </button>
        </div>

        <div class="email-content">
            <BimiManager
                v-if="activeTab === 'bimi'"
                :allowed-domains="allowedDomains" />
        </div>
    </div>
</template>

<script>
import { markRaw } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import ShieldCheck from 'vue-material-design-icons/ShieldCheck.vue'
import BimiManager from './components/BimiManager.vue'

export default {
    name: 'Email',

    components: {
        BimiManager,
    },

    props: {
        allowedDomains: {
            type: Array,
            default: () => [],
        },
    },

    data() {
        return {
            activeTab: 'bimi',
            tabs: [
                { id: 'bimi', label: t('souvera_central', 'BIMI'), icon: markRaw(ShieldCheck) },
            ],
        }
    },

    methods: { t },
}
</script>

<style scoped>
.email-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 24px;
}

.page-header {
    margin-bottom: 20px;
}

.page-header h2 {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 4px;
    color: var(--color-main-text);
}

.header-subtitle {
    font-size: 14px;
    color: var(--color-text-maxcontrast);
    margin: 0;
}

.email-tabs {
    display: flex;
    gap: 6px;
    border-bottom: 2px solid var(--color-border);
    margin-bottom: 24px;
}

.email-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    color: var(--color-text-maxcontrast);
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: color 0.15s ease, border-color 0.15s ease;
}

.email-tab:hover {
    color: var(--color-main-text);
}

.email-tab.active {
    color: var(--color-primary-element);
    border-bottom-color: var(--color-primary-element);
}
</style>
