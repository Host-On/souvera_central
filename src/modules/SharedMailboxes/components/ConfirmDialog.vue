<template>
    <div class="modal-overlay" @click.self="$emit('cancel')">
        <div class="confirm-dialog" :class="{ danger }">
            <div class="dialog-icon">
                <AlertCircle v-if="danger" :size="28" />
                <InformationOutline v-else :size="28" />
            </div>
            <h3>{{ title }}</h3>
            <p>{{ message }}</p>
            <div class="dialog-actions">
                <button class="cancel-button" @click="$emit('cancel')">
                    {{ cancelText }}
                </button>
                <button class="confirm-button" :class="{ danger }" @click="$emit('confirm')">
                    {{ confirmText }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'

export default {
    name: 'ConfirmDialog',

    components: {
        AlertCircle,
        InformationOutline
    },

    props: {
        title: {
            type: String,
            required: true
        },
        message: {
            type: String,
            required: true
        },
        confirmText: {
            type: String,
            default: 'Bestätigen'
        },
        cancelText: {
            type: String,
            default: 'Abbrechen'
        },
        danger: {
            type: Boolean,
            default: false
        }
    },

    emits: ['confirm', 'cancel']
}
</script>

<style scoped>
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
}

.confirm-dialog {
    background: var(--color-main-background);
    border-radius: 12px;
    padding: 30px;
    width: 100%;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 8px 32px var(--color-box-shadow);
}

.dialog-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-background-dark);
    border-radius: 50%;
    color: var(--color-primary-element);
}

.confirm-dialog.danger .dialog-icon {
    background: rgba(var(--color-error-rgb), 0.1);
}

.confirm-dialog.danger .dialog-icon {
    color: var(--color-error);
}

.confirm-dialog h3 {
    margin: 0 0 12px;
    font-size: 18px;
    font-weight: 600;
    color: var(--color-main-text);
}

.confirm-dialog p {
    margin: 0 0 25px;
    font-size: 14px;
    color: var(--color-text-maxcontrast);
    line-height: 1.5;
}

.dialog-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.cancel-button,
.confirm-button {
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s, border-color 0.2s;
}

.cancel-button {
    background: transparent;
    border: 2px solid var(--color-border);
    color: var(--color-main-text);
}

.cancel-button:hover {
    background: var(--color-background-hover);
    border-color: var(--color-border-maxcontrast);
}

.confirm-button {
    background: var(--color-primary-element);
    border: none;
    color: var(--color-primary-element-text);
}

.confirm-button:hover {
    background: var(--color-primary-element-hover);
}

.confirm-button.danger {
    background: var(--color-error);
}

.confirm-button.danger:hover {
    background: var(--color-error-hover);
}
</style>
