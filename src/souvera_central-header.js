/**
 * Souvera Central - Souvera-Header (v34-Header-Umbau)
 *
 * Baut die gepinnten App-Buttons direkt in den Nextcloud-Header:
 *
 *   [Icon] [Dashboard][Dateien][Mail][Link][Deck][Kalender][Central] [Mehr ▾] … [Suche]
 *
 * Bekannter ECHTER DOM (v34.0.3, aus der Instanz verifiziert):
 *
 *   <header id="header">
 *     <div class="header-start">
 *       <a id="nextcloud"><div class="logo logo-icon"></div></a>
 *       <nav id="header-start__appmenu"></nav>   ← Vue rendert hier Grid-Icon + Appname
 *     </div>
 *     <div class="header-end">
 *       <div id="unified-search"></div><div id="notifications"></div>…
 *     </div>
 *   </header>
 *
 * Regeln (aus dem ersten Versuch gelernt):
 *   1. NIEMALS in von Vue verwaltete Subtrees eingreifen
 *      (`#header-start__appmenu` bleibt unberührt) — kein DOM-Krieg.
 *   2. Eigener Container als GESCHWISTER von #nextcloud, `flex: 0 0 auto`
 *      (kollabiert nicht).
 *   3. „Mehr" = EIGENES Dropdown aus `#initial-state-core-apps` minus
 *      gepinnte Apps.
 *   4. Erfolgs-Marker `html.souvera-header-ok`: Erst wenn unsere Buttons
 *      wirklich hängen, wird das NC-App-Menü per CSS ausgeblendet — bei
 *      JS-Fehler bleibt die Original-Navigation voll funktionfähig.
 *   5. Notbremse: `souvera_central.branding.header.enabled = 0`.
 */

