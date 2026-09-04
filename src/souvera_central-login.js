/**
 * Souvera Central – Login-Branding: Split-Screen im Host-On-Stil
 *
 * RICHTUNGSKONTRAKT (pinned Brief):
 * THESIS: Der Login ist die erste Sekunde der Marke — links eine dunkle,
 *   souveräne Bühne mit klarem Anspruch, rechts eine ruhige, präzise Karte.
 *   Kein zentriertes Formular auf Hintergrundbild mehr.
 * OWN-WORLD: Tiefes Navy (#0a0d16) mit feinem Plus-Raster und blauem Glow,
 *   Souvera-Blau (#2f7df6) als einziger Akzent, heller Kartenbereich mit
 *   sichtbaren Feld-Labels. Font: die Instanz-Schrift (etablierte Welt).
 * STORY: Besucher versteht in 2s: Souvera = meine Daten, meine Sicherheit,
 *   mein Workspace — und meldet sich an.
 * FIRST VIEWPORT: ≥1025px 50/50-Split. Links Logo, Headline (Akzentwort
 *   blau), Subline, Feature-Chips. Rechts Karte „Willkommen zurück" mit
 *   Labels über Feldern, „Passwort vergessen?" rechts beim Passwortfeld,
 *   blauer Anmelden-Button, Footer unten zentriert.
 * FORM: Injection (Listener → dieses Bootstrap-JS → login.css mit Cache-
 *   Buster). Brief-pinned: Host-On-Split mit Souvera-Brand.
 * FINISH: unreviewed and undocumented is unfinished; this build ends with
 *   the finish review, the verdict, DESIGN.md, and every shipping raster
 *   carrying its provenance.
 *
 * Architektur: defensiv — jeder Schritt in try/catch, das Login blockiert
 * NIE. Idempotent via data-Attribute. NCs ?v= ist der Core-Hash (ändert
 * sich bei App-Updates nie), daher lädt dieses Script login.css dynamisch
 * mit Zeitstempel-Buster.
 */

