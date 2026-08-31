<?php

declare(strict_types=1);

/**
 * Souvera Central - Mail-Domain-Verwaltung
 *
 * Domains sind first-class: Jede Domain in der Erlaubnisliste ist eine
 * Mail-Domain (Benutzer-Anlage über das Domain-Dropdown im User-Editor,
 * Aliase, Senden). Der Hoster/CloudManager legt sie an — DNS (MX, SPF,
 * DKIM, DMARC) bleibt wie bei der Hauptdomain beim CM.
 *
 * Zugriffswege: occ (`souvera:domain:add|delete|list`) und die Admin-API
 * (`/api/domains`, Souvera-Admin-Middleware). Contract für den CM:
 * docs/MULTI_DOMAIN.md.
 */

namespace OCA\SouveraCentral\Service;

use Psr\Log\LoggerInterface;

class DomainManagementService
{
    public function __construct(
        private StalwartService $stalwart,
        private ConfigService $config,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Alle Domains mit Stalwart-Status und Belegung. Enthält zusätzlich
     * Domains, die nur in Stalwart existieren (nicht erlaubt) — informativ.
     *
     * @return array{domains:list<array{domain:string, allowed:bool, in_stalwart:bool, accounts:int, aliases:int}>, stalwart_available:bool}
     */
    public function listDomains(): array
    {
        $whitelist = array_map(
            static fn ($d) => strtolower(trim((string) $d)),
            $this->config->getAllowedDomains()
        );

        $stalwartAvailable = $this->stalwart->isAvailable();
        $accountsByDomain = [];
        $aliasesByDomain = [];

        if ($stalwartAvailable) {
            try {
                foreach ($this->stalwart->listPrincipalNames() as $email) {
                    $d = $this->domainOf($email);
                    if ($d !== null) {
                        $accountsByDomain[$d] = ($accountsByDomain[$d] ?? 0) + 1;
                    }
                }
                foreach ($this->stalwart->listAllAliases(true) as $alias) {
                    $d = $this->domainOf((string) ($alias['alias'] ?? ''));
                    if ($d !== null) {
                        $aliasesByDomain[$d] = ($aliasesByDomain[$d] ?? 0) + 1;
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Souvera Central: domain usage query failed: ' . $e->getMessage(), ['app' => 'souvera_central']);
            }
        }

        $domains = [];
        foreach ($whitelist as $domain) {
            if ($domain === '') {
                continue;
            }
            $domains[$domain] = [
                'domain' => $domain,
                'allowed' => true,
                'in_stalwart' => $stalwartAvailable && $this->stalwart->resolveDomainId($domain) !== null,
                'accounts' => $accountsByDomain[$domain] ?? 0,
                'aliases' => $aliasesByDomain[$domain] ?? 0,
            ];
        }

        // Stalwart-only Domains (angelegt, aber nicht erlaubt) informativ.
        if ($stalwartAvailable) {
            try {
                foreach ($this->stalwart->domainNameMap() ?: [] as $name) {
                    $name = strtolower((string) $name);
                    if ($name !== '' && !isset($domains[$name])) {
                        $domains[$name] = [
                            'domain' => $name,
                            'allowed' => false,
                            'in_stalwart' => true,
                            'accounts' => $accountsByDomain[$name] ?? 0,
                            'aliases' => $aliasesByDomain[$name] ?? 0,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Souvera Central: stalwart domain map failed: ' . $e->getMessage(), ['app' => 'souvera_central']);
            }
        }

        ksort($domains);

        return [
            'domains' => array_values($domains),
            'stalwart_available' => $stalwartAvailable,
        ];
    }

    /**
     * Legt eine Mail-Domain an: Stalwart-Domain (idempotent) + Erlaubnisliste.
     *
     * @return array{domain:string, created_in_stalwart:bool, allowed:bool}
     * @throws \InvalidArgumentException bei ungültigem Namen
     * @throws \RuntimeException wenn Stalwart die Domain ablehnt
     */
    public function addDomain(string $domain): array
    {
        $domain = strtolower(trim($domain));
        if ($domain === '' || preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain) !== 1) {
            throw new \InvalidArgumentException('Ungültiger Domainname: ' . $domain);
        }

        $created = false;
        if ($this->stalwart->resolveDomainId($domain) === null) {
            if (!$this->stalwart->createDomain($domain)) {
                throw new \RuntimeException(
                    'Stalwart hat die Domain abgelehnt: '
                    . (string) json_encode($this->stalwart->getLastError(), JSON_UNESCAPED_SLASHES)
                );
            }
            $created = true;
        }

        $this->config->addAllowedDomain($domain);

        return ['domain' => $domain, 'created_in_stalwart' => $created, 'allowed' => true];
    }

    /**
     * Entfernt eine Mail-Domain aus der Erlaubnisliste (und aus Stalwart).
     *
     * @throws \RuntimeException wenn die Domain noch benutzt wird
     */
    public function removeDomain(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $usage = $this->usageFor($domain);

        if ($usage['accounts'] > 0 || $usage['aliases'] > 0) {
            throw new \RuntimeException(sprintf(
                'Domain ist noch in Benutzung (%d Postfächer, %d Aliase). Erst umziehen/entfernen.',
                $usage['accounts'],
                $usage['aliases']
            ));
        }

        $this->config->removeAllowedDomain($domain);

        $deleted = false;
        if ($this->stalwart->isAvailable() && $this->stalwart->resolveDomainId($domain) !== null) {
            $deleted = $this->stalwart->deleteDomain($domain);
        }

        return ['domain' => $domain, 'allowed' => false, 'stalwart_deleted' => $deleted];
    }

    /**
     * Belegung einer Domain (Postfächer + Aliase).
     *
     * @return array{accounts:int, aliases:int}
     */
    public function usageFor(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $accounts = 0;
        $aliases = 0;

        if (!$this->stalwart->isAvailable()) {
            return ['accounts' => 0, 'aliases' => 0];
        }

        try {
            foreach ($this->stalwart->listPrincipalNames() as $email) {
                if ($this->domainOf($email) === $domain) {
                    $accounts++;
                }
            }
            foreach ($this->stalwart->listAllAliases(true) as $alias) {
                if ($this->domainOf((string) ($alias['alias'] ?? '')) === $domain) {
                    $aliases++;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Souvera Central: domain usage query failed: ' . $e->getMessage(), ['app' => 'souvera_central']);
        }

        return ['accounts' => $accounts, 'aliases' => $aliases];
    }

    private function domainOf(string $email): ?string
    {
        $at = strrpos($email, '@');
        if ($at === false || $at === strlen($email) - 1) {
            return null;
        }
        return strtolower(substr($email, $at + 1));
    }
}
