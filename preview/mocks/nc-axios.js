/* Standalone preview mock of @nextcloud/axios.
 * Returns canned data so the migrated app renders with realistic content
 * outside of a running Nextcloud instance. */

const users = [
    { id: 'anna.klein@souvera.eu', displayName: 'Anna Klein', enabled: true, groups: ['Geschäftsführung', 'Marketing'], quota: { quota: '5 GB' }, isSouveraUser: true, isSouveraAdmin: true, type: 'souvera' },
    { id: 'ben.schulz@souvera.eu', displayName: 'Ben Schulz', enabled: true, groups: ['Vertrieb'], quota: { quota: '10 GB' }, isSouveraUser: true, type: 'souvera' },
    { id: 'clara.weber@souvera.eu', displayName: 'Clara Weber', enabled: true, groups: ['Marketing'], quota: { quota: '5 GB' }, isSouveraUser: true, type: 'souvera' },
    { id: 'david.meyer@souvera.eu', displayName: 'David Meyer', enabled: false, groups: ['Vertrieb', 'Support'], quota: { quota: '2 GB' }, isSouveraUser: false, type: 'nextcloud' },
    { id: 'eva.fischer@souvera.eu', displayName: 'Eva Fischer', enabled: true, groups: ['Support'], quota: { quota: '5 GB' }, isSouveraUser: true, type: 'souvera' },
    { id: 'felix.wagner@souvera.eu', displayName: 'Felix Wagner', enabled: true, groups: ['Entwicklung'], quota: { quota: '20 GB' }, isSouveraUser: true, type: 'souvera' },
    { id: 'greta.hoffmann@souvera.eu', displayName: 'Greta Hoffmann', enabled: true, groups: ['Entwicklung', 'Geschäftsführung'], quota: { quota: '20 GB' }, isSouveraUser: false, type: 'nextcloud' },
    { id: 'scadmin@souvera.eu', displayName: 'Souvera Administrator', enabled: true, groups: ['souvera-admins', 'souvera-users'], quota: { quota: 'Unbegrenzt' }, isSouveraUser: true, isSouveraAdmin: true, isProtected: true, type: 'souvera' },
    { id: 'admin@souvera.eu', displayName: 'Administrator', enabled: true, groups: ['admin'], quota: { quota: 'Unbegrenzt' }, isSouveraUser: true, isSouveraAdmin: true, type: 'souvera' }
]

// Im UID-Schema entspricht die User-ID der E-Mail; explizit setzen für den Editor.
users.forEach((u) => { u.email = u.email || u.id })

const groups = [
    { id: 'admin', displayName: 'admin', userCount: 1, isProtected: true },
    { id: 'Geschäftsführung', displayName: 'Geschäftsführung', userCount: 2, isProtected: false },
    { id: 'Marketing', displayName: 'Marketing', userCount: 2, isProtected: false },
    { id: 'Vertrieb', displayName: 'Vertrieb', userCount: 2, isProtected: false },
    { id: 'Support', displayName: 'Support', userCount: 2, isProtected: false },
    { id: 'Entwicklung', displayName: 'Entwicklung', userCount: 2, isProtected: false }
]

const mailboxes = [
    { id: 'info', name: 'Info', email: 'info@souvera.eu', memberCount: 4 },
    { id: 'support', name: 'Support', email: 'support@souvera.eu', memberCount: 3 },
    { id: 'sales', name: 'Vertrieb', email: 'sales@souvera.eu', memberCount: 2 }
]

