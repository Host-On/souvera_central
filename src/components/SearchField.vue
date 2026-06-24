<template>
    <div class="search-field">
        <Magnify :size="18" class="search-icon" />
        <input
            v-model="searchQuery"
            type="text"
            :placeholder="placeholder"
            class="search-input"
            @input="handleInput" />
        <button
            v-if="searchQuery"
            class="clear-button"
            :title="t('souvera_central', 'Suche löschen')"
            @click="clearSearch"
        >
            <Close :size="16" />
        </button>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Close from 'vue-material-design-icons/Close.vue'

export default {
    name: 'SearchField',

    components: {
        Magnify,
        Close
    },

    props: {
        modelValue: {
            type: String,
            default: ''
        },
        placeholder: {
            type: String,
            default: 'Suchen...'
        },
        debounce: {
            type: Number,
            default: 300
        }
    },

    data() {
        return {
            searchQuery: this.modelValue,
            debounceTimeout: null
        }
    },

    watch: {
        modelValue(newVal) {
            this.searchQuery = newVal
        }
    },

    methods: {
        t,

        handleInput() {
            // Debounce: Warte kurz bevor die Suche ausgelöst wird
            clearTimeout(this.debounceTimeout)

            this.debounceTimeout = setTimeout(() => {
                this.$emit('update:modelValue', this.searchQuery)
                this.$emit('search', this.searchQuery)
            }, this.debounce)
        },

        clearSearch() {
            this.searchQuery = ''
            this.$emit('update:modelValue', '')
            this.$emit('search', '')
        }
    }
}
</script>

<style scoped>
.search-field {
    position: relative;
    display: flex;
    align-items: center;
    max-width: 400px;
}

.search-icon {
    position: absolute;
    left: 12px;
    opacity: 0.5;
    pointer-events: none;
    color: var(--color-main-text);
    z-index: 1;
}

.search-input {
    width: 100%;
    padding: 10px 40px 10px 40px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.search-input:focus {
    outline: none;
    border-color: var(--color-primary-element);
    box-shadow: 0 0 0 2px var(--color-primary-element-light);
}

.clear-button {
    position: absolute;
    right: 8px;
    background: none;
    border: none;
    padding: 6px;
    cursor: pointer;
    opacity: 0.5;
    transition: opacity 0.2s;
    border-radius: var(--border-radius);
}

.clear-button:hover {
    opacity: 1;
    background: var(--color-background-hover);
}
</style>
