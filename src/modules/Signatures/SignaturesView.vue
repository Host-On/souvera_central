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
				<label class="signatures-view__label" for="sig-template">{{ t('souvera_central', 'Signatur (HTML)') }}</label>
				<textarea id="sig-template" ref="templateArea" v-model="signature.template" class="signatures-view__textarea" rows="8"
					placeholder="<p>%first_name% %last_name%</p><p>%title% · %company%</p><p>Phone: %phone%</p>"></textarea>

				<div class="signatures-view__insert-bar">
					<div class="signatures-view__vars">
						<span class="signatures-view__vars-hint">{{ t('souvera_central', 'Variablen:') }}</span>
						<button v-for="v in variables" :key="v" type="button" class="signatures-view__var"
							:title="t('souvera_central', 'An der Cursor-Position einfügen')"
							@click="insertVariable(v)">{{ v }}</button>
					</div>
					<div v-if="assets.length" class="signatures-view__vars">
						<span class="signatures-view__vars-hint">{{ t('souvera_central', 'Bilder:') }}</span>
						<button v-for="a in assets" :key="a.slug" type="button" class="signatures-view__var signatures-view__var--img"
							:title="t('souvera_central', 'Bild an der Cursor-Position einfügen')"
							@click="insertImage(a)">
							<img v-if="thumbs[a.slug]" :src="thumbs[a.slug]" alt="" class="signatures-view__chip-img">
							<span>{{ a.name }}</span>
						</button>
					</div>
				</div>

				<label class="signatures-view__label">{{ t('souvera_central', 'Vorschau (mit Beispieldaten)') }}</label>
				<iframe class="signatures-view__preview-frame" :srcdoc="previewDoc" sandbox="allow-same-origin"
					data-testid="sig-preview-frame" @load="fitPreview($event)"></iframe>
				<p class="signatures-view__hint">{{ t('souvera_central', 'So sieht die Signatur im E-Mail-Programm aus (Lesefenster ~640 px).') }}</p>
			</div>

			<div class="signatures-view__actions">
				<button class="signatures-view__btn" :disabled="saving" data-testid="sig-global-save" @click="saveGlobal">
					{{ saving ? t('souvera_central', 'Speichern…') : t('souvera_central', 'Vorlage speichern') }}
				</button>
				<button v-if="assets.length" class="signatures-view__btn" data-testid="sig-repair-widths"
					:title="t('souvera_central', 'Setzt bei jedem Bild eine feste Breite (max. 460 px) + max-width, damit die Tabelle nicht aufgeweitet wird.')"
					@click="repairImageWidths">
					{{ t('souvera_central', 'Bild-Breiten reparieren') }}
				</button>
			</div>
		</div>
	</section>

		<!-- 2 · Bilder -->
		<section class="signatures-view__card">
			<div class="signatures-view__card-head">
				<span class="signatures-view__step">2</span>
				<div>
					<h3>{{ t('souvera_central', 'Bilder (Inline-Grafiken für die Signatur)') }}</h3>
					<p class="signatures-view__help">{{ t('souvera_central', 'Mehrere Bilder möglich (PNG/JPG/SVG/WebP, max. 512 KB je Bild). Mit „Bild einfügen“ landet die Grafik direkt in der Vorlage — alternativ auch über die Bild-Chips in Schritt 1.') }}</p>
				</div>
			</div>
			<div class="signatures-view__body">
				<div class="signatures-view__dropzone" :class="{ 'signatures-view__dropzone--over': dragOver }"
					@dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false" @drop.prevent="onDrop">
					<label class="signatures-view__btn" data-testid="sig-assets-label" :class="{ 'signatures-view__btn--disabled': uploading }">
						{{ uploading ? t('souvera_central', 'Wird hochgeladen…') : t('souvera_central', 'Bilder auswählen') }}
						<input type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp" multiple
							class="signatures-view__file-hidden" data-testid="sig-assets-input" :disabled="uploading" @change="onFileChange">
					</label>
					<span class="signatures-view__muted">{{ uploading ? '' : t('souvera_central', 'oder hierher ziehen') }}</span>
				</div>

				<div v-if="assets.length > 0" class="signatures-view__table-wrap">
					<table class="signatures-view__table" data-testid="sig-assets-table">
						<thead>
							<tr><th>{{ t('souvera_central', 'Vorschau') }}</th><th>{{ t('souvera_central', 'Datei / CID') }}</th><th>{{ t('souvera_central', 'Größe') }}</th><th></th></tr>
						</thead>
						<tbody>
							<tr v-for="a in assets" :key="a.slug">
								<td><img v-if="thumbs[a.slug]" :src="thumbs[a.slug]" alt="" class="signatures-view__thumb"></td>
								<td>
									<div class="signatures-view__asset-name">{{ a.name }}</div>
									<code class="signatures-view__cid" tabindex="0" role="button"
									:title="t('souvera_central', 'CID kopieren')" @click="copyCid(a)" @keydown.enter.prevent="copyCid(a)">{{ a.cid }}</code>
								</td>
								<td>{{ formatSize(a.size) }}</td>
								<td class="signatures-view__row-actions">
									<button class="signatures-view__btn" @click="insertImage(a)">{{ t('souvera_central', 'Bild einfügen') }}</button>
									<button class="signatures-view__btn signatures-view__btn--danger" @click="deleteAsset(a.slug)">{{ t('souvera_central', 'Löschen') }}</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
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

				<p class="signatures-view__muted">{{ t('souvera_central', 'Bestehende Zeile anklicken, um sie unten zu bearbeiten.') }}</p>
				<div class="signatures-view__override-form">
					<div class="signatures-view__field">
						<label class="signatures-view__field-label" for="sig-override-scope">{{ t('souvera_central', 'Bereich') }}</label>
						<select id="sig-override-scope" v-model="overrideForm.scope" class="signatures-view__input signatures-view__input--small">
							<option value="group">{{ t('souvera_central', 'Gruppe') }}</option>
							<option value="user">{{ t('souvera_central', 'Benutzer') }}</option>
						</select>
					</div>
					<div class="signatures-view__field">
						<label class="signatures-view__field-label" for="sig-override-value">{{ t('souvera_central', 'Gruppe / Benutzer') }}</label>
						<input id="sig-override-value" v-model="overrideForm.scopeValue" class="signatures-view__input signatures-view__input--small"
							:placeholder="t('souvera_central', 'Gruppen-ID / Benutzer-ID')">
					</div>
					<div class="signatures-view__field">
						<label class="signatures-view__field-label" for="sig-override-prio">{{ t('souvera_central', 'Priorität') }}</label>
						<input id="sig-override-prio" v-model.number="overrideForm.priority" type="number" min="1" max="999" class="signatures-view__input signatures-view__input--small"
							:placeholder="t('souvera_central', 'Priorität (1 = höchste)')">
					</div>
					<div class="signatures-view__field signatures-view__field--action">
						<button class="signatures-view__btn" @click="saveOverride">
							{{ t('souvera_central', 'Override hinzufügen / aktualisieren') }}
						</button>
					</div>
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
					<div v-if="wireInstructions" class="signatures-view__manual-wire">
						<h4>{{ t('souvera_central', 'Manuelle Verdrahtung (Stalwart 0.16+)') }}</h4>
						<ol class="signatures-view__wire-steps">
							<li v-for="(s, i) in wireInstructions.steps" :key="i">{{ s }}</li>
						</ol>
						<p class="signatures-view__hint">{{ t('souvera_central', 'Feldwerte (ID frei wählbar, z. B. souvera-signature):') }}</p>
						<pre class="signatures-view__wire-json" data-testid="sig-wire-json">{{ wireInstructions.objectJson }}</pre>
						<button class="signatures-view__btn" @click="copyWireJson">{{ t('souvera_central', 'JSON kopieren') }}</button>
					</div>
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
						:placeholder="t('souvera_central', 'user@example.com')">
					<button class="signatures-view__btn" @click="resolveTest">{{ t('souvera_central', 'Vorschau') }}</button>
				</div>
				<iframe v-if="previewHtml" class="signatures-view__preview-frame" :srcdoc="resolveDoc"
					sandbox="allow-same-origin" data-testid="sig-resolve-frame" @load="fitPreview($event)"></iframe>
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
			wireInstructions: null,
			stalwartKeys: [],
			webhookKeys: [],
			testEmail: '',
			previewHtml: '',
			previewNotFound: false,
			toast: { show: false, type: 'success', message: '' },
			pageWarning: '',
			thumbs: {},
			dragOver: false,
			uploading: false,
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
		/** Komplettes HTML-Dokument für die iframe-Vorschau — cid:Bilder aufgelöst. */
		previewDoc() {
			return this.buildPreviewDoc(this.renderedPreview)
		},
		/** Dito für den Auflösungstest (Sektion 6). */
		resolveDoc() {
			return this.buildPreviewDoc(this.previewHtml)
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
			this.loadThumbnails()
		} catch (e) {
			console.error('Signature overview load failed', e)
		}
	},
		/** Lädt echte Vorschau-Bilder (Blob-URLs) für alle Assets. */
		async loadThumbnails() {
			this.revokeThumbs()
			const next = {}
			await Promise.all(this.assets.map(async (a) => {
				try {
					const r = await axios.get(
						generateUrl('/apps/souvera_central/api/signature-admin/assets/' + encodeURIComponent(a.slug) + '/bytes'),
						{ responseType: 'blob' },
					)
					next[a.slug] = URL.createObjectURL(r.data)
				} catch (e) {
					console.error('thumbnail failed', a.slug, e)
				}
			}))
			this.thumbs = next
		},
		revokeThumbs() {
			Object.values(this.thumbs).forEach((u) => URL.revokeObjectURL(u))
			this.thumbs = {}
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
		/** Fügt Text an der Cursor-Position des Template-Editors ein (Fallback: anhängen). */
		insertAtCursor(text) {
			const area = this.$refs.templateArea
			const tpl = this.signature.template || ''
			if (area && typeof area.selectionStart === 'number') {
				const start = area.selectionStart
				const end = area.selectionEnd
				this.signature.template = tpl.slice(0, start) + text + tpl.slice(end)
				this.$nextTick(() => {
					area.focus()
					const pos = start + text.length
					area.setSelectionRange(pos, pos)
				})
			} else {
				this.signature.template = tpl + text
			}
		},
		insertVariable(v) {
			this.insertAtCursor(v)
		},
		/**
		 * Fügt das Bild als <img src="cid:…"> an der Cursor-Position ein.
		 * max-width/height verhindern, dass das Bild die Signatur-Tabelle
		 * aufweitet (HTML-Tabellen behandeln width als MINDEST-Breite!).
		 */
		insertImage(asset) {
			const alt = String(asset.name || '').replace(/"/g, '&quot;')
			this.insertAtCursor(`<img src="cid:${asset.cid}" alt="${alt}" style="max-width:100%; height:auto;">`)
			this.toast('success', this.t('souvera_central', 'Bild in die Vorlage eingefügt — nicht vergessen zu speichern.'))
		},
		/**
		 * Baut das srcdoc-Dokument für die iframe-Vorschau: cid:Bilder werden
		 * gegen die geladenen Blob-URLs getauscht, damit sie im Browser
		 * überhaupt sichtbar sind (cid: ist kein Browser-Schema).
		 */
		buildPreviewDoc(html) {
			const body = (html || '').replace(/src=["']cid:([^"']+)["']/gi, (m, cid) => {
				const slug = String(cid).replace(/^souvera-sig-/i, '')
				const url = this.thumbs[slug] || this.thumbs[String(cid)]
				return url ? 'src="' + url + '"' : m
			})
			return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
				+ 'body{margin:0;padding:0;background:#ffffff;color:#222222;font-family:Arial,sans-serif;}'
				+ 'img{max-width:100%;height:auto;}'
				+ '</style></head><body>' + body + '</body></html>'
		},
		/** iframe-Höhe an den Inhalt anpassen (sandbox erlaubt lesenden Zugriff). */
		fitPreview(ev) {
			const f = ev.target
			try {
				const doc = f.contentDocument
				if (doc && doc.body) {
					f.style.height = Math.max(60, doc.body.scrollHeight + 16) + 'px'
				}
			} catch (e) {
				// Fallback: CSS-Mindesthöhe greift
			}
		},
		/**
		 * Setzt bei allen cid-Bildern des Templates eine feste Breite
		 * (natürliche Pixelbreite, gekappt bei 460 px) plus max-width-Style.
		 * Ohne width-Attribut weitet der Inhalt (v. a. Banner) die Tabelle —
		 * HTML-Tabellen behandeln width als Mindestbreite.
		 */
		async repairImageWidths() {
			const tpl = this.signature.template || ''
			if (!tpl.trim()) return
			// Natürliche Dimensionen aus den Blob-URLs lesen
			const dims = {}
			await Promise.all(Object.entries(this.thumbs).map(([slug, url]) => new Promise((resolve) => {
				const im = new Image()
				im.onload = () => { dims['souvera-sig-' + slug] = { w: im.naturalWidth, h: im.naturalHeight }; resolve() }
				im.onerror = () => resolve()
				im.src = url
			})))
			const CAP = 460
			const doc = new DOMParser().parseFromString('<div id="souvera-repair-root">' + tpl + '</div>', 'text/html')
			const root = doc.getElementById('souvera-repair-root')
			if (!root) return
			let changed = 0
			root.querySelectorAll('img').forEach((img) => {
				const src = img.getAttribute('src') || ''
				const m = src.match(/^cid:(souvera-sig-.+)$/i)
				if (!m) return
				let touched = false
				const dim = dims[m[1]]
				const cur = parseInt(img.getAttribute('width') || '0', 10)
				const natural = (dim && dim.w > 0) ? dim.w : 0
				const target = Math.min(cur > 0 ? cur : natural, CAP)
				if (target > 0 && target !== cur) {
					img.setAttribute('width', String(target))
					touched = true
				}
				const style = img.getAttribute('style') || ''
				if (!/max-width/i.test(style)) {
					img.setAttribute('style', (style ? style.replace(/;\s*$/, '') + '; ' : '') + 'max-width:100%; height:auto;')
					touched = true
				}
				if (touched) changed++
			})
			if (changed > 0) {
				this.signature.template = root.innerHTML
				this.toast('success', this.t('souvera_central', '{n} Bild-Tag(s) repariert — jetzt speichern.', { n: changed }))
			} else {
				this.toast('success', this.t('souvera_central', 'Alle Bild-Tags haben bereits Breitenangaben.'))
			}
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
		onFileChange(ev) {
			this.uploadFiles(Array.from(ev.target.files || []))
			ev.target.value = ''
		},
		onDrop(ev) {
			this.dragOver = false
			this.uploadFiles(Array.from(ev.dataTransfer?.files || []))
		},
		async uploadFiles(files) {
			if (files.length === 0) return
			this.uploading = true
			const fd = new FormData()
			for (const f of files) fd.append('assets', f)
			try {
				const r = await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/assets'), fd)
				const data = unwrap(r) || {}
				if (data.error) { this.toast('error', data.error); return }
				this.assets = data.assets || []
				this.loadThumbnails()
				const failed = data.errors || []
				const storedCount = (data.stored || []).length
				if (failed.length) {
					this.toast('error', this.t('souvera_central', '{ok} gespeichert, {n} fehlgeschlagen: {err}', { ok: storedCount, n: failed.length, err: failed.join('; ').slice(0, 200) }))
				} else {
					this.toast('success', this.t('souvera_central', '{n} Bild(er) hochgeladen', { n: storedCount }))
				}
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Upload fehlgeschlagen') + (e.response && e.response.status ? ' (HTTP ' + e.response.status + ')' : ''))
			} finally {
				this.uploading = false
			}
		},
		async deleteAsset(slug) {
			try {
				await axios.delete(generateUrl('/apps/souvera_central/api/signature-admin/assets/' + encodeURIComponent(slug)))
				this.assets = this.assets.filter((a) => a.slug !== slug)
				if (this.thumbs[slug]) {
					URL.revokeObjectURL(this.thumbs[slug])
					const next = { ...this.thumbs }
					delete next[slug]
					this.thumbs = next
				}
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
			this.wireInstructions = null
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
					if (data.instructions) {
						this.wireInstructions = data.instructions
					}
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
		copyWireJson() {
			if (!this.wireInstructions) return
			navigator.clipboard?.writeText(this.wireInstructions.objectJson)?.catch(() => {})
			this.toast('success', this.t('souvera_central', 'JSON kopiert'))
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
.signatures-view__insert-bar { display: flex; flex-direction: column; gap: 6px; margin: 10px 0 4px; }
.signatures-view__var--img { display: inline-flex; align-items: center; gap: 6px; max-width: 220px; }
.signatures-view__var--img span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.signatures-view__chip-img { height: 18px; max-width: 36px; object-fit: contain; border-radius: 3px; flex-shrink: 0; }
.signatures-view__dropzone {
	display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
	border: 1px dashed var(--color-border); border-radius: 10px;
	padding: 14px 16px; margin: 4px 0 12px;
	background: var(--color-background-hover); transition: border-color 0.15s ease-in-out;
}
.signatures-view__dropzone--over { border-color: var(--color-primary-element); }
.signatures-view__file-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
.signatures-view__table-wrap { overflow-x: auto; }
.signatures-view__thumb { max-height: 40px; max-width: 96px; object-fit: contain; border-radius: 4px; }
.signatures-view__asset-name { font-size: 13px; margin-bottom: 2px; }
.signatures-view__row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.signatures-view__field { display: flex; flex-direction: column; gap: 4px; }
.signatures-view__field--action { justify-content: flex-end; }
.signatures-view__field-label { font-size: 12px; font-weight: 600; color: var(--color-text-maxcontrast); }
.signatures-view__btn {
	background: var(--color-primary-element); color: var(--color-primary-element-text);
	border: none; border-radius: 8px; padding: 7px 14px; cursor: pointer; font-size: 13px;
}
.signatures-view__btn:hover { opacity: 0.9; }
.signatures-view__btn--disabled { opacity: 0.6; cursor: progress; }
.signatures-view__btn--danger { background: var(--color-error); color: #fff; }
.signatures-view__preview-frame {
	display: block; width: 100%; max-width: 640px; min-height: 80px;
	border: 1px solid var(--color-border); border-radius: 8px;
	background: #ffffff;
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
.signatures-view__manual-wire { border: 1px solid var(--color-border); border-radius: 10px; padding: 12px 14px; margin-top: 10px; }
.signatures-view__manual-wire h4 { margin: 0 0 8px; font-size: 13.5px; }
.signatures-view__wire-steps { margin: 0 0 10px; padding-left: 20px; font-size: 12.5px; color: var(--color-main-text); }
.signatures-view__wire-steps li { margin-bottom: 4px; }
.signatures-view__wire-json {
	font-family: monospace; font-size: 12px; white-space: pre-wrap; word-break: break-all;
	background: var(--color-background-hover); border: 1px solid var(--color-border);
	border-radius: 8px; padding: 10px; margin: 0 0 10px; max-width: 640px;
}
.signatures-view__resolve { display: flex; gap: 8px; margin: 8px 0; }
.signatures-view__hint { font-size: 12px; color: var(--color-text-maxcontrast); margin-top: 6px; }
.signatures-view__hint--warn { color: var(--color-error-text, var(--color-error)); }
.signatures-view__btn:focus-visible,
.signatures-view__var:focus-visible,
.signatures-view__cid:focus-visible { outline: 2px solid var(--color-primary-element); outline-offset: 2px; }
.signatures-view__cid {
	cursor: copy; font-size: 12px; padding: 1px 6px;
	background: var(--color-background-hover); border-radius: 4px;
}
.signatures-view__cid:hover { color: var(--color-primary-element); }
.signatures-view__dropzone label { cursor: pointer; }
</style>
