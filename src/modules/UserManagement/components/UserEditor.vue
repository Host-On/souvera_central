<template>
    <div class="user-editor-page">
        <!-- Header -->
        <div class="editor-header">
            <div class="header-left">
                <button class="back-button" @click="$emit('close')">
                    <span class="icon-history"></span>
                    {{ t('souvera_central', 'Zurück zur Übersicht') }}
                </button>
                <h2>
                    {{
                        isEditMode
                            ? t('souvera_central', 'Benutzer bearbeiten')
                            : t('souvera_central', 'Neuer Benutzer')
                    }}
                </h2>
            </div>
        </div>

        <!-- Form -->
        <div class="editor-content">
            <form class="user-form" @submit.prevent="saveUser">
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
                        required
                        @input="validateDisplayName"
                    />
                    <p v-if="errors.displayName" class="error-message">{{ errors.displayName }}</p>
                </div>

                <!-- E-Mail -->
                <div class="form-group">
                    <label for="email" class="required">
                        {{ t('souvera_central', 'E-Mail') }}
                    </label>

                    <!-- Email mit Domain Dropdown wenn Domains konfiguriert sind -->
                    <div v-if="allowedDomains.length > 0 && !isEditMode" class="email-input-group">
                        <input
                            id="emailLocalPart"
                            v-model="emailLocalPart"
                            type="text"
                            class="email-local-part"
                            :class="{ error: errors.email }"
                            placeholder="benutzername"
                            required
                            @input="updateFullEmail"
                        />
                        <span class="email-separator">@</span>
                        <select
                            v-model="emailDomain"
                            class="email-domain-select"
                            :class="{ error: errors.email }"
                            required
                            @change="updateFullEmail"
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
                        :disabled="isEditMode"
                        :required="!isEditMode"
                        @input="validateEmail"
                    />

                    <p v-if="errors.email" class="error-message">{{ errors.email }}</p>
                    <p
                        v-else-if="formData.email && !errors.email && !isEditMode && allowedDomains.length === 0"
                        class="success-message"
                    >
                        <span class="icon-checkmark"></span>
                        {{ t('souvera_central', 'E-Mail-Adresse ist gültig') }}
                    </p>
                    <p v-if="isEditMode" class="help-text">
                        {{ t('souvera_central', 'E-Mail-Adresse kann nach der Erstellung nicht geändert werden') }}
                    </p>
                </div>

                <!-- Email-Aliase (nur im Edit-Mode) -->
                <AliasManager
                    v-if="isEditMode"
                    :user-id="user.id"
                    :primary-email="user.email"
                    :allowed-domains="allowedDomains"
                />

                <!-- Passwort -->
                <div class="form-group">
                    <label for="password" :class="{ required: !isEditMode }">
                        {{ t('souvera_central', 'Passwort') }}
                    </label>
                    <input
                        id="password"
                        v-model="formData.password"
                        type="password"
                        :class="{ error: errors.password }"
                        :required="!isEditMode"
                        @input="validatePassword"
                    />
                    <p v-if="errors.password" class="error-message">{{ errors.password }}</p>
                    <p v-else class="help-text">
                        {{
                            isEditMode
                                ? t('souvera_central', 'Mindestens 10 Zeichen. Leer lassen, um Passwort nicht zu ändern.')
                                : t('souvera_central', 'Mindestens 10 Zeichen')
                        }}
                    </p>
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

                <!-- Aktiv/Deaktiviert (nur Anzeige, Änderung über Button unten) -->
                <div v-if="isEditMode" class="form-group">
                    <label>{{ t('souvera_central', 'Status') }}</label>
                    <div class="status-display">
                        <span :class="['status-icon', formData.enabled ? 'icon-checkmark-color' : 'icon-close']"></span>
                        <span :class="['status-text', formData.enabled ? 'status-active' : 'status-inactive']">
                            {{ formData.enabled ? t('souvera_central', 'Aktiv') : t('souvera_central', 'Inaktiv') }}
                        </span>
                    </div>
                    <p class="help-text">
                        {{ t('souvera_central', 'Status kann über "Erweiterte Aktionen" geändert werden') }}
                    </p>
                </div>

                <!-- Gruppen Zone (Collapsible) -->
                <div class="groups-zone collapsible">
                    <button type="button" class="collapsible-header" @click="groupsExpanded = !groupsExpanded">
                        <span :class="groupsExpanded ? 'icon-triangle-s' : 'icon-triangle-e'"></span>
                        <h3>{{ t('souvera_central', 'Gruppenzugehörigkeit') }}</h3>
                        <span class="optional-badge">{{ t('souvera_central', 'Optional') }}</span>
                    </button>

                    <div v-if="groupsExpanded" class="collapsible-content">
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
                                :is-admin-user="user && user.id === 'admin'"
                                mode="admin"
                            />
                        </div>
                    </div>
                </div>

                <!-- Danger Zone (nur im Edit-Mode) -->
                <div v-if="isEditMode" class="danger-zone">
                    <h3>{{ t('souvera_central', 'Erweiterte Aktionen') }}</h3>
                    <div class="danger-actions">
                        <button
                            v-if="user.id !== 'admin'"
                            type="button"
                            :class="['action-button', formData.enabled ? 'warning' : 'success']"
                            :disabled="togglingStatus"
                            @click="toggleUserStatus"
                        >
                            <span v-if="togglingStatus" class="icon-loading-small"></span>
                            <span v-else :class="formData.enabled ? 'icon-close' : 'icon-checkmark'"></span>
                            {{
                                togglingStatus
                                    ? t('souvera_central', 'Speichert...')
                                    : formData.enabled
                                        ? t('souvera_central', 'Benutzer deaktivieren')
                                        : t('souvera_central', 'Benutzer aktivieren')
                            }}
                        </button>

                        <button
                            type="button"
                            class="action-button secondary"
                            :disabled="resendingEmail"
                            @click="resendWelcomeEmail"
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
                            :disabled="wipingDevices"
                            @click="wipeDevices"
                        >
                            <span v-if="wipingDevices" class="icon-loading-small"></span>
                            <span v-else class="icon-delete"></span>
                            {{
                                wipingDevices
                                    ? t('souvera_central', 'Trennt...')
                                    : t('souvera_central', 'Alle Geräte trennen & Daten löschen')
                            }}
                        </button>

                        <button
                            type="button"
                            class="action-button danger"
                            :disabled="deletingUser || isOwnAccount"
                            :title="
                                isOwnAccount ? t('souvera_central', 'Sie können Ihr eigenes Konto nicht löschen') : ''
                            "
                            @click="deleteUser"
                        >
                            <span v-if="deletingUser" class="icon-loading-small"></span>
                            <span v-else class="icon-delete"></span>
                            {{
                                deletingUser ? t('souvera_central', 'Löscht...') : t('souvera_central', 'Konto löschen')
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
import AliasManager from './AliasManager.vue'

export default {
    name: 'UserEditor',

    components: {
        ManagerSelector,
        GroupSelector,
        ConfirmationModal,
        AliasManager
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
            togglingStatus: false,
            deletingUser: false,
            initialManagerData: null,
            currentUserId: null,
            groupsExpanded: false,
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
        },

        isOwnAccount() {
            return this.isEditMode && this.user && this.currentUserId && this.user.id === this.currentUserId
        }
    },

    mounted() {
        this.loadGroups()
        this.loadSettings()
        this.loadCurrentUser()

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

        /**
         * Prüft OCS-Response auf Fehler und wirft Error falls vorhanden
         * OCSController gibt oft HTTP 200 OK mit Fehler in ocs.meta zurück
         */
        checkOCSError(response) {
            const statusCode = response.data?.ocs?.meta?.statuscode
            // Message zuerst in data.error suchen (dort ist sie meist), dann in meta.message
            const message = response.data?.ocs?.data?.error ||
                           response.data?.ocs?.meta?.message

            if (statusCode && statusCode >= 400) {
                throw new Error(message || 'Fehler bei der Anfrage')
            }
        },

        async loadCurrentUser() {
            try {
                const url = generateUrl('/apps/souvera_central/api/users/current')
                const response = await axios.get(url)
                const data = response.data.ocs?.data || response.data.data || response.data
                this.currentUserId = data.id
            } catch (error) {
                // Error loading current user
            }
        },

        async loadGroups() {
            try {
                const url = generateUrl('/apps/souvera_central/api/groups')
                const response = await axios.get(url)
                const groups =
                    response.data.ocs?.data?.groups || response.data.data?.groups || response.data.groups || []
                this.availableGroups = groups
            } catch (error) {
                // Error loading groups
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
                // Error loading settings
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
            if (!this.isEditMode) {
                this.validateEmail()
            }
            this.validatePassword()

            if (!this.isFormValid) {
                return
            }

            this.saving = true

            try {
                if (this.isEditMode) {
                    // Update existing user
                    // Username wird aus user.id genommen (wurde bei Erstellung aus Email generiert)
                    // E-Mail wird NICHT aktualisiert im Edit-Modus
                    const url = generateUrl('/apps/souvera_central/api/users/{id}', { id: this.user.id })

                    const payload = {
                        displayName: this.formData.displayName,
                        // email: wird bewusst NICHT mitgeschickt
                        groups: this.formData.groups,
                        quota: this.formData.quota,
                        enabled: this.formData.enabled,
                        manager: this.formData.manager
                    }

                    // Passwort nur mitschicken wenn es gesetzt ist (nicht leer)
                    if (this.formData.password && this.formData.password.trim() !== '') {
                        payload.password = this.formData.password
                    }

                    const updateResponse = await axios.put(url, payload)
                    this.checkOCSError(updateResponse)
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

                    const createResponse = await axios.post(url, payload)
                    this.checkOCSError(createResponse)

                    // Auto-Email senden wenn aktiviert
                    if (this.settings.email.send_to_new_users) {
                        try {
                            // Username ist Email (vom Backend gesetzt)
                            const emailUrl = generateUrl('/apps/souvera_central/api/users/{id}/resend-welcome-email', {
                                id: this.formData.email
                            })
                            await axios.post(emailUrl)
                        } catch (emailError) {
                            // Error sending welcome email, but don't block
                        }
                    }
                }

                this.$emit('saved')
                this.$emit('close')
            } catch (error) {
                // Zeige Fehlermeldung
                console.error('UserEditor: Error beim Speichern:', error)
                console.log('Error Response:', error.response)

                let errorMessage = this.t('souvera_central', 'Fehler beim Speichern')

                // Prüfe Error.message (von checkOCSError() oder anderen Error-Quellen)
                if (error.message && !error.message.includes('Network Error')) {
                    errorMessage = error.message
                } else if (error.response?.data?.ocs?.data?.error) {
                    errorMessage = error.response.data.ocs.data.error
                } else if (error.response?.data?.error) {
                    errorMessage = error.response.data.error
                }

                console.log('Extracted error message:', errorMessage)

                // Prüfe ob es ein Duplikat-Fehler ist und zeige ihn im Email-Feld
                if (errorMessage.toLowerCase().includes('bereits') || errorMessage.toLowerCase().includes('existiert')) {
                    console.log('Setting errors.email to:', errorMessage)
                    this.errors.email = errorMessage

                    // Zusätzlich: Zeige Fehler-Modal
                    this.confirmModal = {
                        isOpen: true,
                        title: this.t('souvera_central', 'Fehler beim Speichern'),
                        message: errorMessage,
                        details: this.t('souvera_central', 'Bitte verwenden Sie eine andere E-Mail-Adresse.'),
                        type: 'danger',
                        confirmText: this.t('souvera_central', 'OK'),
                        cancelText: '',
                        onConfirm: () => {
                            this.closeConfirmModal()
                            // Scroll zum Fehler
                            this.$nextTick(() => {
                                const emailField = document.getElementById('email') || document.getElementById('emailLocalPart')
                                emailField?.scrollIntoView({ behavior: 'smooth', block: 'center' })
                                emailField?.focus()
                            })
                        }
                    }
                } else {
                    // Zeige generischen Fehler als Toast-Notification
                    if (window.OC?.Notification) {
                        window.OC.Notification.showTemporary(errorMessage, { timeout: 7 })
                    } else {
                        // Fallback wenn OC.Notification nicht verfügbar
                        alert(errorMessage)
                    }
                }
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
                // Error loading manager data
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

        toggleUserStatus() {
            const action = this.formData.enabled ? 'deaktivieren' : 'aktivieren'
            const actionPast = this.formData.enabled ? 'deaktiviert' : 'aktiviert'

            this.confirmModal = {
                isOpen: true,
                title: this.formData.enabled
                    ? this.t('souvera_central', 'Benutzer deaktivieren?')
                    : this.t('souvera_central', 'Benutzer aktivieren?'),
                message: this.t('souvera_central', 'Möchten Sie den Benutzer "{user}" wirklich {action}?', {
                    user: this.formData.displayName,
                    action: action
                }),
                details: this.formData.enabled
                    ? this.t(
                        'souvera_central',
                        'Der Benutzer kann sich nicht mehr anmelden, bis er wieder aktiviert wird.'
                    )
                    : this.t('souvera_central', 'Der Benutzer kann sich wieder anmelden.'),
                type: this.formData.enabled ? 'warning' : 'info',
                confirmText: this.formData.enabled
                    ? this.t('souvera_central', 'Deaktivieren')
                    : this.t('souvera_central', 'Aktivieren'),
                cancelText: this.t('souvera_central', 'Abbrechen'),
                onConfirm: async () => {
                    this.togglingStatus = true

                    try {
                        const apiAction = this.formData.enabled ? 'disable' : 'enable'
                        const url = generateUrl('/apps/souvera_central/api/users/{id}/{action}', {
                            id: this.user.id,
                            action: apiAction
                        })

                        await axios.post(url)

                        // Status lokal aktualisieren
                        this.formData.enabled = !this.formData.enabled

                        // Success Modal
                        this.confirmModal = {
                            isOpen: true,
                            title: this.t('souvera_central', 'Status geändert!'),
                            message: this.t('souvera_central', 'Der Benutzer wurde erfolgreich {action}.', {
                                action: actionPast
                            }),
                            details: '',
                            type: 'success',
                            confirmText: this.t('souvera_central', 'OK'),
                            cancelText: '',
                            onConfirm: () => {
                                this.closeConfirmModal()
                            }
                        }
                    } catch (error) {
                        const errorMessage =
                            error.response?.data?.ocs?.data?.error ||
                            error.response?.data?.error ||
                            this.t('souvera_central', 'Fehler beim Ändern des Status')

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
                        this.togglingStatus = false
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

        deleteUser() {
            this.confirmModal = {
                isOpen: true,
                title: this.t('souvera_central', 'Konto löschen?'),
                message: this.t('souvera_central', 'Möchten Sie das Konto "{user}" wirklich unwiderruflich löschen?', {
                    user: this.formData.displayName
                }),
                details: this.t(
                    'souvera_central',
                    'WARNUNG: Diese Aktion kann nicht rückgängig gemacht werden! Alle Daten des Benutzers werden dauerhaft gelöscht.'
                ),
                type: 'danger',
                confirmText: this.t('souvera_central', 'Ja, Konto löschen'),
                cancelText: this.t('souvera_central', 'Abbrechen'),
                onConfirm: async () => {
                    this.deletingUser = true

                    try {
                        const url = generateUrl('/apps/souvera_central/api/users/{id}', {
                            id: this.user.id
                        })
                        await axios.delete(url)

                        // Success Modal
                        this.confirmModal = {
                            isOpen: true,
                            title: this.t('souvera_central', 'Konto gelöscht!'),
                            message: this.t('souvera_central', 'Das Konto wurde erfolgreich gelöscht.'),
                            details: this.t('souvera_central', 'Der Benutzer "{user}" wurde dauerhaft entfernt.', {
                                user: this.formData.displayName
                            }),
                            type: 'success',
                            confirmText: this.t('souvera_central', 'OK'),
                            cancelText: '',
                            onConfirm: () => {
                                this.closeConfirmModal()
                                // Schließe Editor und kehre zur Liste zurück
                                this.$emit('saved')
                                this.$emit('close')
                            }
                        }
                    } catch (error) {
                        const errorMessage =
                            error.response?.data?.ocs?.data?.error ||
                            error.response?.data?.error ||
                            this.t('souvera_central', 'Fehler beim Löschen des Benutzers')

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
                        this.deletingUser = false
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
    background: transparent;
    padding: 30px;
}

/* Header */
.editor-header {
    padding: 0 0 20px 0;
    border-bottom: none;
    display: flex;
    align-items: center;
}

.header-left {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(6px);
    border: 1.5px solid rgba(0, 0, 0, 0.15);
    color: #000;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    padding: 10px 16px;
    border-radius: 6px;
    transition: all 0.2s;
    align-self: flex-start;
}

.back-button:hover {
    background: rgba(255, 255, 255, 0.9);
    border-color: var(--color-secondary-element);
    transform: translateX(-2px);
}

.back-button [class^='icon-'],
.back-button [class*=' icon-'] {
    font-size: 16px;
    color: #000 !important;
    opacity: 1 !important;
}

.editor-header h2 {
    margin: 0;
    font-size: 28px;
    font-weight: 600;
    color: #000;
}

/* Content */
.editor-content {
    flex: 1;
    overflow-y: auto;
    padding: 0;
    background: rgba(255, 255, 255, 0.25);
    border-radius: 6px;
    padding: 30px;
}

.user-form {
    max-width: 800px;
    margin: 0 auto;
}

/* Form Groups */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #000;
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
    padding: 16px 18px;
    border: 2px solid rgba(0, 0, 0, 0.2);
    border-radius: 6px;
    font-size: 16px;
    line-height: 1.5;
    height: 60px;
    box-sizing: border-box;
    transition: all 0.2s;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(6px);
    color: #000;
    font-weight: 500;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--color-secondary-element);
    box-shadow: 0 0 0 3px rgba(48, 116, 191, 0.3);
    background: #fff;
}

.form-group input.error {
    border-color: var(--color-error);
}

.form-group input.success {
    border-color: var(--color-success);
}

.form-group input:disabled {
    background: rgba(0, 0, 0, 0.05);
    cursor: not-allowed;
    opacity: 0.6;
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
    height: 60px;
    box-sizing: border-box;
}

.email-separator {
    font-weight: 700;
    color: #000;
    font-size: 18px;
}

.email-domain-select {
    flex: 1;
    min-width: 0;
    padding: 16px 18px;
    border: 2px solid rgba(0, 0, 0, 0.2);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(6px);
    color: #000;
    font-size: 16px;
    line-height: 1.5;
    height: 60px;
    cursor: pointer;
    box-sizing: border-box;
    font-weight: 500;
    position: relative;
    z-index: 10;
}

.email-domain-select:focus {
    outline: none;
    border-color: var(--color-secondary-element);
    box-shadow: 0 0 0 3px rgba(48, 116, 191, 0.3);
    background: #fff;
}

.email-domain-select.error {
    border-color: var(--color-error);
}

/* Messages */
.help-text {
    margin: 6px 0 0;
    font-size: 14px;
    color: #666;
    font-weight: 500;
}

.error-message {
    margin: 6px 0 0;
    font-size: 14px;
    color: var(--color-error);
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
}

.success-message {
    margin: 6px 0 0;
    font-size: 14px;
    color: var(--color-success);
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
}

.validating-message {
    margin: 6px 0 0;
    font-size: 13px;
    color: var(--color-text-lighter);
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Status Display */
.status-display {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: var(--color-background-dark);
    border-radius: var(--border-radius);
    border: 1px solid var(--color-border);
}

.status-display .status-icon {
    font-size: 24px;
}

.status-display .icon-checkmark-color {
    color: var(--color-success);
}

.status-display .icon-close {
    color: var(--color-error);
}

.status-display .status-text {
    font-weight: 600;
    font-size: 15px;
}

.status-display .status-text.status-active {
    color: var(--color-success);
}

.status-display .status-text.status-inactive {
    color: var(--color-error);
}

/* Groups Zone (Collapsible) */
.groups-zone.collapsible {
    margin-top: 30px;
    border: 2px solid rgba(0, 0, 0, 0.15);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(6px);
    padding: 0;
    overflow: visible;
    position: relative;
    z-index: 10;
}

.collapsible-header {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 25px;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: background 0.2s;
}

.collapsible-header:hover {
    background: var(--color-background-hover);
}

.collapsible-header span[class^='icon-triangle'] {
    font-size: 16px;
    color: var(--color-text-lighter);
    transition: transform 0.2s;
}

.collapsible-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--color-text-light);
    flex: 1;
}

.optional-badge {
    padding: 4px 12px;
    background: var(--color-primary-element-light);
    color: var(--color-primary);
    border-radius: var(--border-radius-large);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.collapsible-content {
    padding: 0 25px 25px 25px;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.collapsible-content .form-group {
    margin-bottom: 20px;
}

.collapsible-content .form-group:last-child {
    margin-bottom: 0;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid rgba(0, 0, 0, 0.1);
    position: relative;
    z-index: 1;
}

.form-actions button {
    padding: 14px 28px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-actions button.primary {
    background: #28a745;
    color: #fff;
    border: 2px solid #28a745;
    box-shadow: 0 4px 16px rgba(40, 167, 69, 0.4);
}

.form-actions button.primary:hover:not(:disabled) {
    background: #34d058;
    border-color: #34d058;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.6);
}

.form-actions button.primary:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    background: #ccc;
    border-color: #ccc;
    box-shadow: none;
}

.form-actions button.secondary {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(6px);
    border: 2px solid rgba(0, 0, 0, 0.2);
    color: #000;
    font-weight: 600;
}

.form-actions button.secondary:hover {
    background: rgba(255, 255, 255, 0.95);
    border-color: #3074BF;
    transform: translateY(-1px);
}

button.primary {
    background-color: #28a745;
    border: 2px solid #28a745;
    color: #fff;
}

/* Danger Zone */
.danger-zone {
    margin-top: 40px;
    padding: 25px;
    border: 2px solid rgba(227, 56, 80, 0.3);
    border-radius: 6px;
    background: rgba(227, 56, 80, 0.05);
    backdrop-filter: blur(6px);
    position: relative;
    z-index: 1;
}

.danger-zone h3 {
    margin: 0 0 15px;
    font-size: 18px;
    font-weight: 700;
    color: var(--color-error);
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

.action-button [class^='icon-'],
.action-button [class*=' icon-'] {
    color: white !important;
    filter: invert(1) brightness(100) !important;
    opacity: 1 !important;
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

.action-button.warning {
    background: linear-gradient(135deg, #ff6600 0%, #ff5500 100%);
    color: #fff !important;
    border: 2px solid #ff5500;
}

.action-button.warning * {
    color: #fff !important;
}

.action-button.warning [class^='icon-'],
.action-button.warning [class*=' icon-'] {
    color: #fff !important;
    filter: brightness(0) invert(1) !important;
}

.action-button.warning:hover:not(:disabled) {
    background: linear-gradient(135deg, #ff7700 0%, #ff6600 100%);
    border-color: #ff6600;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 102, 0, 0.4);
}

.action-button.success {
    background: var(--color-success);
    color: white;
}

.action-button.success:hover:not(:disabled) {
    background: #46a049;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px var(--color-box-shadow);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .user-editor-page {
        padding: 20px;
    }

    .editor-content {
        padding: 20px;
    }

    .user-form {
        max-width: 100%;
    }
}

@media (max-width: 768px) {
    .user-editor-page {
        padding: 15px;
    }

    .editor-header h2 {
        font-size: 24px;
    }

    .editor-content {
        padding: 15px;
    }

    .form-group input[type='text'],
    .form-group input[type='email'],
    .form-group input[type='password'],
    .form-group select {
        height: 52px;
        padding: 14px 16px;
        font-size: 15px;
    }

    .email-local-part,
    .email-domain-select {
        height: 52px;
    }

    .form-actions {
        flex-direction: column-reverse;
        gap: 10px;
    }

    .form-actions button {
        width: 100%;
        justify-content: center;
    }

    .collapsible-header h3 {
        font-size: 18px;
    }
}

@media (max-width: 480px) {
    .user-editor-page {
        padding: 10px;
    }

    .back-button {
        font-size: 13px;
        padding: 8px 14px;
    }

    .editor-header h2 {
        font-size: 20px;
    }

    .editor-content {
        padding: 10px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        font-size: 14px;
    }

    .form-group input[type='text'],
    .form-group input[type='email'],
    .form-group input[type='password'],
    .form-group select {
        height: 48px;
        padding: 12px 14px;
        font-size: 14px;
    }

    .email-field {
        flex-direction: column;
        gap: 10px;
    }

    .email-local-part,
    .email-domain-select {
        height: 48px;
        width: 100%;
    }

    .form-actions button {
        padding: 12px 24px;
        font-size: 14px;
    }

    .danger-zone {
        padding: 20px;
    }

    .danger-zone h3 {
        font-size: 16px;
    }

    .action-button {
        padding: 10px 18px;
        font-size: 13px;
    }
}
</style>
