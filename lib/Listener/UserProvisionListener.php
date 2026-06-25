<?php

declare(strict_types=1);

/**
 * Souvera Central - User-Provisionierung Listener
 *
 * Legt beim Anlegen eines Nextcloud-Benutzers automatisch das passende
 * Stalwart-Postfach an (idempotent).
 */

namespace OCA\SouveraCentral\Listener;

use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\MailGroupService;
use OCA\SouveraCentral\Service\StalwartService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserCreatedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<UserCreatedEvent>
 */
class UserProvisionListener implements IEventListener {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
        private MailGroupService $mailGroup,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void {
        if (!($event instanceof UserCreatedEvent)) {
            return;
        }
        if (!$this->config->isStalwartConfigured()) {
            return;
        }

        $user = $event->getUser();
        $uid = $user->getUID();
        $password = $event->getPassword(); // Klartext beim Anlegen
        $mail = $this->stalwart->mailFor($user);
        if ($mail === null || $password === null || $password === '') {
            return;
        }

        // Postfächer werden nur für "Souvera User" angelegt. Beim Anlegen über
        // Souvera Central erfolgt die Provisionierung explizit im Controller
        // (UserApiController::create) mit dem Klartext-Passwort. Dieser Listener
        // ist ein Sicherheitsnetz für den seltenen Fall, dass ein Benutzer bereits
        // bei der Erstellung Mitglied der souvera-users-Gruppe ist.
        if (!$this->mailGroup->isMember($user)) {
            return;
        }

        try {
            $this->stalwart->createPrincipal($mail, $password, $user->getDisplayName());
            // Benutzer mit Postfach kommt in die Mail-Gruppe (smail-Sichtbarkeit)
            $this->mailGroup->addUser($user);
        } catch (\Throwable $e) {
            $this->logger->error('SouveraCentral Mailbox-Anlage fehlgeschlagen', [
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
