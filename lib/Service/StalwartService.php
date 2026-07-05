<?php
/**
 * Souvera Central - Stalwart Mail-Server Service (Stalwart v0.16 / JMAP)
 *
 * Ab Stalwart 0.16 wurde die alte REST-Management-API (/api/principal ...)
 * vollständig entfernt und durch die JMAP-Management-API ersetzt. Dieser
 * Service spricht ausschließlich JMAP:
 *
 *   - Session-Discovery : GET  {base}/jmap/session   (liefert apiUrl)
 *   - Methoden-Aufrufe  : POST {base}/jmap            (using: urn:stalwart:jmap; KEINE accountId)
 *   - Objekttypen       : x:Account (User/Group), x:Domain
 *   - Methoden          : x:Account/{get,set,query}, x:Domain/{get,query}
 *
 * Identitätsmodell v0.16: Ein Konto hat name=Localpart + domainId; die
 * E-Mail-Adresse (name@domain) ist der fachliche Schlüssel. In Souvera gilt
 * NC-UID == E-Mail-Adresse, daher arbeiten alle öffentlichen Methoden auf der
 * E-Mail-Adresse.
 */

namespace OCA\SouveraCentral\Service;

use Psr\Log\LoggerInterface;

class StalwartService {
    /** JMAP-Capability für die Stalwart-Management-Objekte (x:*). */
    private const CAP_CORE = 'urn:ietf:params:jmap:core';
    private const CAP_STALWART = 'urn:stalwart:jmap';

    private ConfigService $configService;
    private LoggerInterface $logger;

    /** Session-Cache (pro Request): ['apiUrl' => string] oder false. */
    private array|false|null $sessionCache = null;
    /** @var array<string,?string> domainName => domainId */
    private array $domainIdCache = [];
    /** @var array<string,string>|null domainId => domainName */
    private ?array $domainNameMapCache = null;
    /** Letzter Fehler (JMAP-/HTTP-/Validierungsdetail) – für Diagnose via OCC. */
    private ?array $lastError = null;

    public function __construct(
        ConfigService $configService,
        LoggerInterface $logger
    ) {
        $this->configService = $configService;
        $this->logger = $logger;
    }

    // ============================================================================
    // Öffentliche, E-Mail-zentrische Postfach-API
    // ============================================================================

    /**
     * Postfach (User-Account) anlegen. Idempotent: existiert es bereits,
     * wird nur das Passwort gesetzt.
     *
     * @param string $email       Haupt-Mailadresse (= Identität, local@domain)
     * @param string $password    Klartext-Passwort (Stalwart hasht serverseitig)
     * @param string|null $displayName Anzeigename (description)
     * @param int|null $quota      Disk-Quota in Bytes; null = globaler Standard
     *                             (ConfigService::getDefaultMailboxQuota), 0 = unbegrenzt
     */
    public function createPrincipal(
        string $email,
        string $password,
        ?string $displayName = null,
        ?int $quota = null
    ): bool {
        $email = strtolower(trim($email));
        $parts = $this->splitEmail($email);
        if ($parts === null) {
            $this->lastError = ['stage' => 'validate', 'detail' => 'Ungültige Mailadresse: ' . $email];
            $this->logger->warning('StalwartService: Ungültige Mailadresse für Postfach', ['email' => $email]);
            return false;
        }

        if ($this->principalExists($email)) {
            return $this->setPassword($email, $password);
        }

        $domainId = $this->resolveDomainId($parts['domain']);
        if ($domainId === null) {
            $this->lastError = ['stage' => 'domain', 'detail' => "Domain '{$parts['domain']}' existiert nicht in Stalwart. Anlegen mit: occ souvera:domain:add {$parts['domain']}"];
            $this->logger->error('StalwartService: Domain in Stalwart nicht vorhanden', [
                'email' => $email,
                'domain' => $parts['domain'],
            ]);
            return false;
        }

        // Kein explizites Quota übergeben => globaler Standard aus der Config.
        $effectiveQuota = $quota ?? $this->configService->getDefaultMailboxQuota();

        $object = [
            '@type' => 'User',
            'name' => $parts['local'],
            'domainId' => $domainId,
            'credentials' => (object) ['0' => ['@type' => 'Password', 'secret' => $password]],
            'description' => $displayName ?: $parts['local'],
        ];
        if ($effectiveQuota > 0) {
            $object['quotas'] = ['maxDiskQuota' => $effectiveQuota];
        }

        $resp = $this->jmapSingle('x:Account/set', ['create' => ['nc0' => $object]]);
        if ($resp === null || !array_key_exists('nc0', $resp['created'] ?? [])) {
            if ($resp !== null) {
                $this->lastError = ['stage' => 'create', 'notCreated' => $resp['notCreated'] ?? null];
            }
            $this->logger->warning('StalwartService: Postfach-Anlage abgelehnt', [
                'email' => $email,
                'notCreated' => $resp['notCreated'] ?? null,
            ]);
            return false;
        }

        $this->logger->info('StalwartService: Postfach angelegt', ['email' => $email]);
        return true;
    }

