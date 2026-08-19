<template>
    <div class="pagination">
        <div class="pagination-info">
            {{
                t('souvera_central', 'Show {from} to {to} of {total}', {
                    from: fromItem,
                    to: toItem,
                    total: total
                })
            }}
        </div>

        <div class="pagination-controls">
            <!-- Erste Seite -->
            <button
                class="pagination-button"
                :disabled="currentPage === 1"
                :title="t('souvera_central', 'First page')"
                @click="goToPage(1)"
            >
                <ChevronDoubleLeft :size="18" />
            </button>

            <!-- Vorherige Seite -->
            <button
                class="pagination-button"
                :disabled="currentPage === 1"
                :title="t('souvera_central', 'Previous page')"
                @click="goToPage(currentPage - 1)"
            >
                <ChevronLeft :size="18" />
            </button>

            <!-- Seiten-Nummern -->
            <div class="page-numbers">
                <button
                    v-for="page in visiblePages"
                    :key="page"
                    :class="['page-number', { active: page === currentPage }]"
                    @click="goToPage(page)"
                >
                    {{ page }}
                </button>
            </div>

            <!-- Nächste Seite -->
            <button
                class="pagination-button"
                :disabled="currentPage === totalPages"
                :title="t('souvera_central', 'Next page')"
                @click="goToPage(currentPage + 1)"
            >
                <ChevronRight :size="18" />
            </button>

            <!-- Letzte Seite -->
            <button
                class="pagination-button"
                :disabled="currentPage === totalPages"
                :title="t('souvera_central', 'Last page')"
                @click="goToPage(totalPages)"
            >
                <ChevronDoubleRight :size="18" />
            </button>
        </div>

        <div class="per-page-selector">
            <label>
                {{ t('souvera_central', 'Per page') }}:
                <select v-model.number="selectedPerPage" @change="handlePerPageChange">
                    <option :value="10">10</option>
                    <option :value="20">20</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                </select>
            </label>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import ChevronDoubleLeft from 'vue-material-design-icons/ChevronDoubleLeft.vue'
import ChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import ChevronDoubleRight from 'vue-material-design-icons/ChevronDoubleRight.vue'

export default {
    name: 'Pagination',

    components: {
        ChevronDoubleLeft,
        ChevronLeft,
        ChevronRight,
        ChevronDoubleRight
    },

    props: {
        currentPage: {
            type: Number,
            required: true
        },
        perPage: {
            type: Number,
            default: 20
        },
        total: {
            type: Number,
            required: true
        }
    },

    data() {
        return {
            selectedPerPage: this.perPage
        }
    },

    computed: {
        totalPages() {
            return Math.ceil(this.total / this.perPage) || 1
        },

        fromItem() {
            if (this.total === 0) return 0
            return (this.currentPage - 1) * this.perPage + 1
        },

        toItem() {
            const to = this.currentPage * this.perPage
            return Math.min(to, this.total)
        },

        visiblePages() {
            const pages = []
            const total = this.totalPages
            const current = this.currentPage

            // Zeige maximal 5 Seiten
            const maxVisible = 5
            let start = Math.max(1, current - Math.floor(maxVisible / 2))
            let end = Math.min(total, start + maxVisible - 1)

            // Anpassen wenn am Anfang
            if (end - start + 1 < maxVisible) {
                start = Math.max(1, end - maxVisible + 1)
            }

            for (let i = start; i <= end; i++) {
                pages.push(i)
            }

            return pages
        }
    },

    watch: {
        perPage(newVal) {
            this.selectedPerPage = newVal
        }
    },

    methods: {
        t,

        goToPage(page) {
            if (page < 1 || page > this.totalPages || page === this.currentPage) {
                return
            }
            this.$emit('page-change', page)
        },

        handlePerPageChange() {
            this.$emit('per-page-change', this.selectedPerPage)
        }
    }
}
</script>

<style scoped>
.pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 20px;
    background: transparent;
    border-top: none;
    flex-wrap: wrap;
}

.pagination-info {
    color: var(--color-main-text);
    font-size: 14px;
    font-weight: 600;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pagination-button {
    padding: 8px 12px;
    background: var(--color-main-background);
    border: 1.5px solid var(--color-border);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--color-main-text);
}

.pagination-button:hover:not(:disabled) {
    background: var(--color-main-background);
    border-color: var(--color-secondary-element);
    transform: translateY(-1px);
}

.pagination-button:disabled {
    opacity: 0.25;
    cursor: not-allowed;
}

.page-numbers {
    display: flex;
    gap: 4px;
}

.page-number {
    min-width: 36px;
    padding: 8px 12px;
    background: var(--color-main-background);
    border: 1.5px solid var(--color-border);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 600;
    color: var(--color-main-text);
}

.page-number:hover {
    background: var(--color-main-background);
    border-color: var(--color-secondary-element);
    transform: translateY(-1px);
}

.page-number.active {
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
    border-color: var(--color-primary-element);
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(var(--color-primary-element-rgb), 0.35);
}

.per-page-selector {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: var(--color-main-text);
    font-weight: 600;
}

.per-page-selector select {
    padding: 6px 10px;
    border: 1.5px solid var(--color-border);
    border-radius: 6px;
    background: var(--color-main-background);
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: var(--color-main-text);
}

.per-page-selector select:focus {
    outline: none;
    border-color: var(--color-secondary-element);
    box-shadow: 0 0 0 2px rgba(var(--color-primary-element-rgb), 0.2);
}

@media (max-width: 768px) {
    .pagination {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }

    .pagination-controls {
        justify-content: center;
    }

    .pagination-info,
    .per-page-selector {
        text-align: center;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .pagination {
        padding: 0;
    }
}
</style>