// --- BIMI Preview-Helfer ---
function bimiDmarc(domain) {
    return { found: true, enforced: true, policy: 'reject', pct: 100, rua: 'mailto:dmarc@' + domain, record: 'v=DMARC1; p=reject; rua=mailto:dmarc@' + domain, issues: [], checkedAt: new Date().toISOString() }
}
function bimiPayload(domain, opts) {
    opts = opts || {}
    const base = 'https://cloud.souvera.eu/apps/souvera_central'
    const hasLogo = opts.hasLogo !== undefined ? opts.hasLogo : true
    const ready = opts.ready !== undefined ? opts.ready : true
    const vmcMode = opts.vmcMode || 'pem'
    const vmcUrl = vmcMode === 'none' ? null : base + '/bimi/' + domain + '/vmc.pem'
    let record = 'v=BIMI1; l=' + base + '/bimi/' + domain + '/logo.svg'
    if (vmcUrl) record += '; a=' + vmcUrl
    if (opts.record === null) record = null
    return {
        domain, selector: 'default', host: 'default._bimi.' + domain, type: 'TXT',
        record,
        logoUrl: base + '/bimi/' + domain + '/logo.svg',
        vmcUrl, vmcMode, hasLogo, svgSize: hasLogo ? 3120 : 0,
        dmarc: bimiDmarc(domain), dmarcEnforced: true,
        ready, status: ready ? 'ready' : 'incomplete', updatedAt: new Date().toISOString(),
    }
}

function respond(url) {
    // --- BIMI (Preview-Mock) ---
    const bimiMatch = url.match(/\/api\/bimi\/([^/?]+)/)
    if (url.includes('/api/bimi')) {
        const domain = bimiMatch ? decodeURIComponent(bimiMatch[1]) : 'souvera.eu'
        if (url.includes('/logo')) {
            return { data: { ok: true, size: 2480, warnings: ['<title> ergänzt (in SVG P/S erforderlich).', 'viewBox aus width/height ergänzt.'], payload: bimiPayload(domain, { hasLogo: true, ready: true }) } }
        }
        if (url.includes('/check-dmarc')) {
            return { data: bimiDmarc(domain) }
        }
        if (url.includes('/vmc')) {
            return { data: { ok: true, payload: bimiPayload(domain, { hasLogo: true, ready: true, vmcMode: 'url' }) } }
        }
        if (url.match(/\/api\/bimi\/?$/)) {
            return { data: { domains: [bimiPayload('souvera.eu', { hasLogo: true, ready: true })] } }
        }
        // GET einzelne Domain: sauberer Ausgangszustand (DMARC ok, noch kein Logo)
        return { data: bimiPayload(domain, { hasLogo: false, ready: false, record: null }) }
    }
    if (url.includes('/api/public/bimi')) {
        const domain = bimiMatch ? decodeURIComponent(bimiMatch[1]) : 'souvera.eu'
        return { data: bimiPayload(domain, { hasLogo: true, ready: true }) }
    }

    // --- Hilfe / BookStack (Preview-Mock) ---
    if (url.includes('/api/help/tree')) {
        return { data: helpTree }
    }
    const helpBookMatch = url.match(/\/api\/help\/books\/(\d+)/)
    if (helpBookMatch) {
        return { data: helpBooks[helpBookMatch[1]] || { id: Number(helpBookMatch[1]), name: 'Buch', contents: [] } }
    }
    const helpPageMatch = url.match(/\/api\/help\/pages\/(\d+)/)
    if (helpPageMatch) {
        return { data: helpPages[helpPageMatch[1]] || { id: Number(helpPageMatch[1]), name: 'Seite', book_id: 0, html: '<p>Kein Inhalt.</p>' } }
    }
    if (url.includes('/api/config')) {
        return { data: { total_users: 8, used_licenses: 5, max_licenses: 25, max_groups: 20, max_shared_mailboxes: 10, warning_threshold: 0.8, allowed_domains: ['souvera.eu'], scadmin_group: 'scadmin', souvera_group: 'souvera-users' } }
    }
    if (url.includes('/api/stalwart/status')) {
        return { data: { configured: true, available: true, url: 'http://10.20.0.40:8080' } }
    }
    if (url.includes('/api/stalwart/sync-mailboxes')) {
        return { data: { success: true, created: 2, skipped: 6, noMail: 0, errors: 0, grouped: 6, mailGroup: { id: 'souvera-users', displayName: 'Souvera Users', exists: true, members: 6, enabled: true } } }
    }
    if (url.includes('/api/stalwart/mailgroup')) {
        return { data: { id: 'souvera-users', displayName: 'Souvera Users', exists: true, members: 6, enabled: true } }
    }
    if (url.includes('/api/stalwart/mailboxes')) {
        return { data: { configured: true, available: true, mailboxes: mailboxNames, usage: mailboxUsage, total: mailboxNames.length } }
    }
    if (url.includes('/aliases')) {
        return { data: { aliases: ['team@souvera.eu', 'kontakt@souvera.eu'], maxAliases: 10 } }
    }
    if (url.includes('/mailbox/quota')) {
        return { data: { success: true, quota: 5368709120 } }
    }
    if (url.includes('/mailbox')) {
        return { data: { success: true, created: true, exists: true, email: 'anna.klein@souvera.eu', aliases: [], quota: 5368709120 } }
    }
    if (url.includes('/api/users/current')) {
        return { data: { id: 'admin@souvera.eu', displayName: 'Administrator', isAdmin: true } }
    }
    if (url.includes('/api/users')) {
        return { data: { users, total: users.length, usedLicenses: 5, maxLicenses: 25 } }
    }
    if (url.includes('/api/groups/manage')) {
        return { data: { groups, total: groups.length } }
    }
    if (url.includes('/api/groups')) {
        return { data: { groups, total: groups.length } }
    }
    if (url.includes('/api/shared-mailboxes')) {
        return { data: { mailboxes, total: mailboxes.length } }
    }
    if (url.includes('/api/reseller')) {
        return { data: { support_url: 'https://support.souvera.eu', url: 'https://souvera.eu', name: 'Souvera' } }
    }
    if (url.includes('/api/settings')) {
        return { data: { visibility: { manager: true, groups: true, storage_location: false, last_login: true, email: true, backend: false }, sorting: { groups: 'displayName' }, email: { send_to_new_users: false }, defaults: { quota: 'default' }, shield: { desktop_notifications: true, daily_summary: true, min_spam_score: 2.5 } } }
    }
    return { data: {} }
}

