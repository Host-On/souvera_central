<template>
    <div class="modal-overlay" @click.self="$emit('cancel')">
        <div class="confirm-dialog" :class="{ danger }">
            <div class="dialog-icon">
                <span :class="danger ? 'icon-error' : 'icon-info'"></span>
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
export default {
    name: 'ConfirmDialog',

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
    background: #fff;
    border-radius: 12px;
    padding: 30px;
    width: 100%;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.dialog-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 50%;
}

.confirm-dialog.danger .dialog-icon {
    background: rgba(227, 56, 80, 0.1);
}

.dialog-icon [class^='icon-'] {
    font-size: 28px;
}

.confirm-dialog.danger .dialog-icon [class^='icon-'] {
    color: var(--color-error);
}

.confirm-dialog h3 {
    margin: 0 0 12px;
    font-size: 18px;
    font-weight: 600;
    color: #000;
}

.confirm-dialog p {
    margin: 0 0 25px;
    font-size: 14px;
    color: #666;
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
    transition: all 0.2s;
}

.cancel-button {
    background: transparent;
    border: 2px solid rgba(0, 0, 0, 0.2);
    color: #333;
}

.cancel-button:hover {
    background: rgba(0, 0, 0, 0.05);
    border-color: rgba(0, 0, 0, 0.3);
}

.confirm-button {
    background: var(--color-primary);
    border: none;
    color: #fff;
}

.confirm-button:hover {
    background: var(--color-primary-element-light);
}

.confirm-button.danger {
    background: var(--color-error);
}

.confirm-button.danger:hover {
    background: #c0392b;
}
</style>
