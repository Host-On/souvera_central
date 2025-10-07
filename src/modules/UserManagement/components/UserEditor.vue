<template>
  <div class="user-editor-page">
    <!-- Header -->
    <div class="editor-header">
      <button class="back-button" @click="$emit('close')">
        <span class="icon-arrow-left"></span>
        {{ t('souvera_central', 'Zurück') }}
      </button>
      <h2>{{ isEditMode ? t('souvera_central', 'Benutzer bearbeiten') : t('souvera_central', 'Neuer Benutzer') }}</h2>
    </div>

    <!-- Form -->
    <div class="editor-content">
      <form @submit.prevent="saveUser" class="user-form">
        <!-- Benutzername -->
        <div class="form-group">
          <label for="username" class="required">
            {{ t('souvera_central', 'Benutzername') }}
          </label>
          <input
            id="username"
            v-model="formData.username"
            type="text"
            :disabled="isEditMode"
            :class="{ 'error': errors.username }"
            @input="validateUsername"
            required
          />
          <p v-if="errors.username" class="error-message">{{ errors.username }}</p>
          <p v-else class="help-text">{{ t('souvera_central', 'Eindeutiger Benutzername, kann später nicht geändert werden') }}</p>
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
            :class="{ 'error': errors.displayName }"
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
          <input
            id="email"
            v-model="formData.email"
            type="email"
            :class="{ 'error': errors.email }"
            @input="validateEmail"
            @blur="validateEmailDomain"
            required
          />
          <p v-if="errors.email" class="error-message">{{ errors.email }}</p>
          <p v-else-if="validating.email" class="validating-message">
            <span class="icon-loading-small"></span>
            {{ t('souvera_central', 'Validiere Domain...') }}
          </p>
          <p v-else-if="formData.email && !errors.email" class="success-message">
            <span class="icon-checkmark"></span>
            {{ t('souvera_central', 'E-Mail-Domain ist gültig') }}
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
            :class="{ 'error': errors.password }"
            @input="validatePassword"
            required
          />
          <p v-if="errors.password" class="error-message">{{ errors.password }}</p>
          <p v-else class="help-text">{{ t('souvera_central', 'Mindestens 10 Zeichen') }}</p>
        </div>

        <!-- Gruppen -->
        <div class="form-group">
          <label for="groups">
            {{ t('souvera_central', 'Gruppen') }}
          </label>
          <div class="groups-selector">
            <div v-for="group in availableGroups" :key="group.id" class="group-checkbox">
              <input
                :id="'group-' + group.id"
                v-model="formData.groups"
                type="checkbox"
                :value="group.id"
              />
              <label :for="'group-' + group.id">{{ group.displayName }}</label>
            </div>
          </div>
        </div>

        <!-- Speicherplatz Quota -->
        <div class="form-group">
          <label for="quota">
            {{ t('souvera_central', 'Speicherplatz') }}
          </label>
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

        <!-- Aktiv/Deaktiviert -->
        <div class="form-group checkbox-group">
          <input
            id="enabled"
            v-model="formData.enabled"
            type="checkbox"
          />
          <label for="enabled">
            {{ t('souvera_central', 'Benutzer aktiviert') }}
          </label>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <button type="button" class="secondary" @click="$emit('close')">
            {{ t('souvera_central', 'Abbrechen') }}
          </button>
          <button type="submit" class="primary" :disabled="!isFormValid || saving">
            <span v-if="saving" class="icon-loading-small"></span>
            {{ saving ? t('souvera_central', 'Speichert...') : t('souvera_central', 'Speichern') }}
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

export default {
  name: 'UserEditor',

  props: {
    user: {
      type: Object,
      default: null
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
        quota: 'default',
        enabled: true
      },
      errors: {
        username: null,
        displayName: null,
        email: null,
        password: null
      },
      validating: {
        email: false
      },
      availableGroups: [],
      saving: false
    }
  },

  computed: {
    isEditMode() {
      return this.user !== null
    },

    isFormValid() {
      return this.formData.username &&
             this.formData.displayName &&
             this.formData.email &&
             (!this.isEditMode ? this.formData.password : true) &&
             !this.errors.username &&
             !this.errors.displayName &&
             !this.errors.email &&
             !this.errors.password
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
        groups: this.user.groups.map(g => g.id),
        quota: this.user.quota.quota,
        enabled: this.user.enabled
      }
    }
  },

  methods: {
    t,

    async loadGroups() {
      try {
        const url = generateUrl('/apps/souvera_central/api/groups')
        const response = await axios.get(url)
        const groups = response.data.ocs?.data?.groups || response.data.data?.groups || response.data.groups || []
        this.availableGroups = groups
      } catch (error) {
        console.error('Fehler beim Laden der Gruppen:', error)
        // Fallback
        this.availableGroups = []
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
        await new Promise(resolve => setTimeout(resolve, 500))

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
            enabled: this.formData.enabled
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
            enabled: this.formData.enabled
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
    }
  }
}
</script>

<style scoped>
.user-editor-page {
  height: 100%;
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

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="password"],
.form-group select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  font-size: 14px;
  transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--color-primary);
}

.form-group input.error {
  border-color: var(--color-error);
}

.form-group input:disabled {
  background: var(--color-background-dark);
  cursor: not-allowed;
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
  color: #28a745;
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

/* Groups Selector */
.groups-selector {
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  padding: 12px;
  max-height: 200px;
  overflow-y: auto;
}

.group-checkbox {
  display: flex;
  align-items: center;
  padding: 8px;
  border-radius: var(--border-radius);
  transition: background 0.2s;
}

.group-checkbox:hover {
  background: var(--color-background-hover);
}

.group-checkbox input[type="checkbox"] {
  margin: 0 10px 0 0;
}

.group-checkbox label {
  margin: 0;
  cursor: pointer;
  font-weight: normal;
}

/* Checkbox Group */
.checkbox-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.checkbox-group input[type="checkbox"] {
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
</style>