    /**
     * Passwort eines Postfachs setzen (Spiegelung einer NC-Passwortänderung).
     * Behält bestehende Zweit-Credentials (App-Passwörter / OTP) unverändert.
     */
    public function setPassword(string $email, string $password): bool {
        $account = $this->getAccount($email, 'User');
        if ($account === null) {
            return false;
        }

        $existing = $account['credentials'] ?? [];
        $credentials = [];
        $index = 0;
        $hasPassword = false;
        foreach ($existing as $cred) {
            if (!is_array($cred)) {
                continue;
            }
            if (($cred['@type'] ?? '') === 'Password') {
                // Vorhandenes Passwort-Credential beibehalten, nur Secret ersetzen.
                $cred['secret'] = $password;
                $hasPassword = true;
            }
            $credentials[(string) $index] = $cred;
            $index++;
        }
        if (!$hasPassword) {
            $credentials['0'] = ['@type' => 'Password', 'secret' => $password];
        }

        $accountId = (string) $account['id'];
        $resp = $this->jmapSingle('x:Account/set', [
            'update' => [$accountId => ['credentials' => (object) $credentials]],
        ]);
        $ok = $resp !== null && array_key_exists($accountId, $resp['updated'] ?? []);
        if ($ok) {
            $this->logger->info('StalwartService: Passwort gespiegelt', ['email' => $email]);
        } else {
            $this->logger->warning('StalwartService: Passwort-Update abgelehnt', [
                'email' => $email,
                'notUpdated' => $resp['notUpdated'] ?? null,
            ]);
        }
        return $ok;
    }

    /**
     * Disk-Quota eines Postfachs setzen (0 = unbegrenzt).
     * Patcht gezielt quotas/maxDiskQuota, ohne andere Quotas zu überschreiben.
     */
    public function setMailboxQuota(string $email, int $quotaBytes): bool {
        $accountId = $this->findAccountId($email, 'User');
        if ($accountId === null) {
            return false;
        }

        $resp = $this->jmapSingle('x:Account/set', [
            'update' => [$accountId => ['quotas/maxDiskQuota' => max(0, $quotaBytes)]],
        ]);
        $ok = $resp !== null && array_key_exists($accountId, $resp['updated'] ?? []);
        if ($ok) {
            $this->logger->info('StalwartService: Postfach-Quota gesetzt', [
                'email' => $email,
                'quota' => max(0, $quotaBytes),
            ]);
        }
        return $ok;
    }

    /**
     * Postfach löschen. Existiert es nicht (mehr), gilt das als Erfolg (idempotent).
     */
    public function deletePrincipal(string $email): bool {
        $accountId = $this->findAccountId($email, 'User');
        if ($accountId === null) {
            return true; // bereits weg
        }

        $resp = $this->jmapSingle('x:Account/set', ['destroy' => [$accountId]]);
        if ($resp === null) {
            return false;
        }

        $this->logger->info('StalwartService: Postfach gelöscht', ['email' => $email]);
        return true;
    }

    /**
     * Prüft, ob für die Mailadresse ein User-Postfach existiert.
     */
    public function principalExists(string $email): bool {
        return $this->findAccountId($email, 'User') !== null;
    }

    /**
     * Alle E-Mail-Adressen eines Postfachs (Haupt-Adresse + Aliase).
     *
     * @return string[]
     */
    public function getEmails(string $email): array {
        $account = $this->getAccount($email, 'User');
        if ($account === null) {
            return [];
        }

        $emails = [];
        $primary = $this->primaryAddress($account);
        if ($primary !== null) {
            $emails[] = $primary;
        }
        foreach ($this->aliasAddresses($account) as $alias) {
            $emails[] = $alias;
        }

        return array_values(array_unique($emails));
    }

    /**
     * Aliase eines Postfachs (ohne Haupt-Adresse).
     *
     * @return string[]
     */
    public function getAliases(string $email): array {
        $account = $this->getAccount($email, 'User');
        if ($account === null) {
            return [];
        }
        return $this->aliasAddresses($account);
    }

