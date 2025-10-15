<template>
    <div class="manager-selector">
        <div class="autocomplete-container">
            <input
                ref="searchInput"
                v-model="searchQuery"
                type="text"
                :placeholder="t('souvera_central', 'Manager suchen...')"
                @input="handleSearch"
                @focus="showDropdown = true"
                @blur="handleBlur"
                @keydown.down.prevent="navigateDown"
                @keydown.up.prevent="navigateUp"
                @keydown.enter.prevent="selectHighlighted"
                @keydown.esc="closeDropdown"
                class="manager-input"
            />
            <span v-if="searching" class="icon-loading-small input-icon"></span>

            <!-- Dropdown mit Suchergebnissen -->
            <div v-if="showDropdown && (filteredUsers.length > 0 || searchQuery.length >= 2)" class="dropdown">
                <div
                    v-for="(user, index) in filteredUsers"
                    :key="user.id"
                    :class="['dropdown-item', { highlighted: index === highlightedIndex }]"
                    @mousedown.prevent="selectUser(user)"
                    @mouseenter="highlightedIndex = index"
                >
                    <div class="user-info">
                        <span class="icon-user"></span>
                        <div class="user-details">
                            <div class="user-name">{{ user.displayName }}</div>
                            <div class="user-id">{{ user.id }}</div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="filteredUsers.length === 0 && searchQuery.length >= 2 && !searching"
                    class="dropdown-item empty"
                >
                    <span class="icon-info"></span>
                    {{ t('souvera_central', 'Keine Benutzer gefunden') }}
                </div>
            </div>
        </div>

        <!-- Anzeige des ausgewählten Managers -->
        <div v-if="selectedUser" class="selected-manager">
            <div class="manager-card">
                <span class="icon-user"></span>
                <div class="manager-details">
                    <div class="manager-name">{{ selectedUser.displayName }}</div>
                    <div class="manager-id">{{ selectedUser.id }}</div>
                </div>
                <button type="button" class="remove-button" @click="clearSelection">
                    <span class="icon-close"></span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
    name: 'ManagerSelector',

    props: {
        modelValue: {
            type: String,
            default: ''
        },
        initialManager: {
            type: Object,
            default: null
        }
    },

    emits: ['update:modelValue'],

    data() {
        return {
            searchQuery: '',
            filteredUsers: [],
            selectedUser: this.initialManager,
            showDropdown: false,
            searching: false,
            searchTimeout: null,
            highlightedIndex: -1
        }
    },

    watch: {
        modelValue(newValue) {
            if (!newValue && this.selectedUser) {
                this.selectedUser = null
                this.searchQuery = ''
            }
        }
    },

    methods: {
        t,

        async handleSearch() {
            // Clear previous timeout
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout)
            }

            // Nur suchen wenn mindestens 2 Zeichen eingegeben wurden
            if (this.searchQuery.length < 2) {
                this.filteredUsers = []
                this.showDropdown = false
                return
            }

            // Debounce search
            this.searchTimeout = setTimeout(async () => {
                await this.searchUsers()
            }, 300)
        },

        async searchUsers() {
            try {
                this.searching = true
                const url = generateUrl('/apps/souvera_central/api/users/search')
                const response = await axios.get(url, {
                    params: {
                        query: this.searchQuery,
                        limit: 10
                    }
                })

                const data = response.data.ocs?.data || response.data.data || response.data
                this.filteredUsers = data.users || []
                this.showDropdown = true
                this.highlightedIndex = -1
            } catch (error) {
                console.error('Fehler bei User-Suche:', error)
                this.filteredUsers = []
            } finally {
                this.searching = false
            }
        },

        selectUser(user) {
            this.selectedUser = user
            this.searchQuery = ''
            this.filteredUsers = []
            this.showDropdown = false
            this.$emit('update:modelValue', user.id)
        },

        clearSelection() {
            this.selectedUser = null
            this.searchQuery = ''
            this.$emit('update:modelValue', '')
            this.$refs.searchInput.focus()
        },

        handleBlur() {
            // Verzögerung damit Click auf Dropdown-Item funktioniert
            setTimeout(() => {
                this.showDropdown = false
                this.highlightedIndex = -1
            }, 200)
        },

        closeDropdown() {
            this.showDropdown = false
            this.searchQuery = ''
            this.highlightedIndex = -1
        },

        navigateDown() {
            if (this.highlightedIndex < this.filteredUsers.length - 1) {
                this.highlightedIndex++
            }
        },

        navigateUp() {
            if (this.highlightedIndex > 0) {
                this.highlightedIndex--
            }
        },

        selectHighlighted() {
            if (this.highlightedIndex >= 0 && this.highlightedIndex < this.filteredUsers.length) {
                this.selectUser(this.filteredUsers[this.highlightedIndex])
            }
        }
    }
}
</script>

<style scoped>
.manager-selector {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.autocomplete-container {
    position: relative;
}

.manager-input {
    width: 100%;
    padding: 12px 40px 12px 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    font-size: 15px;
    background: var(--color-main-background);
    box-sizing: border-box;
    height: 46px;
}

.manager-input:focus {
    outline: none;
    border-color: var(--color-primary);
}

.input-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
}

/* Dropdown */
.dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    box-shadow: 0 2px 8px var(--color-box-shadow);
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
}

.dropdown-item {
    padding: 12px 14px;
    cursor: pointer;
    transition: background 0.2s;
    border-bottom: 1px solid var(--color-border);
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item:hover,
.dropdown-item.highlighted {
    background: var(--color-background-hover);
}

.dropdown-item.empty {
    cursor: default;
    color: var(--color-text-lighter);
    display: flex;
    align-items: center;
    gap: 8px;
}

.dropdown-item.empty:hover {
    background: transparent;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-info .icon-user {
    font-size: 20px;
    opacity: 0.7;
}

.user-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.user-name {
    font-weight: 500;
    font-size: 14px;
}

.user-id {
    font-size: 13px;
    color: var(--color-text-lighter);
}

/* Selected Manager Card */
.selected-manager {
    margin-top: 8px;
}

.manager-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: var(--color-background-dark);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
}

.manager-card .icon-user {
    font-size: 24px;
    opacity: 0.7;
}

.manager-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.manager-name {
    font-weight: 600;
    font-size: 15px;
}

.manager-id {
    font-size: 13px;
    color: var(--color-text-lighter);
}

.remove-button {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: all 0.2s;
    padding: 0;
    color: var(--color-text-light);
}

.remove-button:hover {
    background: var(--color-error);
    border-color: var(--color-error);
    color: white;
}
</style>
