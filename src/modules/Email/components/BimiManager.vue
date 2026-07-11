<template>
    <div class="bimi-manager" data-testid="bimi-manager">
        <div class="bimi-intro">
            <p>
                {{ t('souvera_central', 'BIMI zeigt dein verifiziertes Markenlogo neben authentifizierten E-Mails an. Prüfe DMARC, lade dein Logo hoch, und wir erzeugen den fertigen DNS-Eintrag.') }}
            </p>
        </div>

        <!-- Domain-Auswahl -->
        <div class="bimi-card">
            <div class="field-row">
                <div class="form-group">
                    <label for="bimi-domain">{{ t('souvera_central', 'Domain') }}</label>
                    <select
                        id="bimi-domain"
                        v-model="selectedDomain"
                        data-testid="bimi-domain-select"
                        @change="loadDomain">
                        <option v-if="allowedDomains.length === 0" value="">{{ t('souvera_central', 'Keine Domains konfiguriert') }}</option>
                        <option v-for="d in allowedDomains" :key="d" :value="d">{{ d }}</option>
                    </select>
                </div>
                <div v-if="payload" class="overall-status">
                    <span class="badge" :class="payload.ready ? 'badge-ok' : 'badge-warn'" data-testid="bimi-overall-status">
                        {{ payload.ready ? t('souvera_central', 'BIMI bereit') : t('souvera_central', 'Noch nicht vollständig') }}
                    </span>
                </div>
            </div>
        </div>

        <template v-if="selectedDomain">
            <!-- Schritt 1: DMARC -->
            <div class="bimi-card step-card">
                <div class="step-head">
                    <div class="step-title">
                        <span class="step-num">1</span>
                        <h3>{{ t('souvera_central', 'DMARC-Enforcement prüfen') }}</h3>
                    </div>
                    <span class="badge" :class="dmarcBadge.cls" data-testid="bimi-dmarc-badge">{{ dmarcBadge.text }}</span>
                </div>
                <p class="step-desc">{{ t('souvera_central', 'BIMI erfordert DMARC mit p=quarantine (pct=100) oder p=reject für') }} <strong>{{ selectedDomain }}</strong>.</p>

                <button class="btn btn-primary" data-testid="bimi-check-dmarc-btn" :disabled="dmarcChecking" @click="checkDmarc">
                    <NcLoadingIcon v-if="dmarcChecking" :size="18" />
                    <span>{{ dmarcChecking ? t('souvera_central', 'Prüfe…') : t('souvera_central', 'DMARC prüfen') }}</span>
                </button>

                <div v-if="dmarcError" class="result-box box-error" data-testid="bimi-dmarc-error">{{ dmarcError }}</div>

                <div v-if="payload && payload.dmarc" class="result-box" data-testid="bimi-dmarc-result">
                    <div class="kv"><span>{{ t('souvera_central', 'Policy') }}:</span> <code>{{ payload.dmarc.policy || '—' }}</code> <span v-if="payload.dmarc.pct !== null">(pct={{ payload.dmarc.pct }})</span></div>
                    <div v-if="payload.dmarc.record" class="kv record"><span>{{ t('souvera_central', 'Eintrag') }}:</span> <code>{{ payload.dmarc.record }}</code></div>
                    <ul v-if="payload.dmarc.issues && payload.dmarc.issues.length" class="issue-list">
                        <li v-for="(issue, i) in payload.dmarc.issues" :key="i">{{ issue }}</li>
                    </ul>
                </div>
            </div>

            <!-- Schritt 2: Logo -->
            <div class="bimi-card step-card">
                <div class="step-head">
                    <div class="step-title">
                        <span class="step-num">2</span>
                        <h3>{{ t('souvera_central', 'Logo hochladen (SVG)') }}</h3>
                    </div>
                    <span class="badge" :class="payload && payload.hasLogo ? 'badge-ok' : 'badge-neutral'" data-testid="bimi-logo-badge">
                        {{ payload && payload.hasLogo ? t('souvera_central', 'Logo gesetzt') : t('souvera_central', 'Kein Logo') }}
                    </span>
                </div>
                <p class="step-desc">{{ t('souvera_central', 'Nur SVG. Wir konvertieren/validieren automatisch zu BIMI-konformem SVG Tiny P/S (quadratisch, ohne Skripte, max. 32 KB).') }}</p>

                <div class="logo-row">
                    <div class="logo-preview" data-testid="bimi-logo-preview">
                        <img v-if="logoPreviewSrc" :src="logoPreviewSrc" alt="Logo-Vorschau" />
                        <span v-else class="logo-placeholder">SVG</span>
                    </div>
                    <div class="logo-actions">
                        <input ref="svgInput" type="file" accept=".svg,image/svg+xml" class="hidden-file" data-testid="bimi-svg-input" @change="onLogoSelected" />
                        <button class="btn btn-primary" data-testid="bimi-upload-btn" :disabled="svgUploading" @click="$refs.svgInput.click()">
                            <NcLoadingIcon v-if="svgUploading" :size="18" />
                            <span>{{ svgUploading ? t('souvera_central', 'Prüfe…') : t('souvera_central', 'SVG auswählen') }}</span>
                        </button>
                        <p v-if="payload && payload.hasLogo" class="hint">{{ t('souvera_central', 'Größe') }}: {{ formatBytes(payload.svgSize) }}</p>
                    </div>
                </div>

                <div v-if="svgErrors.length" class="result-box box-error" data-testid="bimi-svg-errors">
                    <strong>{{ t('souvera_central', 'Nicht BIMI-konform:') }}</strong>
                    <ul class="issue-list"><li v-for="(e, i) in svgErrors" :key="i">{{ e }}</li></ul>
                </div>
                <div v-if="svgWarnings.length" class="result-box box-warn" data-testid="bimi-svg-warnings">
                    <strong>{{ t('souvera_central', 'Automatisch bereinigt:') }}</strong>
                    <ul class="issue-list"><li v-for="(w, i) in svgWarnings" :key="i">{{ w }}</li></ul>
                </div>
            </div>

            <!-- Schritt 3: VMC (optional) -->
            <div class="bimi-card step-card">
                <div class="step-head">
                    <div class="step-title">
                        <span class="step-num">3</span>
                        <h3>{{ t('souvera_central', 'VMC (optional)') }}</h3>
                    </div>
                    <span class="badge badge-neutral">{{ t('souvera_central', 'Optional') }}</span>
                </div>
                <p class="step-desc">{{ t('souvera_central', 'Gmail zeigt das Logo nur mit gültigem Verified Mark Certificate (VMC). Trage eine VMC-URL ein oder lade dein PEM hoch.') }}</p>

                <div class="vmc-modes">
                    <label class="radio-line"><input type="radio" value="none" v-model="vmcMode" data-testid="bimi-vmc-none" /> {{ t('souvera_central', 'Kein VMC') }}</label>
                    <label class="radio-line"><input type="radio" value="url" v-model="vmcMode" data-testid="bimi-vmc-url-mode" /> {{ t('souvera_central', 'VMC-URL') }}</label>
                    <label class="radio-line"><input type="radio" value="pem" v-model="vmcMode" data-testid="bimi-vmc-pem-mode" /> {{ t('souvera_central', 'VMC-Datei (PEM)') }}</label>
                </div>

                <div v-if="vmcMode === 'url'" class="form-group">
                    <input type="url" v-model="vmcUrl" placeholder="https://…/vmc.pem" data-testid="bimi-vmc-url-input" />
                </div>
                <div v-if="vmcMode === 'pem'" class="vmc-pem">
                    <input ref="vmcInput" type="file" accept=".pem,.crt,application/x-pem-file" class="hidden-file" data-testid="bimi-vmc-file-input" @change="onVmcSelected" />
                    <button class="btn btn-secondary" @click="$refs.vmcInput.click()">{{ vmcPemName || t('souvera_central', 'PEM auswählen') }}</button>
                </div>

                <button class="btn btn-primary" data-testid="bimi-save-vmc-btn" :disabled="vmcSaving" @click="saveVmc">
                    <NcLoadingIcon v-if="vmcSaving" :size="18" />
                    <span>{{ t('souvera_central', 'VMC speichern') }}</span>
                </button>
                <div v-if="vmcError" class="result-box box-error" data-testid="bimi-vmc-error">{{ vmcError }}</div>
            </div>

            <!-- Schritt 4: DNS-Record -->
            <div class="bimi-card step-card">
                <div class="step-head">
                    <div class="step-title">
                        <span class="step-num">4</span>
                        <h3>{{ t('souvera_central', 'Fertiger DNS-Eintrag') }}</h3>
                    </div>
                    <span class="badge" :class="payload && payload.ready ? 'badge-ok' : 'badge-warn'">
                        {{ payload && payload.ready ? t('souvera_central', 'Veröffentlichbar') : t('souvera_central', 'Unvollständig') }}
                    </span>
                </div>

                <div class="record-grid">
                    <div class="record-line">
                        <label>{{ t('souvera_central', 'Host / Name') }}</label>
                        <div class="copy-field">
                            <code data-testid="bimi-record-host">{{ payload ? payload.host : '' }}</code>
                            <button class="copy-btn" data-testid="bimi-copy-host" :disabled="!payload" @click="copyText(payload.host, 'host')">{{ copied === 'host' ? '✓' : t('souvera_central', 'Kopieren') }}</button>
                        </div>
                    </div>
                    <div class="record-line">
                        <label>{{ t('souvera_central', 'Typ') }}</label>
                        <code>TXT</code>
                    </div>
                    <div class="record-line">
                        <label>{{ t('souvera_central', 'Wert') }}</label>
                        <div class="copy-field">
                            <code class="record-value" data-testid="bimi-record-value">{{ payload && payload.record ? payload.record : t('souvera_central', 'Zuerst Logo hochladen') }}</code>
                            <button class="copy-btn" :disabled="!payload || !payload.record" data-testid="bimi-copy-record" @click="copyText(payload.record, 'record')">{{ copied === 'record' ? '✓' : t('souvera_central', 'Kopieren') }}</button>
                        </div>
                    </div>
                </div>

                <div class="public-urls">
                    <div class="record-line">
                        <label>{{ t('souvera_central', 'Logo-URL (l=)') }}</label>
                        <div class="copy-field">
                            <code data-testid="bimi-logo-url">{{ payload ? payload.logoUrl : '' }}</code>
                            <button class="copy-btn" :disabled="!payload" @click="copyText(payload.logoUrl, 'logo')">{{ copied === 'logo' ? '✓' : t('souvera_central', 'Kopieren') }}</button>
                        </div>
                    </div>
                    <div class="record-line highlight-api">
                        <label>{{ t('souvera_central', 'CloudManager-API (öffentlich, ohne Login)') }}</label>
                        <div class="copy-field">
                            <code data-testid="bimi-public-api-url">{{ publicApiUrl }}</code>
                            <button class="copy-btn" data-testid="bimi-copy-api" :disabled="!publicApiUrl" @click="copyText(publicApiUrl, 'api')">{{ copied === 'api' ? '✓' : t('souvera_central', 'Kopieren') }}</button>
                        </div>
                        <p class="hint">{{ t('souvera_central', 'Liefert den BIMI-Record als JSON. Shield übernimmt die laufende Überwachung.') }}</p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

