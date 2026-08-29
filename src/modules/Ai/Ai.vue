<template>
    <div class="ai-container">
        <div class="page-header">
            <h2>{{ t('souvera_central', 'Souvera AI') }}</h2>
            <p class="header-subtitle">{{ t('souvera_central', 'AI function and knowledge base') }}</p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
            <NcLoadingIcon :size="32" />
            <p>{{ t('souvera_central', 'Loading...') }}</p>
        </div>

        <div v-else class="ai-content">
            <!-- 1. AI FUNCTION -->
            <div class="settings-section" data-testid="ai-status-section">
                <div class="section-header">
                    <Robot :size="22" />
                    <h3>{{ t('souvera_central', 'AI function') }}</h3>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'Enables or disables Souvera AI for this instance.') }}
                </p>

                <div class="settings-group">
                    <button
                        class="toggle-button"
                        :class="enabled ? 'toggle-active' : 'toggle-inactive'"
                        :disabled="saving"
                        data-testid="ai-toggle"
                        @click="toggleAi"
                    >
                        <Check v-if="enabled" :size="18" class="toggle-icon" />
                        <Close v-else :size="18" class="toggle-icon" />
                        <span class="toggle-text">
                            {{
                                enabled
                                    ? t('souvera_central', 'Active')
                                    : t('souvera_central', 'Inactive')
                            }}
                        </span>
                    </button>
                    <p class="setting-hint">
                        {{
                            t(
                                'souvera_central',
                                'Also controllable via the command line: occ souvera_central:ai:enable / souvera_central:ai:disable'
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- 2. KNOWLEDGE BASE -->
            <div class="settings-section" data-testid="kb-section">
                <div class="section-header">
                    <Book :size="22" />
                    <h3>{{ t('souvera_central', 'Knowledge base') }}</h3>
                </div>
                <p class="section-description">
                    {{
                        t(
                            'souvera_central',
                            'The factual knowledge files about Souvera used by Souvera AI (resources/ai).'
                        )
                    }}
                </p>

                <div class="kb-layout">
                    <ul class="kb-list" data-testid="kb-list">
                        <li
                            v-for="file in kbFiles"
                            :key="file.name"
                            class="kb-entry"
                            :class="{ 'kb-active': selectedName === file.name }"
                            @click="selectedName = file.name"
                        >
                            <span class="kb-title">{{ file.title || file.name }}</span>
                            <span class="kb-filename">{{ file.name }}</span>
                        </li>
                    </ul>

                    <div class="kb-preview" data-testid="kb-preview">
                        <h4>{{ activeFile.title || activeFile.name }}</h4>
                        <pre class="kb-content">{{ activeFile.content }}</pre>
                    </div>
                </div>

                <p class="setting-hint">
                    {{
                        t(
                            'souvera_central',
                            'These files are maintained in the repository and synced into the AI clouds by the CloudManager. Changes here are local only.'
                        )
                    }}
                </p>
            </div>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import Robot from 'vue-material-design-icons/Robot.vue'
import Book from 'vue-material-design-icons/Book.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Close from 'vue-material-design-icons/Close.vue'

export default {
    name: 'AiView',

    components: {
        NcLoadingIcon,
        Robot,
        Book,
        Check,
        Close,
    },

    data() {
        return {
            loading: true,
            saving: false,
            enabled: false,
            kbFiles: [],
            selectedName: '',
        }
    },

    computed: {
        activeFile() {
            return this.kbFiles.find((f) => f.name === this.selectedName) || this.kbFiles[0] || { name: '', title: '', content: '' }
        },
    },

    async mounted() {
        await Promise.all([this.loadStatus(), this.loadKb()])
        this.loading = false
    },

    methods: {
        t,

        async loadStatus() {
            try {
                const url = generateUrl('/apps/souvera_central/api/ai/status')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data
                this.enabled = !!data.enabled
            } catch (error) {
                console.error('[SouveraCentral] ai status failed:', error?.response?.status)
            }
        },

        async loadKb() {
            try {
                const url = generateUrl('/apps/souvera_central/api/ai/kb')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data
                this.kbFiles = data.files || []
                if (this.kbFiles.length > 0 && this.selectedName === '') {
                    this.selectedName = this.kbFiles[0].name
                }
            } catch (error) {
                console.error('[SouveraCentral] ai kb failed:', error?.response?.status)
            }
        },

        async toggleAi() {
            if (this.saving) {
                return
            }
            this.saving = true
            try {
                const action = this.enabled ? 'disable' : 'enable'
                const url = generateUrl('/apps/souvera_central/api/ai/' + action)
                const response = await axios.post(url)
                const data = response.data.ocs?.data || response.data
                this.enabled = !!data.enabled
            } catch (error) {
                console.error('[SouveraCentral] ai toggle failed:', error?.response?.status, error?.response?.data)
            } finally {
                this.saving = false
            }
        },
    },
}
</script>

<style scoped>
.ai-container {
    padding: 24px;
    max-width: 960px;
}

.page-header h2 {
    margin: 0 0 4px 0;
}

.header-subtitle {
    color: var(--color-text-maxcontrast);
    margin: 0 0 24px 0;
}

.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 48px 0;
    color: var(--color-text-maxcontrast);
}

.settings-section {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    padding: 20px;
    margin-bottom: 20px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.section-header h3 {
    margin: 0;
}

.section-description {
    color: var(--color-text-maxcontrast);
    margin: 0 0 16px 0;
}

.settings-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.toggle-button {
    display: flex;
    align-items: center;
    gap: 8px;
    align-self: flex-start;
    padding: 8px 16px;
    border: 1px solid var(--color-border);
    border-radius: 20px;
    cursor: pointer;
    background: transparent;
    color: var(--color-main-text);
    font-size: 14px;
}

.toggle-button:disabled {
    opacity: 0.5;
    cursor: default;
}

.toggle-active {
    background: var(--color-success);
    border-color: var(--color-success);
    color: white;
}

.toggle-inactive {
    background: var(--color-background-dark);
}

.setting-hint {
    color: var(--color-text-maxcontrast);
    font-size: 13px;
    margin: 4px 0 0 0;
}

.kb-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.kb-list {
    list-style: none;
    margin: 0;
    padding: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    overflow: hidden;
}

.kb-entry {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid var(--color-border);
}

.kb-entry:last-child {
    border-bottom: none;
}

.kb-entry:hover {
    background: var(--color-background-hover);
}

.kb-active {
    background: var(--color-primary-light);
}

.kb-title {
    font-weight: 600;
}

.kb-filename {
    color: var(--color-text-maxcontrast);
    font-size: 12px;
    font-family: monospace;
}

.kb-preview {
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    padding: 16px;
    min-height: 320px;
    max-height: 520px;
    overflow: auto;
}

.kb-preview h4 {
    margin: 0 0 12px 0;
}

.kb-content {
    font-family: monospace;
    font-size: 13px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
    margin: 0;
}
</style>
