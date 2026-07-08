<template>
    <NcContent app-name="souvera_central">
        <NcAppNavigation data-testid="help-navigation" :aria-label="t('souvera_central', 'Hilfe')">
            <template #list>
                <div v-if="loading" class="help-nav-loading" data-testid="help-nav-loading">
                    <span class="icon-loading" />
                </div>
                <template v-else>
                    <template v-for="row in navRows" :key="row.key">
                        <li
                            v-if="row.type === 'caption'"
                            :class="['help-caption', row.cls]">
                            {{ row.text }}
                        </li>
                        <NcAppNavigationItem
                            v-else
                            :name="row.page.name"
                            :active="activePageId === row.page.id"
                            :data-testid="'help-page-' + row.page.id"
                            @click="openPage(row.page)">
                            <template #icon><FileDocumentOutline :size="20" /></template>
                        </NcAppNavigationItem>
                    </template>
                </template>
            </template>
        </NcAppNavigation>

        <NcAppContent>
            <div class="help-content" data-testid="help-content">
                <div v-if="loading" class="help-state">
                    <span class="icon-loading" />
                </div>

                <div v-else-if="!configured" class="help-empty" data-testid="help-not-configured">
                    <HelpCircleOutline :size="64" class="help-empty__icon" />
                    <h2 class="help-empty__title">{{ t('souvera_central', 'Hilfe noch nicht verfügbar') }}</h2>
                    <p class="help-empty__desc">{{ t('souvera_central', 'Die Dokumentation ist derzeit nicht erreichbar. Bitte wenden Sie sich an Ihren Administrator.') }}</p>
                </div>

                <div v-else-if="pageLoading" class="help-state">
                    <span class="icon-loading" />
                </div>

                <article v-else-if="pageHtml" class="help-article" data-testid="help-article">
                    <h2 class="help-article-title">{{ pageTitle }}</h2>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <div class="rich-content" v-html="pageHtml"></div>
                </article>

                <div v-else class="help-empty" data-testid="help-welcome">
                    <HelpCircleOutline :size="64" class="help-empty__icon" />
                    <h2 class="help-empty__title">{{ t('souvera_central', 'Hilfe & Dokumentation') }}</h2>
                    <p class="help-empty__desc">{{ t('souvera_central', 'Wählen Sie links ein Thema, um die Anleitung zu öffnen.') }}</p>
                </div>
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

import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import HelpCircleOutline from 'vue-material-design-icons/HelpCircleOutline.vue'

