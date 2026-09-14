<template>
	<div class="sig-admin" data-testid="signature-admin-section">
		<div v-if="toast.show" class="sig-admin__toast" :class="'sig-admin__toast--' + toast.type">{{ toast.message }}</div>
		<!-- Admin-Fallbacks für Variablen -->
		<div class="sig-admin__block">
			<label class="field-label">{{ t('souvera_central', 'Fallback values (used when a user has no data in their profile)') }}</label>
			<div class="sig-admin__fallbacks">
				<input v-model="fallbacks.title" class="sig-admin__input" :placeholder="t('souvera_central', 'Title — e.g. Senior Consultant')" />
				<input v-model="fallbacks.department" class="sig-admin__input" :placeholder="t('souvera_central', 'Department — e.g. Sales')" />
				<input v-model="fallbacks.phone" class="sig-admin__input" :placeholder="t('souvera_central', 'Phone — e.g. +49 30 12345678')" />
				<input v-model="fallbacks.company" class="sig-admin__input" :placeholder="t('souvera_central', 'Company')" />
			</div>
			<button class="sig-admin__btn" data-testid="sig-fallbacks-save" @click="saveFallbacks">
				{{ t('souvera_central', 'Save fallback values') }}
			</button>
		</div>

		<!-- Logo -->
		<div class="sig-admin__block">
			<label class="field-label">{{ t('souvera_central', 'Logo (inline image for the signature)') }}</label>
			<div class="sig-admin__logo">
				<img v-if="logo" :src="logoUrl" class="sig-admin__logo-img" alt="Logo" />
				<span v-else class="sig-admin__muted">{{ t('souvera_central', 'No logo uploaded') }}</span>
				<input type="file" accept="image/png,image/jpeg,image/svg+xml" @change="uploadLogo" />
				<button v-if="logo" class="sig-admin__btn sig-admin__btn--danger" @click="deleteLogo">
					{{ t('souvera_central', 'Remove logo') }}
				</button>
			</div>
			<p class="sig-admin__hint">
				{{ t('souvera_central', 'Reference the logo in the HTML template with: <img src="cid:souvera-sig-logo">') }}
			</p>
		</div>

		<!-- Overrides -->
		<div class="sig-admin__block">
			<label class="field-label">{{ t('souvera_central', 'Per-group / per-user overrides (take priority over the global template)') }}</label>
			<table class="sig-admin__table">
				<thead>
					<tr><th>{{ t('souvera_central', 'Scope') }}</th><th>{{ t('souvera_central', 'Group / user') }}</th><th>{{ t('souvera_central', 'Priority') }}</th><th>{{ t('souvera_central', 'Active') }}</th><th></th></tr>
				</thead>
				<tbody>
					<tr v-for="o in overrides" :key="o.id">
						<td>{{ o.scope === 'group' ? t('souvera_central', 'Group') : t('souvera_central', 'User') }}</td>
						<td>{{ o.scopeValue }}</td>
						<td>{{ o.priority }}</td>
						<td>{{ o.active ? '✓' : '—' }}</td>
						<td><button class="sig-admin__btn sig-admin__btn--danger" @click="deleteOverride(o.id)">{{ t('souvera_central', 'Delete') }}</button></td>
					</tr>
					<tr v-if="overrides.length === 0"><td colspan="5" class="sig-admin__muted">{{ t('souvera_central', 'No overrides — the global signature applies to everyone.') }}</td></tr>
				</tbody>
			</table>

			<div class="sig-admin__override-form">
				<select v-model="form.scope" class="sig-admin__input sig-admin__input--small">
					<option value="group">{{ t('souvera_central', 'Group') }}</option>
					<option value="user">{{ t('souvera_central', 'User') }}</option>
				</select>
				<input v-model="form.scopeValue" class="sig-admin__input sig-admin__input--small" :placeholder="t('souvera_central', 'Group-ID / user-ID')" />
				<input v-model.number="form.priority" type="number" min="1" max="999" class="sig-admin__input sig-admin__input--small" :placeholder="t('souvera_central', 'Priority (1 = highest)')" />
				<button class="sig-admin__btn" data-testid="sig-override-save" @click="saveOverride">
					{{ t('souvera_central', 'Add / update override') }}
				</button>
			</div>
			<textarea v-model="form.html" class="sig-admin__textarea" rows="5"
				:placeholder="t('souvera_central', 'Override signature (HTML) — variables like %name%, %phone% …')"></textarea>
			<textarea v-model="form.text" class="sig-admin__textarea sig-admin__textarea--small" rows="2"
				:placeholder="t('souvera_central', 'Plain-text variant (optional)')"></textarea>
			<p class="sig-admin__hint">
				{{ t('souvera_central', 'Tip: click an existing row in the table to load it into the form; changing priority re-orders precedence (1 wins).') }}
			</p>
		</div>

		<!-- Stalwart MTA-Hook -->
		<div class="sig-admin__block">
			<label class="checkbox-label">
				<input v-model="hook.enabled" type="checkbox" @change="saveHook" />
				<span>{{ t('souvera_central', 'Enforce signature server-side via Stalwart MTA-Hook (all SMTP clients — Thunderbird, Outlook, mobile)') }}</span>
			</label>
			<div v-if="hook.enabled" class="sig-admin__hook">
				<button class="sig-admin__btn" @click="rotateSecret">{{ t('souvera_central', 'Generate new hook secret') }}</button>
				<input v-model.number="hook.sizeLimit" type="number" min="0" step="1048576" class="sig-admin__input sig-admin__input--small"
					:placeholder="t('souvera_central', 'Size limit in bytes (0 = no limit)')" @change="saveHook" />
				<p v-if="secret" class="sig-admin__secret">{{ secret }}</p>
				<p class="sig-admin__hint">
					{{ t('souvera_central', 'Wire it into Stalwart with: occ souvera_central:signature:hook-config (prints URL, secret and the config snippet).') }}
					{{ t('souvera_central', 'Important: if the Sieve signature script is deployed, remove it first (occ souvera_central:mailsignature:sieve --remove) — otherwise signatures are doubled.') }}
				</p>
			</div>
		</div>

		<!-- Stalwart-Verkabelung (automatisch) -->
		<div class="sig-admin__block">
			<label class="field-label">{{ t('souvera_central', 'Wire into Stalwart (automatic)') }}</label>
			<div class="sig-admin__resolve">
				<button class="sig-admin__btn" data-testid="sig-wire-apply" @click="wireStalwart">
					{{ t('souvera_central', 'Apply hook config to Stalwart') }}
				</button>
				<button class="sig-admin__btn sig-admin__btn--danger" @click="unwireStalwart">
					{{ t('souvera_central', 'Rollback') }}
				</button>
				<button class="sig-admin__btn" @click="loadStalwartStatus">
					{{ t('souvera_central', 'Check Stalwart status') }}
				</button>
			</div>
			<p v-if="wireMessage" class="sig-admin__hint" :class="{ 'sig-admin__hint--warn': wireError }">{{ wireMessage }}</p>
			<p v-if="stalwartKeys.length" class="sig-admin__hint">
				{{ t('souvera_central', 'Hook keys:') }} {{ stalwartKeys.join(', ') }}
				— {{ t('souvera_central', 'Event webhook keys (push notifications) are untouched:') }} {{ webhookKeys.join(', ') || '—' }}
			</p>
		</div>

		<!-- Live-Vorschau -->
		<div class="sig-admin__block">
			<label class="field-label">{{ t('souvera_central', 'Resolve test (preview the final signature for an email address)') }}</label>
			<div class="sig-admin__resolve">
				<input v-model="testEmail" class="sig-admin__input sig-admin__input--small" :placeholder="t('souvera_central', 'user@example.com')" />
				<button class="sig-admin__btn" @click="resolveTest">{{ t('souvera_central', 'Preview') }}</button>
			</div>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<div v-if="previewHtml" class="sig-admin__preview" v-html="previewHtml"></div>
			<p v-if="previewNotFound" class="sig-admin__muted">{{ t('souvera_central', 'No signature resolves for this address (no user found, or none active).') }}</p>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const unwrap = (response) => response.data.ocs?.data || response.data.data || response.data

