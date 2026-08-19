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
                <ShieldAccount v-if="mode === 'admin'" :size="14" class="pill-icon" />
                <AccountGroup v-else :size="14" class="pill-icon" />
                <span class="pill-label">{{ getGroupDisplayName(groupId) }}</span>
                <button
                    v-if="!(isAdminUser && mode === 'admin' && groupId === 'admin')"
                    class="pill-remove"
                    :title="t('souvera_central', 'Remove')"
                    @click="removeGroup(groupId)"
                >
                    <Close :size="14" />
                </button>
                <span
                    v-else
                    class="pill-locked"
                    :title="t('souvera_central', 'The default administrator cannot be removed from the admin group')"
                >
                    <Lock :size="14" />
                </span>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="empty-state">
            <AccountGroup :size="20" class="empty-icon" />
            <span class="empty-text">
                {{
                    mode === 'admin'
                        ? t('souvera_central', 'No admin groups selected')
                        : t('souvera_central', 'No groups selected')
                }}
            </span>
        </div>

        <!-- Dropdown Trigger -->
        <div class="dropdown-container">
            <button type="button" class="dropdown-trigger" @click="toggleDropdown">
                <Plus :size="16" />
                {{
                    mode === 'admin'
                        ? t('souvera_central', 'Add admin group')
                        : t('souvera_central', 'Add group')
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
                        :placeholder="t('souvera_central', 'Search groups...')"
                        @keydown.down.prevent="navigateDown"
                        @keydown.up.prevent="navigateUp"
                        @keydown.enter.prevent="selectHighlighted"
                        @keydown.esc="closeDropdown"
                    />
                </div>

                <!-- Bulk Actions -->
                <div class="dropdown-bulk-actions">
                    <button type="button" class="bulk-action-btn" @click="selectAll">
                        <Check :size="16" />
                        {{ t('souvera_central', 'Select all') }}
                    </button>
                    <button type="button" class="bulk-action-btn" @click="deselectAll">
                        <Close :size="16" />
                        {{ t('souvera_central', 'Deselect all') }}
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
                        <Check v-if="isSelected(group.id)" :size="18" class="checkbox-icon" />
                        <Plus v-else :size="18" class="checkbox-icon" />
                        <div class="group-info">
                            <span class="group-name">{{ group.displayName }}</span>
                            <span class="group-id">{{ group.id }}</span>
                        </div>
                        <span v-if="group.userCount !== undefined" class="group-count">
                            {{ group.userCount }} {{ t('souvera_central', 'Members') }}
                        </span>
                    </div>

                    <div v-if="filteredGroups.length === 0" class="dropdown-empty">
                        <Magnify :size="20" />
                        <p>{{ t('souvera_central', 'No groups found') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import ShieldAccount from 'vue-material-design-icons/ShieldAccount.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Lock from 'vue-material-design-icons/Lock.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'

export default {
    name: 'GroupSelector',

    components: {
        ShieldAccount,
        AccountGroup,
        Close,
        Lock,
        Plus,
        Check,
        Magnify
    },

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
    gap: 8px;
    padding: 4px 0 12px;
    min-height: 0;
}

.group-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px 6px 12px;
    border-radius: var(--border-radius-pill, 100px);
    font-size: 13px;
    font-weight: 600;
    background: var(--color-background-dark);
    border: 1px solid var(--color-border);
    color: var(--color-main-text);
    transition: border-color 0.15s ease, background-color 0.15s ease;
}

.group-pill.member-pill .pill-icon {
    color: var(--color-primary-element);
}

.group-pill.admin-pill .pill-icon {
    color: var(--color-warning);
}

.group-pill.member-pill:hover {
    border-color: var(--color-primary-element);
}

.group-pill.admin-pill:hover {
    border-color: var(--color-warning);
}

.pill-icon {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.pill-label {
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--color-main-text);
    font-weight: 600;
}

.pill-remove {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    background: transparent;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    transition: background-color 0.15s ease, color 0.15s ease;
    padding: 0;
    color: var(--color-text-maxcontrast);
    flex-shrink: 0;
}

.pill-remove:hover {
    background: rgba(var(--color-error-rgb), 0.15);
    color: var(--color-error);
}

.pill-locked {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    color: var(--color-text-maxcontrast);
    flex-shrink: 0;
}

/* Empty State */
.empty-state {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 4px;
    color: var(--color-text-maxcontrast);
    font-size: 13.5px;
    font-weight: 500;
}

.empty-icon {
    opacity: 0.6;
    color: var(--color-text-maxcontrast);
}

/* Dropdown Container */
.dropdown-container {
    position: relative;
}

.dropdown-trigger {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 16px;
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
    border: 1px solid var(--color-primary-element);
    border-radius: var(--border-radius, 8px);
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    width: 100%;
    justify-content: center;
}

.dropdown-trigger:hover {
    background: var(--color-primary-element-hover);
    border-color: var(--color-primary-element-hover);
    box-shadow: 0 2px 8px rgba(var(--color-primary-element-rgb), 0.35);
}

.dropdown-trigger .material-design-icon {
    color: var(--color-primary-element-text);
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
    color: var(--color-main-text);
    font-weight: 500;
}

.search-input:focus {
    outline: none;
    border-color: var(--color-primary-element);
    box-shadow: 0 0 0 3px rgba(var(--color-primary-element-rgb), 0.2);
    background: var(--color-main-background);
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
    color: var(--color-main-text);
}

.bulk-action-btn:hover {
    background: var(--color-main-background);
    border-color: var(--color-primary-element);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(var(--color-primary-element-rgb), 0.2);
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
    background: rgba(var(--color-primary-element-rgb), 0.1);
}

.dropdown-item.selected {
    background: rgba(var(--color-success-rgb), 0.15);
    border-left: 4px solid var(--color-success);
}

.checkbox-icon {
    color: var(--color-primary-element);
    flex-shrink: 0;
}

.dropdown-item.selected .checkbox-icon {
    color: var(--color-success);
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
    color: var(--color-main-text);
}

.group-id {
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.group-count {
    font-size: 13px;
    color: var(--color-text-maxcontrast);
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
    color: var(--color-main-text);
    text-align: center;
}

.dropdown-empty .material-design-icon {
    opacity: 0.4;
    margin-bottom: 12px;
    color: var(--color-text-maxcontrast);
}

.dropdown-empty p {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: var(--color-main-text);
}
</style>
