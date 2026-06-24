<template>
    <div v-if="isOpen" class="modal-overlay" @click.self="close">
        <div class="modal-container" :class="typeClass">
            <div class="modal-header">
                <div class="modal-icon" :class="`icon-${type}`">
                    <AlertCircle v-if="type === 'warning'" :size="24" />
                    <AlertOctagon v-else-if="type === 'danger'" :size="24" />
                    <InformationOutline v-else-if="type === 'info'" :size="24" />
                    <CheckCircle v-else :size="24" />
                </div>
                <h2>{{ title }}</h2>
                <button class="close-button" @click="close">
                    <Close :size="20" />
                </button>
            </div>

            <div class="modal-body">
                <p>{{ message }}</p>
                <p v-if="details" class="modal-details">{{ details }}</p>
            </div>

            <div class="modal-footer">
                <button class="button secondary" @click="close">
                    {{ cancelText }}
                </button>
                <button class="button primary" :class="typeClass" @click="confirm">
                    {{ confirmText }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import AlertOctagon from 'vue-material-design-icons/AlertOctagon.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import Close from 'vue-material-design-icons/Close.vue'

export default {
    name: 'ConfirmationModal',

    components: {
        AlertCircle,
        AlertOctagon,
        InformationOutline,
        CheckCircle,
        Close
    },

    props: {
        isOpen: {
            type: Boolean,
            required: true
        },
        title: {
            type: String,
            required: true
        },
        message: {
            type: String,
            required: true
        },
        details: {
            type: String,
            default: ''
        },
        type: {
            type: String,
            default: 'info', // 'info', 'warning', 'danger', 'success'
            validator: (value) => ['info', 'warning', 'danger', 'success'].includes(value)
        },
        confirmText: {
            type: String,
            default: 'Bestätigen'
        },
        cancelText: {
            type: String,
            default: 'Abbrechen'
        }
    },

    emits: ['confirm', 'cancel', 'close'],

    computed: {
        typeClass() {
            return `modal-${this.type}`
        }
    },

    watch: {
        isOpen(newValue) {
            if (newValue) {
                // Verhindere Body-Scrolling wenn Modal offen ist
                document.body.style.overflow = 'hidden'
            } else {
                document.body.style.overflow = ''
            }
        }
    },

    beforeUnmount() {
        // Cleanup: Scrolling wieder aktivieren
        document.body.style.overflow = ''
    },

    methods: {
        confirm() {
            this.$emit('confirm')
            this.close()
        },

        close() {
            this.$emit('close')
            this.$emit('cancel')
        }
    }
}
</script>

<style scoped>
/* Modal Overlay */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Modal Container */
.modal-container {
    background: var(--color-main-background);
    border-radius: var(--border-radius-large);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Modal Header */
.modal-header {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 25px 30px;
    border-bottom: 1px solid var(--color-border);
}

.modal-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    flex-shrink: 0;
    font-size: 24px;
}

.modal-icon.icon-info {
    background: rgba(0, 130, 201, 0.1);
    color: var(--color-primary-element);
}

.modal-icon.icon-warning {
    background: rgba(255, 102, 0, 0.1);
    color: var(--color-warning);
}

.modal-icon.icon-danger {
    background: rgba(232, 17, 35, 0.1);
    color: var(--color-error);
}

.modal-icon.icon-success {
    background: rgba(70, 160, 73, 0.1);
    color: var(--color-success);
}

.modal-header h2 {
    flex: 1;
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--color-main-text);
}

.close-button {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: background 0.2s;
    padding: 0;
    color: var(--color-text-light);
}

.close-button:hover {
    background: var(--color-background-hover);
}

/* Modal Body */
.modal-body {
    padding: 25px 30px;
    overflow-y: auto;
}

.modal-body p {
    margin: 0 0 15px;
    font-size: 15px;
    line-height: 1.6;
    color: var(--color-main-text);
}

.modal-body p:last-child {
    margin-bottom: 0;
}

.modal-details {
    font-size: 14px;
    color: var(--color-text-lighter);
    padding: 15px;
    background: var(--color-background-dark);
    border-radius: var(--border-radius);
    border-left: 3px solid var(--color-border);
}

/* Modal Footer */
.modal-footer {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding: 20px 30px;
    border-top: 1px solid var(--color-border);
    background: var(--color-background-dark);
}

.button {
    padding: 10px 24px;
    border-radius: var(--border-radius);
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: flex;
    align-items: center;
    gap: 8px;
}

.button.secondary {
    background: transparent;
    border: 1px solid var(--color-border);
    color: var(--color-text-light);
}

.button.secondary:hover {
    background: var(--color-background-hover);
}

.button.primary {
    background: var(--color-primary-element);
    color: white;
}

.button.primary:hover {
    background: var(--color-primary-element-hover);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px var(--color-box-shadow);
}

/* Type-specific Button Styles */
.button.primary.modal-danger {
    background: var(--color-error);
}

.button.primary.modal-danger:hover {
    background: var(--color-error);
}

.button.primary.modal-warning {
    background: var(--color-warning);
}

.button.primary.modal-warning:hover {
    background: var(--color-warning);
}

.button.primary.modal-success {
    background: var(--color-success);
}

.button.primary.modal-success:hover {
    background: var(--color-success);
}
</style>
