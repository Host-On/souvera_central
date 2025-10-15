<template>
    <div class="user-editor-page">
        <!-- Header -->
        <div class="editor-header">
            <button class="back-button" @click="$emit('close')">
                <span class="icon-arrow-left"></span>
                {{ t('souvera_central', 'Zurück') }}
            </button>
            <h2>
                {{ isEditMode ? t('souvera_central', 'Benutzer bearbeiten') : t('souvera_central', 'Neuer Benutzer') }}
            </h2>
        </div>

        <!-- Form -->
        <div class="editor-content">
            <form @submit.prevent="saveUser" class="user-form">
                <!-- Benutzername-Feld entfernt: Username wird automatisch aus Email generiert (Backend) -->

                <!-- Anzeigename -->
                <div class="form-group">
                    <label for="displayName" class="required">
                        {{ t('souvera_central', 'Anzeigename') }}
                    </label>
                    <input
                        id="displayName"
                        v-model="formData.displayName"
                        type="text"
                        :class="{ error: errors.displayName }"
                        @input="validateDisplayName"
                        required
                    />
                    <p v-if="errors.displayName" class="error-message">{{ errors.displayName }}</p>
                </div>

                <!-- E-Mail -->
                <div class="form-group">
                    <label for="email" class="required">
                        {{ t('souvera_central', 'E-Mail') }}
                    </label>

                    <!-- Email mit Domain Dropdown wenn Domains konfiguriert sind -->
                    <div v-if="allowedDomains.length > 0" class="email-input-group">
                        <input
                            id="emailLocalPart"
                            v-model="emailLocalPart"
                            type="text"
                            class="email-local-part"
                            :class="{ error: errors.email }"
                            @input="updateFullEmail"
                            placeholder="benutzername"
                            required
                        />
                        <span class="email-separator">@</span>
                        <select
                            v-model="emailDomain"
                            class="email-domain-select"
                            :class="{ error: errors.email }"
                            @change="updateFullEmail"
                            required
                        >
                            <option value="">{{ t('souvera_central', 'Domain wählen...') }}</option>
                            <option v-for="domain in allowedDomains" :key="domain" :value="domain">
                                {{ domain }}
                            </option>
                        </select>
                    </div>

                    <!-- Normale Email-Eingabe wenn keine Domains konfiguriert -->
                    <input
                        v-else
                        id="email"
                        v-model="formData.email"
                        type="email"
                        :class="{ error: errors.email }"
                        @input="validateEmail"
                        required
                    />

                    <p v-if="errors.email" class="error-message">{{ errors.email }}</p>
                    <p
                        v-else-if="formData.email && !errors.email && allowedDomains.length === 0"
                        class="success-message"
                    >
                        <span class="icon-checkmark"></span>
                        {{ t('souvera_central', 'E-Mail-Adresse ist gültig') }}
                    </p>
                </div>

                <!-- Passwort (nur bei neuem User) -->
                <div v-if="!isEditMode" class="form-group">
                    <label for="password" class="required">
                        {{ t('souvera_central', 'Passwort') }}
                    </label>
                    <input
                        id="password"
                        v-model="formData.password"
                        type="password"
                        :class="{ error: errors.password }"
                        @input="validatePassword"
                        required
                    />
                    <p v-if="errors.password" class="error-message">{{ errors.password }}</p>
                    <p v-else class="help-text">{{ t('souvera_central', 'Mindestens 10 Zeichen') }}</p>
                </div>

                <!-- Gruppen -->
                <div class="form-group">
                    <label for="groups">
                        {{ t('souvera_central', 'Mitglied der folgenden Gruppen') }}
                    </label>
                    <p class="help-text">{{ t('souvera_central', 'Kontengruppen setzen') }}</p>
                    <GroupSelector
                        v-model="formData.groups"
                        :available-groups="availableGroups"
                        mode="member"
                    />
                </div>

                <!-- Gruppen-Administration -->
                <div class="form-group">
                    <label for="adminGroups">
                        {{ t('souvera_central', 'Administration der folgenden Gruppen') }}
                    </label>
                    <p class="help-text">{{ t('souvera_central', 'Konto als Administration setzen für …') }}</p>
                    <GroupSelector
                        v-model="formData.adminGroups"
                        :available-groups="availableGroups"
                        mode="admin"
                    />
                </div>

                <!-- Speicherplatz Quota -->
                <div class="form-group">
                    <label for="quota">
                        {{ t('souvera_central', 'Kontingent') }}
                    </label>
                    <p class="help-text">{{ t('souvera_central', 'Standard Speicherkontingent') }}</p>
                    <select id="quota" v-model="formData.quota">
                        <option value="default">{{ t('souvera_central', 'Standard') }}</option>
                        <option value="1 GB">1 GB</option>
                        <option value="5 GB">5 GB</option>
                        <option value="10 GB">10 GB</option>
                        <option value="50 GB">50 GB</option>
                        <option value="100 GB">100 GB</option>
                        <option value="none">{{ t('souvera_central', 'Unbegrenzt') }}</option>
                    </select>
                </div>

                <!-- Manager -->
                <div class="form-group">
                    <label for="manager">
                        {{ t('souvera_central', 'Manager') }}
                    </label>
                    <p class="help-text">{{ t('souvera_central', 'Manager festlegen') }}</p>
                    <ManagerSelector v-model="formData.manager" :initial-manager="initialManagerData" />
                </div>

                <!-- Aktiv/Deaktiviert -->
                <div class="form-group checkbox-group">
                    <input id="enabled" v-model="formData.enabled" type="checkbox" />
                    <label for="enabled">
                        {{ t('souvera_central', 'Benutzer aktiviert') }}
                    </label>
                </div>

                <!-- Danger Zone (nur im Edit-Mode) -->
                <div v-if="isEditMode" class="danger-zone">
                    <h3>{{ t('souvera_central', 'Erweiterte Aktionen') }}</h3>
                    <div class="danger-actions">
                        <button
                            type="button"
                            class="action-button secondary"
                            @click="resendWelcomeEmail"
                            :disabled="resendingEmail"
                        >
                            <span v-if="resendingEmail" class="icon-loading-small"></span>
                            <span v-else class="icon-mail"></span>
                            {{
                                resendingEmail
                                    ? t('souvera_central', 'Sendet...')
                                    : t('souvera_central', 'Willkommens-Email erneut versenden')
                            }}
                        </button>

                        <button
                            type="button"
                            class="action-button danger"
                            @click="wipeDevices"
                            :disabled="wipingDevices"
                        >
                            <span v-if="wipingDevices" class="icon-loading-small"></span>
                            <span v-else class="icon-delete"></span>
                            {{
                                wipingDevices
                                    ? t('souvera_central', 'Trennt...')
                                    : t('souvera_central', 'Alle Geräte trennen & Daten löschen')
                            }}
                        </button>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="secondary" @click="$emit('close')">
                        {{ t('souvera_central', 'Abbrechen') }}
                    </button>
                    <button type="submit" class="primary" :disabled="!isFormValid || saving">
                        <span v-if="saving" class="icon-loading-small"></span>
                        <template v-if="isEditMode">
                            {{ saving ? t('souvera_central', 'Speichert...') : t('souvera_central', 'Speichern') }}
                        </template>
                        <template v-else>
                            {{
                                saving
                                    ? t('souvera_central', 'Erstellt...')
                                    : t('souvera_central', 'Benutzer erstellen')
                            }}
                        </template>
                    </button>
                </div>
            </form>
        </div>

        <!-- Confirmation Modal -->
        <ConfirmationModal
            :is-open="confirmModal.isOpen"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :details="confirmModal.details"
            :type="confirmModal.type"
            :confirm-text="confirmModal.confirmText"
            :cancel-text="confirmModal.cancelText"
            @confirm="confirmModal.onConfirm"
            @close="closeConfirmModal"
        />
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import ManagerSelector from './ManagerSelector.vue'
import GroupSelector from './GroupSelector.vue'
import ConfirmationModal from '../../../components/ConfirmationModal.vue'