export default {
	name: 'SignatureAdminSection',
	data() {
		return {
			fallbacks: { title: '', department: '', phone: '', company: '' },
			overrides: [],
			logo: null,
			logoUrl: '',
			hook: { enabled: false, sizeLimit: 10485760 },
			secret: '',
			form: { scope: 'group', scopeValue: '', priority: 100, html: '', text: '' },
			testEmail: '',
			previewHtml: '',
			previewNotFound: false,
			wireMessage: '',
			wireError: false,
			stalwartKeys: [],
			webhookKeys: [],
			toast: { show: false, type: 'success', message: '' },
		}
	},
	mounted() {
		this.load()
	},
	methods: {
		toast(type, message) {
			this.toast = { show: true, type, message }
			setTimeout(() => { this.toast.show = false }, 4000)
		},
		async load() {
			try {
				const r = await axios.get(generateUrl('/apps/souvera_central/api/signature-admin/overview'))
				const data = unwrap(r) || {}
				this.fallbacks = { title: '', department: '', phone: '', company: '', ...(data.fallbacks || {}) }
				this.overrides = data.overrides || []
				this.logo = data.logo
				this.logoUrl = data.logo ? generateUrl('/apps/souvera_central/api/signature-admin/logo/bytes') + '?t=' + Date.now() : ''
				this.hook.enabled = !!(data.hook && data.hook.enabled)
				this.hook.sizeLimit = (data.hook && data.hook.sizeLimit) || 10485760
			} catch (e) {
				console.error('Signature admin load failed', e)
			}
		},
		async saveFallbacks() {
			try {
				await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/fallbacks'), { fallbacks: this.fallbacks })
				this.toast('success', this.t('souvera_central', 'Fallback values saved'))
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Save failed'))
			}
		},
		async saveOverride() {
			try {
				const r = await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/overrides'), {
					scope: this.form.scope,
					scopeValue: this.form.scopeValue,
					priority: this.form.priority,
					html: this.form.html,
					text: this.form.text,
					active: true,
				})
				const data = unwrap(r) || {}
				if (data.error) { this.toast('error', data.error); return }
				this.overrides = data.overrides || []
				this.toast('success', this.t('souvera_central', 'Override saved'))
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Save failed'))
			}
		},
		async deleteOverride(id) {
			try {
				const r = await axios.delete(generateUrl('/apps/souvera_central/api/signature-admin/overrides/' + id))
				const data = unwrap(r) || {}
				this.overrides = data.overrides || []
				this.toast('success', this.t('souvera_central', 'Override deleted'))
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Delete failed'))
			}
		},
		async uploadLogo(ev) {
			const file = ev.target.files?.[0]
			if (!file) return
			const fd = new FormData()
			fd.append('logo', file)
			try {
				const r = await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/logo'), fd)
				const data = unwrap(r) || {}
				if (data.error) { this.toast('error', data.error); return }
				this.logo = data.logo
				this.logoUrl = generateUrl('/apps/souvera_central/api/signature-admin/logo/bytes') + '?t=' + Date.now()
				this.toast('success', this.t('souvera_central', 'Logo uploaded'))
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Upload failed'))
			}
		},
		async deleteLogo() {
			try {
				await axios.delete(generateUrl('/apps/souvera_central/api/signature-admin/logo'))
				this.logo = null
				this.logoUrl = ''
			} catch (e) {
				console.error(e)
			}
		},
		async saveHook() {
			try {
				await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/hook'), {
					enabled: this.hook.enabled,
					sizeLimit: this.hook.sizeLimit,
				})
				this.toast('success', this.t('souvera_central', 'Hook settings saved'))
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Save failed'))
			}
		},
		async rotateSecret() {
			try {
				const r = await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/hook/rotate-secret'))
				const data = unwrap(r) || {}
				this.secret = data.secret || ''
				this.toast('success', this.t('souvera_central', 'New secret generated — update the Stalwart config!'))
			} catch (e) {
				console.error(e)
			}
		},
		async wireStalwart() {
			this.wireError = false
			this.wireMessage = this.t('souvera_central', 'Applying… (pre-check → snapshot → write → verify)')
			try {
				const r = await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/wire'))
				const data = unwrap(r) || {}
				if (data.ok) {
					this.wireError = false
					this.wireMessage = this.t('souvera_central', 'Hook wired into Stalwart and verified.')
					this.loadStalwartStatus()
				} else {
					this.wireError = true
					this.wireMessage = (data.error || 'Apply failed') + (data.rollbackAvailable ? ' — Rollback available.' : '')
					if (data.precheck) {
						this.stalwartKeys = Object.keys(data.precheck.existingHooks || {})
						this.webhookKeys = data.precheck.webhookKeys || []
					}
				}
			} catch (e) {
				console.error(e)
				this.wireError = true
				this.wireMessage = this.t('souvera_central', 'Wire failed — see logs')
			}
		},
		async unwireStalwart() {
			this.wireError = false
			try {
				const r = await axios.post(generateUrl('/apps/souvera_central/api/signature-admin/unwire'))
				const data = unwrap(r) || {}
				this.wireError = !data.ok
				this.wireMessage = data.ok ? this.t('souvera_central', 'Rollback OK — previous Stalwart config restored.') : (data.error || 'Rollback failed')
				this.loadStalwartStatus()
			} catch (e) {
				console.error(e)
				this.wireError = true
				this.wireMessage = this.t('souvera_central', 'Rollback failed — see logs')
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
					this.wireMessage = this.t('souvera_central', 'Stalwart status loaded.')
				} else {
					this.wireError = true
					this.wireMessage = data.error || 'Status failed'
				}
			} catch (e) {
				console.error(e)
				this.wireError = true
				this.wireMessage = this.t('souvera_central', 'Stalwart status failed — is souvera_central.stalwart_api_url configured?')
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
.sig-admin__block { margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--color-border); }
.sig-admin__block:first-child { border-top: none; margin-top: 0; padding-top: 0; }
.sig-admin__fallbacks { display: flex; flex-direction: column; gap: 6px; margin: 8px 0; }
.sig-admin__input { width: 100%; max-width: 420px; }
.sig-admin__input--small { max-width: 260px; }
.sig-admin__btn {
	background: var(--color-primary-element); color: var(--color-primary-element-text);
	border: none; border-radius: 8px; padding: 7px 14px; cursor: pointer; font-size: 13px;
}
.sig-admin__btn:hover { opacity: 0.9; }
.sig-admin__btn--danger { background: var(--color-error); color: #fff; }
.sig-admin__table { width: 100%; max-width: 640px; border-collapse: collapse; margin: 8px 0; font-size: 13px; }
.sig-admin__table th, .sig-admin__table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--color-border); }
.sig-admin__override-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 10px; }
.sig-admin__textarea { width: 100%; max-width: 640px; margin-top: 8px; font-family: monospace; }
.sig-admin__textarea--small { max-width: 420px; }
.sig-admin__logo { display: flex; align-items: center; gap: 12px; margin: 8px 0; }
.sig-admin__logo-img { max-height: 48px; max-width: 160px; object-fit: contain; }
.sig-admin__hint { font-size: 12px; color: var(--color-text-maxcontrast); margin-top: 6px; }
.sig-admin__muted { color: var(--color-text-maxcontrast); font-size: 12px; }
.sig-admin__secret { font-family: monospace; font-size: 12px; word-break: break-all; background: var(--color-background-hover); padding: 6px 8px; border-radius: 6px; }
.sig-admin__resolve { display: flex; gap: 8px; margin: 8px 0; }
.sig-admin__preview { border: 1px solid var(--color-border); border-radius: 8px; padding: 12px; max-width: 640px; margin-top: 8px; }
.sig-admin__toast { margin-bottom: 10px; padding: 8px 12px; border-radius: 8px; font-size: 13px; }
.sig-admin__toast--success { background: var(--color-success, #2d7d46); color: #fff; }
.sig-admin__toast--error { background: var(--color-error); color: #fff; }
</style>
