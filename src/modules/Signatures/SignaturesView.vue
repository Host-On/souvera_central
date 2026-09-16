<template>
	<div class="signatures-view">
		<div v-if="toast.show" class="signatures-view__toast" :class="'signatures-view__toast--' + toast.type">{{ toast.message }}</div>
		<header class="signatures-view__header">
			<h2>{{ t('souvera_central', 'E-mail signatures') }}</h2>
			<p class="signatures-view__intro">
				{{ t('souvera_central', 'Central signature management: the template below applies to ALL outgoing mail — webmail, Thunderbird, Outlook, mobile. Server-side injection happens in Stalwart (before DKIM signing); webmail users see the signature while composing.') }}
			</p>
		</header>

		<!-- Globales Template -->
		<section class="signatures-view__card">
			<h3>{{ t('souvera_central', 'Global signature template') }}</h3>
			<label class="signatures-view__checkbox">
				<input v-model="signature.enabled" type="checkbox" data-testid="sig-global-enabled" />
				<span>{{ t('souvera_central', 'Enable global mail signature') }}</span>
			</label>

			<div v-if="signature.enabled" class="signatures-view__editor">
				<label class="signatures-view__label">{{ t('souvera_central', 'Signature (HTML)') }}</label>
				<textarea v-model="signature.template" class="signatures-view__textarea" rows="8"
					placeholder="<p>%first_name% %last_name%</p><p>%title% · %company%</p><p>Phone: %phone%</p>"></textarea>

				<div class="signatures-view__vars">
					<span class="signatures-view__vars-hint">{{ t('souvera_central', 'Variables (click to insert):') }}</span>
					<button v-for="v in variables" :key="v" type="button" class="signatures-view__var"
						@click="insertVariable(v)">{{ v }}</button>
				</div>

				<div class="signatures-view__actions">
					<button class="signatures-view__btn" :disabled="saving" @click="saveGlobal">
						{{ saving ? t('souvera_central', 'Saving…') : t('souvera_central', 'Save template') }}
					</button>
				</div>

				<label class="signatures-view__label">{{ t('souvera_central', 'Preview (with sample data)') }}</label>
				<!-- eslint-disable-next-line vue/no-v-html -->
				<div class="signatures-view__preview" v-html="renderedPreview"></div>
			</div>
		</section>

		<!-- Fallbacks / Overrides / Logo / Hook -->
		<section class="signatures-view__card">
			<h3>{{ t('souvera_central', 'Overrides, fallbacks, logo & Stalwart hook') }}</h3>
			<SignatureAdminSection />
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
			signature: { enabled: false, template: '', server_side: false },
		}
	},
	computed: {
		// Vorschau mit Platzhalter-Beispieldaten (Fehlertext bei leerem Template).
		renderedPreview() {
			if (!this.signature.template.trim()) {
				return '<em>' + this.t('souvera_central', 'No template yet.') + '</em>'
			}
			const vars = {
				'%name%': 'Mia Musterfrau',
				'%first_name%': 'Mia',
				'%last_name%': 'Musterfrau',
				'%email%': 'mia@' + (window.location.hostname.replace(/^www\./, '')),
				'%domain%': window.location.hostname.replace(/^www\./, ''),
				'%title%': 'Senior Consultant',
				'%department%': 'Sales',
				'%phone%': '+49 30 12345678',
				'%company%': 'Souvera',
			}
			return this.signature.template.replace(/%\w+%/g, (m) => vars[m] ?? m)
		},
	},
	mounted() {
		this.loadGlobal()
	},
	methods: {
		toast(type, message) {
			this.toast = { show: true, type, message }
			setTimeout(() => { this.toast.show = false }, 4000)
		},
		/** l10n-Übersetzung als Methode — shorthand auf den IMPORT (nicht
		 * this.t — das wäre Rekursion). Templates lösen t() über _ctx auf. */
		t,		insertVariable(v) {
			this.signature.template += v
		},
		async loadGlobal() {
			try {
				const r = await axios.get(generateUrl('/apps/souvera_central/api/signature-admin/overview'))
				const data = unwrap(r) || {}
				this.signature.enabled = !!data.globalEnabled
				this.signature.template = data.globalTemplate || ''
			} catch (e) {
				console.error('Signature overview load failed', e)
			}
		},
		async saveGlobal() {
			this.saving = true
			try {
				await axios.put(generateUrl('/apps/souvera_central/api/settings'), {
					signature: {
						enabled: this.signature.enabled,
						template: this.signature.template,
						server_side: this.signature.server_side,
					},
				})
				this.toast('success', this.t('souvera_central', 'Signature template saved'))
			} catch (e) {
				console.error(e)
				this.toast('error', this.t('souvera_central', 'Save failed'))
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.signatures-view { padding: 20px 24px; max-width: 900px; }
.signatures-view__header h2 { margin: 0 0 6px; font-size: 22px; }
.signatures-view__intro { color: var(--color-text-maxcontrast); margin: 0 0 18px; max-width: 720px; }
.signatures-view__card {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 18px 20px;
	margin-bottom: 18px;
}
.signatures-view__card h3 { margin: 0 0 12px; font-size: 16px; }
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
	border: none; border-radius: 8px; padding: 8px 16px; cursor: pointer;
}
.signatures-view__btn:disabled { opacity: 0.6; cursor: default; }
.signatures-view__toast { margin-bottom: 12px; padding: 8px 12px; border-radius: 8px; font-size: 13px; }
.signatures-view__toast--success { background: var(--color-success, #2d7d46); color: #fff; }
.signatures-view__toast--error { background: var(--color-error); color: #fff; }
.signatures-view__preview {
	border: 1px solid var(--color-border); border-radius: 8px; padding: 14px;
	min-height: 48px; background: var(--color-main-background);
}
</style>
