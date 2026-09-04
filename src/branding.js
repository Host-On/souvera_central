/**
 * Souvera Central - Instanzweite App-Umbenennung + Icon-Override (Fallback)
 *
 * Benennt die Anzeigenamen von Talk (spreed) und Office/Collabora
 * (richdocuments) in „Link" bzw. „Desk" um und tauscht optional deren Icon im
 * App-Menü gegen ein Souvera-Icon aus.
 *
 * HINWEIS zur Architektur (NC 30+/v34): Das App-Menü oben ist ein von Vue
 * gerendertes Waffle-Popover (core/AppMenu.vue → AppItem.vue). Die Kacheln
 * existieren nur, wenn das Menü geöffnet ist, und werden per Teleport an
 * <body> gehängt. Die Namen/Icons stammen aus dem Initial-State
 * `core/apps` (loadState). Wir gehen daher zweigleisig vor:
 *   1) Initial-State `#initial-state-core-apps` VOR dem Lesen durch Core
 *      patchen (flackerfrei, wenn unser Skript früh genug läuft).
 *   2) DOM-Fallback: bei jedem (Re-)Render die Kacheln (.app-item) sowie den
 *      Header-Button der aktiven App (.app-menu__current-app-*) korrigieren.
 * Ein saubererer, dauerhafter Weg wäre der Theme-l10n-Override
 * (occ souvera:branding:install-theme) – der greift aber nur bei AKTIVEM Theme.
 */

