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

function respond(url) {
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
        return { data: { configured: true, available: true, mailboxes: mailboxNames, total: mailboxNames.length } }
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
        return { data: { visibility: { manager: true, groups: true, storage_location: false, last_login: true, email: true, backend: false }, sorting: { groups: 'displayName' }, email: { send_to_new_users: false }, defaults: { quota: 'default', mailbox_quota: 53687091200 }, shield: { desktop_notifications: true, daily_summary: true, min_spam_score: 2.5 } } }
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
