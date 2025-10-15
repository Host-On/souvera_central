<template>
    <div class="group-selector">
        <!-- Selected Groups Display (Pills) -->
        <div v-if="selectedGroups.length > 0" class="selected-groups">
            <div
                v-for="groupId in selectedGroups"
                :key="groupId"
                class="group-pill"
                :class="mode === 'admin' ? 'admin-pill' : 'member-pill'"
            >
                <span v-if="mode === 'admin'" class="pill-icon icon-password"></span>
                <span v-else class="pill-icon icon-group"></span>
                <span class="pill-label">{{ getGroupDisplayName(groupId) }}</span>
                <button class="pill-remove" @click="removeGroup(groupId)" :title="t('souvera_central', 'Entfernen')">
                    <span class="icon-close"></span>
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="empty-state">
            <span class="empty-icon icon-group"></span>
            <span class="empty-text">
                {{
                    mode === 'admin'
                        ? t('souvera_central', 'Keine Admin-Gruppen ausgewählt')
                        : t('souvera_central', 'Keine Gruppen ausgewählt')
                }}
            </span>
        </div>

        <!-- Dropdown Trigger -->
        <div class="dropdown-container">
            <button type="button" class="dropdown-trigger" @click="toggleDropdown">
                <span class="icon-add"></span>
                {{
                    mode === 'admin'
                        ? t('souvera_central', 'Admin-Gruppe hinzufügen')
                        : t('souvera_central', 'Gruppe hinzufügen')
                }}
            </button>

            <!-- Dropdown Menu -->
            <div v-if="isDropdownOpen" class="dropdown-menu">
                <!-- Search Input -->
                <div class="dropdown-header">
                    <input
                        ref="searchInput"
                        v-model="searchQuery"
                        type="text"
                        class="search-input"
                        :placeholder="t('souvera_central', 'Gruppen suchen...')"
                        @keydown.down.prevent="navigateDown"
                        @keydown.up.prevent="navigateUp"
                        @keydown.enter.prevent="selectHighlighted"
                        @keydown.esc="closeDropdown"
                    />
                </div>

                <!-- Bulk Actions -->
                <div class="dropdown-bulk-actions">
                    <button type="button" class="bulk-action-btn" @click="selectAll">
                        <span class="icon-checkmark"></span>
                        {{ t('souvera_central', 'Alle auswählen') }}
                    </button>
                    <button type="button" class="bulk-action-btn" @click="deselectAll">
                        <span class="icon-close"></span>
                        {{ t('souvera_central', 'Alle abwählen') }}
                    </button>
                </div>

                <!-- Groups List -->
                <div class="dropdown-list">
                    <div
                        v-for="(group, index) in filteredGroups"
                        :key="group.id"
                        class="dropdown-item"
                        :class="{ highlighted: index === highlightedIndex, selected: isSelected(group.id) }"
                        @click="toggleGroup(group.id)"
                        @mouseenter="highlightedIndex = index"
                    >
                        <span class="checkbox-icon" :class="isSelected(group.id) ? 'icon-checkmark' : 'icon-add'"></span>
                        <div class="group-info">
                            <span class="group-name">{{ group.displayName }}</span>
                            <span class="group-id">{{ group.id }}</span>
                        </div>
                        <span v-if="group.userCount !== undefined" class="group-count">
                            {{ group.userCount }} {{ t('souvera_central', 'Mitglieder') }}
                        </span>
                    </div>

                    <div v-if="filteredGroups.length === 0" class="dropdown-empty">
                        <span class="icon-search"></span>
                        <p>{{ t('souvera_central', 'Keine Gruppen gefunden') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
    name: 'GroupSelector',

    props: {
        modelValue: {
            type: Array,
            default: () => []
        },
        availableGroups: {
            type: Array,
            required: true
        },
        mode: {
            type: String,
            default: 'member', // 'member' or 'admin'
            validator: (value) => ['member', 'admin'].includes(value)
        }
    },

    emits: ['update:modelValue'],

    data() {
        return {
            isDropdownOpen: false,
            searchQuery: '',
            highlightedIndex: 0
        }
    },

    computed: {
        selectedGroups() {
            return this.modelValue || []
        },

        filteredGroups() {
            if (!this.searchQuery) {
                return this.availableGroups
            }

            const query = this.searchQuery.toLowerCase()
            return this.availableGroups.filter(
                (group) =>
                    group.id.toLowerCase().includes(query) || group.displayName.toLowerCase().includes(query)
            )
        }
    },

    watch: {
        isDropdownOpen(newValue) {
            if (newValue) {
                this.$nextTick(() => {
                    this.$refs.searchInput?.focus()
                })
                // Close on outside click
                document.addEventListener('click', this.handleOutsideClick)
            } else {
                document.removeEventListener('click', this.handleOutsideClick)
            }
        },

        searchQuery() {
            this.highlightedIndex = 0
        }
    },

    beforeUnmount() {
        document.removeEventListener('click', this.handleOutsideClick)
    },

    methods: {
        t,

        toggleDropdown() {
            this.isDropdownOpen = !this.isDropdownOpen
            if (!this.isDropdownOpen) {
                this.searchQuery = ''
            }
        },

        closeDropdown() {
            this.isDropdownOpen = false
            this.searchQuery = ''
        },

        handleOutsideClick(event) {
            if (!this.$el.contains(event.target)) {
                this.closeDropdown()
            }
        },

        getGroupDisplayName(groupId) {
            const group = this.availableGroups.find((g) => g.id === groupId)
            return group ? group.displayName : groupId
        },

        isSelected(groupId) {
            return this.selectedGroups.includes(groupId)
        },

        toggleGroup(groupId) {
            const selected = [...this.selectedGroups]
            const index = selected.indexOf(groupId)

            if (index > -1) {
                selected.splice(index, 1)
            } else {
                selected.push(groupId)
            }

            this.$emit('update:modelValue', selected)
        },

        removeGroup(groupId) {
            const selected = this.selectedGroups.filter((id) => id !== groupId)
            this.$emit('update:modelValue', selected)
        },

        selectAll() {
            const allGroupIds = this.availableGroups.map((g) => g.id)
            this.$emit('update:modelValue', allGroupIds)
        },

        deselectAll() {
            this.$emit('update:modelValue', [])
        },

        navigateDown() {
            if (this.highlightedIndex < this.filteredGroups.length - 1) {
                this.highlightedIndex++
            }
        },

        navigateUp() {
            if (this.highlightedIndex > 0) {
                this.highlightedIndex--
            }
        },

        selectHighlighted() {
            if (this.filteredGroups[this.highlightedIndex]) {
                this.toggleGroup(this.filteredGroups[this.highlightedIndex].id)
            }
        }
    }
}
</script>

<style scoped>
.group-selector {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Selected Groups (Pills) */
.selected-groups {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 12px;
    background: var(--color-background-dark);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    min-height: 50px;
}

.group-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px 6px 12px;
    border-radius: var(--border-radius-large);
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}

.group-pill.member-pill {
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
}

.group-pill.admin-pill {
    background: linear-gradient(135deg, #ffa500 0%, #ff8c00 100%);
    color: white;
}

.pill-icon {
    opacity: 0.9;
    font-size: 14px;
}

.pill-label {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pill-remove {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    background: rgba(0, 0, 0, 0.2);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
    padding: 0;
    color: inherit;
}

.pill-remove:hover {
    background: rgba(0, 0, 0, 0.3);
    transform: scale(1.1);
}

.pill-remove .icon-close {
    font-size: 12px;
}

/* Empty State */
.empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 20px;
    background: var(--color-background-dark);
    border: 1px dashed var(--color-border);
    border-radius: var(--border-radius-large);
    color: var(--color-text-lighter);
    font-size: 14px;
}

.empty-icon {
    font-size: 20px;
    opacity: 0.5;
}

/* Dropdown Container */
.dropdown-container {
    position: relative;
}

.dropdown-trigger {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
    border: none;
    border-radius: var(--border-radius);
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    width: 100%;
    justify-content: center;
}

.dropdown-trigger:hover {
    background: var(--color-primary-element-light);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px var(--color-box-shadow);
}

/* Dropdown Menu */
.dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    box-shadow: 0 4px 16px var(--color-box-shadow);
    z-index: 1000;
    max-height: 400px;
    display: flex;
    flex-direction: column;
    animation: dropdownSlide 0.2s ease-out;
}

@keyframes dropdownSlide {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Dropdown Header */
.dropdown-header {
    padding: 12px;
    border-bottom: 1px solid var(--color-border);
}

.search-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    font-size: 14px;
    background: var(--color-main-background);
}

.search-input:focus {
    outline: none;
    border-color: var(--color-primary);
}

/* Bulk Actions */
.dropdown-bulk-actions {
    display: flex;
    gap: 8px;
    padding: 8px 12px;
    border-bottom: 1px solid var(--color-border);
}

.bulk-action-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 12px;
    background: transparent;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--color-text-light);
}

.bulk-action-btn:hover {
    background: var(--color-background-hover);
    border-color: var(--color-primary);
}

/* Dropdown List */
.dropdown-list {
    overflow-y: auto;
    max-height: 280px;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
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

.dropdown-item.selected {
    background: var(--color-primary-element-light);
}

.checkbox-icon {
    font-size: 16px;
    color: var(--color-primary);
    flex-shrink: 0;
}

.dropdown-item.selected .checkbox-icon {
    color: var(--color-success);
}

.group-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.group-name {
    font-weight: 500;
    font-size: 14px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.group-id {
    font-size: 12px;
    color: var(--color-text-lighter);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.group-count {
    font-size: 12px;
    color: var(--color-text-lighter);
    flex-shrink: 0;
}

/* Empty Dropdown */
.dropdown-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    color: var(--color-text-lighter);
    text-align: center;
}

.dropdown-empty .icon-search {
    font-size: 32px;
    opacity: 0.3;
    margin-bottom: 10px;
}

.dropdown-empty p {
    margin: 0;
    font-size: 14px;
}
</style>
