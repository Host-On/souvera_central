<?php

declare(strict_types=1);

/**
 * Souvera Central - User-Deprovisionierung Listener
 *
 * Entfernt das Stalwart-Postfach, wenn ein Nextcloud-Benutzer gelöscht wird.
 */

namespace OCA\SouveraCentral\Listener;

use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\StalwartService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeprovisionListener implements IEventListener {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void {
        if (!($event instanceof UserDeletedEvent)) {
            return;
        }
        if (!$this->config->isStalwartConfigured()) {
            return;
        }

        $uid = $event->getUser()->getUID();
        try {
            $this->stalwart->deletePrincipal($uid);
        } catch (\Throwable $e) {
            $this->logger->error('SouveraCentral Mailbox-Löschung fehlgeschlagen', [
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