(function () {
    'use strict'

    var HEADER_ID = 'souvera-header-apps'
    var DESKTOP_MIN = 1025
    var OK_MARKER = 'souvera-header-ok'
    var DEBUG = /[?&]souveraDebug=1/.test(window.location.search)

    function log(msg) {
        if (DEBUG) {
            try { console.log('[SouveraHeader] ' + msg) } catch (e) { /* noop */ }
        }
    }

    function loadBranding() {
        var el = document.getElementById('initial-state-souvera_central-branding')
        if (!el) { return null }
        try { return JSON.parse(atob(el.value)) } catch (e) { return null }
    }

    function prettyAppId(appId) {
        return String(appId)
            .replace(/^souvera_/, '')
            .replace(/[-_]+/g, ' ')
            .replace(/^./, function (c) { return c.toUpperCase() })
    }

    /** Base64 → UTF-8 (NCs State ist base64 von UTF-8-Bytes; atob allein
     *  macht aus „Aktivität" ein „AktivitÃ¤t"). */
    function b64ToUtf8(b64) {
        var bin = atob(b64)
        try {
            if (typeof TextDecoder !== 'undefined') {
                return new TextDecoder('utf-8').decode(Uint8Array.from(bin, function (c) { return c.charCodeAt(0) }))
            }
            return decodeURIComponent(escape(bin))
        } catch (e) { return bin }
    }

    function loadCoreApps(cfg, names) {
        var el = document.getElementById('initial-state-core-apps')
        if (!el) { return [] }
        var apps
        try {
            apps = JSON.parse(b64ToUtf8(el.value))
        } catch (e) { return [] }
        if (!Array.isArray(apps)) { return [] }

        // Extra-Apps (Whitelist aus der Branding-Konfig): installierte Apps mit
        // dynamischer Navigation (z. B. souvera_documents), die NICHT im
        // core/apps-State landen — NUR diese ergänzen, niemals alle
        // installierten Apps (Backend-Apps wie dav/comments gehören nicht ins
        // Menü). Link über die NC-Route (/apps/<id>), NICHT über den physischen
        // Webroot (/custom_apps/<id>).
        try {
            var webroots = (window.OC && window.OC.appswebroots) || {}
            var extra = (cfg && Array.isArray(cfg.extraApps)) ? cfg.extraApps : []
            extra.forEach(function (appId) {
                for (var i = 0; i < apps.length; i++) {
                    if (apps[i] && apps[i].id === appId) { return }
                }
                if (!webroots[appId]) { return }
                apps.push({
                    id: appId,
                    name: (names && names[appId]) || prettyAppId(appId),
                    href: OC.generateUrl('/apps/' + appId),
                    icon: webroots[appId] + '/img/app.svg'
                })
            })
        } catch (e) { /* noop */ }
        return apps
    }

    function headerConfig() {
        var cfg = loadBranding()
        var header = cfg && cfg.header
        if (!header || header.enabled !== true) { return null }
        header.pinned = Array.isArray(header.pinned) ? header.pinned : []
        header.adminOnly = Array.isArray(header.adminOnly) ? header.adminOnly : []
        return header
    }

    function findApp(apps, appId) {
        var i, a
        for (i = 0; i < apps.length; i++) {
            a = apps[i]
            if (a && a.id === appId) { return a }
        }
        for (i = 0; i < apps.length; i++) {
            a = apps[i]
            if (a && typeof a.href === 'string' && a.href.indexOf('/apps/' + appId) !== -1) { return a }
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

    function isDarkTheme() {
        try {
            var raw = getComputedStyle(document.body)
                .getPropertyValue('--color-main-background').trim()
            var rgb = null
            if (raw.charAt(0) === '#') {
                var h = raw.slice(1)
                if (h.length === 3) { h = h.charAt(0) + h.charAt(0) + h.charAt(1) + h.charAt(1) + h.charAt(2) + h.charAt(2) }
                if (h.length >= 6) {
                    rgb = [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)]
                }
            } else {
                var m = raw.match(/rgba?\(([^)]+)\)/)
                if (m) {
                    var parts = m[1].split(',').map(function (x) { return parseFloat(x) })
                    if (parts.length >= 3) { rgb = parts.slice(0, 3) }
                }
            }
            if (rgb) {
                // Luminanz < 0.5 → dunkles Theme
                return (0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2]) / 255 < 0.5
            }
        } catch (e) { /* noop */ }
        return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
    }

    /** Filter für Icons auf Panel-Hintergrund (hell → schwarz, dunkel → weiß) */
    function panelIconFilter() {
        return isDarkTheme() ? 'brightness(0) invert(1)' : 'brightness(0)'
    }

    function makeIcon(src) {
        var img = document.createElement('img')
        img.setAttribute('src', src)
        img.setAttribute('alt', '')
        return img
    }

    function makeChevron() {
        var span = document.createElement('span')
        span.className = 'souvera-header-chevron'
        span.setAttribute('aria-hidden', 'true')
        span.textContent = '▾'
        return span
    }

    function headerEl() {
        return document.querySelector('header#header') || document.getElementById('header')
    }

    // ---- „Mehr"-Dropdown (eigenes, außerhalb von Vue-Territorium) -----------

    var openDropdown = null

    function closeDropdown() {
        if (openDropdown) {
            openDropdown.remove()
            openDropdown = null
        }
        document.removeEventListener('click', onOutsideClick, true)
        document.removeEventListener('keydown', onEscape, true)
    }

    function onOutsideClick(e) {
        if (openDropdown && !openDropdown.contains(e.target)) { closeDropdown() }
    }

    function onEscape(e) {
        if (e.key === 'Escape') { closeDropdown() }
    }

    function buildDropdown(remaining) {
        var panel = document.createElement('div')
        panel.className = 'souvera-header-dropdown'
        panel.setAttribute('role', 'menu')

        remaining.forEach(function (app) {
            var a = document.createElement('a')
            a.className = 'souvera-header-dropdown-entry'
            a.setAttribute('href', app.href || '#')
            a.setAttribute('role', 'menuitem')
            var icon = makeIcon(app.icon || '')
            // Mask-Icons (weiß) auf Panel-Hintergrund: je Theme tönen
            icon.style.filter = panelIconFilter()
            a.appendChild(icon)
            var label = document.createElement('span')
            label.textContent = app.name || app.id || ''
            a.appendChild(label)
            panel.appendChild(a)
        })

        return panel
    }

    // ---- Rendering -----------------------------------------------------------

    function headerEl() {
        return document.querySelector('header#header') || document.getElementById('header')
    }

    function buildContainer(headerRoot) {
        var container = document.getElementById(HEADER_ID)
        if (container && container.isConnected && headerRoot.contains(container)) {
            return container
        }
        var logo = headerRoot.querySelector('#nextcloud')
        if (!logo) { return null }
        container = container || document.createElement('div')
        container.id = HEADER_ID
        // Geschwister des Logos in .header-start — kein Vue-Territorium.
        logo.insertAdjacentElement('afterend', container)
        return container
    }

    function cleanupMobile() {
        // Mobil: Original-NC-Header (Hamburger, Assistant, …) — unsere
        // Buttons raus, Marker und Inline-Hides zurücksetzen.
        var container = document.getElementById(HEADER_ID)
        if (container) { container.remove() }
        document.documentElement.className = document.documentElement.className
            .split(/\s+/).filter(function (c) { return c !== OK_MARKER }).join(' ')
        var oldMenu = document.getElementById('header-start__appmenu')
        if (oldMenu) { oldMenu.style.display = '' }
    }

    function render() {
        // Mobil (≤1024px): Original-Header, kein Umbau.
        if (window.innerWidth <= 1024) {
            cleanupMobile()
            // Anti-Flash-Freigabe auch auf Mobil (Original-Header ist final)
            try { document.body.classList.add('souvera-header-ready') } catch (e) { /* noop */ }
            return
        }

        var branding = loadBranding()
        var names = (branding && branding.names) || {}
        var headerRoot = headerEl()
        if (!headerRoot) { log('no header element yet'); return }

        var cfg = headerConfig()
        if (!cfg) { log('header disabled or state missing'); return }

        var container = buildContainer(headerRoot)
        if (!container) { log('no logo anchor — cannot place container'); return }
        container.innerHTML = ''

        var apps = loadCoreApps(cfg, names)
        log('core apps: ' + apps.length + ', pinned: ' + cfg.pinned.join(','))

        cfg.pinned.forEach(function (appId) {
            if (cfg.adminOnly.indexOf(appId) !== -1 && !cfg.isSouveraAdmin) {
                log('skip ' + appId + ' (admin only)')
                return
            }
            var app = findApp(apps, appId)
            if (!app || !app.href) {
                log('skip ' + appId + ' (not in core apps)')
                return
            }

            var a = document.createElement('a')
            a.className = 'souvera-header-app'
            a.setAttribute('href', app.href)
            a.setAttribute('data-active', isActive(app.href) ? '1' : '0')
            a.setAttribute('title', app.name || appId)
            a.appendChild(makeIcon(app.icon || ''))
            var label = document.createElement('span')
            label.className = 'souvera-header-app-label'
            label.textContent = app.name || appId
            a.appendChild(label)
            container.appendChild(a)
        })

        // „Mehr ▾": restliche Apps (core apps minus gepinnte, minus adminOnly)
        var remaining = apps.filter(function (app) {
            if (!app || !app.href) { return false }
            if (cfg.pinned.indexOf(app.id) !== -1) { return false }
            if (cfg.adminOnly.indexOf(app.id) !== -1 && !cfg.isSouveraAdmin) { return false }
            return true
        })

        var more = document.createElement('button')
        more.className = 'souvera-header-more'
        more.setAttribute('type', 'button')
        more.setAttribute('aria-haspopup', 'menu')
        more.setAttribute('aria-expanded', 'false')
        more.setAttribute('title', 'Weitere Apps')
        more.appendChild(makeChevron())
        var moreLabel = document.createElement('span')
        moreLabel.className = 'souvera-header-app-label'
        moreLabel.textContent = 'Mehr'
        more.appendChild(moreLabel)

        more.addEventListener('click', function (e) {
            e.stopPropagation()
            if (openDropdown) {
                closeDropdown()
                more.setAttribute('aria-expanded', 'false')
                return
            }
            openDropdown = buildDropdown(remaining)
            more.setAttribute('aria-expanded', 'true')
            // Unter dem Button positionieren (Header ist position:fixed)
            var rect = more.getBoundingClientRect()
            openDropdown.style.top = (rect.bottom + 6) + 'px'
            openDropdown.style.left = Math.max(8, rect.left - 40) + 'px'
            document.body.appendChild(openDropdown)
            document.addEventListener('click', onOutsideClick, true)
            document.addEventListener('keydown', onEscape, true)
        })
        container.appendChild(more)

        // Erfolgs-Marker + altes NC-App-Menü ausblenden. Robust gegen
        // unbekannte IDs/Klassen: ALLE Kinder von .header-start außer Logo
        // und eigenem Container werden per Inline-Style versteckt (das
        // Grid-Icon + „Current-App"-Display von NC lebt dort). Zusätzlich
        // als CSS-Backup mit dem Marker (siehe header.css).
        // Anti-Flash-Freigabe: Header einblenden (siehe header.css)
        try { document.body.classList.add('souvera-header-ready') } catch (e) { /* noop */ }
        if (document.documentElement.className.indexOf(OK_MARKER) === -1) {
            document.documentElement.className += ' ' + OK_MARKER
        }
        try {
            var startSection = headerRoot.querySelector('.header-start')
            if (startSection) {
                var kids = startSection.children
                for (var k = kids.length - 1; k >= 0; k--) {
                    var kid = kids[k]
                    if (kid.id === 'nextcloud' || kid.id === HEADER_ID) { continue }
                    kid.style.setProperty('display', 'none', 'important')
                }
            }
        } catch (e) { /* noop */ }
        log('rendered ' + cfg.pinned.length + ' pinned + ' + remaining.length + ' more')
    }

    function start() {
        try {
            render()
        } catch (e) {
            log('render error: ' + (e && e.message))
            try { console.error('[SouveraHeader] render failed', e) } catch (e2) { /* noop */ }
            return
        }

        // Resilienz: Nur neu rendern, wenn unser Container ENTFERNT wurde
        // (Vue-/Core-Re-Render). Kein Umbauen fremder Nodes → kein Krieg.
        try {
            var observer = new MutationObserver(function () {
                var header = headerEl()
                if (!header) { return }
                var container = document.getElementById(HEADER_ID)
                if (!container || !header.contains(container)) {
                    try { render() } catch (e) { /* noop */ }
                }
            })
            observer.observe(document.body, { childList: true, subtree: true })
            window.addEventListener('resize', function () { render() })
        } catch (e) { /* noop */ }

        // Erst-Render-Resilienz: kurze Retry-Phase (Vue mountet verzögert)
        var tries = 0
        var timer = setInterval(function () {
            tries++
            var container = document.getElementById(HEADER_ID)
            if (!container || container.children.length === 0) {
                try { render() } catch (e) { /* noop */ }
            }
            if (tries >= 20 || (container && container.children.length > 0)) { clearInterval(timer) }
        }, 500)
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start)
    } else {
        start()
    }
})()
