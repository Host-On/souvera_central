<template>
    <div class="pagination">
        <div class="pagination-info">
            {{
                t('souvera_central', 'Zeige {from} bis {to} von {total}', {
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
                :title="t('souvera_central', 'Erste Seite')"
                @click="goToPage(1)"
            >
                <span class="icon-double-left"></span>
            </button>

            <!-- Vorherige Seite -->
            <button
                class="pagination-button"
                :disabled="currentPage === 1"
                :title="t('souvera_central', 'Vorherige Seite')"
                @click="goToPage(currentPage - 1)"
            >
                <span class="icon-previous"></span>
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
                :title="t('souvera_central', 'Nächste Seite')"
                @click="goToPage(currentPage + 1)"
            >
                <span class="icon-next"></span>
            </button>

            <!-- Letzte Seite -->
            <button
                class="pagination-button"
                :disabled="currentPage === totalPages"
                :title="t('souvera_central', 'Letzte Seite')"
                @click="goToPage(totalPages)"
            >
                <span class="icon-double-right"></span>
            </button>
        </div>

        <div class="per-page-selector">
            <label>
                {{ t('souvera_central', 'Pro Seite') }}:
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

export default {
    name: 'Pagination',

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
    color: #000;
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
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(6px);
    border: 1.5px solid rgba(0, 0, 0, 0.15);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    color: #000;
}

.pagination-button:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.9);
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
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(6px);
    border: 1.5px solid rgba(0, 0, 0, 0.15);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 600;
    color: #000;
}

.page-number:hover {
    background: rgba(255, 255, 255, 0.9);
    border-color: var(--color-secondary-element);
    transform: translateY(-1px);
}

.page-number.active {
    background: var(--color-primary-element);
    color: #000;
    border-color: var(--color-primary-element);
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(255, 246, 219, 0.6);
}

.per-page-selector {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #000;
    font-weight: 600;
}

.per-page-selector select {
    padding: 6px 10px;
    border: 1.5px solid rgba(0, 0, 0, 0.15);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(6px);
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: #000;
}

.per-page-selector select:focus {
    outline: none;
    border-color: var(--color-secondary-element);
    box-shadow: 0 0 0 2px rgba(48, 116, 191, 0.2);
}

/* Icons für Nextcloud (Fallback wenn Icons nicht verfügbar) */
.icon-double-left::before {
    content: '«';
}
.icon-previous::before {
    content: '‹';
}
.icon-next::before {
    content: '›';
}
.icon-double-right::before {
    content: '»';
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
</style>
