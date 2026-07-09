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

        $user = $event->getUser();
        $uid = $user->getUID();
        $mail = $this->stalwart->mailFor($user);
        if ($mail === null) {
            return;
        }
        // Geschützter Souvera-Administrator (scadmin): Postfach NIE mitlöschen,
        // selbst wenn der NC-Account außerhalb von Central entfernt wurde.
        if ($this->config->isAdminAccount($uid, $user->getEMailAddress())) {
            $this->logger->warning('SouveraCentral: Löschung des geschützten scadmin erkannt – Postfach bleibt erhalten.', [
                'uid' => $uid,
            ]);
            return;
        }
        try {
            $this->stalwart->deletePrincipal($mail);
        } catch (\Throwable $e) {
            $this->logger->error('SouveraCentral Mailbox-Löschung fehlgeschlagen', [
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
