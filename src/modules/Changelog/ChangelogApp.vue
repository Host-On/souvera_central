<template>
    <NcContent app-name="souvera_central">
        <NcAppNavigation data-testid="changelog-navigation" :aria-label="t('souvera_central', 'Changelog')">
            <template #list>
                <NcAppNavigationItem
                    name="Souvera Mail"
                    :active="activeApp === 'souvera_mail'"
                    data-testid="nav-souvera_mail"
                    @click="selectApp('souvera_mail')">
                    <template #icon><EmailOutline :size="20" /></template>
                </NcAppNavigationItem>
                <NcAppNavigationItem
                    name="Souvera Central"
                    :active="activeApp === 'souvera_central'"
                    data-testid="nav-souvera_central"
                    @click="selectApp('souvera_central')">
                    <template #icon><Server :size="20" /></template>
                </NcAppNavigationItem>
                <NcAppNavigationItem
                    name="Souvera Shield"
                    :active="activeApp === 'souvera_shield'"
                    data-testid="nav-souvera_shield"
                    @click="selectApp('souvera_shield')">
                    <template #icon><ShieldOutline :size="20" /></template>
                </NcAppNavigationItem>
            </template>
        </NcAppNavigation>

        <NcAppContent>
            <div class="changelog-content" data-testid="changelog-content">
                <div v-if="loading" class="changelog-state">
                    <span class="icon-loading"></span>
                </div>

                <div v-else-if="error" class="changelog-state changelog-state--error" data-testid="changelog-error">
                    <AlertCircleOutline :size="64" class="changelog-empty__icon" />
                    <h2 class="changelog-empty__title">{{ t('souvera_central', 'Changelog not available') }}</h2>
                    <p class="changelog-empty__desc">{{ error }}</p>
                </div>

                <template v-else>
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
                </template>
            </div>
        </NcAppContent>
    </NcContent>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'

import EmailOutline from 'vue-material-design-icons/EmailOutline.vue'
import Server from 'vue-material-design-icons/Server.vue'
import ShieldOutline from 'vue-material-design-icons/ShieldOutline.vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'

export default {
    name: 'ChangelogApp',

    components: {
        NcContent,
        NcAppNavigation,
        NcAppNavigationItem,
        NcAppContent,
        EmailOutline,
        Server,
        ShieldOutline,
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
            } catch (err) {
                this.error = t('souvera_central', 'The changelog could not be loaded. Please try again later.')
                console.error('changelog load failed', err)
            } finally {
                this.loading = false
            }
        },

        selectApp(appId) {
            this.activeApp = appId
        }
    }
}
</script>

<style scoped>
.changelog-content {
    max-width: 860px;
    padding: 24px;
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
