<?php

declare(strict_types=1);

/**
 * Souvera Central - Branding Script Listener
 *
 * Spielt bei jedem gerenderten Template (für angemeldete Benutzer) ein kleines
 * Frontend-Skript ein, das instanzweit die Anzeigenamen von Talk (spreed) und
 * Office/Collabora (richdocuments) überschreibt – in Souvera heißen sie „Link"
 * bzw. „Desk". Immer aktiv; die Namen sind über Central editierbar.
 *
 * Zusätzlich (Souvera-Header): baut bei jedem gerenderten Template den
 * v34-Header um — gepinnte App-Buttons direkt im Header, „Dashboard"-
 * Breadcrumb aus, Suche rechts kompakt, „Mehr" = der bestehende App-Grid-
 * Dropdown. Globales Feature (Notbremse: branding.header.enabled = 0).
 */

namespace OCA\SouveraCentral\Listener;

use OCA\SouveraCentral\AppInfo\Application;
use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\PermissionService;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IURLGenerator;
use OCP\Util;

/** @template-implements IEventListener<BeforeTemplateRenderedEvent> */
class BrandingScriptListener implements IEventListener {
    public function __construct(
        private ConfigService $config,
        private IInitialState $initialState,
        private IURLGenerator $urlGenerator,
        private PermissionService $permission,
    ) {
    }

    public function handle(Event $event): void {
        // Login-Seiten (NC >= 28): dediziertes Event mit getResponse()
        if ($event instanceof BeforeLoginTemplateRenderedEvent) {
            $this->handleGuest($event->getResponse());
            return;
        }

        if (!($event instanceof BeforeTemplateRenderedEvent)) {
            return;
        }

        // Übrige Gast-Seiten (2FA, Fehler): Souvera-Login-Layout.
        // Public-Share-Seiten (renderAs = public) bleiben unangetastet.
        if (!$event->isLoggedIn()) {
            $this->handleGuest($event->getTemplateResponse());
            return;
        }

        $branding = $this->config->getBrandingConfig();
        // Icon-Override (Talk/spreed -> Souvera-Icon). Weitere Apps können hier
        // analog ergänzt werden, sobald ein Motiv vorliegt.
        $branding['icons'] = [
            'spreed' => $this->urlGenerator->imagePath(Application::APP_ID, 'link.svg'),
        ];

        // Souvera-Header: Central-Button nur für Souvera-Admins sichtbar
        // (client-seitig gefiltert, server-seitig vorgefiltert im State).
        $branding['header']['isSouveraAdmin'] = $this->permission->isSouveraAdmin();

        $this->initialState->provideInitialState('branding', $branding);
        Util::addScript(Application::APP_ID, Application::APP_ID . '-branding');

        // Hinweis: header.css/-header.js werden vom Branding-Script DYNAMISCH
        // mit Zeitstempel nachgeladen (NCs Cache-Buster ?v= ist der Core-Hash
        // und ändert sich bei App-Updates nie → Browser-Caches wären sonst
        // ewig stale).
    }

    /**
     * Login-/Guest-Seiten: Split-Screen-Login im Host-On-Stil.
     *
     * Injiziert das Bootstrap-Script (souvera_central-login), das das
     * login.css dynamisch mit Cache-Buster nachlädt und das Brand-Panel
     * links neben der Anmeldekarte aufbaut. Notbremse:
     * occ config:system:set souvera_login.enabled --value 0
     */
    private function handleGuest(TemplateResponse $response): void {
        if ($response->getRenderAs() !== 'guest') {
            return;
        }
        if (!$this->config->isLoginBrandingEnabled()) {
            return;
        }

        $lang = [
            'de' => [
                'headline1' => 'Deine Daten.',
                'headline2' => 'Dein Workspace.',
                'subline' => 'Mail, Kalender, Shield und mehr — nahtlos verbunden, DSGVO-konform in Deutschland.',
                'chips' => ['E-Mail', 'Kalender', 'Shield', 'Desktop'],
                'cardTitle' => 'Willkommen zurück',
                'cardSub' => 'Melde dich in deinem Workspace an.',
                'labelUser' => 'E-Mail oder Benutzername',
                'labelPassword' => 'Passwort',
                'placeholderUser' => 'Kontoname oder E-Mail-Adresse',
                'placeholderPassword' => 'Passwort',
            ],
            'en' => [
                'headline1' => 'Your data.',
                'headline2' => 'Your workspace.',
                'subline' => 'Mail, calendar, shield and more — seamlessly connected, GDPR-compliant in Germany.',
                'chips' => ['E-Mail', 'Calendar', 'Shield', 'Desktop'],
                'cardTitle' => 'Welcome back',
                'cardSub' => 'Sign in to your workspace.',
                'labelUser' => 'Email or username',
                'labelPassword' => 'Password',
                'placeholderUser' => 'Username or email address',
                'placeholderPassword' => 'Password',
            ],
        ];

        $this->initialState->provideInitialState('loginBranding', [
            'text' => $lang,
        ]);
        // CSS statisch in den Head → vor dem ersten Paint (kein Layout-Flackern).
        // Das JS deckt zusätzlich dynamisch mit Cache-Buster auf, falls der
        // statische Link fehlt.
        Util::addStyle(Application::APP_ID, 'souvera_central-login');
        Util::addScript(Application::APP_ID, Application::APP_ID . '-login');
    }
}