export default {
    name: 'BimiManager',

    components: { NcLoadingIcon },

    props: {
        allowedDomains: {
            type: Array,
            default: () => [],
        },
    },

    data() {
        return {
            selectedDomain: '',
            payload: null,
            dmarcChecking: false,
            dmarcError: '',
            svgUploading: false,
            svgErrors: [],
            svgWarnings: [],
            localSvgDataUrl: '',
            vmcMode: 'none',
            vmcUrl: '',
            vmcPem: '',
            vmcPemName: '',
            vmcSaving: false,
            vmcError: '',
            copied: '',
        }
    },

    computed: {
        dmarcBadge() {
            const d = this.payload && this.payload.dmarc
            if (!d) return { cls: 'badge-neutral', text: t('souvera_central', 'Nicht geprüft') }
            if (d.enforced) return { cls: 'badge-ok', text: t('souvera_central', 'Enforcement aktiv') }
            if (d.found) return { cls: 'badge-warn', text: t('souvera_central', 'Nicht ausreichend') }
            return { cls: 'badge-error', text: t('souvera_central', 'Kein DMARC') }
        },
        logoPreviewSrc() {
            if (this.localSvgDataUrl) return this.localSvgDataUrl
            if (this.payload && this.payload.hasLogo && this.payload.logoUrl) return this.payload.logoUrl
            return ''
        },
        publicApiUrl() {
            if (!this.selectedDomain) return ''
            const path = generateUrl('/apps/souvera_central/api/public/bimi/' + this.selectedDomain)
            try {
                return window.location.origin + path
            } catch (e) {
                return path
            }
        },
    },

    mounted() {
        if (this.allowedDomains.length > 0) {
            this.selectedDomain = this.allowedDomains[0]
            this.loadDomain()
        }
    },

    methods: {
        t,

        unwrap(res) {
            return res.data.ocs?.data || res.data.data || res.data
        },

        async loadDomain() {
            this.svgErrors = []
            this.svgWarnings = []
            this.dmarcError = ''
            this.vmcError = ''
            this.localSvgDataUrl = ''
            this.copied = ''
            if (!this.selectedDomain) {
                this.payload = null
                return
            }
            try {
                const url = generateUrl('/apps/souvera_central/api/bimi/' + this.selectedDomain)
                const res = await axios.get(url)
                this.payload = this.unwrap(res)
                this.vmcMode = this.payload.vmcMode || 'none'
                this.vmcUrl = this.payload.vmcUrl || ''
            } catch (e) {
                console.error('BIMI: Domain konnte nicht geladen werden', e)
                this.payload = null
            }
        },

        async checkDmarc() {
            this.dmarcChecking = true
            this.dmarcError = ''
            try {
                const url = generateUrl('/apps/souvera_central/api/bimi/' + this.selectedDomain + '/check-dmarc')
                await axios.post(url)
                await this.loadDomain()
            } catch (e) {
                console.error('BIMI DMARC-Prüfung fehlgeschlagen', e)
                this.dmarcError = t('souvera_central', 'DMARC-Prüfung fehlgeschlagen. Bitte später erneut versuchen.')
            } finally {
                this.dmarcChecking = false
            }
        },

        onLogoSelected(event) {
            const file = event.target.files && event.target.files[0]
            if (!file) return
            const reader = new FileReader()
            reader.onload = async () => {
                const svg = String(reader.result || '')
                this.localSvgDataUrl = 'data:image/svg+xml;utf8,' + encodeURIComponent(svg)
                await this.uploadLogo(svg)
            }
            reader.readAsText(file)
            event.target.value = ''
        },

        async uploadLogo(svg) {
            this.svgUploading = true
            this.svgErrors = []
            this.svgWarnings = []
            try {
                const url = generateUrl('/apps/souvera_central/api/bimi/' + this.selectedDomain + '/logo')
                const res = await axios.post(url, { svg })
                const data = this.unwrap(res)
                this.svgWarnings = data.warnings || []
                this.payload = data.payload || this.payload
            } catch (e) {
                const data = e.response && (e.response.data.ocs?.data || e.response.data.data || e.response.data) || {}
                this.svgErrors = data.errors || [data.error || t('souvera_central', 'Upload fehlgeschlagen.')]
                this.svgWarnings = data.warnings || []
                this.localSvgDataUrl = ''
            } finally {
                this.svgUploading = false
            }
        },

        onVmcSelected(event) {
            const file = event.target.files && event.target.files[0]
            if (!file) return
            this.vmcPemName = file.name
            const reader = new FileReader()
            reader.onload = () => { this.vmcPem = String(reader.result || '') }
            reader.readAsText(file)
        },

        async saveVmc() {
            this.vmcSaving = true
            this.vmcError = ''
            try {
                const url = generateUrl('/apps/souvera_central/api/bimi/' + this.selectedDomain + '/vmc')
                const res = await axios.post(url, { mode: this.vmcMode, url: this.vmcUrl, pem: this.vmcPem })
                const data = this.unwrap(res)
                if (data.payload) this.payload = data.payload
            } catch (e) {
                const data = e.response && (e.response.data.ocs?.data || e.response.data.data || e.response.data) || {}
                this.vmcError = data.error || t('souvera_central', 'VMC konnte nicht gespeichert werden.')
            } finally {
                this.vmcSaving = false
            }
        },

        async copyText(text, key) {
            if (!text) return
            try {
                await navigator.clipboard.writeText(text)
            } catch (e) {
                const ta = document.createElement('textarea')
                ta.value = text
                document.body.appendChild(ta)
                ta.select()
                document.execCommand('copy')
                document.body.removeChild(ta)
            }
            this.copied = key
            setTimeout(() => { if (this.copied === key) this.copied = '' }, 1800)
        },

        formatBytes(bytes) {
            if (!bytes) return '0 B'
            if (bytes < 1024) return bytes + ' B'
            return (bytes / 1024).toFixed(1) + ' KB'
        },
    },
}
</script>