    /**
     * Alias hinzufügen.
     */
    public function addAlias(string $email, string $alias): bool {
        $alias = strtolower(trim($alias));
        if (!filter_var($alias, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('StalwartService: Ungültiges Email-Format für Alias', [
                'email' => $email, 'alias' => $alias,
            ]);
            return false;
        }
        if (!$this->configService->isEmailDomainAllowed($alias)) {
            $this->logger->warning('StalwartService: Domain nicht erlaubt für Alias', [
                'email' => $email, 'alias' => $alias,
            ]);
            return false;
        }

        $account = $this->getAccount($email, 'User');
        if ($account === null) {
            return false;
        }
        $parts = $this->splitEmail($alias);
        if ($parts === null) {
            return false;
        }
        $aliasDomainId = $this->resolveDomainId($parts['domain']);
        if ($aliasDomainId === null) {
            $this->logger->warning('StalwartService: Alias-Domain in Stalwart nicht vorhanden', [
                'alias' => $alias, 'domain' => $parts['domain'],
            ]);
            return false;
        }

        $entries = $this->aliasEntries($account);
        foreach ($entries as $entry) {
            if (strtolower((string) ($entry['name'] ?? '')) === $parts['local']
                && (string) ($entry['domainId'] ?? '') === $aliasDomainId) {
                return true; // existiert bereits
            }
        }
        $entries[] = ['name' => $parts['local'], 'domainId' => $aliasDomainId];

        $accountId = (string) $account['id'];
        $resp = $this->jmapSingle('x:Account/set', [
            'update' => [$accountId => ['aliases' => (object) $this->reindexAliasEntries($entries)]],
        ]);
        $ok = $resp !== null && array_key_exists($accountId, $resp['updated'] ?? []);
        if ($ok) {
            $this->logger->info('StalwartService: Alias hinzugefügt', ['email' => $email, 'alias' => $alias]);
        } elseif ($resp !== null) {
            $this->lastError = ['stage' => 'alias:add', 'notUpdated' => $resp['notUpdated'] ?? null];
        }
        return $ok;
    }

    /**
     * Alias entfernen (die Haupt-Adresse kann nicht entfernt werden).
     */
    public function removeAlias(string $email, string $alias): bool {
        $alias = strtolower(trim($alias));
        if ($alias === strtolower(trim($email))) {
            return false; // Haupt-Adresse
        }

        $account = $this->getAccount($email, 'User');
        if ($account === null) {
            return false;
        }
        $parts = $this->splitEmail($alias);
        if ($parts === null) {
            return false;
        }
        $aliasDomainId = $this->resolveDomainId($parts['domain']);

        $entries = $this->aliasEntries($account);
        $filtered = array_values(array_filter($entries, function ($entry) use ($parts, $aliasDomainId) {
            $sameName = strtolower((string) ($entry['name'] ?? '')) === $parts['local'];
            $sameDomain = $aliasDomainId === null
                || (string) ($entry['domainId'] ?? '') === $aliasDomainId;
            return !($sameName && $sameDomain);
        }));

        if (count($filtered) === count($entries)) {
            return true; // war nicht vorhanden
        }

        $accountId = (string) $account['id'];
        $resp = $this->jmapSingle('x:Account/set', [
            'update' => [$accountId => ['aliases' => (object) $this->reindexAliasEntries($filtered)]],
        ]);
        $ok = $resp !== null && array_key_exists($accountId, $resp['updated'] ?? []);
        if ($ok) {
            $this->logger->info('StalwartService: Alias entfernt', ['email' => $email, 'alias' => $alias]);
        }
        return $ok;
    }

    // ============================================================================
    // Globale Alias-Verwaltung (serverweit, unabhängig vom Hauptpostfach)
    //
    // Zweck: Eine Mailadresse kann als Alias auf einem (Alt-)Konto "hängen" und
    // dadurch die Anlage eines neuen Postfachs blockieren. Diese Methoden finden
    // und entfernen einen Alias serverweit – unabhängig davon, welches
    // Hauptpostfach ihn hält.
    // ============================================================================

    /**
     * Globale Alias-Übersicht über ALLE Konten (User + optional Group).
     *
     * @param bool $includeGroups Auch geteilte Postfächer (@type=Group) durchsuchen.
     * @return array<int, array{alias:string, owner:?string, ownerType:string, accountId:string}>
     */
    public function listAllAliases(bool $includeGroups = true): array {
        if (!$this->configService->isStalwartConfigured()) {
            return [];
        }

        $ids = $this->queryAccountIds($includeGroups);
        if (empty($ids)) {
            return [];
        }

        $got = $this->jmapSingle('x:Account/get', [
            'ids' => array_values($ids),
            'properties' => ['@type', 'name', 'domainId', 'emailAddress', 'aliases'],
        ]);

        $result = [];
        foreach ($got['list'] ?? [] as $acc) {
            if (!is_array($acc)) {
                continue;
            }
            $owner = $this->primaryAddress($acc);
            $ownerType = ($acc['@type'] ?? 'User') === 'Group' ? 'group' : 'user';
            $accountId = (string) ($acc['id'] ?? '');
            foreach ($this->aliasAddresses($acc) as $alias) {
                $result[] = [
                    'alias' => $alias,
                    'owner' => $owner,
                    'ownerType' => $ownerType,
                    'accountId' => $accountId,
                ];
            }
        }
        return $result;
    }

    /**
     * Findet alle Konten, die einen bestimmten Alias führen (serverweit).
     *
     * @return array<int, array{owner:?string, ownerType:string, accountId:string}>
     */
    public function findAliasOwners(string $alias, bool $includeGroups = true): array {
        $alias = strtolower(trim($alias));
        $owners = [];
        foreach ($this->listAllAliases($includeGroups) as $entry) {
            if ($entry['alias'] === $alias) {
                $owners[] = [
                    'owner' => $entry['owner'],
                    'ownerType' => $entry['ownerType'],
                    'accountId' => $entry['accountId'],
                ];
            }
        }
        return $owners;
    }

    /**
     * Entfernt einen Alias serverweit von JEDEM Konto, das ihn führt – gibt die
     * Adresse frei (z. B. wenn sie ein neues Postfach blockiert).
     *
     * @return array<int, array{owner:?string, ownerType:string, accountId:string, removed:bool}>
     */
    public function removeAliasByAddress(string $alias, bool $includeGroups = true): array {
        $alias = strtolower(trim($alias));
        $parts = $this->splitEmail($alias);
        if ($parts === null) {
            $this->lastError = ['stage' => 'validate', 'detail' => 'Ungültige Alias-Adresse: ' . $alias];
            return [];
        }
        $aliasDomainId = $this->resolveDomainId($parts['domain']);

        $results = [];
        foreach ($this->findAliasOwners($alias, $includeGroups) as $owner) {
            $removed = $this->removeAliasFromAccountId($owner['accountId'], $parts['local'], $aliasDomainId);
            $results[] = [
                'owner' => $owner['owner'],
                'ownerType' => $owner['ownerType'],
                'accountId' => $owner['accountId'],
                'removed' => $removed,
            ];
        }
        return $results;
    }

    /**
     * Prüft, ob eine Adresse bereits als Haupt-Adresse eines Kontos vergeben ist.
     */
    public function isEmailTaken(string $email): bool {
        $parts = $this->splitEmail(strtolower(trim($email)));
        if ($parts === null) {
            return false;
        }
        $domainId = $this->resolveDomainId($parts['domain']);
        if ($domainId === null) {
            return false; // unbekannte Domain -> nicht vergeben
        }

        $resp = $this->jmapSingle('x:Account/query', [
            'filter' => $this->andFilter([
                ['name' => $parts['local']],
                ['domainId' => $domainId],
            ]),
        ]);
        return !empty($resp['ids'] ?? []);
    }

    /**
     * Postfach-Status für einen Benutzer.
     *
     * @return array{exists: bool, email: ?string, aliases: array, quota: int, used: int, configured: bool}
     */
    public function getMailboxStatus(string $email): array {
        $configured = $this->configService->isStalwartConfigured();
        if (!$configured) {
            return ['exists' => false, 'email' => null, 'aliases' => [], 'quota' => 0, 'used' => 0, 'configured' => false];
        }

        $account = $this->getAccount($email, 'User');
        if ($account === null) {
            return ['exists' => false, 'email' => null, 'aliases' => [], 'quota' => 0, 'used' => 0, 'configured' => true];
        }

        return [
            'exists' => true,
            'email' => $this->primaryAddress($account),
            'aliases' => $this->aliasAddresses($account),
            'quota' => (int) ($account['quotas']['maxDiskQuota'] ?? 0),
            'used' => (int) ($account['usedDiskQuota'] ?? 0),
            'configured' => true,
        ];
    }

    /**
     * E-Mail-Adressen aller individuellen Postfächer (User-Accounts).
     *
     * @return string[]
     */
    public function listPrincipalNames(): array {
        if (!$this->configService->isStalwartConfigured()) {
            return [];
        }

        $query = $this->jmapSingle('x:Account/query', ['filter' => ['@type' => 'User']]);
        $ids = $query['ids'] ?? [];
        if (empty($ids)) {
            return [];
        }

        $got = $this->jmapSingle('x:Account/get', [
            'ids' => array_values($ids),
            'properties' => ['emailAddress'],
        ]);
        $emails = [];
        foreach ($got['list'] ?? [] as $acc) {
            if (isset($acc['emailAddress']) && $acc['emailAddress'] !== '') {
                $emails[] = strtolower((string) $acc['emailAddress']);
            }
        }
        return $emails;
    }

    /**
     * Belegung + Limit aller individuellen Postfächer (User-Accounts).
     * Nutzt die von Stalwart berechneten Felder usedDiskQuota + quotas/maxDiskQuota.
     *
     * @return array<string, array{used:int, quota:int}> Key = Mailadresse (lowercase)
     */
    public function listMailboxUsage(): array {
        if (!$this->configService->isStalwartConfigured()) {
            return [];
        }

        $query = $this->jmapSingle('x:Account/query', ['filter' => ['@type' => 'User']]);
        $ids = $query['ids'] ?? [];
        if (empty($ids)) {
            return [];
        }

        $got = $this->jmapSingle('x:Account/get', [
            'ids' => array_values($ids),
            'properties' => ['emailAddress', 'usedDiskQuota', 'quotas'],
        ]);

        $result = [];
        foreach ($got['list'] ?? [] as $acc) {
            $email = strtolower((string) ($acc['emailAddress'] ?? ''));
            if ($email === '') {
                continue;
            }
            $result[$email] = [
                'used' => (int) ($acc['usedDiskQuota'] ?? 0),
                'quota' => (int) ($acc['quotas']['maxDiskQuota'] ?? 0),
            ];
        }
        return $result;
    }

    // ============================================================================
    // Wartung / Mail-Reindexierung (Stalwart-Tasks)
    //
    // Übersetzt die stalwart-cli-Befehle `create task/AccountMaintenance` bzw.
    // `create task/StoreMaintenance` in JMAP: die CLI-Kurzform Object/Variant
    // legt ein x:Task-Objekt an und injiziert @type=<Variant>. Damit ist der
    // JMAP-Aufruf `x:Task/set` mit create + @type=AccountMaintenance/StoreMaintenance.
    // ============================================================================

    /**
     * Reindexiert die Mails eines EINZELNEN Kontos (Postfach).
     * Entspricht: stalwart-cli create task/AccountMaintenance
     *   --field accountId=<ID> --field maintenanceType=reindex --field status={...}.
     *
     * @param string $email Mailadresse des Postfachs (accountId wird aufgelöst)
     * @param string|null $due ISO-8601-Fälligkeit (Default: jetzt, UTC)
     */
    public function reindexAccount(string $email, ?string $due = null): bool {
        $accountId = $this->findAccountId($email, 'User');
        if ($accountId === null) {
            $this->lastError = ['stage' => 'reindex', 'detail' => "Kein Postfach für {$email} gefunden."];
            return false;
        }
        return $this->createMaintenanceTask([
            '@type' => 'AccountMaintenance',
            'accountId' => $accountId,
            'maintenanceType' => 'reindex',
            'status' => ['@type' => 'Pending', 'due' => $this->taskDue($due)],
        ]);
    }

    /**
     * Reindexiert die Mails ALLER Konten (Store-weit).
     * Entspricht: stalwart-cli create task/StoreMaintenance
     *   --field maintenanceType=reindexAccounts --field status={...}.
     *
     * @param string|null $due ISO-8601-Fälligkeit (Default: jetzt, UTC)
     */
    public function reindexAllAccounts(?string $due = null): bool {
        return $this->createMaintenanceTask([
            '@type' => 'StoreMaintenance',
            'maintenanceType' => 'reindexAccounts',
            'status' => ['@type' => 'Pending', 'due' => $this->taskDue($due)],
        ]);
    }

    /**
     * Legt einen Stalwart-Wartungs-Task an (x:Task/set create).
     */
    private function createMaintenanceTask(array $object): bool {
        $resp = $this->jmapSingle('x:Task/set', ['create' => ['nc0' => $object]]);
        $ok = $resp !== null && array_key_exists('nc0', $resp['created'] ?? []);
        if ($ok) {
            $this->logger->info('StalwartService: Wartungs-Task angelegt', [
                'type' => $object['@type'] ?? '?',
                'maintenanceType' => $object['maintenanceType'] ?? '?',
            ]);
        } elseif ($resp !== null) {
            $this->lastError = ['stage' => 'task:create', 'notCreated' => $resp['notCreated'] ?? null];
        }
        return $ok;
    }

    /**
     * Normalisiert die Task-Fälligkeit auf ISO-8601 (UTC). Ohne Angabe: jetzt.
     */
    private function taskDue(?string $due): string {
        if ($due !== null && trim($due) !== '') {
            return trim($due);
        }
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    /**
     * Leitet aus einem NC-User die zu verwendende Mailadresse ab.
     */
    public function mailFor(\OCP\IUser $user): ?string {
        $email = $user->getEMailAddress();
        if ($email && $this->configService->isEmailDomainAllowed($email)) {
            return strtolower($email);
        }

        $uid = $user->getUID();
        if (filter_var($uid, FILTER_VALIDATE_EMAIL)) {
            return strtolower($uid);
        }

        $domains = $this->configService->getAllowedDomains();
        if (empty($domains)) {
            return null;
        }

        return strtolower($uid) . '@' . $domains[0];
    }

    // ============================================================================
    // Status & Health
    // ============================================================================

    public function isAvailable(): bool {
        return $this->configService->isStalwartConfigured() && $this->session() !== false;
    }

    /**
     * @return array{configured: bool, available: bool, url: ?string}
     */
    public function getStatus(): array {
        $url = $this->configService->getStalwartApiUrl();
        return [
            'configured' => $this->configService->isStalwartConfigured(),
            'available' => $this->isAvailable(),
            'url' => $url ? preg_replace('/\/\/[^:]+:[^@]+@/', '//***:***@', $url) : null,
        ];
    }

    /**
     * Letzter erfasster Fehler (JMAP-/HTTP-/Validierungsdetail) für Diagnose.
     *
     * @return array|null
     */
    public function getLastError(): ?array {
        return $this->lastError;
    }

    // ============================================================================
    // JMAP-Bausteine (auch von SharedMailboxService genutzt)
    // ============================================================================

    /**
     * Domain-ID per Domain-Namen auflösen (gecached).
     */
    public function resolveDomainId(string $domain): ?string {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return null;
        }
        if (array_key_exists($domain, $this->domainIdCache)) {
            return $this->domainIdCache[$domain];
        }

        $resp = $this->jmapSingle('x:Domain/query', ['filter' => ['name' => $domain]]);
        $id = $resp['ids'][0] ?? null;
        $this->domainIdCache[$domain] = $id !== null ? (string) $id : null;
        return $this->domainIdCache[$domain];
    }

    /**
     * Map domainId => domainName (gecached).
     *
     * @return array<string,string>
     */
    public function domainNameMap(): array {
        if ($this->domainNameMapCache !== null) {
            return $this->domainNameMapCache;
        }

        $map = [];
        $query = $this->jmapSingle('x:Domain/query', []);
        $ids = $query['ids'] ?? [];
        if (!empty($ids)) {
            $got = $this->jmapSingle('x:Domain/get', [
                'ids' => array_values($ids),
                'properties' => ['name'],
            ]);
            foreach ($got['list'] ?? [] as $domain) {
                if (isset($domain['id'], $domain['name'])) {
                    $map[(string) $domain['id']] = strtolower((string) $domain['name']);
                }
            }
        }
        $this->domainNameMapCache = $map;
        return $map;
    }

    /**
     * Rohdaten aller Domains (für Diagnose): IDs + vollständige Objekte.
     *
     * @return array{ids: array, list: array}
     */
    public function listDomainsRaw(): array {
        $query = $this->jmapSingle('x:Domain/query', []);
        $ids = $query['ids'] ?? [];
        if (empty($ids)) {
            return ['ids' => [], 'list' => []];
        }
        $got = $this->jmapSingle('x:Domain/get', ['ids' => array_values($ids)]);
        return ['ids' => array_values($ids), 'list' => $got['list'] ?? []];
    }

    /**
     * Domain in Stalwart anlegen (idempotent). Voraussetzung für Postfächer/Aliase.
     */
    public function createDomain(string $name): bool {
        $name = strtolower(trim($name));
        if ($name === '') {
            return false;
        }
        if ($this->resolveDomainId($name) !== null) {
            return true; // existiert bereits
        }

        $resp = $this->jmapSingle('x:Domain/set', ['create' => ['nc0' => ['name' => $name]]]);
        $ok = $resp !== null && array_key_exists('nc0', $resp['created'] ?? []);
        if ($ok) {
            $this->domainIdCache = [];
            $this->domainNameMapCache = null;
            $this->logger->info('StalwartService: Domain angelegt', ['domain' => $name]);
        } elseif ($resp !== null) {
            $this->lastError = ['stage' => 'domain:create', 'notCreated' => $resp['notCreated'] ?? null];
        }
        return $ok;
    }

    /**
     * Domain in Stalwart löschen.
     */
    public function deleteDomain(string $name): bool {
        $name = strtolower(trim($name));
        $id = $this->resolveDomainId($name);
        if ($id === null) {
            return false;
        }

        $resp = $this->jmapSingle('x:Domain/set', ['destroy' => [$id]]);
        $ok = $resp !== null && in_array($id, $resp['destroyed'] ?? [], true);
        if ($ok) {
            $this->domainIdCache = [];
            $this->domainNameMapCache = null;
            $this->logger->info('StalwartService: Domain gelöscht', ['domain' => $name]);
        } elseif ($resp !== null) {
            $this->lastError = ['stage' => 'domain:delete', 'notDestroyed' => $resp['notDestroyed'] ?? null];
        }
        return $ok;
    }

    /**
     * Konto-ID per Mailadresse finden.
     *
     * @param string $email
     * @param string|null $type 'User' | 'Group' | null (beliebig)
     */
    public function findAccountId(string $email, ?string $type = null): ?string {
        $parts = $this->splitEmail(strtolower(trim($email)));
        if ($parts === null) {
            return null;
        }
        $domainId = $this->resolveDomainId($parts['domain']);
        if ($domainId === null) {
            return null;
        }

        $conditions = [['name' => $parts['local']], ['domainId' => $domainId]];
        if ($type !== null) {
            $conditions[] = ['@type' => $type];
        }

        $resp = $this->jmapSingle('x:Account/query', ['filter' => $this->andFilter($conditions)]);
        $id = $resp['ids'][0] ?? null;
        return $id !== null ? (string) $id : null;
    }

    /**
     * Vollständiges Konto-Objekt per Mailadresse laden.
     *
     * @return array|null  JMAP-Objekt inkl. id, emailAddress, aliases, quotas, ...
     */
    public function getAccount(string $email, ?string $type = null): ?array {
        $id = $this->findAccountId($email, $type);
        if ($id === null) {
            return null;
        }
        return $this->getAccountById($id);
    }

    /**
     * Vollständiges Konto-Objekt per Konto-ID laden.
     */
    public function getAccountById(string $id): ?array {
        $resp = $this->jmapSingle('x:Account/get', ['ids' => [$id]]);
        $list = $resp['list'] ?? [];
        return $list[0] ?? null;
    }

    /**
     * Einen einzelnen JMAP-Methodenaufruf absetzen und die Antwort-Argumente
     * zurückgeben.
     *
     * Management-Methoden (x:Account/x:Domain) sind serverglobal und benötigen
     * KEINE accountId – die offizielle stalwart-cli sendet ebenfalls keine.
     * Die Args werden daher unverändert übernommen.
     *
     * @return array|null  Antwort-Argumente oder null bei Transport-/Methodenfehler
     */
    public function jmapSingle(string $method, array $args, string $callId = 'c0'): ?array {
        $responses = $this->jmapCall([[$method, $args, $callId]]);
        if ($responses === null || !isset($responses[0])) {
            return null;
        }

        [$name, $respArgs] = [$responses[0][0] ?? '', $responses[0][1] ?? []];
        if ($name === 'error') {
            $this->lastError = ['stage' => 'method:' . $method, 'error' => $respArgs];
            $this->logger->warning('StalwartService: JMAP-Methodenfehler', [
                'method' => $method,
                'error' => $respArgs,
            ]);
            return null;
        }
        return is_array($respArgs) ? $respArgs : [];
    }

    /**
     * Mehrere JMAP-Methodenaufrufe in einem Request absetzen.
     *
     * @param array $methodCalls Liste von [methodName, args, callId]
     * @return array|null  methodResponses oder null bei Transportfehler
     */
    public function jmapCall(array $methodCalls): ?array {
        $session = $this->session();
        if ($session === false) {
            return null;
        }

        // JMAP-Methoden-Argumente MÜSSEN ein Objekt sein. Leere Args ([]) würde
        // PHP als JSON-Array [] kodieren -> Stalwart lehnt mit "notRequest" ab
        // (z. B. x:Domain/query ohne Filter). Daher leere Args in {} umwandeln.
        $normalizedCalls = [];
        foreach ($methodCalls as $call) {
            if (is_array($call) && array_key_exists(1, $call) && $call[1] === []) {
                $call[1] = new \stdClass();
            }
            $normalizedCalls[] = $call;
        }

        $body = [
            'using' => [self::CAP_CORE, self::CAP_STALWART],
            'methodCalls' => $normalizedCalls,
        ];

        $result = $this->http('POST', $session['apiUrl'], $body);
        if ($result === null) {
            return null;
        }
        [$status, $decoded] = $result;
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            $this->lastError = ['stage' => 'http', 'status' => $status, 'response' => is_array($decoded) ? $decoded : null];
            $this->logger->warning('StalwartService: JMAP-Request fehlgeschlagen', [
                'status' => $status,
                'response' => is_array($decoded) ? $decoded : null,
            ]);
            return null;
        }
        return $decoded['methodResponses'] ?? null;
    }