(function () {
    'use strict'

    // Nur auf der Login-/Guest-Oberfläche
    if (document.body && document.body.id !== 'body-login') { return }

    /** Base64 → UTF-8-String (NCs State ist base64 von UTF-8-Bytes; atob allein macht Umlaute kaputt) */
    function b64ToUtf8(b64) {
        var bin = atob(b64)
        try {
            if (typeof TextDecoder !== 'undefined') {
                return new TextDecoder('utf-8').decode(Uint8Array.from(bin, function (c) { return c.charCodeAt(0) }))
            }
            return decodeURIComponent(escape(bin))
        } catch (e) {
            return bin
        }
    }

    function readState() {
        var el = document.getElementById('initial-state-souvera_central-loginBranding')
        if (!el) { return null }
        try { return JSON.parse(b64ToUtf8(el.value || el.textContent)) } catch (e) { return null }
    }

    var cfg = readState() || {}
    var texts = cfg.text || {}
    var lang = (document.documentElement.getAttribute('lang') || 'de').slice(0, 2).toLowerCase()
    var t = texts[lang] || texts.de || {}

    function q(sel, root) { return (root || document).querySelector(sel) }

    /**
     * Das Instanz-Hintergrundbild (Theming, z. B. blaues Flächenmuster) —
     * SOFORT beim Script-Start lesen, bevor das eigene login.css den
     * Body-Hintergrund überschreibt. Fallback: null (Navy-Verlauf bleibt).
     */
    function captureInstanceBackground() {
        // 1. Quelle: Theming-Variable --image-background (occ theming:set-background)
        try {
            var v = getComputedStyle(document.documentElement).getPropertyValue('--image-background').trim()
            if (v && v.indexOf('url(') !== -1) { return v }
        } catch (e) { /* noop */ }
        // 2. Quelle: berechneter Body-Hintergrund (bevor eigenes CSS greift)
        try {
            var bg = getComputedStyle(document.body).backgroundImage || ''
            if (bg.indexOf('url(') !== -1) { return bg }
        } catch (e) { /* noop */ }
        // Fallback: Body-Regeln in geladenen Stylesheets durchsuchen
        try {
            for (var i = 0; i < document.styleSheets.length; i++) {
                var sheet = document.styleSheets[i]
                var rules
                try { rules = sheet.cssRules } catch (e) { continue }
                if (!rules) { continue }
                for (var r = 0; r < rules.length; r++) {
                    var rule = rules[r]
                    if (!rule.selectorText || !rule.style) { continue }
                    var sel = rule.selectorText
                    if (sel.indexOf('body') === -1) { continue }
                    var img = rule.style.backgroundImage || ''
                    if (img.indexOf('url(') !== -1 && rule.style.getPropertyPriority('background-image') !== 'important') {
                        return img
                    }
                }
            }
        } catch (e) { /* noop */ }
        return null
    }

    var instanceBg = captureInstanceBackground()

    function loadCss() {
        // Der Listener liefert das CSS statisch im Head. Fallback: falls der
        // Link fehlt (z. B. alter Cache-Stand), dynamisch mit Cache-Buster.
        try {
            if (document.querySelector('link[href*="souvera_central-login.css"]')) { return }
            var link = document.createElement('link')
            link.rel = 'stylesheet'
            link.href = OC.filePath('souvera_central', 'css', 'souvera_central-login.css') + '?t=' + Date.now()
            document.head.appendChild(link)
        } catch (e) { /* noop */ }
    }

    // ---- Brand-Panel (linke Bühne) -----------------------------------------

    function buildBrandPanel() {
        if (document.documentElement.getAttribute('data-souvera-login') === '1') { return }
        var wrapper = q('.wrapper')
        if (!wrapper) { return }
        document.documentElement.setAttribute('data-souvera-login', '1')

        var aside = document.createElement('aside')
        aside.className = 'souvera-login-brand'
        if (instanceBg) {
            aside.classList.add('has-image')
            aside.style.backgroundImage = instanceBg
        }

        var inner = document.createElement('div')
        inner.className = 'souvera-login-brand__inner'

        // Logo: Theming-Icon (Login-Header .logo bzw. --image-logoheader) +
        // Wordmark aus dem Theming-Namen (serverseitiges h1)
        var logoRow = document.createElement('div')
        logoRow.className = 'souvera-login-brand__logo-row'
        var logoSrc = null
        var logoEl = q('#header.header-guest .logo') || q('#header .logo')
        if (logoEl) {
            try {
                var bg = getComputedStyle(logoEl).backgroundImage
                if (bg && bg !== 'none') { logoSrc = bg }
            } catch (e) { /* noop */ }
        }
        if (!logoSrc) {
            try {
                var lh = getComputedStyle(document.documentElement).getPropertyValue('--image-logoheader').trim()
                if (lh && lh.indexOf('url(') !== -1) { logoSrc = lh }
            } catch (e) { /* noop */ }
        }
        if (logoSrc) {
            var brandLogo = document.createElement('div')
            brandLogo.className = 'souvera-login-brand__logo'
            brandLogo.style.backgroundImage = logoSrc
            logoRow.appendChild(brandLogo)
        }
        var name = ''
        try {
            var h1 = q('h1.hidden-visually')
            if (h1) { name = (h1.textContent || '').trim() }
        } catch (e) { /* noop */ }
        if (name) {
            var wordmark = document.createElement('span')
            wordmark.className = 'souvera-login-brand__wordmark'
            wordmark.textContent = name
            logoRow.appendChild(wordmark)
        }
        if (logoRow.firstChild) {
            inner.appendChild(logoRow)
        }
        if (logoEl) {
            try {
                var header = logoEl.closest('header') || q('header.header-guest')
                if (header) { header.style.display = 'none' }
            } catch (e) { /* noop */ }
        }

        var textWrap = document.createElement('div')
        textWrap.className = 'souvera-login-brand__text'

        // Headline: Zeile 1 normal, Zeile 2 mit Blau-Akzent (kein Kicker, kein Icon)
        if (t.headline1 || t.headline2) {
            var headline = document.createElement('p')
            headline.className = 'souvera-login-brand__headline'
            var line1 = document.createElement('span')
            line1.className = 'souvera-login-brand__headline-line'
            line1.textContent = t.headline1 || ''
            var line2 = document.createElement('span')
            line2.className = 'souvera-login-brand__headline-line souvera-login-brand__accent'
            line2.textContent = t.headline2 || ''
            headline.appendChild(line1)
            headline.appendChild(line2)
            textWrap.appendChild(headline)
        }

        if (t.subline) {
            var sub = document.createElement('p')
            sub.className = 'souvera-login-brand__sub'
            sub.textContent = t.subline
            textWrap.appendChild(sub)
        }

        if (t.chips && t.chips.length) {
            var chips = document.createElement('div')
            chips.className = 'souvera-login-brand__chips'
            t.chips.forEach(function (c) {
                var chip = document.createElement('span')
                chip.textContent = c
                chips.appendChild(chip)
            })
            textWrap.appendChild(chips)
        }

        inner.appendChild(textWrap)
        aside.appendChild(inner)
        wrapper.parentNode.insertBefore(aside, wrapper)
    }

    // ---- Karte (rechts) -----------------------------------------------------

    function addLabelRow(id, text) {
        var input = document.getElementById(id)
        if (!input || !text) { return null }
        // Doppel-Labels vermeiden (NC rendert teils eigene, visuell versteckte)
        try {
            document.querySelectorAll('label[for="' + id + '"]').forEach(function (n) { n.remove() })
        } catch (e) { /* noop */ }
        var row = document.createElement('div')
        row.className = 'souvera-login-label-row'
        var label = document.createElement('label')
        label.setAttribute('for', id)
        label.textContent = text
        row.appendChild(label)
        // Anker: bei zusammengesetzten Feldern (Input + Icons im Wrapper) VOR
        // den Wrapper einfügen, sonst landet das Label im Feld
        var wrap = input.parentNode
        var composite = wrap && wrap !== document.body && wrap.children.length > 1
        var anchor = composite ? wrap : input
        anchor.parentNode.insertBefore(row, anchor)
        return row
    }

    function buildCard() {
        var box = q('.guest-box.login-box')
        if (!box) { return false }
        if (box.getAttribute('data-souvera-login') === '1') { return true }
        box.setAttribute('data-souvera-login', '1')

        // Headline umschreiben + Subline darunter
        var headline = q('.login-form__headline', box)
        if (headline && t.cardTitle) {
            headline.textContent = t.cardTitle
            if (t.cardSub) {
                var sub = document.createElement('p')
                sub.className = 'souvera-login-card-sub'
                sub.textContent = t.cardSub
                headline.insertAdjacentElement('afterend', sub)
            }
        }

        // Sichtbare Label-Zeilen (Host-On-Stil)
        addLabelRow('user', t.labelUser)
        var pwRow = addLabelRow('password', t.labelPassword)

        // „Passwort vergessen?" rechts neben das Passwort-Label
        var lost = q('#lost-password')
        if (lost && pwRow) {
            try {
                lost.classList.add('souvera-login-label-row__link')
                pwRow.appendChild(lost)
            } catch (e) { /* noop */ }
        }

        return true
    }

    // ---- Footer in die rechte Spalte ---------------------------------------

    function moveFooter() {
        var footer = q('body > footer.guest-box')
        var wrapper = q('.wrapper')
        if (footer && wrapper && footer.parentNode !== wrapper) {
            wrapper.appendChild(footer)
        }
    }

    // ---- Orchestrierung ------------------------------------------------------

    var attempts = 0
    function whenReady() {
        // Panel + Footer: servergerendertes DOM, sofort möglich
        try { buildBrandPanel() } catch (e) { /* noop */ }
        try { moveFooter() } catch (e) { /* noop */ }

        // Karte: von Vue gerendert — warten, bis irgendeine Gastbox da ist
        var box = q('.guest-content .guest-box')
        if (!box && attempts < 600) {
            attempts++
            setTimeout(whenReady, 50)
            return
        }
        try { buildCard() } catch (e) { /* noop */ }

        // Alles steht → Seite freigeben (Anti-Flash, siehe login.css)
        try { document.body.classList.add('souvera-login-ready') } catch (e) { /* noop */ }
    }

    loadCss()
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', whenReady)
    } else {
        whenReady()
    }
})()
