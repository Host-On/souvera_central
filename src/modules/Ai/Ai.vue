<template>
    <div class="ai-container">
        <div class="page-header">
            <h2>{{ t('souvera_central', 'Souvera AI') }}</h2>
            <p class="header-subtitle">{{ t('souvera_central', 'AI function, internal knowledge base and MCP endpoint') }}</p>
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
                    {{ t('souvera_central', 'Enables or disables Souvera AI for this instance. On activation the MCP access token is generated automatically.') }}
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
                            {{ enabled ? t('souvera_central', 'Active') : t('souvera_central', 'Inactive') }}
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

            <!-- 2. MCP ENDPOINT -->
            <div class="settings-section" data-testid="mcp-section">
                <div class="section-header">
                    <LanConnect :size="22" />
                    <h3>{{ t('souvera_central', 'MCP endpoint') }}</h3>
                </div>
                <p class="section-description">
                    {{
                        t(
                            'souvera_central',
                            'The Nextcloud agent reads the knowledge base live via MCP. The access token is provided internally via the shared API and never leaves this instance.'
                        )
                    }}
                </p>

                <div class="settings-group">
                    <div class="mcp-row">
                        <span class="mcp-label">{{ t('souvera_central', 'Endpoint') }}</span>
                        <code class="mcp-value" data-testid="mcp-endpoint">{{ status.mcp.endpoint }}</code>
                    </div>
                    <div class="mcp-row">
                        <span class="mcp-label">{{ t('souvera_central', 'Access token') }}</span>
                        <span class="mcp-value">
                            {{ status.mcp.token_set ? t('souvera_central', 'Created') + ' (' + (status.mcp.created_at || '') + ')' : t('souvera_central', 'Not created') }}
                        </span>
                    </div>
                    <div class="mcp-actions">
                        <button
                            class="secondary-button"
                            :disabled="saving"
                            data-testid="mcp-rotate"
                            @click="rotateToken"
                        >
                            {{ deleteArmMcp ? t('souvera_central', 'Really rotate token?') : t('souvera_central', 'Rotate token') }}
                        </button>
                        <span v-if="deleteArmMcp" class="confirm-cancel" @click="deleteArmMcp = false">
                            {{ t('souvera_central', 'Cancel') }}
                        </span>
                    </div>
                    <p class="setting-hint">
                        {{
                            t(
                                'souvera_central',
                                'Internal retrieval for apps: OCP\\Server::get(AiMcpTokenService::class)->getToken() — see docs/SHARED_AI_MCP.md.'
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- 3. KNOWLEDGE BASE -->
            <div class="settings-section" data-testid="kb-section">
                <div class="section-header">
                    <Book :size="22" />
                    <h3>{{ t('souvera_central', 'Knowledge base') }}</h3>
                    <span class="kb-count">{{ t('souvera_central', '{count} articles', { count: articles.length }) }}</span>
                </div>
                <p class="section-description">
                    {{
                        t(
                            'souvera_central',
                            'Internal knowledge for the AI agent: company info, FAQ, processes. The agent searches these articles via MCP.'
                        )
                    }}
                </p>

                <div class="kb-toolbar">
                    <button class="primary-button" data-testid="kb-new" @click="startCreate">
                        {{ t('souvera_central', 'New article') }}
                    </button>
                </div>

                <div class="kb-layout">
                    <ul class="kb-list" data-testid="kb-list">
                        <li
                            v-for="article in articles"
                            :key="article.id"
                            class="kb-entry"
                            :class="{ 'kb-active': editor.id === article.id }"
                            @click="startEdit(article)"
                        >
                            <span class="kb-title">{{ article.title }}</span>
                            <span class="kb-excerpt">{{ article.excerpt }}</span>
                        </li>
                        <li v-if="articles.length === 0" class="kb-empty">
                            {{ t('souvera_central', 'No articles yet.') }}
                        </li>
                    </ul>

                    <div class="kb-editor" data-testid="kb-editor">
                        <template v-if="editor.mode !== 'closed'">
                            <input
                                v-model="editor.title"
                                class="kb-title-input"
                                type="text"
                                :placeholder="t('souvera_central', 'Title')"
                                data-testid="kb-title-input"
                            >
                            <textarea
                                v-model="editor.content"
                                class="kb-content-input"
                                rows="16"
                                :placeholder="t('souvera_central', 'Markdown content...')"
                                data-testid="kb-content-input"
                            ></textarea>
                            <div class="kb-editor-actions">
                                <button class="primary-button" :disabled="saving" data-testid="kb-save" @click="saveArticle">
                                    {{ t('souvera_central', 'Save') }}
                                </button>
                                <button class="secondary-button" @click="closeEditor">
                                    {{ t('souvera_central', 'Cancel') }}
                                </button>
                                <span v-if="editor.mode === 'edit'" class="confirm-delete">
                                    <button class="danger-button" data-testid="kb-delete" @click="armDelete">
                                        {{ deleteArm ? t('souvera_central', 'Really delete?') : t('souvera_central', 'Delete') }}
                                    </button>
                                    <span v-if="deleteArm" class="confirm-cancel" @click="deleteArm = false">
                                        {{ t('souvera_central', 'Cancel') }}
                                    </span>
                                </span>
                            </div>
                        </template>
                        <div v-else class="kb-editor-empty">
                            {{ t('souvera_central', 'Select an article or create a new one.') }}
                        </div>
                    </div>
                </div>
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
import LanConnect from 'vue-material-design-icons/LanConnect.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Close from 'vue-material-design-icons/Close.vue'

export default {
    name: 'AiView',

    components: {
        NcLoadingIcon,
        Robot,
        Book,
        LanConnect,
        Check,
        Close,
    },

    data() {
        return {
            loading: true,
            saving: false,
            enabled: false,
            status: {
                kb_count: 0,
                mcp: { token_set: false, created_at: null, endpoint: '' },
            },
            articles: [],
            editor: {
                mode: 'closed', // closed | create | edit
                id: null,
                title: '',
                content: '',
            },
            deleteArm: false,
            deleteArmMcp: false,
        }
    },

    async mounted() {
        await Promise.all([this.loadStatus(), this.loadArticles()])
        this.loading = false
    },

    methods: {
        t,

        api(path) {
            return generateUrl('/apps/souvera_central/api/ai' + path)
        },

        async loadStatus() {
            try {
                const response = await axios.get(this.api('/status'))
                const data = response.data.ocs?.data || response.data
                this.enabled = !!data.enabled
                this.status = {
                    kb_count: data.kb_count || 0,
                    mcp: {
                        token_set: !!data.mcp?.token_set,
                        created_at: data.mcp?.created_at || null,
                        endpoint: data.mcp?.endpoint || '',
                    },
                }
            } catch (error) {
                console.error('[SouveraCentral] ai status failed:', error?.response?.status)
            }
        },

        async loadArticles() {
            try {
                const response = await axios.get(this.api('/kb'))
                const data = response.data.ocs?.data || response.data
                this.articles = data.articles || []
            } catch (error) {
                console.error('[SouveraCentral] ai kb list failed:', error?.response?.status)
            }
        },

        async toggleAi() {
            if (this.saving) {
                return
            }
            this.saving = true
            try {
                const action = this.enabled ? 'disable' : 'enable'
                const response = await axios.post(this.api('/' + action))
                const data = response.data.ocs?.data || response.data
                this.enabled = !!data.enabled
                this.status.mcp.token_set = !!data.mcp?.token_set
                this.status.mcp.created_at = data.mcp?.created_at || this.status.mcp.created_at
            } catch (error) {
                console.error('[SouveraCentral] ai toggle failed:', error?.response?.status, error?.response?.data)
            } finally {
                this.saving = false
            }
        },

        async rotateToken() {
            if (this.saving) {
                return
            }
            if (!this.deleteArmMcp) {
                this.deleteArmMcp = true
                return
            }
            this.deleteArmMcp = false
            this.saving = true
            try {
                const response = await axios.post(this.api('/mcp/rotate'))
                const data = response.data.ocs?.data || response.data
                this.status.mcp.token_set = !!data.mcp?.token_set
                this.status.mcp.created_at = data.mcp?.created_at || null
            } catch (error) {
                console.error('[SouveraCentral] ai mcp rotate failed:', error?.response?.status)
            } finally {
                this.saving = false
            }
        },

        startCreate() {
            this.deleteArm = false
            this.editor = { mode: 'create', id: null, title: '', content: '' }
        },

        startEdit(article) {
            this.deleteArm = false
            this.editor = { mode: 'edit', id: article.id, title: article.title, content: article.content || '' }
            this.loadFullArticle(article.id)
        },

        async loadFullArticle(id) {
            try {
                const response = await axios.get(this.api('/kb/' + id))
                const data = response.data.ocs?.data || response.data
                if (this.editor.id === id) {
                    this.editor.content = data.content || ''
                }
            } catch (error) {
                console.error('[SouveraCentral] ai kb get failed:', error?.response?.status)
            }
        },

        closeEditor() {
            this.editor = { mode: 'closed', id: null, title: '', content: '' }
            this.deleteArm = false
        },

        async saveArticle() {
            if (this.saving || this.editor.mode === 'closed') {
                return
            }
            if (this.editor.title.trim() === '') {
                return
            }
            this.saving = true
            try {
                if (this.editor.mode === 'create') {
                    await axios.post(this.api('/kb'), {
                        title: this.editor.title,
                        content: this.editor.content,
                    })
                } else {
                    await axios.put(this.api('/kb/' + this.editor.id), {
                        title: this.editor.title,
                        content: this.editor.content,
                    })
                }
                await this.loadArticles()
                await this.loadStatus()
                this.closeEditor()
            } catch (error) {
                console.error('[SouveraCentral] ai kb save failed:', error?.response?.status, error?.response?.data)
            } finally {
                this.saving = false
            }
        },

        async armDelete() {
            if (!this.deleteArm) {
                this.deleteArm = true
                return
            }
            this.deleteArm = false
            if (this.editor.id === null) {
                return
            }
            this.saving = true
            try {
                await axios.delete(this.api('/kb/' + this.editor.id))
                await this.loadArticles()
                await this.loadStatus()
                this.closeEditor()
            } catch (error) {
                console.error('[SouveraCentral] ai kb delete failed:', error?.response?.status)
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

.kb-count {
    margin-left: auto;
    color: var(--color-text-maxcontrast);
    font-size: 13px;
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

.primary-button,
.secondary-button,
.danger-button {
    padding: 8px 16px;
    border-radius: var(--border-radius-element, 8px);
    border: 1px solid var(--color-border);
    cursor: pointer;
    font-size: 14px;
}

.primary-button {
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
    border-color: var(--color-primary-element);
}

.secondary-button {
    background: transparent;
    color: var(--color-main-text);
}

.danger-button {
    background: var(--color-error);
    border-color: var(--color-error);
    color: var(--color-error-text, white);
}

.setting-hint {
    color: var(--color-text-maxcontrast);
    font-size: 13px;
    margin: 4px 0 0 0;
}

.mcp-row {
    display: flex;
    align-items: baseline;
    gap: 12px;
    flex-wrap: wrap;
}

.mcp-label {
    min-width: 120px;
    color: var(--color-text-maxcontrast);
    font-size: 13px;
}

.mcp-value {
    font-family: monospace;
    font-size: 13px;
    word-break: break-all;
}

.mcp-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 4px;
}

.confirm-cancel {
    color: var(--color-text-maxcontrast);
    cursor: pointer;
    font-size: 13px;
}

.confirm-delete {
    display: inline-flex;
    align-items: center;
    gap: 12px;
}

.kb-toolbar {
    margin-bottom: 12px;
}

.kb-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 16px;
    margin-bottom: 8px;
}

.kb-list {
    list-style: none;
    margin: 0;
    padding: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    overflow: hidden;
    max-height: 480px;
    overflow-y: auto;
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

.kb-excerpt {
    color: var(--color-text-maxcontrast);
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.kb-empty {
    padding: 16px;
    color: var(--color-text-maxcontrast);
}

.kb-editor {
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    padding: 16px;
    min-height: 320px;
    display: flex;
    flex-direction: column;
}

.kb-editor-empty {
    color: var(--color-text-maxcontrast);
    margin: auto;
}

.kb-title-input {
    width: 100%;
    padding: 8px 10px;
    margin-bottom: 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-element, 8px);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 14px;
}

.kb-content-input {
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-element, 8px);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-family: monospace;
    font-size: 13px;
    line-height: 1.5;
    resize: vertical;
    flex: 1;
    min-height: 220px;
}

.kb-editor-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 12px;
}
</style>