<style scoped>
.bimi-manager { display: flex; flex-direction: column; gap: 18px; }

.bimi-intro p {
    margin: 0;
    font-size: 14px;
    color: var(--color-text-maxcontrast);
    line-height: 1.5;
}

.bimi-card {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 20px 22px;
}

.field-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.form-group { display: flex; flex-direction: column; gap: 6px; min-width: 260px; }
.form-group label { font-weight: 600; font-size: 13px; color: var(--color-main-text); }

.step-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 6px;
}
.step-title { display: flex; align-items: center; gap: 12px; }
.step-title h3 { margin: 0; font-size: 16px; font-weight: 700; color: var(--color-main-text); }
.step-num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 26px; height: 26px; border-radius: 50%;
    background: var(--color-primary-element); color: var(--color-primary-element-text);
    font-weight: 700; font-size: 13px; flex-shrink: 0;
}
.step-desc { font-size: 13px; color: var(--color-text-maxcontrast); margin: 0 0 14px; line-height: 1.5; }

/* Buttons */
.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 18px; border-radius: 8px; border: 1px solid transparent;
    font-weight: 600; font-size: 14px; cursor: pointer;
    transition: background-color 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.btn:disabled { opacity: 0.6; cursor: default; }
.btn-primary { background: var(--color-primary-element); color: var(--color-primary-element-text); }
.btn-primary:hover:not(:disabled) { background: var(--color-primary-element-hover); box-shadow: 0 2px 8px rgba(var(--color-primary-element-rgb), 0.35); }
.btn-secondary { background: var(--color-background-dark); color: var(--color-main-text); border-color: var(--color-border); }
.btn-secondary:hover { background: var(--color-background-darker); }

