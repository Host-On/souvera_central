<?php
/**
 * Souvera Central - Shared Mailbox Service (Stalwart v0.16 / JMAP)
 *
 * Geteilte Postfächer sind in Stalwart 0.16 Konten vom Typ "Group"
 * (x:Account mit @type=Group). Mitgliedschaft wird am USER-Konto über das
 * Feld memberGroupIds (Map<groupId, true>) gepflegt.
 *
 * Der Service nutzt die JMAP-Bausteine des StalwartService (jmapSingle,
 * resolveDomainId, findAccountId, getAccountById, domainNameMap).
 */

namespace OCA\SouveraCentral\Service;

use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class SharedMailboxService {
    private ConfigService $configService;
    private StalwartService $stalwartService;
    private IUserManager $userManager;
    private LoggerInterface $logger;

    public function __construct(
        ConfigService $configService,
        StalwartService $stalwartService,
        IUserManager $userManager,
        LoggerInterface $logger
    ) {
        $this->configService = $configService;
        $this->stalwartService = $stalwartService;
        $this->userManager = $userManager;
        $this->logger = $logger;
    }

    // ============================================================================
    // CRUD
    // ============================================================================

    /**
     * Alle geteilten Postfächer (Group-Konten).
     *
     * @return array<int,array>
     */
    public function list(): array {
        $query = $this->stalwartService->jmapSingle('x:Account/query', ['filter' => ['@type' => 'Group']]);
        $ids = $query['ids'] ?? [];
        if (empty($ids)) {
            return [];
        }

        $got = $this->stalwartService->jmapSingle('x:Account/get', ['ids' => array_values($ids)]);
        $result = [];
        foreach ($got['list'] ?? [] as $group) {
            if (is_array($group)) {
                $result[] = $this->shape($group);
            }
        }
        return $result;
    }

    /**
     * Geteiltes Postfach erstellen.
     */
    public function create(string $name, string $email, string $description = ''): ?array {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('SharedMailboxService: Ungültiges Email-Format', ['email' => $email]);
            return null;
        }
        if (!$this->configService->isEmailDomainAllowed($email)) {
            $this->logger->warning('SharedMailboxService: Domain nicht erlaubt', ['email' => $email]);
            return null;
        }

        $pos = strrpos($email, '@');
        $local = substr($email, 0, $pos);
        $domain = substr($email, $pos + 1);
        $domainId = $this->stalwartService->resolveDomainId($domain);
        if ($domainId === null) {
            $this->logger->error('SharedMailboxService: Domain in Stalwart nicht vorhanden', ['domain' => $domain]);
            return null;
        }

        $object = [
            '@type' => 'Group',
            'name' => $local,
            'domainId' => $domainId,
            'description' => $description !== '' ? $description : $name,
        ];

        $resp = $this->stalwartService->jmapSingle('x:Account/set', ['create' => ['sm0' => $object]]);
        $created = $resp['created']['sm0'] ?? null;
        if ($resp === null || !array_key_exists('sm0', $resp['created'] ?? [])) {
            $this->logger->warning('SharedMailboxService: Anlage abgelehnt', [
                'email' => $email,
                'notCreated' => $resp['notCreated'] ?? null,
            ]);
            return null;
        }

        $this->logger->info('SharedMailboxService: Geteiltes Postfach erstellt', ['email' => $email]);

        // Bevorzugt über die zurückgegebene ID das vollständige Objekt laden.
        if (is_array($created) && isset($created['id'])) {
            $group = $this->stalwartService->getAccountById((string) $created['id']);
            if ($group !== null) {
                return $this->shape($group);
            }
        }
        return $this->get($local);
    }

    /**
     * Geteiltes Postfach per Name (Localpart) abrufen.
     */
    public function get(string $name): ?array {
        $groupId = $this->groupId($name);
        if ($groupId === null) {
            return null;
        }
        $group = $this->stalwartService->getAccountById($groupId);
        if ($group === null || ($group['@type'] ?? '') !== 'Group') {
            return null;
        }
        return $this->shape($group);
    }

    /**
     * Geteiltes Postfach aktualisieren (aktuell: Beschreibung).
     */
    public function update(string $name, array $updates): bool {
        if (!array_key_exists('description', $updates)) {
            return true;
        }
        $groupId = $this->groupId($name);
        if ($groupId === null) {
            return false;
        }

        $resp = $this->stalwartService->jmapSingle('x:Account/set', [
            'update' => [$groupId => ['description' => (string) $updates['description']]],
        ]);
        return $resp !== null && array_key_exists($groupId, $resp['updated'] ?? []);
    }

    /**
     * Geteiltes Postfach löschen.
     */
    public function delete(string $name): bool {
        $groupId = $this->groupId($name);
        if ($groupId === null) {
            return true; // bereits weg
        }
        $resp = $this->stalwartService->jmapSingle('x:Account/set', ['destroy' => [$groupId]]);
        if ($resp === null) {
            return false;
        }
        $this->logger->info('SharedMailboxService: Geteiltes Postfach gelöscht', ['name' => $name]);
        return true;
    }

    // ============================================================================
    // Mitglieder (memberGroupIds am User-Konto)
    // ============================================================================

    /**
     * Mitglieder eines geteilten Postfachs (E-Mail-Adressen der User-Konten).
     *
     * @return string[]
     */
    public function getMembers(string $name): array {
        $groupId = $this->groupId($name);
        if ($groupId === null) {
            return [];
        }

        $query = $this->stalwartService->jmapSingle('x:Account/query', [
            'filter' => ['operator' => 'AND', 'conditions' => [
                ['memberGroupIds' => $groupId],
                ['@type' => 'User'],
            ]],
        ]);
        $ids = $query['ids'] ?? [];
        if (empty($ids)) {
            return [];
        }

        $got = $this->stalwartService->jmapSingle('x:Account/get', [
            'ids' => array_values($ids),
            'properties' => ['emailAddress'],
        ]);
        $members = [];
        foreach ($got['list'] ?? [] as $acc) {
            if (isset($acc['emailAddress']) && $acc['emailAddress'] !== '') {
                $members[] = strtolower((string) $acc['emailAddress']);
            }
        }
        return $members;
    }

    /**
     * Mitglied hinzufügen.
     *
     * @param string $name   Localpart des geteilten Postfachs
     * @param string $userId NC-Benutzer-ID (= E-Mail)
     */
    public function addMember(string $name, string $userId): bool {
        return $this->setMembership($name, $userId, true);
    }

    /**
     * Mitglied entfernen.
     */
    public function removeMember(string $name, string $userId): bool {
        return $this->setMembership($name, $userId, false);
    }

    // ============================================================================
    // Hilfsmethoden
    // ============================================================================

    public function isEmailTaken(string $email): bool {
        return $this->stalwartService->isEmailTaken($email);
    }

    /**
     * Setzt/entfernt die Gruppen-Mitgliedschaft am User-Konto.
     */
    private function setMembership(string $name, string $userId, bool $add): bool {
        $groupId = $this->groupId($name);
        if ($groupId === null) {
            return false;
        }

        $email = $this->emailForUserId($userId);
        if ($email === null) {
            return false;
        }
        $userAccountId = $this->stalwartService->findAccountId($email, 'User');
        if ($userAccountId === null) {
            $this->logger->warning('SharedMailboxService: Kein Postfach für Mitglied', ['userId' => $userId]);
            return false;
        }

        $resp = $this->stalwartService->jmapSingle('x:Account/set', [
            'update' => [$userAccountId => ['memberGroupIds/' . $groupId => $add]],
        ]);
        $ok = $resp !== null && array_key_exists($userAccountId, $resp['updated'] ?? []);
        if ($ok) {
            $this->logger->info('SharedMailboxService: Mitgliedschaft aktualisiert', [
                'mailbox' => $name, 'userId' => $userId, 'add' => $add,
            ]);
        }
        return $ok;
    }

    /**
     * Group-Konto-ID per Localpart-Namen (über alle Domains) auflösen.
     */
    private function groupId(string $name): ?string {
        $name = strtolower(trim($name));
        if ($name === '') {
            return null;
        }
        $query = $this->stalwartService->jmapSingle('x:Account/query', [
            'filter' => ['operator' => 'AND', 'conditions' => [
                ['name' => $name],
                ['@type' => 'Group'],
            ]],
        ]);
        $id = $query['ids'][0] ?? null;
        return $id !== null ? (string) $id : null;
    }

    /**
     * NC-Benutzer-ID -> Mailadresse.
     */
    private function emailForUserId(string $userId): ?string {
        $user = $this->userManager->get($userId);
        if ($user !== null) {
            return $this->stalwartService->mailFor($user);
        }
        return filter_var($userId, FILTER_VALIDATE_EMAIL) ? strtolower($userId) : null;
    }

    /**
     * JMAP-Group-Objekt in die vom Frontend erwartete Form bringen.
     *
     * @return array{id:string,name:string,email:?string,emails:array,description:?string,type:string}
     */
    private function shape(array $group): array {
        $email = isset($group['emailAddress']) && $group['emailAddress'] !== ''
            ? strtolower((string) $group['emailAddress'])
            : null;
        return [
            'id' => (string) ($group['name'] ?? ($group['id'] ?? '')),
            'name' => (string) ($group['name'] ?? ''),
            'email' => $email,
            'emails' => $email !== null ? [$email] : [],
            'description' => $group['description'] ?? null,
            'type' => 'group',
        ];
    }
}
