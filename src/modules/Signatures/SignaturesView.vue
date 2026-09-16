<template>
	<div class="signatures-view">
		<div v-if="toast.show" class="signatures-view__toast" :class="'signatures-view__toast--' + toast.type">{{ toast.message }}</div>
		<div v-if="pageWarning" class="signatures-view__warning" data-testid="sig-page-warning">{{ pageWarning }}</div>
		<header class="signatures-view__header">
			<h2>{{ t('souvera_central', 'E-Mail-Signaturen') }}</h2>
			<p class="signatures-view__intro">
				{{ t('souvera_central', 'Zentrale Verwaltung: Die Vorlage unten gilt für ALLE ausgehenden Mails — Webmail, Thunderbird, Outlook, mobil. Die serverseitige Injektion passiert in Stalwart (vor der DKIM-Signierung); Webmail-Nutzer sehen die Signatur bereits beim Verfassen.') }}
			</p>
		</header>

		<!-- 1 · Globale Vorlage -->
		<section class="signatures-view__card">
			<div class="signatures-view__card-head">
				<span class="signatures-view__step">1</span>
				<div>
					<h3>{{ t('souvera_central', 'Globale Signatur-Vorlage') }}</h3>
					<p class="signatures-view__help">{{ t('souvera_central', 'Gilt für alle Nutzer ohne eigenen Override (siehe Schritt 4). Variablen werden pro Nutzer automatisch ersetzt.') }}</p>
				</div>
			</div>
			<div class="signatures-view__body">
				<label class="signatures-view__checkbox">
					<input v-model="signature.enabled" type="checkbox" data-testid="sig-global-enabled" />
					<span>{{ t('souvera_central', 'Globale Mail-Signatur aktivieren') }}</span>
				</label>

				<div v-if="signature.enabled" class="signatures-view__editor">
					<label class="signatures-view__label">{{ t('souvera_central', 'Signatur (HTML)') }}</label>
					<textarea v-model="signature.template" class="signatures-view__textarea" rows="8"
						placeholder="<p>%first_name% %last_name%</p><p>%title% · %company%</p><p>Phone: %phone%</p>"></textarea>

					<div class="signatures-view__vars">
						<span class="signatures-view__vars-hint">{{ t('souvera_central', 'Variablen (zum Einfügen anklicken):') }}</span>
						<button v-for="v in variables" :key="v" type="button" class="signatures-view__var"
							@click="insertVariable(v)">{{ v }}</button>
					</div>

					<div class="signatures-view__actions">
						<button class="signatures-view__btn" :disabled="saving" @click="saveGlobal">
							{{ saving ? t('souvera_central', 'Speichern…') : t('souvera_central', 'Vorlage speichern') }}
						</button>
					</div>

					<label class="signatures-view__label">{{ t('souvera_central', 'Vorschau (mit Beispieldaten)') }}</label>
					<!-- eslint-disable-next-line vue/no-v-html -->
					<div class="signatures-view__preview" v-html="renderedPreview"></div>
				</div>
			</div>
		</section>

		<!-- 2 · Bilder (Multi-Upload) -->
		<section class="signatures-view__card">
			<div class="signatures-view__card-head">
				<span class="signatures-view__step">2</span>
				<div>
					<h3>{{ t('souvera_central', 'Bilder (Inline-Grafiken für die Signatur)') }}</h3>
					<p class="signatures-view__help">{{ t('souvera_central', 'Mehrere Bilder möglich (PNG/JPG/SVG/WebP, max. 512 KB je Bild). Jedes Bild erhält eine CID (siehe Liste) — im HTML-Template verwenden:') }} <code class="signatures-view__code">src="cid:CID-AUS-DER-LISTE"</code></p>
				</div>
			</div>
			<div class="signatures-view__body">
				<input type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp" multiple
					data-testid="sig-assets-input" @change="uploadAssets($event)" />

				<table v-if="assets.length > 0" class="signatures-view__table" data-testid="sig-assets-table">
					<thead>
						<tr><th>{{ t('souvera_central', 'Bild') }}</th><th>{{ t('souvera_central', 'CID (zum Kopieren anklicken)') }}</th><th>{{ t('souvera_central', 'Größe') }}</th><th></th></tr>
					</thead>
					<tbody>
						<tr v-for="a in assets" :key="a.slug">
							<td>{{ a.name }}</td>
							<td><code class="signatures-view__cid" :title="t('souvera_central', 'CID kopieren')" @click="copyCid(a)">{{ a.cid }}</code></td>
							<td>{{ formatSize(a.size) }}</td>
							<td><button class="signatures-view__btn signatures-view__btn--danger" @click="deleteAsset(a.slug)">{{ t('souvera_central', 'Löschen') }}</button></td>
						</tr>
					</tbody>
				</table>
				<p v-else class="signatures-view__muted">{{ t('souvera_central', 'Noch keine Bilder hochgeladen.') }}</p>
			</div>
		</section>

		<!-- 3 · Fallback-Werte -->
		<section class="signatures-view__card">
			<div class="signatures-view__card-head">
				<span class="signatures-view__step">3</span>
				<div>
					<h3>{{ t('souvera_central', 'Ersatzwerte für Variablen') }}</h3>
					<p class="signatures-view__help">{{ t('souvera_central', 'Gilt, wenn ein Nutzer die Daten nicht in seinem Profil hat (z. B. Firmen-Telefonzentrale statt eigener Durchwahl).') }}</p>
				</div>
			</div>
			<div class="signatures-view__body">
				<div class="signatures-view__fallbacks">
					<input v-model="fallbacks.title" class="signatures-view__input" :placeholder="t('souvera_central', 'Titel — z. B. Senior Consultant')" />
					<input v-model="fallbacks.department" class="signatures-view__input" :placeholder="t('souvera_central', 'Abteilung — z. B. Vertrieb')" />
					<input v-model="fallbacks.phone" class="signatures-view__input" :placeholder="t('souvera_central', 'Telefon — z. B. +49 30 1234567')" />
					<input v-model="fallbacks.company" class="signatures-view__input" :placeholder="t('souvera_central', 'Firma')" />
				</div>
				<button class="signatures-view__btn" @click="saveFallbacks">
					{{ t('souvera_central', 'Ersatzwerte speichern') }}
				</button>
			</div>
		</section>

		<!-- 4 · Overrides -->
		<section class="signatures-view__card">
			<div class="signatures-view__card-head">
				<span class="signatures-view__step">4</span>
				<div>
					<h3>{{ t('souvera_central', 'Overrides pro Gruppe / Benutzer') }}</h3>
					<p class="signatures-view__help">{{ t('souvera_central', 'Eigene Signatur-Vorlagen für Gruppen oder einzelne Nutzer — Vorrang vor der globalen Vorlage. Niedrigste Prioritätszahl gewinnt.') }}</p>
				</div>
			</div>
			<div class="signatures-view__body">
				<table class="signatures-view__table">
					<thead>
						<tr><th>{{ t('souvera_central', 'Bereich') }}</th><th>{{ t('souvera_central', 'Gruppe / Benutzer') }}</th><th>{{ t('souvera_central', 'Priorität') }}</th><th>{{ t('souvera_central', 'Aktiv') }}</th><th></th></tr>
					</thead>
					<tbody>
						<tr v-for="o in overrides" :key="o.id" class="signatures-view__row" @click="loadOverride(o)">
							<td>{{ o.scope === 'group' ? t('souvera_central', 'Gruppe') : t('souvera_central', 'Benutzer') }}</td>
							<td>{{ o.scopeValue }}</td>
							<td>{{ o.priority }}</td>
							<td>{{ o.active ? '✓' : '—' }}</td>
							<td><button class="signatures-view__btn signatures-view__btn--danger" @click.stop="deleteOverride(o.id)">{{ t('souvera_central', 'Löschen') }}</button></td>
						</tr>
						<tr v-if="overrides.length === 0"><td colspan="5" class="signatures-view__muted">{{ t('souvera_central', 'Keine Overrides — die globale Signatur gilt für alle.') }}</td></tr>
					</tbody>
				</table>

				<div class="signatures-view__override-form">
					<select v-model="overrideForm.scope" class="signatures-view__input signatures-view__input--small">
						<option value="group">{{ t('souvera_central', 'Gruppe') }}</option>
						<option value="user">{{ t('souvera_central', 'Benutzer') }}</option>
					</select>
					<input v-model="overrideForm.scopeValue" class="signatures-view__input signatures-view__input--small"
						:placeholder="t('souvera_central', 'Gruppen-ID / Benutzer-ID')" />
					<input v-model.number="overrideForm.priority" type="number" min="1" max="999" class="signatures-view__input signatures-view__input--small"
						:placeholder="t('souvera_central', 'Priorität (1 = höchste)')" />
					<button class="signatures-view__btn" @click="saveOverride">
						{{ t('souvera_central', 'Override hinzufügen / aktualisieren') }}
					</button>
				</div>
				<textarea v-model="overrideForm.html" class="signatures-view__textarea" rows="5"
					:placeholder="t('souvera_central', 'Override-Signatur (HTML) — Variablen wie %name%, %phone% …')"></textarea>
				<textarea v-model="overrideForm.text" class="signatures-view__textarea signatures-view__textarea--small" rows="2"
					:placeholder="t('souvera_central', 'Klartext-Variante (optional)')"></textarea>
			</div>
		</section>

		<!-- 5 · Stalwart MTA-Hook -->
		<section class="signatures-view__card">
			<div class="signatures-view__card-head">
				<span class="signatures-view__step">5</span>
				<div>
					<h3>{{ t('souvera_central', 'Stalwart MTA-Hook (serverseitige Injektion)') }}</h3>
					<p class="signatures-view__help">{{ t('souvera_central', 'Injiziert die Signatur in ALLE ausgehenden SMTP-Mails — Thunderbird, Outlook, mobil. Läuft vor der DKIM-Signierung; Skip-Regeln für verschlüsselte/nummerierte Mails sind eingebaut. Stalwart-Patch ≥ 0.16.x erforderlich.') }}</p>
				</div>
			</div>
			<div class="signatures-view__body">
				<label class="signatures-view__checkbox">
					<input v-model="hook.enabled" type="checkbox" @change="saveHook" />
					<span>{{ t('souvera_central', 'Signatur serverseitig via Stalwart MTA-Hook erzwingen (alle SMTP-Clients)') }}</span>
				</label>
				<div v-if="hook.enabled" class="signatures-view__hook">
					<div class="signatures-view__hook-row">
						<button class="signatures-view__btn" @click="rotateSecret">{{ t('souvera_central', 'Neues Hook-Secret generieren') }}</button>
						<input v-model.number="hook.sizeLimit" type="number" min="0" step="1048576" class="signatures-view__input signatures-view__input--small"
							:placeholder="t('souvera_central', 'Größenlimit in Bytes (0 = unbegrenzt)')" @change="saveHook" />
					</div>
					<p v-if="secret" class="signatures-view__secret">{{ secret }}</p>
					<div class="signatures-view__hook-row">
						<button class="signatures-view__btn" data-testid="sig-wire-apply" @click="wireStalwart">
							{{ t('souvera_central', 'Hook-Konfiguration auf Stalwart anwenden') }}
						</button>
						<button class="signatures-view__btn signatures-view__btn--danger" @click="unwireStalwart">
							{{ t('souvera_central', 'Rollback') }}
						</button>
						<button class="signatures-view__btn" @click="loadStalwartStatus">
							{{ t('souvera_central', 'Stalwart-Status prüfen') }}
						</button>
					</div>
					<p v-if="wireMessage" class="signatures-view__hint" :class="{ 'signatures-view__hint--warn': wireError }">{{ wireMessage }}</p>
					<p v-if="stalwartKeys.length" class="signatures-view__hint">
						{{ t('souvera_central', 'Hook-Keys:') }} {{ stalwartKeys.join(', ') }}
						— {{ t('souvera_central', 'Push-Webhooks (anderes Subsystem) bleiben unberührt:') }} {{ webhookKeys.join(', ') || '—' }}
					</p>
				</div>
			</div>
		</section>

		<!-- 6 · Test -->
		<section class="signatures-view__card">
			<div class="signatures-view__card-head">
				<span class="signatures-view__step">6</span>
				<div>
					<h3>{{ t('souvera_central', 'Vorschau: finale Signatur') }}</h3>
					<p class="signatures-view__help">{{ t('souvera_central', 'Zeigt die Signatur, die für diese E-Mail-Adresse tatsächlich versendet wird (mit allen Variablen/Overrides aufgelöst).') }}</p>
				</div>
			</div>
			<div class="signatures-view__body">
				<div class="signatures-view__resolve">
					<input v-model="testEmail" class="signatures-view__input signatures-view__input--small"
						:placeholder="t('souvera_central', 'user@example.com')" />
					<button class="signatures-view__btn" @click="resolveTest">{{ t('souvera_central', 'Vorschau') }}</button>
				</div>
				<!-- eslint-disable-next-line vue/no-v-html -->
				<div v-if="previewHtml" class="signatures-view__preview" v-html="previewHtml"></div>
				<p v-if="previewNotFound" class="signatures-view__muted">{{ t('souvera_central', 'Für diese Adresse löst keine Signatur auf (kein Nutzer gefunden oder nicht aktiv).') }}</p>
			</div>
		</section>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import SignatureAdminSection from '../Settings/SignatureAdminSection.vue'