export default {
    name: 'UserEditor',

    components: {
        ManagerSelector,
        GroupSelector,
        ConfirmationModal
    },

    props: {
        user: {
            type: Object,
            default: null
        },
        allowedDomains: {
            type: Array,
            default: () => []
        }
    },

    emits: ['close', 'saved'],

    data() {
        return {
            formData: {
                displayName: '',
                email: '',
                password: '',
                groups: [],
                adminGroups: [],
                quota: 'default',
                manager: '',
                enabled: true
            },
            emailLocalPart: '',
            emailDomain: '',
            errors: {
                displayName: null,
                email: null,
                password: null
            },
            validating: {
                email: false
            },
            availableGroups: [],
            saving: false,
            resendingEmail: false,
            wipingDevices: false,
            initialManagerData: null,
            settings: {
                defaults: {
                    quota: 'default'
                },
                email: {
                    send_to_new_users: false
                }
            },
            confirmModal: {
                isOpen: false,
                title: '',
                message: '',
                details: '',
                type: 'info',
                confirmText: 'Bestätigen',
                cancelText: 'Abbrechen',
                onConfirm: () => {}
            }
        }
    },

    computed: {
        isEditMode() {
            return this.user !== null
        },

        isFormValid() {
            return (
                this.formData.displayName &&
                this.formData.email &&
                (!this.isEditMode ? this.formData.password : true) &&
                !this.errors.displayName &&
                !this.errors.email &&
                !this.errors.password
            )
        }
    },

    mounted() {
        this.loadGroups()
        this.loadSettings()

        if (this.isEditMode) {
            this.formData = {
                displayName: this.user.displayName,
                email: this.user.email,
                password: '',
                groups: this.user.groups.map((g) => g.id),
                adminGroups: [],
                quota: this.user.quota.quota,
                manager: this.user.manager || '',
                enabled: this.user.enabled
            }

            // Manager-Daten für ManagerSelector vorbereiten
            if (this.user.manager) {
                this.loadManagerData(this.user.manager)
            }

            // Parse Email für Dropdown
            if (this.user.email && this.allowedDomains.length > 0) {
                const parts = this.user.email.split('@')
                if (parts.length === 2) {
                    this.emailLocalPart = parts[0]
                    this.emailDomain = parts[1]
                }
            }
        } else {
            // Bei neuem User: Settings laden und Default-Quota setzen
            this.loadSettings().then(() => {
                this.formData.quota = this.settings.defaults.quota
            })

            // Erste Domain vorauswählen
            if (this.allowedDomains.length > 0) {
                this.emailDomain = this.allowedDomains[0]
            }
        }
    },

    methods: {
        t,

        async loadGroups() {
            try {
                const url = generateUrl('/apps/souvera_central/api/groups')
                const response = await axios.get(url)
                const groups =
                    response.data.ocs?.data?.groups || response.data.data?.groups || response.data.groups || []
                this.availableGroups = groups
            } catch (error) {
                console.error('Fehler beim Laden der Gruppen:', error)
                // Fallback
                this.availableGroups = []
            }
        },

        async loadSettings() {
            try {
                const url = generateUrl('/apps/souvera_central/api/settings')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data

                if (data) {
                    this.settings = {
                        defaults: data.defaults || this.settings.defaults,
                        email: data.email || this.settings.email
                    }
                }
            } catch (error) {
                console.error('Fehler beim Laden der Einstellungen:', error)
            }
        },

        updateFullEmail() {
            if (this.emailLocalPart && this.emailDomain) {
                this.formData.email = `${this.emailLocalPart}@${this.emailDomain}`
                this.validateEmail()
            } else {
                this.formData.email = ''
            }
        },

        validateDisplayName() {
            this.errors.displayName = null

            if (!this.formData.displayName) {
                this.errors.displayName = this.t('souvera_central', 'Anzeigename ist erforderlich')
            }
        },

        validateEmail() {
            this.errors.email = null

            if (!this.formData.email) {
                this.errors.email = this.t('souvera_central', 'E-Mail ist erforderlich')
                return
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
            if (!emailRegex.test(this.formData.email)) {
                this.errors.email = this.t('souvera_central', 'Ungültige E-Mail-Adresse')
            }
        },

        async validateEmailDomain() {
            if (this.errors.email || !this.formData.email) {
                return
            }

            this.validating.email = true

            try {
                // TODO: Call domain validation API
                await new Promise((resolve) => setTimeout(resolve, 500))

                // Simulated validation
                const domain = this.formData.email.split('@')[1]
                console.log('Validiere Domain:', domain)

                // TODO: Gegen Nextcloud Config oder externe API validieren
            } catch (error) {
                this.errors.email = this.t('souvera_central', 'Domain-Validierung fehlgeschlagen')
            } finally {
                this.validating.email = false
            }
        },

        validatePassword() {
            this.errors.password = null

            if (!this.formData.password && !this.isEditMode) {
                this.errors.password = this.t('souvera_central', 'Passwort ist erforderlich')
                return
            }

            if (this.formData.password && this.formData.password.length < 10) {
                this.errors.password = this.t('souvera_central', 'Passwort muss mindestens 10 Zeichen lang sein')
            }
        },

        async saveUser() {
            // Validate all fields
            this.validateDisplayName()
            this.validateEmail()
            this.validatePassword()

            if (!this.isFormValid) {
                console.warn('Form validation failed')
                return
            }

            this.saving = true

            try {
                if (this.isEditMode) {
                    // Update existing user
                    // Username wird aus user.id genommen (wurde bei Erstellung aus Email generiert)
                    const url = generateUrl('/apps/souvera_central/api/users/{id}', { id: this.user.id })

                    console.log('=== UPDATE USER REQUEST ===')
                    console.log('URL:', url)
                    console.log('Payload:', {
                        displayName: this.formData.displayName,
                        email: this.formData.email,
                        groups: this.formData.groups,
                        quota: this.formData.quota,
                        enabled: this.formData.enabled
                    })

                    const response = await axios.put(url, {
                        displayName: this.formData.displayName,
                        email: this.formData.email,
                        groups: this.formData.groups,
                        quota: this.formData.quota,
                        enabled: this.formData.enabled,
                        manager: this.formData.manager
                    })

                    console.log('=== UPDATE USER RESPONSE ===')
                    console.log('Status:', response.status)
                    console.log('Data:', response.data)
                } else {
                    // Create new user
                    // Username wird vom Backend automatisch aus Email generiert
                    const url = generateUrl('/apps/souvera_central/api/users')
                    const payload = {
                        username: this.formData.email, // Backend setzt username = email automatisch
                        displayName: this.formData.displayName,
                        email: this.formData.email,
                        password: this.formData.password,
                        groups: this.formData.groups,
                        quota: this.formData.quota,
                        enabled: this.formData.enabled,
                        manager: this.formData.manager
                    }

                    console.log('=== CREATE USER REQUEST ===')
                    console.log('URL:', url)
                    console.log('Payload:', payload)
                    console.log('Payload JSON:', JSON.stringify(payload))

                    const response = await axios.post(url, payload)

                    console.log('=== CREATE USER RESPONSE ===')
                    console.log('Status:', response.status)
                    console.log('Headers:', response.headers)
                    console.log('Data:', response.data)
                    console.log('Full Response:', response)

                    // Auto-Email senden wenn aktiviert
                    if (this.settings.email.send_to_new_users) {
                        try {
                            // Username ist Email (vom Backend gesetzt)
                            const emailUrl = generateUrl('/apps/souvera_central/api/users/{id}/resend-welcome-email', {
                                id: this.formData.email
                            })
                            await axios.post(emailUrl)
                            console.log('Willkommens-Email automatisch versendet an:', this.formData.email)
                        } catch (emailError) {
                            console.warn('Fehler beim automatischen Versenden der Willkommens-Email:', emailError)
                            // Nicht blockieren, nur warnen
                        }
                    }
                }

                this.$emit('saved')
                this.$emit('close')
            } catch (error) {
                console.error('=== ERROR beim Speichern ===')
                console.error('Error object:', error)
                console.error('Error response:', error.response)
                console.error('Error response data:', error.response?.data)
                console.error('Error response status:', error.response?.status)
                console.error('Error response headers:', error.response?.headers)

                // Zeige Fehlermeldung
                let errorMessage = this.t('souvera_central', 'Fehler beim Speichern')
                let debugInfo = ''

                if (error.response?.data?.ocs?.data?.error) {
                    errorMessage = error.response.data.ocs.data.error
                    debugInfo = JSON.stringify(error.response.data.ocs.data.debug || {})
                } else if (error.response?.data?.error) {
                    errorMessage = error.response.data.error
                    debugInfo = JSON.stringify(error.response.data.debug || {})
                }

                console.error('Error message:', errorMessage)
                console.error('Debug info:', debugInfo)

                alert(errorMessage + (debugInfo ? '\n\nDebug: ' + debugInfo : '')) // TODO: Bessere Error-UI
            } finally {
                this.saving = false
            }
        },

        async loadManagerData(managerId) {
            try {
                const url = generateUrl('/apps/souvera_central/api/users/{id}', { id: managerId })
                const response = await axios.get(url)
                const userData = response.data.ocs?.data || response.data.data || response.data

                if (userData) {
                    this.initialManagerData = {
                        id: userData.id,
                        displayName: userData.displayName,
                        email: userData.email || ''
                    }
                }
            } catch (error) {
                console.error('Fehler beim Laden der Manager-Daten:', error)
            }
        },

        resendWelcomeEmail() {
            this.confirmModal = {
                isOpen: true,
                title: this.t('souvera_central', 'Willkommens-Email versenden?'),
                message: this.t(
                    'souvera_central',
                    'Möchten Sie die Willkommens-Email an "{user}" wirklich erneut versenden?',
                    { user: this.formData.displayName }
                ),
                details: this.t('souvera_central', 'Die E-Mail wird an: {email} gesendet', {
                    email: this.formData.email
                }),
                type: 'info',
                confirmText: this.t('souvera_central', 'Email senden'),
                cancelText: this.t('souvera_central', 'Abbrechen'),
                onConfirm: async () => {
                    this.resendingEmail = true

                    try {
                        // Username ist Email (im Edit-Mode ist user.id = Email)
                        const url = generateUrl('/apps/souvera_central/api/users/{id}/resend-welcome-email', {
                            id: this.user.id
                        })
                        await axios.post(url)

                        // Success Modal
                        this.confirmModal = {
                            isOpen: true,
                            title: this.t('souvera_central', 'Email versendet!'),
                            message: this.t('souvera_central', 'Die Willkommens-Email wurde erfolgreich versendet.'),
                            details: this.t('souvera_central', 'Die E-Mail wurde an {email} gesendet', {
                                email: this.formData.email
                            }),
                            type: 'success',
                            confirmText: this.t('souvera_central', 'OK'),
                            cancelText: '',
                            onConfirm: () => {
                                this.closeConfirmModal()
                            }
                        }
                    } catch (error) {
                        console.error('Fehler beim Versenden der Willkommens-Email:', error)
                        const errorMessage =
                            error.response?.data?.ocs?.data?.error ||
                            error.response?.data?.error ||
                            this.t('souvera_central', 'Fehler beim Versenden der E-Mail')

                        // Error Modal
                        this.confirmModal = {
                            isOpen: true,
                            title: this.t('souvera_central', 'Fehler beim Versenden'),
                            message: errorMessage,
                            details: '',
                            type: 'danger',
                            confirmText: this.t('souvera_central', 'OK'),
                            cancelText: '',
                            onConfirm: () => {
                                this.closeConfirmModal()
                            }
                        }
                    } finally {
                        this.resendingEmail = false
                    }
                }
            }
        },

        wipeDevices() {
            this.confirmModal = {
                isOpen: true,
                title: this.t('souvera_central', 'Alle Geräte trennen?'),
                message: this.t(
                    'souvera_central',
                    'Möchten Sie wirklich ALLE Geräte von "{user}" trennen und lokale Daten löschen?',
                    { user: this.formData.displayName }
                ),
                details: this.t(
                    'souvera_central',
                    'WARNUNG: Der Benutzer wird auf allen Geräten abgemeldet und muss sich überall neu anmelden. Diese Aktion kann nicht rückgängig gemacht werden!'
                ),
                type: 'danger',
                confirmText: this.t('souvera_central', 'Ja, alle Geräte trennen'),
                cancelText: this.t('souvera_central', 'Abbrechen'),
                onConfirm: async () => {
                    this.wipingDevices = true

                    try {
                        // Username ist Email (im Edit-Mode ist user.id = Email)
                        const url = generateUrl('/apps/souvera_central/api/users/{id}/wipe-devices', {
                            id: this.user.id
                        })
                        await axios.post(url)

                        // Success Modal
                        this.confirmModal = {
                            isOpen: true,
                            title: this.t('souvera_central', 'Geräte getrennt!'),
                            message: this.t('souvera_central', 'Alle Geräte wurden erfolgreich getrennt.'),
                            details: this.t(
                                'souvera_central',
                                'Der Benutzer "{user}" wurde auf allen Geräten abgemeldet.',
                                { user: this.formData.displayName }
                            ),
                            type: 'success',
                            confirmText: this.t('souvera_central', 'OK'),
                            cancelText: '',
                            onConfirm: () => {
                                this.closeConfirmModal()
                            }
                        }
                    } catch (error) {
                        console.error('Fehler beim Trennen der Geräte:', error)
                        const errorMessage =
                            error.response?.data?.ocs?.data?.error ||
                            error.response?.data?.error ||
                            this.t('souvera_central', 'Fehler beim Trennen der Geräte')

                        // Error Modal
                        this.confirmModal = {
                            isOpen: true,
                            title: this.t('souvera_central', 'Fehler'),
                            message: errorMessage,
                            details: '',
                            type: 'danger',
                            confirmText: this.t('souvera_central', 'OK'),
                            cancelText: '',
                            onConfirm: () => {
                                this.closeConfirmModal()
                            }
                        }
                    } finally {
                        this.wipingDevices = false
                    }
                }
            }
        },

        closeConfirmModal() {
            this.confirmModal.isOpen = false
        }
    }
}
</script>

<style scoped>
.user-editor-page {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    background: var(--color-main-background);
}

/* Header */
.editor-header {
    padding: 20px 30px;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    gap: 20px;
}

.back-button {
    display: flex;
    align-items: center;
    gap: 8px;
    background: none;
    border: none;
    color: var(--color-primary);
    cursor: pointer;
    font-size: 14px;
    padding: 8px 12px;
    border-radius: var(--border-radius);
    transition: background 0.2s;
}

.back-button:hover {
    background: var(--color-background-hover);
}

.editor-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

/* Content */
.editor-content {
    flex: 1;
    overflow-y: auto;
    padding: 30px;
}

.user-form {
    max-width: 700px;
    margin: 0 auto;
}

/* Form Groups */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 8px;
    color: var(--color-text-light);
}

