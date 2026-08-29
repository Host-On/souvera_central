<?php
/**
 * Souvera User Management - Routes
 *
 * Definiert alle App-Routen für Frontend und API
 */

return [
    'routes' => [
        // Haupt-Seite (Dashboard)
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // Module-Seiten
        ['name' => 'page#dashboard', 'url' => '/dashboard', 'verb' => 'GET'],
        ['name' => 'page#users', 'url' => '/users', 'verb' => 'GET'],
        ['name' => 'page#users_new', 'url' => '/users/new', 'verb' => 'GET'],
        ['name' => 'page#users_edit', 'url' => '/users/edit/{id}', 'verb' => 'GET'],
        ['name' => 'page#groups', 'url' => '/groups', 'verb' => 'GET'],
        ['name' => 'page#sharedMailboxes', 'url' => '/shared-mailboxes', 'verb' => 'GET'],
        ['name' => 'page#settings', 'url' => '/settings', 'verb' => 'GET'],
        ['name' => 'page#changelogs', 'url' => '/changelogs', 'verb' => 'GET'],

        // User API-Routen
        ['name' => 'user_api#getCurrentUser', 'url' => '/api/users/current', 'verb' => 'GET'],
        ['name' => 'user_api#list', 'url' => '/api/users', 'verb' => 'GET'],
        ['name' => 'user_api#search', 'url' => '/api/users/search', 'verb' => 'GET'],
        ['name' => 'user_api#get', 'url' => '/api/users/{id}', 'verb' => 'GET'],
        ['name' => 'user_api#create', 'url' => '/api/users', 'verb' => 'POST'],
        ['name' => 'user_api#update', 'url' => '/api/users/{id}', 'verb' => 'PUT'],
        ['name' => 'user_api#delete', 'url' => '/api/users/{id}', 'verb' => 'DELETE'],
        ['name' => 'user_api#enable', 'url' => '/api/users/{id}/enable', 'verb' => 'POST'],
        ['name' => 'user_api#disable', 'url' => '/api/users/{id}/disable', 'verb' => 'POST'],
        ['name' => 'user_api#wipeDevices', 'url' => '/api/users/{id}/wipe-devices', 'verb' => 'POST'],
        ['name' => 'user_api#resendWelcomeEmail', 'url' => '/api/users/{id}/resend-welcome-email', 'verb' => 'POST'],
        ['name' => 'user_api#makeAdmin', 'url' => '/api/users/{id}/make-admin', 'verb' => 'POST'],
        ['name' => 'user_api#removeAdmin', 'url' => '/api/users/{id}/remove-admin', 'verb' => 'POST'],

        // Groups API-Routen (einfache Liste für User-Management)
        ['name' => 'user_api#listGroups', 'url' => '/api/groups', 'verb' => 'GET'],

        // Group Management API-Routen (vollständige CRUD-Operationen)
        ['name' => 'group_api#list', 'url' => '/api/groups/manage', 'verb' => 'GET'],
        ['name' => 'group_api#get', 'url' => '/api/groups/manage/{id}', 'verb' => 'GET'],
        ['name' => 'group_api#create', 'url' => '/api/groups/manage', 'verb' => 'POST'],
        ['name' => 'group_api#update', 'url' => '/api/groups/manage/{id}', 'verb' => 'PUT'],
        ['name' => 'group_api#delete', 'url' => '/api/groups/manage/{id}', 'verb' => 'DELETE'],
        ['name' => 'group_api#getMembers', 'url' => '/api/groups/manage/{id}/members', 'verb' => 'GET'],
        ['name' => 'group_api#addMember', 'url' => '/api/groups/manage/{id}/members', 'verb' => 'POST'],
        ['name' => 'group_api#removeMember', 'url' => '/api/groups/manage/{id}/members/{userId}', 'verb' => 'DELETE'],

        // Config API-Route
        ['name' => 'user_api#getConfig', 'url' => '/api/config', 'verb' => 'GET'],

        // Mail-Speicher-Pool (Gesamt/verteilt/verfügbar)
        ['name' => 'user_api#getMailStorage', 'url' => '/api/mail-storage', 'verb' => 'GET'],
        ['name' => 'user_api#getMailStorageDistribution', 'url' => '/api/mail-storage/distribution', 'verb' => 'GET'],

        // Settings API-Routen
        ['name' => 'settings_api#getSettings', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'settings_api#updateSettings', 'url' => '/api/settings', 'verb' => 'PUT'],
        ['name' => 'mail_settings_api#getMailSettings', 'url' => '/api/mail-settings', 'verb' => 'GET'],

        // Reseller API-Route
        ['name' => 'reseller_api#getResellerInfo', 'url' => '/api/reseller', 'verb' => 'GET'],

        // Souvera AI: Status-Steuerung + Knowledge-Basis-Verwaltung
        ['name' => 'ai_api#status', 'url' => '/api/ai/status', 'verb' => 'GET'],
        ['name' => 'ai_api#enable', 'url' => '/api/ai/enable', 'verb' => 'POST'],
        ['name' => 'ai_api#disable', 'url' => '/api/ai/disable', 'verb' => 'POST'],
        ['name' => 'ai_api#kbList', 'url' => '/api/ai/kb', 'verb' => 'GET'],
        ['name' => 'ai_api#kbCreate', 'url' => '/api/ai/kb', 'verb' => 'POST'],
        ['name' => 'ai_api#kbGet', 'url' => '/api/ai/kb/{id}', 'verb' => 'GET'],
        ['name' => 'ai_api#kbUpdate', 'url' => '/api/ai/kb/{id}', 'verb' => 'PUT'],
        ['name' => 'ai_api#kbDelete', 'url' => '/api/ai/kb/{id}', 'verb' => 'DELETE'],
        ['name' => 'ai_api#mcpRotate', 'url' => '/api/ai/mcp/rotate', 'verb' => 'POST'],

        // MCP-Endpoint für den internen AI-Agenten (KB, read-only)
        ['name' => 'mcp#call', 'url' => '/mcp', 'verb' => 'POST'],
        ['name' => 'mcp#callGet', 'url' => '/mcp', 'verb' => 'GET'],

        // Debug-Route
        ['name' => 'user_api#debug', 'url' => '/api/debug', 'verb' => 'GET'],

        // Stalwart / Alias API-Routen
        ['name' => 'alias_api#getStatus', 'url' => '/api/stalwart/status', 'verb' => 'GET'],
        ['name' => 'alias_api#list', 'url' => '/api/users/{userId}/aliases', 'verb' => 'GET'],
        ['name' => 'alias_api#add', 'url' => '/api/users/{userId}/aliases', 'verb' => 'POST'],
        ['name' => 'alias_api#remove', 'url' => '/api/users/{userId}/aliases/{alias}', 'verb' => 'DELETE'],
        ['name' => 'alias_api#checkAvailability', 'url' => '/api/aliases/check', 'verb' => 'GET'],

        // Stalwart Postfach-Verwaltung (Admin)
        ['name' => 'alias_api#listMailboxes', 'url' => '/api/stalwart/mailboxes', 'verb' => 'GET'],
        ['name' => 'alias_api#syncMailboxes', 'url' => '/api/stalwart/sync-mailboxes', 'verb' => 'POST'],
        ['name' => 'alias_api#getMailGroup', 'url' => '/api/stalwart/mailgroup', 'verb' => 'GET'],
        ['name' => 'alias_api#getMailbox', 'url' => '/api/users/{userId}/mailbox', 'verb' => 'GET'],
        ['name' => 'alias_api#createMailbox', 'url' => '/api/users/{userId}/mailbox', 'verb' => 'POST'],
        ['name' => 'alias_api#setMailboxQuota', 'url' => '/api/users/{userId}/mailbox/quota', 'verb' => 'POST'],

        // Shared Mailbox API-Routen
        ['name' => 'shared_mailbox_api#list', 'url' => '/api/shared-mailboxes', 'verb' => 'GET'],
        ['name' => 'shared_mailbox_api#create', 'url' => '/api/shared-mailboxes', 'verb' => 'POST'],
        ['name' => 'shared_mailbox_api#get', 'url' => '/api/shared-mailboxes/{id}', 'verb' => 'GET'],
        ['name' => 'shared_mailbox_api#update', 'url' => '/api/shared-mailboxes/{id}', 'verb' => 'PUT'],
        ['name' => 'shared_mailbox_api#delete', 'url' => '/api/shared-mailboxes/{id}', 'verb' => 'DELETE'],
        ['name' => 'shared_mailbox_api#getMembers', 'url' => '/api/shared-mailboxes/{id}/members', 'verb' => 'GET'],
        ['name' => 'shared_mailbox_api#addMember', 'url' => '/api/shared-mailboxes/{id}/members', 'verb' => 'POST'],
        ['name' => 'shared_mailbox_api#removeMember', 'url' => '/api/shared-mailboxes/{id}/members/{userId}', 'verb' => 'DELETE'],

        // Hilfe-Seite (Souvera-User + Souvera-Admins) + BookStack-Doku-Proxy
        ['name' => 'status#devops', 'url' => '/api/status/devops', 'verb' => 'GET'],
        // Changelog-Viewer: Seite (in der Haupt-App) + interner JSON-Feed
        // (Daten kommen aus den öffentlichen CloudManager-Endpunkten,
        // s. ChangelogService).
        ['name' => 'changelog#all', 'url' => '/api/changelogs', 'verb' => 'GET'],
        ['name' => 'help#index', 'url' => '/help', 'verb' => 'GET'],
        ['name' => 'help_api#tree', 'url' => '/api/help/tree', 'verb' => 'GET'],
        ['name' => 'help_api#book', 'url' => '/api/help/books/{id}', 'verb' => 'GET'],
        ['name' => 'help_api#page', 'url' => '/api/help/pages/{id}', 'verb' => 'GET'],
    ],
];
