<template>
    <div class="changelog" data-testid="changelog-view">
        <div v-if="loading" class="changelog-state">
            <span class="icon-loading"></span>
        </div>

        <div v-else-if="error" class="changelog-state changelog-state--error" data-testid="changelog-error">
            <AlertCircleOutline :size="64" class="changelog-empty__icon" />
            <h2 class="changelog-empty__title">{{ t('souvera_central', 'Changelog not available') }}</h2>
            <p class="changelog-empty__desc">{{ error }}</p>
        </div>

        <template v-else>
            <div class="changelog-tabs" role="tablist" data-testid="changelog-tabs">
                <button
                    v-for="app in apps"
                    :key="app.app_id"
                    type="button"
                    role="tab"
                    :aria-selected="activeApp === app.app_id"
                    :class="['changelog-tab', { 'changelog-tab--active': activeApp === app.app_id }]"
                    :data-testid="'changelog-tab-' + app.app_id"
                    @click="activeApp = app.app_id">
                    {{ app.app_label }}
                </button>
            </div>

            <div class="changelog-panel" role="tabpanel" data-testid="changelog-panel">
                <header class="changelog-header">
                    <h2 class="changelog-header__title">{{ activeLabel }}</h2>
                    <p v-if="entries.length === 0" class="changelog-header__empty">
                        {{ t('souvera_central', 'No changelog entries available yet.') }}
                    </p>
                </header>

                <ol class="changelog-list" data-testid="changelog-list">
                    <li v-for="entry in entries" :key="entry.version" class="changelog-entry">
                        <div class="changelog-entry__head">
                            <span class="changelog-entry__version">v{{ entry.version }}</span>
                            <span v-if="entry.date" class="changelog-entry__date">{{ entry.date }}</span>
                        </div>
                        <h3 v-if="entry.title" class="changelog-entry__title">{{ entry.title }}</h3>
                        <pre v-if="entry.body" class="changelog-entry__body">{{ entry.body }}</pre>
                    </li>
                </ol>
            </div>
        </template>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'

export default {
    name: 'ChangelogView',

    components: {
        AlertCircleOutline
    },

    data() {
        return {
            apps: [],
            activeApp: 'souvera_mail',
            loading: true,
            error: ''
        }
    },

    computed: {
        activeChangelog() {
            return this.apps.find((app) => app.app_id === this.activeApp)
        },
        entries() {
            return this.activeChangelog?.entries ?? []
        },
        activeLabel() {
            return this.activeChangelog?.app_label ?? this.activeApp
        }
    },

    mounted() {
        this.load()
    },

    methods: {
        t,

        async load() {
            this.loading = true
            this.error = ''
            try {
                const { data } = await axios.get(generateUrl('/apps/souvera_central/api/changelogs'))
                this.apps = Array.isArray(data) ? data : []
                if (!this.apps.some((app) => app.app_id === this.activeApp)) {
                    this.activeApp = this.apps[0]?.app_id ?? 'souvera_mail'
                }
            } catch (err) {
                this.error = t('souvera_central', 'The changelog could not be loaded. Please try again later.')
                console.error('changelog load failed', err)
            } finally {
                this.loading = false
            }
        }
    }
}
</script>

<style scoped>
.changelog {
    padding: 24px;
    max-width: 860px;
}

.changelog-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 48px 0;
    color: var(--color-text-maxcontrast);
}

.changelog-state--error {
    color: var(--color-error);
}

.changelog-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: 8px;
}

.changelog-tab {
    padding: 8px 16px;
    border: 1px solid var(--color-border);
    border-radius: 20px;
    background: var(--color-background-hover);
    color: var(--color-main-text);
    cursor: pointer;
    font-size: 14px;
}

.changelog-tab--active {
    background: var(--color-primary);
    color: var(--color-primary-text);
    border-color: var(--color-primary);
}

.changelog-header {
    margin-bottom: 20px;
}

.changelog-header__title {
    margin: 0 0 4px;
    font-size: 22px;
    font-weight: 600;
}

.changelog-header__empty {
    color: var(--color-text-maxcontrast);
}

.changelog-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.changelog-entry {
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 16px 20px;
    background: var(--color-main-background);
}

.changelog-entry__head {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 6px;
}

.changelog-entry__version {
    font-weight: 700;
    color: var(--color-primary-text, #0069a3);
}

.changelog-entry__date {
    font-size: 13px;
    color: var(--color-text-maxcontrast);
}

.changelog-entry__title {
    margin: 0 0 8px;
    font-size: 16px;
    font-weight: 600;
}

.changelog-entry__body {
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
    font-family: var(--font-face);
    font-size: 14px;
    line-height: 1.5;
    color: var(--color-main-text);
}

.changelog-empty__icon {
    color: var(--color-text-maxcontrast);
}

.changelog-empty__title {
    margin: 0;
}

.changelog-empty__desc {
    margin: 0;
    color: var(--color-text-maxcontrast);
}
</style>