.form-group label.required::after {
    content: ' *';
    color: var(--color-error);
}

.form-group input[type='text'],
.form-group input[type='email'],
.form-group input[type='password'],
.form-group select {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    font-size: 15px;
    line-height: 1.5;
    height: 46px;
    box-sizing: border-box;
    transition: border-color 0.2s;
    background: var(--color-main-background);
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--color-primary);
}

.form-group input.error {
    border-color: var(--color-error);
}

.form-group input.success {
    border-color: var(--color-success);
}

.form-group input:disabled {
    background: var(--color-background-dark);
    cursor: not-allowed;
}

/* Input with Icon (for validation feedback) */
.input-with-icon {
    position: relative;
    display: flex;
    align-items: center;
}

.input-with-icon input {
    flex: 1;
    padding-right: 40px;
}

.input-icon {
    position: absolute;
    right: 12px;
    pointer-events: none;
}

.input-icon.success-icon {
    color: var(--color-success);
    opacity: 1;
}

/* Email Input Group */
.email-input-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.email-local-part {
    flex: 1;
    min-width: 0;
    height: 46px;
    box-sizing: border-box;
}

.email-separator {
    font-weight: 600;
    color: var(--color-text-lighter);
}

.email-domain-select {
    flex: 1;
    min-width: 0;
    padding: 12px 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 15px;
    line-height: 1.5;
    height: 46px;
    cursor: pointer;
    box-sizing: border-box;
}

