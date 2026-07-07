<?php

declare(strict_types=1);

/**
 * Souvera Central - Branding Script Listener
 *
 * Spielt bei jedem gerenderten Template (für angemeldete Benutzer) ein kleines
 * Frontend-Skript ein, das instanzweit die Anzeigenamen von Talk (spreed) und
 * Office/Collabora (richdocuments) überschreibt – in Souvera heißen sie „Link"
 * bzw. „Desk". Immer aktiv; die Namen sind über Central editierbar.
 */

namespace OCA\SouveraCentral\Listener;

use OCA\SouveraCentral\AppInfo\Application;
use OCA\SouveraCentral\Service\ConfigService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/** @template-implements IEventListener<BeforeTemplateRenderedEvent> */
class BrandingScriptListener implements IEventListener {
    public function __construct(
        private ConfigService $config,
        private IInitialState $initialState,
    ) {
    }

    public function handle(Event $event): void {
        if (!($event instanceof BeforeTemplateRenderedEvent)) {
            return;
        }
        // Nur für angemeldete Benutzer (nicht auf Login-/Public-Seiten).
        if (!$event->isLoggedIn()) {
            return;
        }

        $this->initialState->provideInitialState('branding', $this->config->getBrandingConfig());
        Util::addScript(Application::APP_ID, Application::APP_ID . '-branding');
    }
}