export default {
    name: 'HelpApp',

    components: {
        NcContent,
        NcAppNavigation,
        NcAppNavigationItem,
        NcAppContent,
        FileDocumentOutline,
        HelpCircleOutline,
    },

    data() {
        return {
            loading: true,
            configured: true,
            shelves: [],
            activePageId: null,
            pageTitle: '',
            pageHtml: '',
            pageLoading: false,
        }
    },

    computed: {
        // Flache Zeilenliste (Regal/Buch/Kapitel = Überschrift, Seite = Eintrag).
        // Bewusst vorab flach gerechnet: vermeidet gemischte v-if/v-else auf
        // <template> + Element innerhalb v-for (Vue-3 "emitsOptions"-Falle).
        navRows() {
            const rows = []
            this.shelves.forEach((shelf) => {
                rows.push({ key: 'shelf-' + shelf.id, type: 'caption', text: shelf.name, cls: 'help-caption--shelf' })
                shelf.books.forEach((book) => {
                    rows.push({ key: 'book-' + book.id, type: 'caption', text: book.name, cls: 'help-caption--book' })
                    ;(book.contents || []).forEach((node) => {
                        if (node.type === 'chapter') {
                            rows.push({ key: 'chapter-' + node.id, type: 'caption', text: node.name, cls: 'help-caption--chapter' })
                            ;(node.pages || []).forEach((pg) => rows.push({ key: 'page-' + pg.id, type: 'page', page: pg }))
                        } else {
                            rows.push({ key: 'page-' + node.id, type: 'page', page: node })
                        }
                    })
                })
            })
            return rows
        },
    },

    mounted() {
        this.loadTree()
    },

    methods: {
        t,

        unwrap(response) {
            const d = response?.data
            return d?.ocs?.data || d?.data || d || {}
        },

        async loadTree() {
            this.loading = true
            try {
                const url = generateUrl('/apps/souvera_central/api/help/tree')
                const data = this.unwrap(await axios.get(url))
                this.configured = data.configured !== false
                const shelves = (data.shelves || []).map((shelf) => ({
                    id: shelf.id,
                    name: shelf.name,
                    books: (shelf.books || []).map((book) => ({
                        id: book.id,
                        name: book.name,
                        contents: [],
                    })),
                }))
                this.shelves = shelves
                if (this.configured) {
                    // Buchinhalte (Kapitel + Seiten) laden, damit der Navigationsbaum
                    // vollständig ist und nativ auf-/zugeklappt werden kann.
                    const jobs = []
                    shelves.forEach((shelf) => {
                        shelf.books.forEach((book) => jobs.push(this.loadBookContents(book)))
                    })
                    await Promise.all(jobs)
                }
            } catch (error) {
                this.configured = false
            } finally {
                this.loading = false
            }
        },

        async loadBookContents(book) {
            try {
                const url = generateUrl('/apps/souvera_central/api/help/books/{id}', { id: book.id })
                const data = this.unwrap(await axios.get(url))
                book.contents = data.contents || []
            } catch (error) {
                book.contents = []
            }
        },

        async openPage(page) {
            this.activePageId = page.id
            this.pageLoading = true
            this.pageTitle = page.name
            try {
                const url = generateUrl('/apps/souvera_central/api/help/pages/{id}', { id: page.id })
                const data = this.unwrap(await axios.get(url))
                this.pageTitle = data.name || page.name
                this.pageHtml = data.html || ''
            } catch (error) {
                this.pageHtml = ''
            } finally {
                this.pageLoading = false
            }
        },
    },
}
</script>

<style scoped>
.help-content {
    width: 100%;
    min-height: 100%;
    box-sizing: border-box;
}

.help-nav-loading {
    display: flex;
    justify-content: center;
    padding: 24px 0;
}

/* Hierarchische Beschriftungen: Regal > Buch > Kapitel */
.help-caption {
    list-style: none;
    margin: 0;
    padding: 14px 16px 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-maxcontrast);
}

.help-caption--book {
    text-transform: none;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-main-text);
    padding-top: 16px;
}

.help-caption--chapter {
    text-transform: none;
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--color-text-maxcontrast);
    padding-inline-start: 28px;
    padding-top: 8px;
}

.help-state {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
}

.icon-loading {
    display: inline-block;
    width: 44px;
    height: 44px;
    border: 3px solid var(--color-border-dark);
    border-top-color: var(--color-primary-element);
    border-radius: 50%;
    animation: help-spin 0.8s linear infinite;
}

@keyframes help-spin {
    to { transform: rotate(360deg); }
}

.help-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    min-height: 60vh;
    padding: 24px;
    gap: 8px;
}

.help-empty__icon {
    color: var(--color-text-maxcontrast);
    opacity: 0.7;
    margin-bottom: 8px;
}

.help-empty__title {
    font-size: 1.3rem;
    font-weight: var(--font-weight-heading, 700);
    color: var(--color-main-text);
    margin: 0;
}

.help-empty__desc {
    color: var(--color-text-maxcontrast);
    max-width: 420px;
    margin: 0;
}

.help-article {
    max-width: none;
    margin: 0;
    padding: 32px clamp(20px, 4vw, 64px) 64px;
}

.help-article-title {
    font-size: 1.7rem;
    font-weight: var(--font-weight-heading, 700);
    margin: 0 0 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--color-border);
    color: var(--color-main-text);
}

