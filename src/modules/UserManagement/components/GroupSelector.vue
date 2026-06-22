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
                <button
                    v-if="!(isAdminUser && mode === 'admin' && groupId === 'admin')"
                    class="pill-remove"
                    :title="t('souvera_central', 'Entfernen')"
                    @click="removeGroup(groupId)"
                >
                    <span class="icon-close"></span>
                </button>
                <span
                    v-else
                    class="pill-locked"
                    :title="t('souvera_central', 'Standard-Administrator kann nicht aus Admin-Gruppe entfernt werden')"
                >
                    <span class="icon-password"></span>
                </span>
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
                        <span
                            class="checkbox-icon"
                            :class="isSelected(group.id) ? 'icon-checkmark' : 'icon-add'"
                        ></span>
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
        },
        isAdminUser: {
            type: Boolean,
            default: false
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
                (group) => group.id.toLowerCase().includes(query) || group.displayName.toLowerCase().includes(query)
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
            // Verhindere Entfernung von admin aus admin-Gruppe
            if (this.isAdminUser && this.mode === 'admin' && groupId === 'admin') {
                return
            }
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
    gap: 10px;
    padding: 16px;
    background: var(--color-main-background);
    border: 2px solid var(--color-border);
    border-radius: 6px;
    min-height: 60px;
}

.group-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px 8px 14px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 700;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.group-pill.member-pill {
    background: var(--color-primary-element);
    color: #fff;
    border: 2px solid var(--color-primary-element);
}

.group-pill.admin-pill {
    background: linear-gradient(135deg, #ff9500 0%, #ff8000 100%);
    color: #fff;
    border: 2px solid #ff8000;
}

.pill-icon {
    opacity: 1;
    font-size: 14px;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-main-background);
    border-radius: 50%;
    color: #fff;
    filter: brightness(0) invert(1);
}

.pill-label {
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #fff;
    font-weight: 700;
}

.pill-remove {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 33px;
    height: 33px;
    background: var(--color-main-background);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
    padding: 0;
    color: #fff;
}

.pill-remove:hover {
    background: var(--color-main-background);
    transform: scale(1.15);
}

.pill-remove .icon-close {
    font-size: 14px;
    color: #fff;
    filter: brightness(0) invert(1);
}

/* Empty State */
.empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 24px;
    background: var(--color-main-background);
    border: 2px dashed rgba(0, 0, 0, 0.2);
    border-radius: 6px;
    color: #000;
    font-size: 15px;
    font-weight: 600;
}

.empty-icon {
    font-size: 24px;
    opacity: 0.6;
    color: #000;
}

/* Dropdown Container */
.dropdown-container {
    position: relative;
}

.dropdown-trigger {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    background: var(--color-primary-element);
    color: #fff;
    border: 2px solid var(--color-primary-element);
    border-radius: 6px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s;
    width: 100%;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(48, 116, 191, 0.3);
}

.dropdown-trigger:hover {
    background: #2563a8;
    border-color: #2563a8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(48, 116, 191, 0.4);
}

.dropdown-trigger .icon-add {
    color: #fff;
    filter: brightness(0) invert(1);
    font-size: 16px;
}

/* Dropdown Menu */
.dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: var(--color-main-background);
    border: 2px solid var(--color-border);
    border-radius: 6px;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.2);
    z-index: 1000;
    max-height: min(350px, 40vh);
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
    padding: 14px;
    border-bottom: 2px solid var(--color-border);
}

.search-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--color-border);
    border-radius: 6px;
    font-size: 15px;
    background: var(--color-main-background);
    color: #000;
    font-weight: 500;
}

.search-input:focus {
    outline: none;
    border-color: var(--color-primary-element);
    box-shadow: 0 0 0 3px rgba(48, 116, 191, 0.2);
    background: #fff;
}

/* Bulk Actions */
.dropdown-bulk-actions {
    display: flex;
    gap: 10px;
    padding: 10px 14px;
    border-bottom: 2px solid var(--color-border);
    background: var(--color-main-background);
}

.bulk-action-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 14px;
    background: var(--color-main-background);
    border: 2px solid var(--color-border);
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    color: #000;
}

.bulk-action-btn:hover {
    background: #fff;
    border-color: var(--color-primary-element);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(48, 116, 191, 0.2);
}

/* Dropdown List */
.dropdown-list {
    overflow-y: auto;
    max-height: min(220px, 25vh);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    cursor: pointer;
    transition: all 0.2s;
    border-bottom: 1px solid var(--color-border);
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item:hover,
.dropdown-item.highlighted {
    background: rgba(48, 116, 191, 0.1);
}

.dropdown-item.selected {
    background: rgba(30, 214, 122, 0.15);
    border-left: 4px solid #1ED67A;
}

.checkbox-icon {
    font-size: 18px;
    color: var(--color-primary-element);
    flex-shrink: 0;
    font-weight: 700;
}

.dropdown-item.selected .checkbox-icon {
    color: #1ED67A;
    font-weight: 900;
}

.group-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.group-name {
    font-weight: 700;
    font-size: 15px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #000;
}

.group-id {
    font-size: 13px;
    color: #666;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.group-count {
    font-size: 13px;
    color: #666;
    font-weight: 600;
    flex-shrink: 0;
}

/* Empty Dropdown */
.dropdown-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    color: #000;
    text-align: center;
}

.dropdown-empty .icon-search {
    font-size: 40px;
    opacity: 0.4;
    margin-bottom: 12px;
    color: #000;
}

.dropdown-empty p {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #000;
}
</style>
