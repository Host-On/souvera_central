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
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;

/** @template-implements IEventListener<BeforeTemplateRenderedEvent> */
class BrandingScriptListener implements IEventListener {
    public function __construct(
        private ConfigService $config,
        private IInitialState $initialState,
        private IURLGenerator $urlGenerator,
        private PermissionService $permission,
        private IRequest $request,
    ) {
    }

    /** Letzter Auto-Write-Versuch (in-process throttle, 5 Min) */
    private static ?int $lastThemeWriteAttempt = null;

    public function handle(Event $event): void {
        // Theme-L10n-Dateien best effort schreiben (einmalig, dann Marker im
        // App-Config; bei Fehlschlag alle 5 Min erneut). Aktivierung bleibt
        // manuell: occ config:system:set theme --value souvera
        $this->autoWriteThemeL10n();

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
            $this->handleGuest($event->getResponse());
            return;
        }

        $branding = $this->config->getBrandingConfig();

        // Titel serverseitig branden (FOUC-frei im Tab): pageTitle-Parameter
        // der Response VOR dem Rendern umbenennen — NC rendert
        // „<pageTitle> – <Instanzname>" daraus.
        $this->brandPageTitle($event, $branding['names']);

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

        // Souvera-Header: Assets STATISCH im Head (vor dem First Paint) —
        // NC bustert App-Assets pro Datei (?v= ändert sich pro Deploy, live
        // verifiziert). Das eliminiert das Header-FOUC (Logo/Talk-Schirm).
        // branding.js behält den dynamischen ?t=-Loader nur als Fallback.
        if (!empty($branding['header']['enabled'])) {
            Util::addStyle(Application::APP_ID, 'souvera_central-header');
            Util::addScript(Application::APP_ID, Application::APP_ID . '-header');
        }
    }

    /**
     * Login-/Guest-Seiten: Split-Screen-Login im Host-On-Stil.
     *
     * Injiziert das Bootstrap-Script (souvera_central-login), das das
     * login.css dynamisch mit Cache-Buster nachlädt und das Brand-Panel
     * links neben der Anmeldekarte aufbaut. Notbremse:
     * occ config:system:set souvera_login.enabled --value 0
     */
    /**
     * Theme-L10n-Dateien einmalig schreiben. Schlägt fehl (Rechte), wird alle
     * 5 Minuten erneut versucht. Der Operator aktiviert danach manuell.
     */
    private function autoWriteThemeL10n(): void {
        try {
            if ($this->config->getSelfAppValue('theme_l10n_installed') === '1') {
                return;
            }
            $now = time();
            if (self::$lastThemeWriteAttempt !== null && ($now - self::$lastThemeWriteAttempt) < 300) {
                return;
            }
            self::$lastThemeWriteAttempt = $now;
            if ($this->config->writeThemeL10nFiles()) {
                $this->config->setSelfAppValue('theme_l10n_installed', '1');
            }
        } catch (\Throwable $e) {
            // Nie den Seitenaufbau gefährden
        }
    }

    /**
     * pageTitle serverseitig umbenennen (Talk→Link, Office→Desk). Längste
     * Phrasen zuerst, damit „Nextcloud Talk" nicht als „Nextcloud Link"
     * endet. Läuft VOR dem Rendern — der Browser-Titel ist sofort korrekt.
     */
    private function brandPageTitle(BeforeTemplateRenderedEvent $event, array $names): void {
        try {
            $response = $event->getResponse();
            if (!$response instanceof TemplateResponse) {
                return;
            }
            $params = $response->getParams();

            // App-ID aus dem Pfad (z. B. /apps/spreed) → gebrandeter Name
            $pathName = null;
            if (preg_match('#/apps/([^/?#]+)#', (string)$this->request->getPathInfo(), $m) && isset($names[$m[1]])) {
                $pathName = (string)$names[$m[1]];
            }

            // Kein pageTitle gesetzt → forciert setzen (sonst lautet der erste
            // Titel nur „<Instanzname>“ und die Vue-Apps schreiben später
            // clientseitig „Talk“ dazwischen = Flash im Tab)
            if ((!isset($params['pageTitle']) || !is_string($params['pageTitle']) || $params['pageTitle'] === '') && $pathName !== null) {
                $params['pageTitle'] = $pathName;
                $response->setParams($params);
            }

            if (!isset($params['pageTitle']) || !is_string($params['pageTitle']) || $params['pageTitle'] === '') {
                return;
            }

            $link = (string)($names['spreed'] ?? 'Link');
            $desk = (string)($names['richdocuments'] ?? 'Desk');
            $search = ['Nextcloud Talk', 'Talk', 'Nextcloud Office', 'Collabora Online', 'Collabora', 'Office'];
            $replace = [$link, $link, $desk, $desk, $desk, $desk];

            $title = str_replace($search, $replace, $params['pageTitle']);
            if ($title !== $params['pageTitle']) {
                $params['pageTitle'] = $title;
                $response->setParams($params);
            }
        } catch (\Throwable $e) {
            // Titel nicht branden können ≠ Seite brechen
        }
    }

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