const unwrap = (response) => response.data.ocs?.data || response.data.data || response.data

export default {
	name: 'SignaturesView',
	components: { SignatureAdminSection },
	data() {
		return {
			saving: false,
			variables: ['%name%', '%first_name%', '%last_name%', '%email%', '%domain%', '%title%', '%department%', '%phone%', '%company%'],
			signature: { enabled: false, template: '' },
			fallbacks: { title: '', department: '', phone: '', company: '' },
			overrides: [],
			overrideForm: { scope: 'group', scopeValue: '', priority: 100, html: '', text: '' },
			assets: [],
			hook: { enabled: false, sizeLimit: 10485760 },
			secret: '',
			wireMessage: '',
			wireError: false,
			stalwartKeys: [],
			webhookKeys: [],
			testEmail: '',
			previewHtml: '',
			previewNotFound: false,
			toast: { show: false, type: 'success', message: '' },
			pageWarning: '',
		}
	},
	computed: {
		// Vorschau mit Platzhalter-Beispieldaten (Fehlertext bei leerer Vorlage).
		renderedPreview() {
			if (!this.signature.template.trim()) {
				return '<em>' + this.t('souvera_central', 'Noch keine Vorlage.') + '</em>'
			}
			const host = window.location.hostname.replace(/^www\./, '')
			const vars = {
				'%name%': 'Mia Musterfrau',
				'%first_name%': 'Mia',
				'%last_name%': 'Musterfrau',
				'%email%': 'mia@' + host,
				'%domain%': host,
				'%title%': 'Senior Consultant',
				'%department%': 'Vertrieb',
				'%phone%': '+49 30 1234567',
				'%company%': 'Souvera',
			}
			return this.signature.template.replace(/%\w+%/g, (m2) => vars[m2] ?? m2)
		},
	},
	mounted() {
		this.loadGlobal()
	},
	methods: {
		/** Inline-Toast (central hat kein @nextcloud/dialogs als Dependency). */
		toast(type, message) {
			this.toast = { show: true, type, message }
			setTimeout(() => { this.toast = { show: false, type, message } }, 4000)
		},
		async loadGlobal() {
			try {
				const r = await axios.get(generateUrl('/apps/souvera_central/api/signature-admin/overview'))
				const data = unwrap(r) || {}
				this.signature.enabled = !!data.globalEnabled
				this.signature.template = data.globalTemplate || ''
				this.fallbacks = { title: '', department: '', phone: '', company: '', ...(data.fallbacks || {}) }
				this.overrides = data.overrides || []
				this.assets = data.assets || []
				this.pageWarning = data.warning || ''
				this.hook.enabled = !!(data.hook && data.hook.enabled)
				this.hook.sizeLimit = (data.hook && data.hook.sizeLimit) || 10485760
			} catch (e) {
				console.error('Signature overview load failed', e)
			}
		},
		async saveGlobal() {
			this.saving = true
			try {
				const r = await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/global'), {
					signature: {
						enabled: this.signature.enabled,
						template: this.signature.template,
					},
				})
				const data = unwrap(r) || {}
				if (data.error) {
					this.toast('error', data.error)
					return
				}
				this.toast('success', this.t('souvera_central', 'Signatur-Vorlage gespeichert'))
				if (data.signature_deploy && data.signature_deploy.ok === false && data.signature_deploy.error) {
					this.toast('error', this.t('souvera_central', 'Stalwart-Abgleich fehlgeschlagen: {e}', { e: String(data.signature_deploy.error).slice(0, 120) }))
				}
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Speichern fehlgeschlagen'))
			} finally {
				this.saving = false
			}
		},
		insertVariable(v) {
			this.signature.template += v
		},
		async saveFallbacks() {
			try {
				await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/fallbacks'), { fallbacks: this.fallbacks })
				this.toast('success', this.t('souvera_central', 'Ersatzwerte gespeichert'))
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Speichern fehlgeschlagen'))
			}
		},
		async saveOverride() {
			try {
				const r = await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/overrides'), {
					scope: this.overrideForm.scope,
					scopeValue: this.overrideForm.scopeValue,
					priority: this.overrideForm.priority,
					html: this.overrideForm.html,
					text: this.overrideForm.text,
					active: true,
				})
				const data = unwrap(r) || {}
				if (data.error) { this.toast('error', data.error); return }
				this.overrides = data.overrides || []
				this.toast('success', this.t('souvera_central', 'Override gespeichert'))
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Speichern fehlgeschlagen'))
			}
		},
		async deleteOverride(id) {
			try {
				const r = await axios.delete(generateUrl('/apps/souvera_central/api/signature-admin/overrides/' + id))
				const data = unwrap(r) || {}
				this.overrides = data.overrides || []
				this.toast('success', this.t('souvera_central', 'Override gelöscht'))
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Löschen fehlgeschlagen'))
			}
		},
		loadOverride(o) {
			this.overrideForm = {
				scope: o.scope,
				scopeValue: o.scopeValue,
				priority: o.priority,
				html: o.html || '',
				text: o.text || '',
			}
		},
		async uploadAssets(ev) {
			const files = Array.from(ev.target.files || [])
			ev.target.value = ''
			if (files.length === 0) return
			const fd = new FormData()
			for (const f of files) fd.append('assets', f)
			try {
				const r = await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/assets'), fd)
				const data = unwrap(r) || {}
				if (data.error) { this.toast('error', data.error); return }
				this.assets = data.assets || []
				if ((data.errors || []).length) this.toast('error', data.errors.join('; '))
				this.toast('success', this.t('souvera_central', '{n} Bild(er) hochgeladen', { n: (data.stored || []).length }))
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Upload fehlgeschlagen'))
			}
		},
		async deleteAsset(slug) {
			try {
				await axios.delete(generateUrl('/apps/souvera_central/api/signature-admin/assets/' + encodeURIComponent(slug)))
				this.assets = this.assets.filter((a) => a.slug !== slug)
				this.toast('success', this.t('souvera_central', 'Bild gelöscht'))
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Löschen fehlgeschlagen'))
			}
		},
		copyCid(asset) {
			navigator.clipboard?.writeText('cid:' + asset.cid)?.catch(() => {})
			this.toast('success', this.t('souvera_central', 'CID kopiert: {cid}', { cid: asset.cid }))
		},
		formatSize(bytes) {
			if (!bytes) return '—'
			if (bytes > 1048576) return (bytes / 1048576).toFixed(1) + ' MB'
			if (bytes > 1024) return (bytes / 1024).toFixed(0) + ' KB'
			return bytes + ' B'
		},
		async rotateSecret() {
			try {
				const r = await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/hook/rotate-secret'))
				const data = unwrap(r) || {}
				this.secret = data.secret || ''
				this.toast('success', this.t('souvera_central', 'Neues Secret generiert — Stalwart-Konfiguration aktualisieren!'))
			} catch (e) {
				console.error(e)
			}
		},
		async wireStalwart() {
			this.wireError = false
			this.wireMessage = this.t('souvera_central', 'Anwenden… (Pre-Check → Snapshot → Schreiben → Verify)')
			try {
				const r = await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/wire'))
				const data = unwrap(r) || {}
				if (data.ok) {
					this.wireError = false
					this.wireMessage = this.t('souvera_central', 'Hook in Stalwart eingekabelt und verifiziert.')
					this.loadStalwartStatus()
				} else {
					this.wireError = true
					this.wireMessage = (data.error || 'Apply failed') + (data.rollbackAvailable ? ' — Rollback verfügbar.' : '')
					if (data.precheck) {
						this.stalwartKeys = Object.keys(data.precheck.existingHooks || {})
						this.webhookKeys = data.precheck.webhookKeys || []
					}
				}
			} catch (e) {
				console.error(e)
				this.wireError = true
				this.wireMessage = this.t('souvera_central', 'Einkabeln fehlgeschlagen — siehe Logs')
			}
		},
		async unwireStalwart() {
			this.wireError = false
			try {
				const r = await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/unwire'))
				const data = unwrap(r) || {}
				this.wireError = !data.ok
				this.wireMessage = data.ok ? this.t('souvera_central', 'Rollback OK — vorherige Stalwart-Konfiguration wiederhergestellt.') : (data.error || 'Rollback failed')
				this.loadStalwartStatus()
			} catch (e) {
				console.error(e)
				this.wireError = true
				this.wireMessage = this.t('souvera_central', 'Rollback fehlgeschlagen — siehe Logs')
			}
		},
		async loadStalwartStatus() {
			try {
				const r = await axios.get(generateUrl('/apps/souvera_central/api/signature-admin/stalwart-status'))
				const data = unwrap(r) || {}
				if (data.ok) {
					this.stalwartKeys = Object.keys(data.signatureHook || {})
					this.webhookKeys = data.webhookKeys || []
					this.wireError = false
					this.wireMessage = this.t('souvera_central', 'Stalwart-Status geladen.')
				} else {
					this.wireError = true
					this.wireMessage = data.error || 'Status failed'
				}
			} catch (e) {
				console.error(e)
				this.wireError = true
				this.wireMessage = this.t('souvera_central', 'Stalwart-Status fehlgeschlagen — ist souvera_central.stalwart_api_url konfiguriert?')
			}
		},
		async resolveTest() {
			this.previewNotFound = false
			this.previewHtml = ''
			if (!this.testEmail.trim()) return
			try {
				const r = await axios.get(generateUrl('/apps/souvera_central/api/signature-admin/resolve-test'), {
					params: { email: this.testEmail.trim() },
				})
				const data = unwrap(r) || {}
				if (data.found) {
					this.previewHtml = data.html
				} else {
					this.previewNotFound = true
				}
			} catch (e) {
				console.error(e)
			}
		},
	},
}

