/**
 * Souvera Central - Instanzweite App-Umbenennung (Fallback)
 *
 * Benennt die Anzeigenamen von Talk (spreed) und Office/Collabora
 * (richdocuments) in „Link" bzw. „Desk" um. HINWEIS: Der SAUBERE, dauerhafte
 * Weg ist der Theme-l10n-Override (occ souvera:branding:install-theme bzw. der
 * automatische Repair-Step) – das App-Menü oben wird von Vue gerendert und kann
 * per JS nur „best effort" korrigiert werden. Dieses Skript ist die Absicherung
 * für Instanzen ohne aktives Theme: es re-appliziert bei jedem Vue-Re-Render.
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

    // Bekannte Original-Namen je App-ID (zum Erkennen der Textknoten).
    const OLD_NAMES = {
        spreed: ['Talk', 'Nextcloud Talk'],
        richdocuments: ['Office', 'Nextcloud Office', 'Collabora', 'Collabora Online', 'Collabora Online Development Edition'],
        richdocumentscode: ['Office', 'Nextcloud Office', 'Collabora', 'Collabora Online'],
    }

    function nameFor(appId) {
        return appId && Object.prototype.hasOwnProperty.call(names, appId) ? names[appId] : null
    }

    function setText(el, newName) {
        if (el && el.textContent !== newName) { el.textContent = newName }
    }

    function setAttr(el, attr, newName) {
        if (el && el.hasAttribute(attr) && el.getAttribute(attr) !== newName) {
            el.setAttribute(attr, newName)
        }
    }

    // Innerhalb eines App-Eintrags Labels/Attribute umbenennen.
    function renameEntry(entry, appId) {
        const newName = nameFor(appId)
        if (!newName || !entry) { return }
        const olds = OLD_NAMES[appId] || []

        // 1) Bekannte Label-Elemente.
        entry.querySelectorAll('.app-menu-entry__label, .hidden-visually, .app-menu-entry__name').forEach(function (el) {
            setText(el, newName)
        })
        // 2) Generische Textknoten, deren Inhalt exakt ein Originalname ist.
        entry.querySelectorAll('span, a').forEach(function (el) {
            const txt = (el.textContent || '').trim()
            if (olds.indexOf(txt) !== -1) { setText(el, newName) }
        })
        // 3) title/aria-label am Link und am Eintrag selbst.
        const link = entry.querySelector('a') || (entry.tagName === 'A' ? entry : null)
        ;[link, entry].forEach(function (el) {
            setAttr(el, 'title', newName)
            setAttr(el, 'aria-label', newName)
        })
    }

    function renameAppMenu() {
        Object.keys(names).forEach(function (appId) {
            const selectors = [
                '[data-app-id="' + appId + '"]',
                '#app-' + appId,
                'li.app-menu-entry a[href*="/apps/' + appId + '"]',
                'a.app-menu-entry__link[href*="/apps/' + appId + '/"]',
            ]
            document.querySelectorAll(selectors.join(',')).forEach(function (node) {
                // Auf den umschließenden Eintrag hochgehen, falls ein <a> matcht.
                const entry = node.closest('li, .app-menu-entry') || node
                renameEntry(entry, appId)
            })
        })
    }

    function renameTitle() {
        const match = window.location.pathname.match(/\/apps\/([^/?#]+)/)
        if (!match) { return }
        const newName = nameFor(match[1])
        if (!newName) { return }
        const parts = document.title.split(' - ')
        if (parts.length >= 2 && parts[0] !== newName) {
            parts[0] = newName
            document.title = parts.join(' - ')
        }
    }

    let scheduled = false
    function apply() {
        if (scheduled) { return }
        scheduled = true
        window.requestAnimationFrame(function () {
            scheduled = false
            try { renameAppMenu() } catch (e) { /* noop */ }
            try { renameTitle() } catch (e) { /* noop */ }
        })
    }

    function run() {
        apply()
        // Gesamtes Dokument beobachten (App-Menü + „Mehr"-Popover werden von Vue
        // ggf. außerhalb von #header/als Portal gerendert). Debounced via rAF.
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run)
    } else {
        run()
    }
})()
