<?php

declare(strict_types=1);

/**
 * Souvera Central - Passwort-Sync Listener
 *
 * Spiegelt JEDE Nextcloud-Passwortänderung nach Stalwart. Das
 * PasswordUpdatedEvent wird vom NC-Core zentral gefeuert - unabhängig vom
 * Auslöser (Self-Service unter Einstellungen->Sicherheit, Admin-Reset,
 * "Passwort vergessen", `occ user:resetpassword`, API).
 */

namespace OCA\SouveraCentral\Listener;

use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\MailGroupService;
use OCA\SouveraCentral\Service\StalwartService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\PasswordUpdatedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<PasswordUpdatedEvent>
 */
class PasswordSyncListener implements IEventListener {
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
        private MailGroupService $mailGroup,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void {
        if (!($event instanceof PasswordUpdatedEvent)) {
            return;
        }
        if (!$this->config->isStalwartConfigured()) {
            return;
        }

        $user = $event->getUser();
        $uid = $user->getUID();
        $password = $event->getPassword(); // KLARTEXT, genau hier verfügbar
        if ($password === null || $password === '') {
            return;
        }

        try {
            // Postfach existiert? Wenn nicht (Erst-Sync), anlegen statt nur PW setzen.
            if (!$this->stalwart->principalExists($uid)) {
                $mail = $this->stalwart->mailFor($user);
                if ($mail !== null) {
                    $this->stalwart->createPrincipal($uid, $password, $mail, $user->getDisplayName());
                    // Erstanlage -> Mail-Gruppe pflegen (smail-Sichtbarkeit)
                    $this->mailGroup->addUser($user);
                }
            } else {
                $this->stalwart->setPassword($uid, $password);
                // Sicherstellen, dass Bestandspostfächer in der Mail-Gruppe sind
                $this->mailGroup->addUser($user);
            }
        } catch (\Throwable $e) {
            $this->logger->error('SouveraCentral PW-Sync fehlgeschlagen', [
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