(function () {
    'use strict'

    function loadBranding() {
        const el = document.getElementById('initial-state-souvera_central-branding')
        if (!el) { return null }
        try { return JSON.parse(atob(el.value)) } catch (e) { return null }
    }

    const cfg = loadBranding()
    if (!cfg || !cfg.names) { return }
    const names = cfg.names
    const icons = cfg.icons || {}

    // ---- Titel-Interceptor: Vue-Apps schreiben document.title clientseitig
    //      („Talk – …“) — jeder Schreibvorgang wird SOFORT umbenannt, kein
    //      Observer-Rennen im Tab. -------------------------------------------
    const TITLE_RULES = [
        { words: ['Nextcloud Talk', 'Talk'], to: (names.spreed || 'Link') },
        { words: ['Nextcloud Office', 'Collabora Online', 'Collabora', 'Office'], to: (names.richdocuments || 'Desk') }
    ]

    function renameTitleString(title) {
        if (typeof title !== 'string' || title === '') { return title }
        for (let i = 0; i < TITLE_RULES.length; i++) {
            const rule = TITLE_RULES[i]
            const re = wordsRegex(rule.words, 'g')
            if (re.test(title)) { return title.replace(re, rule.to) }
        }
        return title
    }

    try {
        const proto = Object.getPrototypeOf(document)
        const desc = Object.getOwnPropertyDescriptor(proto, 'title')
            || Object.getOwnPropertyDescriptor(Document.prototype, 'title')
        if (desc && desc.get && desc.set && !document.__souveraTitleHooked) {
            Object.defineProperty(document, 'title', {
                configurable: true,
                get: function () { return desc.get.call(document) },
                set: function (v) {
                    try { v = renameTitleString(v) } catch (e) { /* noop */ }
                    desc.set.call(document, v)
                }
            })
            document.__souveraTitleHooked = true
        }
    } catch (e) { /* noop */ }

    // ---- Souvera-Header: Fallback-Loader ------------------------------------
    // Der Listener liefert header.css/-js STATISCH im Head (vor dem First
    // Paint — kein FOUC). NC bustert App-Assets pro Datei (?v= ändert sich
    // pro Deploy). Dieser Loader greift nur, falls der statische Link fehlt
    // (alter Cache-Stand des Bootstrap-Scripts).
    if (cfg.header && cfg.header.enabled === true) {
        try {
            if (!document.querySelector('link[href*="souvera_central-header.css"]')) {
                var bust = '?t=' + Date.now()
                var css = document.createElement('link')
                css.rel = 'stylesheet'
                css.href = OC.filePath('souvera_central', 'css', 'souvera_central-header.css') + bust
                document.head.appendChild(css)
            }
            if (!document.querySelector('script[src*="souvera_central-header.js"]')) {
                var js = document.createElement('script')
                js.src = OC.filePath('souvera_central', 'js', 'souvera_central-header.js') + '?t=' + Date.now()
                js.defer = true
                document.head.appendChild(js)
            }
        } catch (e) { /* noop */ }
    }

    // Ursprüngliche Produktbezeichnungen je App-ID (für Text-/Icon-Erkennung im
    // Dashboard-Widget-Titel, der eine ganze Phrase sein kann, z. B. „Talk
    // Erwähnungen"). Von der spezifischsten zur allgemeinsten Phrase.
    const DASH_RULES = [
        { words: ['Nextcloud Talk', 'Talk'], to: (names.spreed || 'Link'), icon: icons.spreed || null },
        { words: ['Nextcloud Office', 'Collabora Online', 'Collabora', 'Office'], to: (names.richdocuments || 'Desk'), icon: icons.richdocuments || null }
    ]

    function reEscape(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') }
    function wordsRegex(words, flags) {
        return new RegExp('\\b(' + words.map(reEscape).join('|') + ')\\b', flags)
    }

    // ---- Theme-Erkennung + kontextabhängige Icon-Farbe -----------------------
    // Icon-Vorlage ist schwarzes Linien-Motiv auf Transparenz.
    //   WEISS  = brightness(0) invert(1)   (schwarz -> weiss)
    //   SCHWARZ = brightness(0)             (bleibt schwarz)
    const FILTER_WHITE = 'brightness(0) invert(1)'
    const FILTER_BLACK = 'brightness(0)'

    function parseColorToRgb(str) {
        str = (str || '').trim()
        if (str === '') { return null }
        if (str.charAt(0) === '#') {
            let h = str.slice(1)
            if (h.length === 3) { h = h.charAt(0) + h.charAt(0) + h.charAt(1) + h.charAt(1) + h.charAt(2) + h.charAt(2) }
            if (h.length >= 6) {
                return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)]
            }
            return null
        }
        const m = str.match(/rgba?\(([^)]+)\)/)
        if (m) {
            const p = m[1].split(',').map(function (s) { return parseFloat(s) })
            if (p.length >= 3) { return [p[0], p[1], p[2]] }
        }
        return null
    }

    // Effektiven Hintergrund (--color-main-background) auswerten → Dark, wenn dunkel.
    function isDarkMode() {
        try {
            const bg = getComputedStyle(document.body).getPropertyValue('--color-main-background')
            const rgb = parseColorToRgb(bg)
            if (rgb) {
                const lum = (0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2]) / 255
                return lum < 0.5
            }
        } catch (e) { /* noop */ }
        return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
    }

    // App-Menü: Kreis ist farbig/hell → Light=WEISS, Dark=SCHWARZ.
    function menuFilter() { return isDarkMode() ? FILTER_BLACK : FILTER_WHITE }
    // Dashboard-Panel: Fläche hell/dunkel → Light=SCHWARZ, Dark=WEISS.
    function dashFilter() { return isDarkMode() ? FILTER_WHITE : FILTER_BLACK }

    function nameFor(appId) {
        return appId && Object.prototype.hasOwnProperty.call(names, appId) ? names[appId] : null
    }

    function iconFor(appId) {
        return appId && Object.prototype.hasOwnProperty.call(icons, appId) ? icons[appId] : null
    }

    // App-ID aus einem /apps/<id>-Pfad (href oder location) extrahieren.
    function appIdFromPath(path) {
        const m = (path || '').match(/\/apps\/([^/?#]+)/)
        return m ? m[1] : null
    }

    // ---- Base64 <-> UTF-8 (NCs State ist base64 von UTF-8-Bytes; atob/btoa
    //      allein zerlegen Umlaute) ------------------------------------------
    function b64ToUtf8(b64) {
        const bin = atob(b64)
        try {
            if (typeof TextDecoder !== 'undefined') {
                return new TextDecoder('utf-8').decode(Uint8Array.from(bin, function (c) { return c.charCodeAt(0) }))
            }
            return decodeURIComponent(escape(bin))
        } catch (e) { return bin }
    }

    function utf8ToB64(str) {
        try {
            const bytes = new TextEncoder().encode(str)
            let bin = ''
            for (let i = 0; i < bytes.length; i++) { bin += String.fromCharCode(bytes[i]) }
            return btoa(bin)
        } catch (e) { return btoa(unescape(encodeURIComponent(str))) }
    }

    // ---- 1) Initial-State patchen (flackerfrei, best effort) ----------------
    function patchInitialApps() {
        const el = document.getElementById('initial-state-core-apps')
        if (!el || el.getAttribute('data-souvera-branded') === '1') { return }
        let apps
        try { apps = JSON.parse(b64ToUtf8(el.value)) } catch (e) { return }
        if (!Array.isArray(apps)) { return }
        let changed = false
        apps.forEach(function (app) {
            if (!app || !app.id) { return }
            const nn = nameFor(app.id)
            if (nn && app.name !== nn) { app.name = nn; changed = true }
            const ic = iconFor(app.id)
            if (ic && app.icon !== ic) { app.icon = ic; changed = true }
        })
        if (changed) {
            try { el.value = utf8ToB64(JSON.stringify(apps)) } catch (e) { /* noop */ }
        }
        el.setAttribute('data-souvera-branded', '1')
    }

    // ---- 2) DOM-Fallback ----------------------------------------------------
    // Nur den ersten echten Textknoten ersetzen, damit z. B. ein „unread"-Span
    // erhalten bleibt.
    function setLabelText(el, newName) {
        if (!el) { return }
        for (let i = 0; i < el.childNodes.length; i++) {
            const n = el.childNodes[i]
            if (n.nodeType === 3 && n.nodeValue && n.nodeValue.trim() !== '') {
                if (n.nodeValue.trim() !== newName) { n.nodeValue = newName }
                return
            }
        }
        if (el.textContent !== newName) { el.textContent = newName }
    }

    function setAttr(el, attr, newName) {
        if (el && el.getAttribute(attr) !== newName) { el.setAttribute(attr, newName) }
    }

    // Icon-<img> auf unser Motiv umbiegen; Maske/Filter neutralisieren und den
    // übergebenen Farb-Filter (WEISS/SCHWARZ, theme-abhängig) anwenden.
    function applyIcon(img, url, filterVal) {
        if (!img || !url) { return }
        if (img.getAttribute('src') !== url) { img.setAttribute('src', url) }
        img.style.setProperty('mask', 'none', 'important')
        img.style.setProperty('-webkit-mask', 'none', 'important')
        img.style.setProperty('filter', filterVal, 'important')
    }

    // Kachel im Waffle-Grid (a.app-item).
    function renameAppItem(anchor, appId) {
        const newName = nameFor(appId)
        if (!newName) { return }
        setAttr(anchor, 'title', newName)
        setLabelText(anchor.querySelector('.app-item__label'), newName)
        const url = iconFor(appId)
        if (url) { applyIcon(anchor.querySelector('.app-item__icon'), url, menuFilter()) }
    }

    function renameAppMenu() {
        Object.keys(names).forEach(function (appId) {
            document.querySelectorAll('a.app-item[href*="/apps/' + appId + '"]').forEach(function (anchor) {
                renameAppItem(anchor, appId)
            })
        })
    }

    // Header-Button der aktuell geöffneten App.
    function renameCurrentApp() {
        const appId = appIdFromPath(window.location.pathname)
        const newName = nameFor(appId)
        if (!newName) { return }
        document.querySelectorAll('.app-menu__current-app-name').forEach(function (el) {
            setLabelText(el, newName)
        })
        const url = iconFor(appId)
        if (url) {
            document.querySelectorAll('.app-menu__current-app-icon').forEach(function (img) {
                applyIcon(img, url, menuFilter())
            })
        }
    }

    // Seitentitel (…„Talk" → …„Link").
    function renameTitle() {
        const newName = nameFor(appIdFromPath(window.location.pathname))
        if (!newName) { return }
        const parts = document.title.split(' - ')
        if (parts.length >= 2 && parts[0] !== newName) {
            parts[0] = newName
            document.title = parts.join(' - ')
        }
    }

    // Favicon/Tab-Icon der aktuell geöffneten App (kommt aus der Theming-App und
    // basiert auf dem Original-App-Icon). Für Apps mit Souvera-Icon umbiegen.
    function renameFavicon() {
        const appId = appIdFromPath(window.location.pathname)
        const url = iconFor(appId)
        if (!url) { return }
        document.querySelectorAll('link[rel~="icon"], link[rel="apple-touch-icon"], link[rel="shortcut icon"]').forEach(function (link) {
            if (link.getAttribute('href') !== url) {
                link.setAttribute('href', url)
                link.setAttribute('type', 'image/png')
            }
        })
    }

    // Icon im Dashboard-Widget-Header umbiegen (theme-abhängige Farbe via dashFilter).
    function swapDashIcon(h2, url) {
        const img = h2.querySelector('img')
        if (img) {
            applyIcon(img, url, dashFilter())
            return
        }
        const span = h2.querySelector('span')
        if (!span) { return }
        if (span.getAttribute('data-souvera-icon') !== url) {
            span.style.setProperty('background-image', 'url("' + url + '")', 'important')
            span.style.setProperty('background-size', 'contain', 'important')
            span.style.setProperty('background-repeat', 'no-repeat', 'important')
            span.style.setProperty('background-position', 'center', 'important')
            span.style.setProperty('mask', 'none', 'important')
            span.style.setProperty('-webkit-mask', 'none', 'important')
            span.style.setProperty('display', 'inline-block')
            if (!span.style.width) { span.style.setProperty('width', '20px') }
            if (!span.style.height) { span.style.setProperty('height', '20px') }
            span.setAttribute('data-souvera-icon', url)
        }
        // Farb-Filter immer aktualisieren (reagiert auf Theme-Wechsel).
        span.style.setProperty('filter', dashFilter(), 'important')
    }

    // Dashboard-Widgets (App „Dashboard"): Panel-Titel enthalten ganze Phrasen
    // wie „Talk Erwähnungen". Wortweise ersetzen + Icon tauschen.
    function renameDashboard() {
        document.querySelectorAll('#app-dashboard .panel--header h2').forEach(function (h2) {
            const text = h2.textContent || ''
            for (let i = 0; i < DASH_RULES.length; i++) {
                const rule = DASH_RULES[i]
                if (!wordsRegex(rule.words).test(text)) { continue }
                const reG = wordsRegex(rule.words, 'g')
                for (let j = 0; j < h2.childNodes.length; j++) {
                    const n = h2.childNodes[j]
                    if (n.nodeType === 3 && n.nodeValue) {
                        const rep = n.nodeValue.replace(reG, rule.to)
                        if (rep !== n.nodeValue) { n.nodeValue = rep }
                    }
                }
                if (rule.icon) { swapDashIcon(h2, rule.icon) }
                break
            }
        })
    }

    let scheduled = false
    function apply() {
        if (scheduled) { return }
        scheduled = true
        window.requestAnimationFrame(function () {
            scheduled = false
            try { renameAppMenu() } catch (e) { /* noop */ }
            try { renameCurrentApp() } catch (e) { /* noop */ }
            try { renameFavicon() } catch (e) { /* noop */ }
            try { renameDashboard() } catch (e) { /* noop */ }
            try { renameTitle() } catch (e) { /* noop */ }
        })
    }

    function run() {
        // Initial-State so früh wie möglich patchen (vor Core, falls Reihenfolge passt).
        try { patchInitialApps() } catch (e) { /* noop */ }
        apply()
        // Gesamtes Dokument beobachten (App-Menü/Popover werden von Vue als Portal
        // an <body> gehängt). Debounced via rAF.
        try {
            new MutationObserver(apply).observe(document.body, { childList: true, subtree: true })
        } catch (e) { /* noop */ }
        // Auf Theme-Wechsel (Dark/Light) reagieren, damit die Icon-Farbe nachgezogen
        // wird: Attribute an <html>/<body> (NC setzt data-theme*/Klassen) + System-
        // Präferenz.
        try {
            new MutationObserver(apply).observe(document.documentElement, { attributes: true })
            new MutationObserver(apply).observe(document.body, { attributes: true })
        } catch (e) { /* noop */ }
        try {
            const mq = window.matchMedia('(prefers-color-scheme: dark)')
            if (mq.addEventListener) { mq.addEventListener('change', apply) }
            else if (mq.addListener) { mq.addListener(apply) }
        } catch (e) { /* noop */ }
        // Absicherung gegen späte Hydration in den ersten Sekunden.
        let ticks = 0
        const iv = setInterval(function () {
            apply()
            if (++ticks >= 20) { clearInterval(iv) }
        }, 500)
    }

    // Initial-State-Patch sofort versuchen (noch vor DOMContentLoaded), damit
    // Core beim Mounten ggf. schon unsere Werte liest.
    try { patchInitialApps() } catch (e) { /* noop */ }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run)
    } else {
        run()
    }
})()