    // ============================================================================
    // Interne Helfer
    // ============================================================================

    /**
     * JMAP-Session holen (gecached). Liefert ['apiUrl' => string] oder false.
     *
     * Management-Methoden (x:Account/x:Domain) sind serverglobal: es wird KEINE
     * accountId benötigt (die offizielle stalwart-cli liest ebenfalls nur
     * Capabilities + apiUrl). Die Session gilt daher als verfügbar, sobald
     * /jmap/session mit HTTP 2xx + JSON antwortet.
     */
    private function session(): array|false {
        if ($this->sessionCache !== null) {
            return $this->sessionCache;
        }

        if (!$this->configService->isStalwartConfigured()) {
            return $this->sessionCache = false;
        }

        $config = $this->configService->getStalwartConfig();
        $base = rtrim((string) $config['url'], '/');
        // Legacy-Pfade (alte REST-Konfiguration) entfernen -> reine Server-Basis.
        $base = preg_replace('#/(api|jmap)(/session)?$#', '', $base) ?? $base;
        $result = $this->http('GET', $base . '/jmap/session', null);
        if ($result === null) {
            return $this->sessionCache = false;
        }
        [$status, $decoded] = $result;
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            $this->logger->warning('StalwartService: JMAP-Session nicht erreichbar', ['status' => $status]);
            return $this->sessionCache = false;
        }

