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

    // ---- 1) Initial-State patchen (flackerfrei, best effort) ----------------
    function patchInitialApps() {
        const el = document.getElementById('initial-state-core-apps')
        if (!el || el.getAttribute('data-souvera-branded') === '1') { return }
        let apps
        try { apps = JSON.parse(atob(el.value)) } catch (e) { return }
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
            try { el.value = btoa(JSON.stringify(apps)) } catch (e) { /* noop */ }
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

    // Icon-<img> auf unser Motiv umbiegen; Maske/Filter neutralisieren und die
    // schwarzen Linien per Filter weiß einfärben (Kontrast auf farbigem Kreis).
    function applyIcon(img, url) {
        if (!img || !url) { return }
        if (img.getAttribute('src') !== url) { img.setAttribute('src', url) }
        img.style.setProperty('mask', 'none', 'important')
        img.style.setProperty('-webkit-mask', 'none', 'important')
        img.style.setProperty('filter', 'brightness(0) invert(1)', 'important')
    }

    // Kachel im Waffle-Grid (a.app-item).
    function renameAppItem(anchor, appId) {
        const newName = nameFor(appId)
        if (!newName) { return }
        setAttr(anchor, 'title', newName)
        setLabelText(anchor.querySelector('.app-item__label'), newName)
        const url = iconFor(appId)
        if (url) { applyIcon(anchor.querySelector('.app-item__icon'), url) }
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
                applyIcon(img, url)
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

    let scheduled = false
    function apply() {
        if (scheduled) { return }
        scheduled = true
        window.requestAnimationFrame(function () {
            scheduled = false
            try { renameAppMenu() } catch (e) { /* noop */ }
            try { renameCurrentApp() } catch (e) { /* noop */ }
            try { renameFavicon() } catch (e) { /* noop */ }
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
