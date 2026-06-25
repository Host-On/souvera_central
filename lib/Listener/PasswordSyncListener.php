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

        $mail = $this->stalwart->mailFor($user);
        if ($mail === null) {
            return;
        }

        try {
            // Postfach existiert? Dann nur Passwort spiegeln.
            if ($this->stalwart->principalExists($mail)) {
                $this->stalwart->setPassword($mail, $password);
                return;
            }
            // Kein Postfach: nur für "Souvera User" (Mitglieder der souvera-users-Gruppe)
            // automatisch anlegen. "Nextcloud User" (unlizenziert) erhalten KEIN Postfach.
            if (!$this->mailGroup->isMember($user)) {
                return;
            }
            $this->stalwart->createPrincipal($mail, $password, $user->getDisplayName());
            $this->mailGroup->addUser($user);
        } catch (\Throwable $e) {
            $this->logger->error('SouveraCentral PW-Sync fehlgeschlagen', [
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
