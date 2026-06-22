<?php

declare(strict_types=1);

/**
 * Souvera Central - Application Bootstrap
 *
 * Registriert die Event-Listener, die jede Nextcloud User-/Passwort-Änderung
 * nach Stalwart spiegeln (Provisionierung).
 */

namespace OCA\SouveraCentral\AppInfo;

use OCA\SouveraCentral\Listener\PasswordSyncListener;
use OCA\SouveraCentral\Listener\UserDeprovisionListener;
use OCA\SouveraCentral\Listener\UserProvisionListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\PasswordUpdatedEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'souvera_central';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        // Postfach beim Anlegen eines NC-Users erzeugen
        $context->registerEventListener(UserCreatedEvent::class, UserProvisionListener::class);
        // Jede NC-Passwortänderung (Self-Service, Admin-Reset, occ, API) spiegeln
        $context->registerEventListener(PasswordUpdatedEvent::class, PasswordSyncListener::class);
        // Postfach beim Löschen eines NC-Users entfernen
        $context->registerEventListener(UserDeletedEvent::class, UserDeprovisionListener::class);
    }

    public function boot(IBootContext $context): void {
    }
}
