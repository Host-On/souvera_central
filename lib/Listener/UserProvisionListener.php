<?php

declare(strict_types=1);

/**
 * Souvera Central - User-Provisionierung Listener
 *
 * Legt beim Anlegen eines Nextcloud-Benutzers automatisch das passende
 * Stalwart-Postfach an (idempotent).
 *
 * Zwei Fälle:
 *   1. Lokale Anlage (Central-UI / occ, Klartext-Passwort verfügbar):
 *      Stalwart-Principal wird mit diesem Passwort angelegt.
 *   2. EXTERNE AUTHENTIFIZIERUNG (Feature-Flag `ext_idp.enabled`): Ein
 *      föderierter Benutzer (z. B. Authentik/Keycloak via NC `user_oidc`
 *      oder `user_saml`) hat KEIN Passwort — der Listener erzeugt dann ein
 *      zufälliges internes Stalwart-Passwort. Der Login läuft über SSO, die
 *      Mail-Auth über das H2CK/oidc-JWT (OAUTHBEARER) — das interne Passwort
 *      verlässt die Instanz nie.
 *
 * Externe Benutzer werden AUSSCHLIESSLICH mit einer explizit erlaubten
 * Mailadresse provisioniert (kein Fallback auf die erste konfigurierte
 * Domain — das würde Postfächer auf der falschen Domain erzeugen).
 * Siehe docs/EXTERNAL_IDP.md.
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
        $password = $event->getPassword(); // Klartext beim Anlegen (null/'' bei Föderation)
        $external = ($password === null || $password === '');

        // Externe Authentifizierung ist ein explizites Feature-Flag.
        if ($external && !$this->config->isExternalIdpProvisioningEnabled()) {
            return;
        }

        $mail = $this->stalwart->mailFor($user);
        if ($mail === null) {
            return;
        }

        // Externe Benutzer: strikt die Claim-Adresse — kein Fallback auf die
        // erste konfigurierte Domain (falsche-Postfach-Falle).
        if ($external) {
            $email = $user->getEMailAddress();
            if ($email === null || trim($email) === '' || !$this->config->isEmailDomainAllowed($email)) {
                $this->logger->warning('SouveraCentral: föderierter Benutzer ohne erlaubte Mailadresse übersprungen', [
                    'uid'   => $uid,
                    'email' => $email,
                ]);
                return;
            }
            $mail = strtolower(trim($email));
            $password = bin2hex(random_bytes(16)); // internes Stalwart-Passwort (SSO/JWT-Auth)
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
            if ($external) {
                $this->logger->info('SouveraCentral: Postfach für föderierten Benutzer (externe Authentifizierung) angelegt', [
                    'uid'  => $uid,
                    'mail' => $mail,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('SouveraCentral Mailbox-Anlage fehlgeschlagen', [
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