        // apiUrl aus der Session nur als PFAD übernehmen und an die intern
        // konfigurierte Basis hängen. Stalwart 0.16 publiziert in der Discovery
        // den öffentlichen Hostname – dieser ist intern u. U. nicht erreichbar.
        $apiPath = $this->apiPathFromSession($decoded['apiUrl'] ?? null);
        return $this->sessionCache = [
            'apiUrl' => $base . $apiPath,
        ];
    }

    /**
     * Extrahiert aus dem Session-apiUrl einen relativen Pfad (Default /jmap).
     */
    private function apiPathFromSession(mixed $apiUrl): string {
        if (!is_string($apiUrl) || trim($apiUrl) === '') {
            return '/jmap';
        }
        $apiUrl = trim($apiUrl);
        if (preg_match('#^https?://#i', $apiUrl)) {
            $path = parse_url($apiUrl, PHP_URL_PATH);
            $apiUrl = is_string($path) ? $path : '/jmap';
        }
        $apiUrl = '/' . trim($apiUrl, '/');
        return $apiUrl === '/' ? '/jmap' : $apiUrl;
    }

    /**
     * HTTP-Request (Basic Auth, JSON). Liefert [statusCode, decodedBody|null] oder null.
     * protected: wird in Tests überschrieben.
     *
     * @return array{0:int,1:mixed}|null
     */
    protected function http(string $method, string $url, ?array $body): ?array {
        $config = $this->configService->getStalwartConfig();

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_USERPWD => $config['user'] . ':' . $config['password'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        if ($body !== null) {
            // Slashes (z. B. in Methodennamen "x:Account/set" und JSON-Pointer-Patches
            // wie "quotas/maxDiskQuota") sowie Unicode NICHT escapen – identisch zur
            // offiziellen stalwart-cli; verhindert "x:Account\/set" auf dem Draht.
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error('StalwartService: cURL-Fehler', [
                'url' => $url, 'method' => $method, 'error' => $error,
            ]);
            return null;
        }

        $decoded = $response !== '' && $response !== false ? json_decode((string) $response, true) : [];
        return [$httpCode, $decoded];
    }

    /**
     * @return array{local:string,domain:string}|null
     */
    private function splitEmail(string $email): ?array {
        $email = strtolower(trim($email));
        $pos = strrpos($email, '@');
        if ($pos === false || $pos === 0 || $pos === strlen($email) - 1) {
            return null;
        }
        return [
            'local' => substr($email, 0, $pos),
            'domain' => substr($email, $pos + 1),
        ];
    }

    /**
     * Baut einen JMAP-Filter aus mehreren Bedingungen (nur AND wird unterstützt).
     */
    private function andFilter(array $conditions): array {
        $conditions = array_values($conditions);
        if (count($conditions) === 1) {
            return $conditions[0];
        }
        return ['operator' => 'AND', 'conditions' => $conditions];
    }

    /**
     * Haupt-Adresse eines Kontos (emailAddress wird vom Server berechnet).
     */
    private function primaryAddress(array $account): ?string {
        if (isset($account['emailAddress']) && $account['emailAddress'] !== '') {
            return strtolower((string) $account['emailAddress']);
        }
        // Fallback aus name + domainId
        $name = $account['name'] ?? null;
        $domainId = isset($account['domainId']) ? (string) $account['domainId'] : null;
        if ($name !== null && $domainId !== null) {
            $domain = $this->domainNameMap()[$domainId] ?? null;
            if ($domain !== null) {
                return strtolower($name . '@' . $domain);
            }
        }
        return null;
    }

    /**
     * Roh-Alias-Einträge eines Kontos als Liste (name, domainId, enabled, description).
     *
     * @return array<int,array>
     */
    private function aliasEntries(array $account): array {
        $aliases = $account['aliases'] ?? [];
        if (!is_array($aliases)) {
            return [];
        }
        return array_values(array_filter($aliases, 'is_array'));
    }

    /**
     * Alias-Adressen eines Kontos als E-Mail-Strings.
     *
     * @return string[]
     */
    private function aliasAddresses(array $account): array {
        $map = $this->domainNameMap();
        $result = [];
        foreach ($this->aliasEntries($account) as $entry) {
            $name = $entry['name'] ?? null;
            $domainId = isset($entry['domainId']) ? (string) $entry['domainId'] : null;
            if ($name === null || $domainId === null) {
                continue;
            }
            $domain = $map[$domainId] ?? null;
            if ($domain !== null) {
                $result[] = strtolower($name . '@' . $domain);
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * Alias-Einträge als JMAP-List (Map index => Eintrag) neu indizieren.
     * Aufrufer casten das Ergebnis via (object), damit es als JSON-Objekt
     * {"0":{...}} statt als Array serialisiert wird (Stalwart-Anforderung).
     *
     * @return array<string,array>
     */
    private function reindexAliasEntries(array $entries): array {
        $map = [];
        $i = 0;
        foreach (array_values($entries) as $entry) {
            $clean = [
                'name' => (string) $entry['name'],
                'domainId' => (string) $entry['domainId'],
            ];
            if (isset($entry['description']) && $entry['description'] !== null) {
                $clean['description'] = (string) $entry['description'];
            }
            $map[(string) $i] = $clean;
            $i++;
        }
        return $map;
    }

    /**
     * IDs aller Konten (User; optional auch Group) für die serverweite
     * Alias-Suche. includeGroups=true -> kein @type-Filter (alle Konten).
     *
     * @return array<int,string>
     */
    private function queryAccountIds(bool $includeGroups): array {
        $args = $includeGroups ? [] : ['filter' => ['@type' => 'User']];
        $query = $this->jmapSingle('x:Account/query', $args);
        return $query['ids'] ?? [];
    }

    /**
     * Entfernt einen Alias (Localpart + domainId) direkt von einem Konto per ID.
     * Idempotent: fehlt der Alias, gilt das als Erfolg.
     */
    private function removeAliasFromAccountId(string $accountId, string $aliasLocal, ?string $aliasDomainId): bool {
        $account = $this->getAccountById($accountId);
        if ($account === null) {
            return false;
        }

        $entries = $this->aliasEntries($account);
        $filtered = array_values(array_filter($entries, function ($entry) use ($aliasLocal, $aliasDomainId) {
            $sameName = strtolower((string) ($entry['name'] ?? '')) === $aliasLocal;
            $sameDomain = $aliasDomainId === null
                || (string) ($entry['domainId'] ?? '') === $aliasDomainId;
            return !($sameName && $sameDomain);
        }));

        if (count($filtered) === count($entries)) {
            return true; // war nicht vorhanden
        }

        $resp = $this->jmapSingle('x:Account/set', [
            'update' => [$accountId => ['aliases' => (object) $this->reindexAliasEntries($filtered)]],
        ]);
        $ok = $resp !== null && array_key_exists($accountId, $resp['updated'] ?? []);
        if ($ok) {
            $this->logger->info('StalwartService: Alias serverweit entfernt', [
                'alias' => $aliasLocal, 'accountId' => $accountId,
            ]);
        } elseif ($resp !== null) {
            $this->lastError = ['stage' => 'alias:global-remove', 'notUpdated' => $resp['notUpdated'] ?? null];
        }
        return $ok;
    }
}