// Postfächer: 6 von 8 Benutzern haben ein Stalwart-Postfach (david + greta fehlen)
const mailboxNames = [
    'anna.klein@souvera.eu',
    'ben.schulz@souvera.eu',
    'clara.weber@souvera.eu',
    'eva.fischer@souvera.eu',
    'felix.wagner@souvera.eu',
    'admin@souvera.eu'
]

// Belegung + Limit je Postfach (Bytes; quota 0 = unbegrenzt)
const mailboxUsage = {
    'anna.klein@souvera.eu': { used: 48318382080, quota: 53687091200 },
    'ben.schulz@souvera.eu': { used: 5368709120, quota: 53687091200 },
    'clara.weber@souvera.eu': { used: 42949672960, quota: 53687091200 },
    'eva.fischer@souvera.eu': { used: 1073741824, quota: 10737418240 },
    'felix.wagner@souvera.eu': { used: 2147483648, quota: 0 },
    'admin@souvera.eu': { used: 21474836480, quota: 53687091200 }
}

const helpTree = {
    configured: true,
    shelves: [
        { id: 1, name: 'Benutzer', books: [
            { id: 10, name: 'Erste Schritte' },
            { id: 11, name: 'Mail & Postfach' }
        ] },
        { id: 2, name: 'Administratoren', books: [
            { id: 20, name: 'Betrieb & Wartung' }
        ] }
    ]
}
const helpBooks = {
    10: { id: 10, name: 'Erste Schritte', contents: [
        { type: 'page', id: 100, name: 'Willkommen bei Souvera' },
        { type: 'chapter', id: 150, name: 'Anmeldung', pages: [
            { id: 101, name: 'Passwort ändern' },
            { id: 102, name: 'Zwei-Faktor-Authentifizierung' }
        ] }
    ] },
    11: { id: 11, name: 'Mail & Postfach', contents: [
        { type: 'page', id: 110, name: 'Postfach einrichten' }
    ] },
    20: { id: 20, name: 'Betrieb & Wartung', contents: [
        { type: 'page', id: 200, name: 'Backups' }
    ] }
}
const helpPages = {
    100: { id: 100, name: 'Willkommen bei Souvera', book_id: 10, html: '<p><strong>Souvera Mail</strong> ist deine E-Mail-Anwendung in Nextcloud. Du kannst Nachrichten lesen und schreiben, Anhänge direkt aus deinem Nextcloud-Dateispeicher hinzufügen, ICS-Kalendereinladungen speichern, Serverfilter (Sieve) verwalten und externe Mail-Programme wie Thunderbird oder K-9 Mail zusätzlich einrichten.</p><div class="screenshot-placeholder" style="border:2px dashed #94a3b8;padding:1.5em;margin:1.2em 0;text-align:center;border-radius:8px;"><strong>🖼️ Screenshot einfügen</strong><br><em>Kachel-Übersicht in Nextcloud mit dem Souvera-Mail-Icon oben in der App-Leiste hervorgehoben.</em></div><h2>Was Souvera Mail für dich tut</h2><ul><li><strong>Webmail direkt im Browser</strong> — kein zusätzliches Programm nötig.</li><li><strong>Kontakte &amp; Kalender integriert</strong> — Empfänger werden automatisch aus deinem Nextcloud-Adressbuch vorgeschlagen.</li><li><strong>Dateien anhängen</strong> — sowohl vom Computer als auch direkt aus deinem Nextcloud-Dateispeicher.</li></ul><div style="border-left:4px solid #3b82f6;padding:0.9em 1.1em;margin:1em 0;border-radius:4px;"><div style="font-weight:600;margin-bottom:0.3em;">💡 Wie öffne ich Souvera Mail?</div><div>Klicke oben in der Nextcloud-Leiste auf das <strong>Umschlag-Symbol</strong>. Ist es dort nicht sichtbar, findest du es unter <em>„Weitere Apps“</em> — oder frage deinen Administrator, dich für die Gruppe <code>souvera-users</code> freizuschalten.</div></div><h3>Beispiel-Screenshot</h3><p><img src="https://doku.souvera.eu/uploads/images/gallery/2026-07/scaled-1680-/bildschirmfoto-2026-07-04-um-16-19-39.png" alt="Beispiel-Screenshot der Oberfläche"></p>' },
    101: { id: 101, name: 'Passwort ändern', book_id: 10, html: '<h2>Passwort ändern</h2><p>So ändern Sie Ihr Passwort in wenigen Schritten.</p><ol><li>Öffnen Sie die Einstellungen.</li><li>Wählen Sie „Sicherheit".</li><li>Vergeben Sie ein neues, starkes Passwort.</li></ol>' },
    102: { id: 102, name: 'Zwei-Faktor-Authentifizierung', book_id: 10, html: '<h2>Zwei-Faktor-Authentifizierung</h2><p>Erhöhen Sie die Sicherheit Ihres Kontos mit einem zweiten Faktor.</p>' },
    110: { id: 110, name: 'Postfach einrichten', book_id: 11, html: '<h2>Postfach einrichten</h2><p>Richten Sie Ihr E-Mail-Postfach auf allen Geräten ein.</p>' },
    200: { id: 200, name: 'Backups', book_id: 20, html: '<h2>Backups</h2><p>Interne Admin-Dokumentation zur Datensicherung.</p>' }
}

const mock = {
    get: (url) => Promise.resolve(respond(url)),
    post: (url) => Promise.resolve(respond(url)),
    put: (url) => Promise.resolve(respond(url)),
    patch: (url) => Promise.resolve(respond(url)),
    delete: (url) => Promise.resolve({ data: {} })
}

export default mock
export const get = mock.get
export const post = mock.post
export const put = mock.put
export const patch = mock.patch