/* BookStack-HTML im Central-Look rendern */
.rich-content {
    color: var(--color-main-text);
    line-height: 1.65;
    font-size: 1rem;
}

.rich-content :deep(h1),
.rich-content :deep(h2),
.rich-content :deep(h3),
.rich-content :deep(h4) {
    color: var(--color-main-text);
    font-weight: var(--font-weight-heading, 700);
    margin: 1.6em 0 0.6em;
    line-height: 1.3;
}

.rich-content :deep(h1) { font-size: 1.5rem; }
.rich-content :deep(h2) { font-size: 1.3rem; }
.rich-content :deep(h3) { font-size: 1.12rem; }

.rich-content :deep(p) { margin: 0 0 1em; }

.rich-content :deep(a) {
    color: var(--color-primary-element);
    text-decoration: underline;
}

.rich-content :deep(ul),
.rich-content :deep(ol) {
    margin: 0 0 1em;
    padding-inline-start: 1.5em;
}

.rich-content :deep(li) { margin: 0.25em 0; }

.rich-content :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: var(--border-radius-large, 12px);
    border: 1px solid var(--color-border);
    margin: 0.5em 0;
}

.rich-content :deep(code) {
    background: var(--color-background-dark);
    padding: 0.15em 0.4em;
    border-radius: var(--border-radius-small, 4px);
    font-family: monospace;
    font-size: 0.9em;
}

.rich-content :deep(pre) {
    background: var(--color-background-dark);
    padding: 16px;
    border-radius: var(--border-radius-large, 12px);
    overflow: auto;
    margin: 0 0 1em;
}

.rich-content :deep(pre code) {
    background: none;
    padding: 0;
}

.rich-content :deep(blockquote) {
    margin: 0 0 1em;
    padding: 8px 16px;
    border-inline-start: 4px solid var(--color-primary-element);
    background: var(--color-background-hover);
    border-radius: var(--border-radius);
}

.rich-content :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 0 0 1em;
}

.rich-content :deep(th),
.rich-content :deep(td) {
    border: 1px solid var(--color-border);
    padding: 8px 12px;
    text-align: start;
}

.rich-content :deep(th) {
    background: var(--color-background-dark);
    font-weight: 600;
}

/* BookStack-Callouts */
.rich-content :deep(.callout) {
    padding: 12px 16px;
    border-radius: var(--border-radius-large, 12px);
    border-inline-start: 4px solid var(--color-primary-element);
    background: var(--color-background-hover);
    margin: 0 0 1em;
}

.rich-content :deep(.callout.info) {
    border-inline-start-color: var(--color-primary-element);
    background: rgba(var(--color-primary-element-rgb), 0.08);
}

.rich-content :deep(.callout.success) {
    border-inline-start-color: var(--color-success);
    background: rgba(var(--color-success-rgb), 0.08);
}

.rich-content :deep(.callout.warning) {
    border-inline-start-color: var(--color-warning);
    background: rgba(var(--color-warning-rgb), 0.1);
}

.rich-content :deep(.callout.danger) {
    border-inline-start-color: var(--color-error);
    background: rgba(var(--color-error-rgb), 0.08);
}

/* BookStack-Seiten nutzen inline gestylte Info-/Screenshot-Boxen (helle
   Hintergründe fürs Light-Theme). Farb-/Hintergrundwerte werden serverseitig
   entfernt; hier ein dezenter, theme-bewusster Hintergrund für gute Lesbarkeit
   in Light UND Dark. Ränder (border-left etc.) bleiben aus dem Inline-Style. */
.rich-content :deep(.screenshot-placeholder) {
    background: var(--color-background-hover);
    color: var(--color-text-maxcontrast);
}

.rich-content :deep([style*="border-left"]),
.rich-content :deep([style*="border-inline-start"]) {
    background: var(--color-background-hover);
    border-radius: var(--border-radius, 8px);
}
</style>
