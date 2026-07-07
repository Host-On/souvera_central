/**
 * Souvera Central - Instanzweite App-Umbenennung
 *
 * Überschreibt global die Anzeigenamen von Talk (spreed) und Office/Collabora
 * (richdocuments) – in Souvera „Link" bzw. „Desk". Läuft auf jeder Seite,
 * benennt die Einträge im App-Menü (obere Leiste + Überlauf) sowie den
 * Seitentitel um und beobachtet spätere DOM-Änderungen.
 */

(function () {
    'use strict'

    function loadBranding() {
        const el = document.getElementById('initial-state-souvera_central-branding')
        if (!el) {
            return null
        }
        try {
            return JSON.parse(atob(el.value))
        } catch (e) {
            return null
        }
    }

    const cfg = loadBranding()
    if (!cfg || !cfg.names) {
        return
    }
    const names = cfg.names

    function nameFor(appId) {
        return appId && Object.prototype.hasOwnProperty.call(names, appId) ? names[appId] : null
    }

    function renameAppMenu() {
        document.querySelectorAll('[data-app-id]').forEach(function (entry) {
            const newName = nameFor(entry.getAttribute('data-app-id'))
            if (!newName) {
                return
            }
            entry.querySelectorAll('.app-menu-entry__label').forEach(function (span) {
                if (span.textContent !== newName) {
                    span.textContent = newName
                }
            })
            const link = entry.querySelector('a')
            if (link) {
                if (link.hasAttribute('title') && link.getAttribute('title') !== newName) {
                    link.setAttribute('title', newName)
                }
                if (link.hasAttribute('aria-label') && link.getAttribute('aria-label') !== newName) {
                    link.setAttribute('aria-label', newName)
                }
            }
        })
    }

    function renameTitle() {
        const match = window.location.pathname.match(/\/apps\/([^/?#]+)/)
        if (!match) {
            return
        }
        const newName = nameFor(match[1])
        if (!newName) {
            return
        }
        // Titel-Format: "<Appname> - <Cloud>" -> ersten Abschnitt ersetzen
        const parts = document.title.split(' - ')
        if (parts.length >= 2 && parts[0] !== newName) {
            parts[0] = newName
            document.title = parts.join(' - ')
        }
    }

    function apply() {
        try { renameAppMenu() } catch (e) { /* noop */ }
        try { renameTitle() } catch (e) { /* noop */ }
    }

    function run() {
        apply()
        const header = document.getElementById('header') || document.body
        if (header) {
            new MutationObserver(apply).observe(header, { childList: true, subtree: true })
        }
        const titleEl = document.querySelector('title')
        if (titleEl) {
            new MutationObserver(function () {
                try { renameTitle() } catch (e) { /* noop */ }
            }).observe(titleEl, { childList: true })
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run)
    } else {
        run()
    }
})()