</script>

<style scoped>
.signatures-view { padding: 20px 24px; max-width: 920px; }
.signatures-view__header h2 { margin: 0 0 6px; font-size: 22px; }
.signatures-view__intro { color: var(--color-text-maxcontrast); margin: 0 0 20px; max-width: 720px; }
.signatures-view__card {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 16px 20px;
	margin-bottom: 16px;
}
.signatures-view__card-head { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 12px; }
.signatures-view__card-head h3 { margin: 0; font-size: 15px; }
.signatures-view__step {
	display: inline-flex; align-items: center; justify-content: center;
	width: 26px; height: 26px; flex-shrink: 0;
	background: var(--color-primary-element); color: var(--color-primary-element-text);
	border-radius: 50%; font-size: 13px; font-weight: 700;
}
.signatures-view__help { font-size: 12.5px; color: var(--color-text-maxcontrast); margin: 2px 0 0; max-width: 640px; }
.signatures-view__body { padding-left: 38px; }
.signatures-view__checkbox { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; cursor: pointer; }
.signatures-view__label { display: block; font-weight: 600; margin: 12px 0 4px; }
.signatures-view__textarea {
	width: 100%; min-height: 140px; font-family: monospace; font-size: 12.5px;
	border: 1px solid var(--color-border); border-radius: 8px; padding: 10px;
	background: var(--color-main-background); color: var(--color-main-text);
}
.signatures-view__vars { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0; align-items: center; }
.signatures-view__vars-hint { font-size: 12px; color: var(--color-text-maxcontrast); }
.signatures-view__var {
	background: var(--color-background-hover); border: 1px solid var(--color-border);
	border-radius: 14px; padding: 2px 10px; font-size: 12px; cursor: pointer; color: var(--color-main-text);
}
.signatures-view__var:hover { background: var(--color-background-dark); }
.signatures-view__actions { margin: 10px 0; }
.signatures-view__btn {
	background: var(--color-primary-element); color: var(--color-primary-element-text);
	border: none; border-radius: 8px; padding: 7px 14px; cursor: pointer; font-size: 13px;
}
.signatures-view__btn:hover { opacity: 0.9; }
.signatures-view__btn--danger { background: var(--color-error); color: #fff; }
.signatures-view__preview {
	border: 1px solid var(--color-border); border-radius: 8px; padding: 14px;
	min-height: 48px; background: var(--color-main-background);
}
.signatures-view__fallbacks { display: flex; flex-direction: column; gap: 6px; margin: 8px 0; }
.signatures-view__input { width: 100%; max-width: 420px; }
.signatures-view__input--small { max-width: 260px; }
.signatures-view__logo { display: flex; align-items: center; gap: 12px; margin: 8px 0; }
.signatures-view__logo-img { max-height: 48px; max-width: 160px; object-fit: contain; }
.signatures-view__muted { color: var(--color-text-maxcontrast); font-size: 12px; }
.signatures-view__table { width: 100%; max-width: 640px; border-collapse: collapse; margin: 8px 0; font-size: 13px; }
.signatures-view__table th, .signatures-view__table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--color-border); }
.signatures-view__row { cursor: pointer; }
.signatures-view__row:hover { background: var(--color-background-hover); }
.signatures-view__override-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 10px; }
.signatures-view__hook { margin-top: 10px; display: flex; flex-direction: column; gap: 8px; }
.signatures-view__hook-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.signatures-view__secret { font-family: monospace; font-size: 12px; word-break: break-all; background: var(--color-background-hover); padding: 6px 8px; border-radius: 6px; }
.signatures-view__resolve { display: flex; gap: 8px; margin: 8px 0; }
.signatures-view__hint { font-size: 12px; color: var(--color-text-maxcontrast); margin-top: 6px; }
.signatures-view__hint--warn { color: var(--color-error-text, var(--color-error)); }
</style>
