<template>
    <div class="documents-container">
        <div class="page-header">
            <h2>{{ t('souvera_central', 'Souvera Documents') }}</h2>
            <p class="header-subtitle">
                {{ t('souvera_central', 'Instance-wide document management settings (DMS core)') }}
            </p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
            <NcLoadingIcon :size="32" />
            <p>{{ t('souvera_central', 'Loading...') }}</p>
        </div>

        <div v-else class="documents-content">
        <div v-if="notice" class="notice" :data-kind="noticeKind">{{ notice }}</div>
            <!-- 1. CORE SETTINGS -->
            <div class="settings-section" data-testid="documents-settings-section">
                <div class="section-header">
                    <FileDocument :size="22" />
                    <h3>{{ t('souvera_central', 'DMS settings') }}</h3>
                </div>

                <div class="settings-group">
                    <label class="setting-row">
                        <span>{{ t('souvera_central', 'DMS enabled') }}</span>
                        <input v-model="settings.enabled" type="checkbox">
                    </label>

                    <label class="setting-row">
                        <span>{{ t('souvera_central', 'Inbox folder (per user)') }}</span>
                        <input v-model="settings.inbox_path" type="text">
                    </label>

                    <label class="setting-row">
                        <span>{{ t('souvera_central', 'Archive folder (per user)') }}</span>
                        <input v-model="settings.archive_path" type="text">
                    </label>

                    <label class="setting-row">
                        <span>{{ t('souvera_central', 'Process documents automatically') }}</span>
                        <input v-model="settings.auto_process" type="checkbox">
                    </label>

                    <label class="setting-row">
                        <span>{{ t('souvera_central', 'Archive documents automatically') }}</span>
                        <input v-model="settings.auto_archive" type="checkbox">
                    </label>
                </div>

                <div class="settings-actions">
                    <NcButton variant="primary" :disabled="saving" @click="save">
                        {{ saving ? t('souvera_central', 'Saving...') : t('souvera_central', 'Save settings') }}
                    </NcButton>
                </div>
            </div>

            <!-- 2. INTEGRATION STATUS -->
            <div class="settings-section" data-testid="documents-integration-section">
                <div class="section-header">
                    <LinkVariant :size="22" />
                    <h3>{{ t('souvera_central', 'Integration') }}</h3>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'Mode detected by the documents app via its integration discovery.') }}
                </p>
                <div class="settings-group">
                    <span class="integration-mode">{{ integrationMode }}</span>
                </div>
            </div>

            <!-- 3. DOCUMENT TYPES -->
            <div class="settings-section" data-testid="documents-types-section">
                <div class="section-header">
                    <Tag :size="22" />
                    <h3>{{ t('souvera_central', 'Document types') }}</h3>
                </div>
                <p class="section-description">
                    {{ t('souvera_central', 'Type configuration with metadata fields happens in the Documents app (per-user admin view).') }}
                </p>
                <ul class="types-list">
                    <li v-for="type in types" :key="type.id" class="type-row">
                        <span class="type-color" :style="{ backgroundColor: type.color || 'var(--color-primary)' }" />
                        {{ type.name }}
                    </li>
                    <li v-if="types.length === 0" class="type-empty">
                        {{ t('souvera_central', 'No document types defined yet') }}
                    </li>
                </ul>
                <div class="settings-actions">
                    <NcButton @click="openApp">
                        {{ t('souvera_central', 'Open Documents app') }}
                    </NcButton>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import FileDocument from 'vue-material-design-icons/FileDocument.vue'
import LinkVariant from 'vue-material-design-icons/LinkVariant.vue'
import Tag from 'vue-material-design-icons/Tag.vue'

// NC 34: OCS-Controller-Routen nur unter /apps/<app>/… (kein /ocs/v2-Pfad)
// NC 34 (live verifiziert): die documents-Routen laufen unter /apps/…,
// aber der OCS-Controller antwortet dort DEFAULTMÄSSIG MIT XML — JSON nur
// mit Accept-Header. Ohne ihn crasht data.ocs.data auf einem XML-String.
axios.defaults.headers.common['Accept'] = 'application/json'

const documentsApi = generateUrl('/apps/souvera_documents/api/v1')

function ocsData(response) {
	const d = response && response.data
	if (d && d.ocs && d.ocs.data !== undefined) {
		return d.ocs.data
	}
	return d
}

export default {
    name: 'DocumentsView',

    components: {
        NcButton,
        NcLoadingIcon,
        FileDocument,
        LinkVariant,
        Tag,
    },

    data() {
        return {
            loading: true,
            saving: false,
            notice: null,
            noticeKind: 'success',
            settings: {
                enabled: true,
                inbox_path: '/Documents/Inbox',
                archive_path: '/Documents/Archive',
                auto_process: true,
                auto_archive: false,
            },
            integrationMode: 'standalone',
            types: [],
        }
    },

    async created() {
        try {
            const [settings, integration, types] = await Promise.all([
                axios.get(`${documentsApi}/settings`),
                axios.get(`${documentsApi}/integration`),
                axios.get(`${documentsApi}/types`),
            ])
            this.settings = ocsData(settings)
            this.integrationMode = (ocsData(integration) || {}).mode || 'standalone'
            this.types = ocsData(types) || []
        } catch (e) {
            this.notice = t('souvera_central', 'Documents app is not reachable — is it installed and enabled?')
            this.noticeKind = 'error'
        } finally {
            this.loading = false
        }
    },

    methods: {
        t,

        async save() {
            this.saving = true
            try {
                await axios.patch(`${documentsApi}/settings`, {
                    settings: {
                        enabled: !!this.settings.enabled,
                        inbox_path: this.settings.inbox_path,
                        archive_path: this.settings.archive_path,
                        auto_process: !!this.settings.auto_process,
                        auto_archive: !!this.settings.auto_archive,
                    },
                })
                this.notice = t('souvera_central', 'Settings saved')
                this.noticeKind = 'success'
            } catch (e) {
                this.notice = e?.response?.data?.ocs?.data?.error || t('souvera_central', 'Saving failed')
                this.noticeKind = 'error'
            } finally {
                this.saving = false
            }
        },

        openApp() {
            window.open(generateUrl('/apps/souvera_documents'), '_blank')
        },
    },
}
</script>

<style scoped>
.notice {
    padding: 10px 14px;
    border-radius: 8px;
    margin-bottom: 12px;
    font-size: 13px;
}

.notice[data-kind='success'] {
    background: #dff3e2;
    color: #1d6a2a;
}

.notice[data-kind='error'] {
    background: #fde2e2;
    color: #a02020;
}
</style>

<style scoped>
.documents-container {
    padding: 16px;
    max-width: 760px;
}

.page-header h2 {
    margin-bottom: 4px;
}

.header-subtitle {
    color: var(--color-text-maxcontrast);
    margin-top: 0;
}

.settings-section {
    margin-top: 24px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-header h3 {
    margin: 0;
}

.section-description {
    color: var(--color-text-maxcontrast);
    font-size: 13px;
}

.settings-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin: 12px 0;
}

.setting-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.setting-row input[type='text'] {
    max-width: 280px;
}

.settings-actions {
    margin-top: 12px;
}

.integration-mode {
    font-family: monospace;
    background: var(--color-background-dark);
    padding: 4px 10px;
    border-radius: 6px;
}

.types-list {
    list-style: none;
    margin: 8px 0;
    padding: 0;
}

.type-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
}

.type-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.type-empty {
    color: var(--color-text-maxcontrast);
}
</style>