.email-domain-select:focus {
    outline: none;
    border-color: var(--color-primary);
}

.email-domain-select.error {
    border-color: var(--color-error);
}

/* Messages */
.help-text {
    margin: 6px 0 0;
    font-size: 13px;
    color: var(--color-text-lighter);
}

.error-message {
    margin: 6px 0 0;
    font-size: 13px;
    color: var(--color-error);
    display: flex;
    align-items: center;
    gap: 5px;
}

.success-message {
    margin: 6px 0 0;
    font-size: 13px;
    color: var(--color-success);
    display: flex;
    align-items: center;
    gap: 5px;
}

.validating-message {
    margin: 6px 0 0;
    font-size: 13px;
    color: var(--color-text-lighter);
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Checkbox Group */
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-group input[type='checkbox'] {
    margin: 0;
}

.checkbox-group label {
    margin: 0;
    cursor: pointer;
    font-weight: normal;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--color-border);
}

.form-actions button {
    padding: 10px 24px;
    border-radius: var(--border-radius);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-actions button.primary {
    background: var(--color-primary);
    color: white;
    border: none;
}

.form-actions button.primary:hover:not(:disabled) {
    background: var(--color-primary-element-light);
}

.form-actions button.primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.form-actions button.secondary {
    background: transparent;
    border: 1px solid var(--color-border);
    color: var(--color-text-light);
}

.form-actions button.secondary:hover {
    background: var(--color-background-hover);
}

/* Danger Zone */
.danger-zone {
    margin-top: 40px;
    padding: 25px;
    border: 2px solid var(--color-border);
    border-radius: var(--border-radius-large);
    background: var(--color-background-dark);
}

.danger-zone h3 {
    margin: 0 0 15px;
    font-size: 18px;
    font-weight: 600;
    color: var(--color-text-light);
}

.danger-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.action-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px 20px;
    border-radius: var(--border-radius);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
    border: none;
}

.action-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.action-button.secondary {
    background: var(--color-primary);
    color: white;
}

.action-button.secondary:hover:not(:disabled) {
    background: var(--color-primary-element-light);
    transform: translateY(-2px);
    box-shadow: 0 2px 8px var(--color-box-shadow);
}

.action-button.danger {
    background: var(--color-error);
    color: white;
}

.action-button.danger:hover:not(:disabled) {
    background: #c9302c;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px var(--color-box-shadow);
}
</style>
