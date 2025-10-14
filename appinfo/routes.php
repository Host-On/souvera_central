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
        ['name' => 'page#settings', 'url' => '/settings', 'verb' => 'GET'],

        // User API-Routen
        ['name' => 'user_api#list', 'url' => '/api/users', 'verb' => 'GET'],
        ['name' => 'user_api#get', 'url' => '/api/users/{id}', 'verb' => 'GET'],
        ['name' => 'user_api#create', 'url' => '/api/users', 'verb' => 'POST'],
        ['name' => 'user_api#update', 'url' => '/api/users/{id}', 'verb' => 'PUT'],
        ['name' => 'user_api#delete', 'url' => '/api/users/{id}', 'verb' => 'DELETE'],

        // Groups API-Routen
        ['name' => 'user_api#listGroups', 'url' => '/api/groups', 'verb' => 'GET'],

        // Config API-Route
        ['name' => 'user_api#getConfig', 'url' => '/api/config', 'verb' => 'GET'],

        // Debug-Route
        ['name' => 'user_api#debug', 'url' => '/api/debug', 'verb' => 'GET'],
    ]
];
