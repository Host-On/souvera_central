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

use OCA\SouveraCentral\Command\InstallBrandingThemeCommand;
use OCA\SouveraCentral\Listener\BrandingScriptListener;
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
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\L10N\IFactory;
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

        // Instanzweite App-Umbenennung (Talk -> "Link", Office/Collabora -> "Desk")
        // global auf jeder Seite einspielen.
        $context->registerEventListener(BeforeTemplateRenderedEvent::class, BrandingScriptListener::class);
        // Login-Seiten erhalten ein EIGENES Event (NC >= 28): BeforeLoginTemplateRenderedEvent
        $context->registerEventListener(BeforeLoginTemplateRenderedEvent::class, BrandingScriptListener::class);
    }

    public function boot(IBootContext $context): void {
        // Self-update background jobs for all managed apps. Must run
        // independently of any user session (cron executes without one).
        // JobList::add() is idempotent per class+argument. The whole block
        // is failure-tolerant: a job-registration hiccup must never take
        // down the app boot (symptom: "Lade Souvera Central…" forever).
        $context->injectFn(function (\OCP\BackgroundJob\IJobList $jobList): void {
            foreach (['souvera_central', 'souvera_mail', 'souvera_shield', 'souvera_mailarchiv'] as $appId) {
                try {
                    $jobList->add(\OCA\SouveraCentral\DevOps\SelfUpdateJob::class, ['app' => $appId]);
                } catch (\Throwable $e) {
                    \OCP\Server::get(\Psr\Log\LoggerInterface::class)->error(
                        'souvera_central: SelfUpdateJob registration failed for ' . $appId . ': ' . $e->getMessage(),
                        ['app' => 'souvera_central', 'exception' => $e]
                    );
                }
            }
        });

        $context->injectFn(function (
            IUserSession $userSession,
            INavigationManager $navigationManager,
            IURLGenerator $urlGenerator,
            PermissionService $permission,
            IFactory $l10nFactory,
        ): void {
            $user = $userSession->getUser();
            if ($user === null) {
                return;
            }
            // IL10N NICHT direkt autowiren (schlägt im CLI/Upgrade-Kontext mit
            // "Could not resolve OCP\IL10N" fehl) – über die IFactory holen.
            $l10n = $l10nFactory->get(self::APP_ID);
            // Verwaltungs-Navigation (App-Menü, oben) – NUR Souvera-Admins.
            // Erscheint nur, wenn die App für den Benutzer aktiviert ist UND er
            // Souvera-Admin ist – sonst kein toter Eintrag.
            if ($permission->canSeeCentralNavigation()) {
                $navigationManager->add(static function () use ($urlGenerator, $l10n): array {
                    return [
                        'id' => self::APP_ID,
                        'order' => 10,
                        'href' => $urlGenerator->linkToRoute('souvera_central.page.index'),
                        'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
                        'name' => $l10n->t('Central'),
                    ];
                });
            }
            // Hilfe (Nutzer-Menü oben rechts, type "settings") – Souvera-User UND
            // Souvera-Admins. Bewusst getrennt von der Verwaltung: normale
            // Souvera-User sehen ausschließlich die Hilfe, nie die Verwaltung.
            if ($permission->canSeeHelp()) {
                $navigationManager->add(static function () use ($urlGenerator, $l10n): array {
                    return [
                        'id' => 'souvera_central_help',
                        'order' => 5,
                        'type' => 'settings',
                        'href' => $urlGenerator->linkToRoute('souvera_central.help.index'),
                        'icon' => $urlGenerator->imagePath(self::APP_ID, 'help.svg'),
                        'name' => $l10n->t('Help'),
                    ];
                });
            }
        });
    }
}