/* Badges */
.badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
.badge-ok { background: #e6f4ea; color: #1e7e34; }
.badge-warn { background: #fff4e0; color: #a86a00; }
.badge-error { background: #fdecea; color: #c62828; }
.badge-neutral { background: var(--color-background-dark); color: var(--color-text-maxcontrast); }

/* Result / issues */
.result-box {
    margin-top: 14px; padding: 12px 14px; border-radius: 8px;
    background: var(--color-background-dark); font-size: 13px; color: var(--color-main-text);
}
.result-box.box-error { background: #fdecea; color: #7a1b16; border: 1px solid #f0b4ae; }
.result-box.box-warn { background: #fff4e0; color: #6b4600; border: 1px solid #f0d29a; }
.kv { margin-bottom: 6px; word-break: break-all; }
.kv.record code { font-size: 12px; }
.issue-list { margin: 8px 0 0; padding-left: 18px; }
.issue-list li { margin-bottom: 4px; line-height: 1.45; }

/* Logo */
.logo-row { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; }
.logo-preview {
    width: 96px; height: 96px; border-radius: 12px; border: 1px dashed var(--color-border);
    display: flex; align-items: center; justify-content: center; overflow: hidden;
    background: var(--color-background-dark); flex-shrink: 0;
}
.logo-preview img { width: 100%; height: 100%; object-fit: contain; padding: 8px; box-sizing: border-box; }
.logo-placeholder { color: var(--color-text-maxcontrast); font-weight: 700; font-size: 13px; }
.logo-actions { display: flex; flex-direction: column; gap: 8px; }
.hidden-file { display: none; }
.hint { font-size: 12px; color: var(--color-text-maxcontrast); margin: 0; }

/* VMC */
.vmc-modes { display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px; }
.radio-line { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--color-main-text); cursor: pointer; }
.vmc-pem { margin-bottom: 14px; }

/* Record */
.record-grid, .public-urls { display: flex; flex-direction: column; gap: 14px; }
.public-urls { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--color-border); }
.record-line { display: flex; flex-direction: column; gap: 6px; }
.record-line label { font-weight: 600; font-size: 13px; color: var(--color-main-text); }
.copy-field { display: flex; align-items: stretch; gap: 8px; }
.copy-field code {
    flex: 1; padding: 10px 12px; border-radius: 8px; background: var(--color-background-dark);
    font-size: 13px; word-break: break-all; color: var(--color-main-text); border: 1px solid var(--color-border);
}
.record-value { font-size: 12px; }
.copy-btn {
    padding: 8px 14px; border-radius: 8px; border: 1px solid var(--color-primary-element);
    background: var(--color-primary-element); color: var(--color-primary-element-text);
    font-weight: 600; font-size: 13px; cursor: pointer; white-space: nowrap;
}
.copy-btn:hover:not(:disabled) { background: var(--color-primary-element-hover); }
.copy-btn:disabled { opacity: 0.5; cursor: default; }
.highlight-api .copy-field code { border-color: var(--color-primary-element); border-style: dashed; }
</style>
