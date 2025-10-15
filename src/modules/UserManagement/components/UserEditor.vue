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
                <!-- Benutzername -->
                <div class="form-group">
                    <label for="username" class="required">
                        {{ t('souvera_central', 'Benutzername') }}
                    </label>
                    <div class="input-with-icon">
                        <input
                            id="username"
                            v-model="formData.username"
                            type="text"
                            :disabled="isEditMode"
                            :class="{
                                error: errors.username,
                                success: formData.username && !isEditMode && !errors.username && !validating.username
                            }"
                            @input="debouncedUsernameCheck"
                            @blur="validateUsernameNow"
                            required
                        />
                        <span v-if="validating.username" class="input-icon icon-loading-small"></span>
                        <span
                            v-else-if="formData.username && !isEditMode && !errors.username"
                            class="input-icon icon-checkmark success-icon"
                        ></span>
                    </div>
                    <p v-if="errors.username" class="error-message">{{ errors.username }}</p>
                    <p v-else class="help-text">
                        {{ t('souvera_central', 'Eindeutiger Benutzername, kann später nicht geändert werden') }}
                    </p>
                </div>

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
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import ManagerSelector from './ManagerSelector.vue'
import GroupSelector from './GroupSelector.vue'

export default {
    name: 'UserEditor',

    components: {
        ManagerSelector,
        GroupSelector
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
                username: '',
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
                username: null,
                displayName: null,
                email: null,
                password: null
            },
            validating: {
                username: false,
                email: false
            },
            availableGroups: [],
            saving: false,
            resendingEmail: false,
            wipingDevices: false,
            usernameCheckTimeout: null,
            initialManagerData: null
        }
    },

    computed: {
        isEditMode() {
            return this.user !== null
        },

        isFormValid() {
            return (
                this.formData.username &&
                this.formData.displayName &&
                this.formData.email &&
                (!this.isEditMode ? this.formData.password : true) &&
                !this.errors.username &&
                !this.errors.displayName &&
                !this.errors.email &&
                !this.errors.password
            )
        }
    },

    mounted() {
        this.loadGroups()

        if (this.isEditMode) {
            this.formData = {
                username: this.user.id,
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
        } else if (this.allowedDomains.length > 0) {
            // Bei neuem User erste Domain vorauswählen
            this.emailDomain = this.allowedDomains[0]
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

        updateFullEmail() {
            if (this.emailLocalPart && this.emailDomain) {
                this.formData.email = `${this.emailLocalPart}@${this.emailDomain}`
                this.validateEmail()
            } else {
                this.formData.email = ''
            }
        },

        debouncedUsernameCheck() {
            // Clear previous timeout
            if (this.usernameCheckTimeout) {
                clearTimeout(this.usernameCheckTimeout)
            }

            // Einfache Validierung sofort
            this.errors.username = null
            if (!this.formData.username) {
                this.errors.username = this.t('souvera_central', 'Benutzername ist erforderlich')
                return
            }

            if (this.formData.username.length < 3) {
                this.errors.username = this.t('souvera_central', 'Benutzername muss mindestens 3 Zeichen lang sein')
                return
            }

            // API-Check mit delay
            this.usernameCheckTimeout = setTimeout(() => {
                this.validateUsernameNow()
            }, 500)
        },

        async validateUsernameNow() {
            if (this.isEditMode) {
                return
            }

            if (!this.formData.username || this.formData.username.length < 3) {
                this.validating.username = false
                return
            }

            this.validating.username = true
            this.errors.username = null

            try {
                const url = generateUrl('/apps/souvera_central/api/users/{id}', { id: this.formData.username })
                console.log('Checking username availability:', this.formData.username, 'URL:', url)
                const response = await axios.get(url)
                console.log('Username check response:', response.data)

                // OCS API: HTTP status ist immer 200, aber meta.statuscode zeigt echten Status
                const ocsStatusCode = response.data?.ocs?.meta?.statuscode
                console.log('OCS Status Code:', ocsStatusCode)

                if (ocsStatusCode === 100) {
                    // User gefunden (statuscode 100 = OK) = Username bereits vergeben
                    console.log('Username taken!')
                    this.errors.username = this.t('souvera_central', 'Benutzername bereits vergeben')
                } else if (ocsStatusCode === 404) {
                    // User nicht gefunden = Username verfügbar ✓
                    console.log('Username available!')
                    this.errors.username = null
                }
            } catch (error) {
                console.log('Username check error:', error)
                // Bei echtem HTTP-Fehler (Netzwerk, Server down etc.)
                if (error.response?.status === 404) {
                    // Username verfügbar
                    console.log('Username available (404)!')
                    this.errors.username = null
                } else {
                    console.error('Fehler bei Username-Prüfung:', error)
                }
            } finally {
                this.validating.username = false
            }
        },

        validateUsername() {
            this.errors.username = null

            if (!this.formData.username) {
                this.errors.username = this.t('souvera_central', 'Benutzername ist erforderlich')
                return
            }

            if (this.formData.username.length < 3) {
                this.errors.username = this.t('souvera_central', 'Benutzername muss mindestens 3 Zeichen lang sein')
                return
            }

            if (!/^[a-zA-Z0-9_-]+$/.test(this.formData.username)) {
                this.errors.username = this.t('souvera_central', 'Nur Buchstaben, Zahlen, _ und - erlaubt')
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
            this.validateUsername()
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
                    const url = generateUrl('/apps/souvera_central/api/users/{id}', { id: this.formData.username })

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
                    const url = generateUrl('/apps/souvera_central/api/users')
                    const payload = {
                        username: this.formData.username,
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

        async resendWelcomeEmail() {
            if (!confirm(this.t('souvera_central', 'Möchten Sie die Willkommens-Email wirklich erneut versenden?'))) {
                return
            }

            this.resendingEmail = true

            try {
                const url = generateUrl('/apps/souvera_central/api/users/{id}/resend-welcome-email', {
                    id: this.formData.username
                })
                await axios.post(url)

                alert(this.t('souvera_central', 'Willkommens-Email wurde erfolgreich versendet'))
            } catch (error) {
                console.error('Fehler beim Versenden der Willkommens-Email:', error)
                const errorMessage =
                    error.response?.data?.ocs?.data?.error ||
                    error.response?.data?.error ||
                    this.t('souvera_central', 'Fehler beim Versenden der E-Mail')
                alert(errorMessage)
            } finally {
                this.resendingEmail = false
            }
        },

        async wipeDevices() {
            const confirmed = confirm(
                this.t(
                    'souvera_central',
                    'WARNUNG: Diese Aktion trennt ALLE Geräte dieses Benutzers und löscht lokale Daten. Der Benutzer muss sich überall neu anmelden. Fortfahren?'
                )
            )

            if (!confirmed) {
                return
            }

            this.wipingDevices = true

            try {
                const url = generateUrl('/apps/souvera_central/api/users/{id}/wipe-devices', {
                    id: this.formData.username
                })
                await axios.post(url)

                alert(this.t('souvera_central', 'Alle Geräte wurden erfolgreich getrennt'))
            } catch (error) {
                console.error('Fehler beim Trennen der Geräte:', error)
                const errorMessage =
                    error.response?.data?.ocs?.data?.error ||
                    error.response?.data?.error ||
                    this.t('souvera_central', 'Fehler beim Trennen der Geräte')
                alert(errorMessage)
            } finally {
                this.wipingDevices = false
            }
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
