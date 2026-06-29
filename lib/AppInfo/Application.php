<?php

declare(strict_types=1);

/**
 * Souvera Central - Application Bootstrap
 *
 * Registriert Event-Listener (Provisionierung + Gruppen-Schutz), die
 * Souvera-Admin-Middleware (delegierte Verwaltung) sowie die dynamische
 * Navigation, die auch Mitgliedern der souvera-admins-Gruppe das App-Icon zeigt.
 */

namespace OCA\SouveraCentral\AppInfo;

use OCA\SouveraCentral\Listener\GroupDeletionGuardListener;
use OCA\SouveraCentral\Listener\PasswordSyncListener;
use OCA\SouveraCentral\Listener\UserDeprovisionListener;
use OCA\SouveraCentral\Listener\UserProvisionListener;
use OCA\SouveraCentral\Middleware\SouveraAdminMiddleware;
use OCA\SouveraCentral\Service\PermissionService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\User\Events\PasswordUpdatedEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'souvera_central';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        // Postfach beim Anlegen eines NC-Users erzeugen (nur Souvera User)
        $context->registerEventListener(UserCreatedEvent::class, UserProvisionListener::class);
        // Jede NC-Passwortänderung (Self-Service, Admin-Reset, occ, API) spiegeln
        $context->registerEventListener(PasswordUpdatedEvent::class, PasswordSyncListener::class);
        // Postfach beim Löschen eines NC-Users entfernen
        $context->registerEventListener(UserDeletedEvent::class, UserDeprovisionListener::class);
        // Geschützte Gruppen ("Souvera Users" + "souvera-admins") bei Löschung wiederherstellen
        $context->registerEventListener(GroupDeletedEvent::class, GroupDeletionGuardListener::class);

        // Delegierte Verwaltung: Souvera-Admins (NC-Superadmin ODER souvera-admins-Mitglied) zulassen.
        $context->registerMiddleware(SouveraAdminMiddleware::class);
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function (
            IUserSession $userSession,
            INavigationManager $navigationManager,
            IURLGenerator $urlGenerator,
            PermissionService $permission,
            IL10N $l10n,
        ): void {
            $user = $userSession->getUser();
            if ($user === null) {
                return;
            }
            // Nur anzeigen, wenn die App für diesen Benutzer aktiviert ist
            // (Gruppenbeschränkung) UND er Souvera-Admin ist – sonst kein toter Eintrag.
            if (!$permission->canSeeCentralNavigation()) {
                return;
            }
            $navigationManager->add(static function () use ($urlGenerator, $l10n): array {
                return [
                    'id' => self::APP_ID,
                    'order' => 10,
                    'href' => $urlGenerator->linkToRoute('souvera_central.page.index'),
                    'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
                    'name' => $l10n->t('Central'),
                ];
            });
        });
    }
}
