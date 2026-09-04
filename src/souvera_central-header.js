/**
 * Souvera Central - Souvera-Header (v34-Header-Umbau)
 *
 * Baut die gepinnten App-Buttons direkt in den Nextcloud-Header:
 *
 *   [Icon] [Dashboard][Dateien][Mail][Link][Deck][Kalender][Central][Mehr ▾] … [Suche]
 *
 * Datenquellen:
 *   - `#initial-state-core-apps`        → alle Apps (id, name, icon, href)
 *   - `#initial-state-souvera_central-branding` → header.enabled/pinned/adminOnly
 *     + isSouveraAdmin (server-seitig geprüft)
 *
 * „Mehr" = der bestehende v34-App-Menu-Trigger (Grid-Icon), wird ans Ende
 * der Buttons umgehängt — das Dropdown bleibt NC-synchron.
 *
 * Failsafe: Alle Schritte guarded; ohne DOM/State passiert nichts. Die
 * Notbremse ist `souvera_central.branding.header.enabled = 0`.
 */

(function () {
    'use strict'

    var HEADER_ID = 'souvera-header-apps'

    function loadBranding() {
        var el = document.getElementById('initial-state-souvera_central-branding')
        if (!el) { return null }
        try { return JSON.parse(atob(el.value)) } catch (e) { return null }
    }

    function loadCoreApps() {
        var el = document.getElementById('initial-state-core-apps')
        if (!el) { return [] }
        try {
            var apps = JSON.parse(atob(el.value))
            return Array.isArray(apps) ? apps : []
        } catch (e) { return [] }
    }

    function headerConfig() {
        var cfg = loadBranding()
        var header = cfg && cfg.header
        if (!header || header.enabled !== true) { return null }
        header.pinned = Array.isArray(header.pinned) ? header.pinned : []
        header.adminOnly = Array.isArray(header.adminOnly) ? header.adminOnly : []
        return header
    }

    // App anhand der ID finden; Fallback: href-Match auf /apps/<id>
    function findApp(apps, appId) {
        for (var i = 0; i < apps.length; i++) {
            var a = apps[i]
            if (a && a.id === appId) { return a }
        }
        for (var j = 0; j < apps.length; j++) {
            var b = apps[j]
            if (b && typeof b.href === 'string' && b.href.indexOf('/apps/' + appId) !== -1) { return b }
        }
        return null
    }

    function pathOf(href) {
        var m = (href || '').match(/\/apps\/([^/?#]+)/)
        return m ? '/apps/' + m[1] : (href || '')
    }

    function isActive(href) {
        var target = pathOf(href)
        if (target === '') { return false }
        var path = window.location.pathname
        return path === target || path.indexOf(target + '/') === 0 || path.indexOf(target + '?') === 0
    }

    function makeIcon(src) {
        var img = document.createElement('img')
        img.setAttribute('src', src)
        img.setAttribute('alt', '')
        img.style.filter = 'brightness(0) invert(1)'
        return img
    }

    function makeLabel(text) {
        var span = document.createElement('span')
        span.className = 'souvera-header-app-label'
        span.textContent = text
        return span
    }

    function headerEl() {
        return document.querySelector('header#header') || document.getElementById('header')
    }

    function logoEl(header) {
        return header ? header.querySelector('#nextcloud') : null
    }

    // Der v34-App-Menu-Trigger (Grid-Icon + Current-App-Anzeige) — wird zum
    // „Mehr"-Button umgehängt. Fallback: null → Trigger bleibt, wo er ist.
    function findMenuTrigger(header) {
        if (!header) { return null }
        return header.querySelector('.app-menu') || header.querySelector('[class*="app-menu"]')
    }

    function buildContainer(header) {
        var existing = document.getElementById(HEADER_ID)
        if (existing && existing.isConnected && header.contains(existing)) {
            return existing
        }
        var logo = logoEl(header)
        if (!logo) { return null }
        var container = existing || document.createElement('div')
        container.id = HEADER_ID
        logo.insertAdjacentElement('afterend', container)
        return container
    }

    function render() {
        var header = headerEl()
        if (!header) { return }

        var cfg = headerConfig()
        if (!cfg) {
            var stale = document.getElementById(HEADER_ID)
            if (stale) { stale.remove() }
            return
        }

        var container = buildContainer(header)
        if (!container) { return }
        container.innerHTML = ''

        var apps = loadCoreApps()

        cfg.pinned.forEach(function (appId) {
            if (header.adminOnly.indexOf(appId) !== -1 && !header.isSouveraAdmin) { return }
            var app = findApp(apps, appId)
            if (!app || !app.href) { return }

            var a = document.createElement('a')
            a.className = 'souvera-header-app'
            a.setAttribute('href', app.href)
            a.setAttribute('data-active', isActive(app.href) ? '1' : '0')
            a.setAttribute('title', app.name || appId)
            a.appendChild(makeIcon(app.icon || ''))
            a.appendChild(makeLabel(app.name || appId))
            container.appendChild(a)
        })

        // „Mehr": bestehenden App-Menu-Trigger umhängen (Dropdown bleibt NC-synchron)
        var trigger = findMenuTrigger(header)
        if (trigger && trigger.parentElement !== container) {
            trigger.classList.add('souvera-header-more')
            container.appendChild(trigger)
        }
    }

    function start() {
        render()
        // SPA-/Vue-Re-Render abfangen: Container weg oder Trigger zurückgesort → neu bauen
        try {
            var observer = new MutationObserver(function () {
                var header = headerEl()
                if (!header) { return }
                var container = document.getElementById(HEADER_ID)
                if (!container || !header.contains(container)) { render() }
            })
            observer.observe(document.body, { childList: true, subtree: true })
        } catch (e) { /* noop */ }
        // Erst-Render-Resilienz: kurze Retry-Phase wie im Branding-Script
        var tries = 0
        var timer = setInterval(function () {
            render()
            if (++tries >= 20) { clearInterval(timer) }
        }, 500)
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start)
    } else {
        start()
    }
})()
