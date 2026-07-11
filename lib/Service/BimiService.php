<?php

declare(strict_types=1);

/**
 * Souvera Central - BIMI Service
 *
 * Kapselt die BIMI-Logik: Domain-/DMARC-Prüfung, SVG-Tiny-P/S-Validierung +
 * Normalisierung, DNS-Record-Erzeugung und persistente Ablage der Konfiguration
 * in der AppConfig (Schlüssel `bimi.configs`, JSON-Map pro Domain). Die Ablage in
 * AppConfig ist bewusst gewählt, damit die Shield-App den Zustand direkt auslesen
 * und laufend überwachen kann (Quelle der Wahrheit = Central).
 */

namespace OCA\SouveraCentral\Service;

use OCP\IConfig;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class BimiService {
    private const APP_ID = 'souvera_central';
    private const STORE_KEY = 'bimi.configs';
    public const SELECTOR = 'default';
    private const MAX_SVG_BYTES = 32768;

    /** SVG-Elemente, die in SVG P/S verboten sind und automatisch entfernt werden. */
    private const STRIP_TAGS = [
        'script', 'style', 'animate', 'animatecolor', 'animatemotion', 'animatetransform',
        'set', 'metadata', 'foreignobject', 'audio', 'video', 'iframe', 'switch',
    ];

    public function __construct(
        private IConfig $config,
        private IURLGenerator $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    // ========================================================================
    // Persistenz (AppConfig; von Shield lesbar)
    // ========================================================================

    /** @return array<string,array> */
    public function listConfigs(): array {
        $raw = $this->config->getAppValue(self::APP_ID, self::STORE_KEY, '');
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public function getConfig(string $domain): ?array {
        $domain = $this->normalizeDomain($domain);
        $all = $this->listConfigs();
        return $all[$domain] ?? null;
    }

    private function saveConfig(string $domain, array $data): void {
        $domain = $this->normalizeDomain($domain);
        $all = $this->listConfigs();
        $all[$domain] = $data;
        $this->config->setAppValue(self::APP_ID, self::STORE_KEY, json_encode($all));
    }

    public function deleteConfig(string $domain): void {
        $domain = $this->normalizeDomain($domain);
        $all = $this->listConfigs();
        unset($all[$domain]);
        $this->config->setAppValue(self::APP_ID, self::STORE_KEY, json_encode($all));
    }

    private function ensureConfig(string $domain): array {
        return $this->getConfig($domain) ?? [
            'domain' => $this->normalizeDomain($domain),
            'svg' => null,
            'svgSize' => 0,
            'svgUpdatedAt' => null,
            'vmcMode' => 'none',
            'vmcUrl' => null,
            'vmcPem' => null,
            'dmarc' => null,
            'updatedAt' => null,
        ];
    }

    // ========================================================================
    // Domain / DMARC
    // ========================================================================

    public function normalizeDomain(string $domain): string {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^[a-z]+://#', '', $domain) ?? $domain;
        if (str_contains($domain, '@')) {
            $domain = substr($domain, strrpos($domain, '@') + 1);
        }
        $domain = trim($domain, " \t\n\r\0\x0B/.");
        return $domain;
    }

    public function isValidDomain(string $domain): bool {
        $domain = $this->normalizeDomain($domain);
        return $domain !== '' && (bool) preg_match('/^(?=.{1,253}$)([a-z0-9](-?[a-z0-9])*)(\.[a-z0-9](-?[a-z0-9])*)+$/i', $domain);
    }

    /** @return string[] raw TXT strings */
    protected function fetchTxt(string $host): array {
        $records = [];
        try {
            $res = @dns_get_record($host, DNS_TXT);
            if (is_array($res)) {
                foreach ($res as $r) {
                    if (isset($r['txt'])) {
                        $records[] = $r['txt'];
                    } elseif (isset($r['entries']) && is_array($r['entries'])) {
                        $records[] = implode('', $r['entries']);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->debug('BIMI DNS lookup failed for ' . $host . ': ' . $e->getMessage());
        }
        return $records;
    }

    public function checkDmarc(string $domain): array {
        $domain = $this->normalizeDomain($domain);
        $checkedAt = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $txts = $this->fetchTxt('_dmarc.' . $domain);

        $record = null;
        foreach ($txts as $t) {
            if (stripos(trim($t), 'v=DMARC1') === 0) {
                $record = trim($t);
                break;
            }
        }

        if ($record === null) {
            return [
                'found' => false,
                'enforced' => false,
                'policy' => null,
                'pct' => null,
                'rua' => null,
                'record' => null,
                'issues' => ['Kein DMARC-Eintrag unter _dmarc.' . $domain . ' gefunden. Voraussetzung für BIMI: DMARC mit p=quarantine (pct=100) oder p=reject.'],
                'checkedAt' => $checkedAt,
            ];
        }

        $tags = [];
        foreach (explode(';', $record) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $tags[strtolower(trim($kv[0]))] = trim($kv[1]);
            }
        }

        $policy = isset($tags['p']) ? strtolower($tags['p']) : null;
        $pct = isset($tags['pct']) ? (int) $tags['pct'] : 100;
        $rua = $tags['rua'] ?? null;
        $issues = [];
        $enforced = false;

        if ($policy === 'reject') {
            $enforced = true;
        } elseif ($policy === 'quarantine') {
            if ($pct === 100) {
                $enforced = true;
            } else {
                $issues[] = 'DMARC-Policy "quarantine" mit pct=' . $pct . '. Für BIMI muss pct=100 gelten.';
            }
        } else {
            $issues[] = 'DMARC-Policy ist "' . ($policy ?? 'nicht gesetzt') . '". Für BIMI ist p=quarantine (pct=100) oder p=reject erforderlich.';
        }

        if ($rua === null || $rua === '') {
            $issues[] = 'Hinweis: Kein rua= (Aggregat-Report-Adresse) gesetzt – von einigen BIMI-Prüfern empfohlen.';
        }

        $result = [
            'found' => true,
            'enforced' => $enforced,
            'policy' => $policy,
            'pct' => $pct,
            'rua' => $rua,
            'record' => $record,
            'issues' => $issues,
            'checkedAt' => $checkedAt,
        ];

        // Ergebnis persistieren (für Shield-Überwachung)
        $cfg = $this->ensureConfig($domain);
        $cfg['dmarc'] = $result;
        $cfg['updatedAt'] = $checkedAt;
        $this->saveConfig($domain, $cfg);

        return $result;
    }

    // ========================================================================
    // SVG Tiny P/S – Validierung + Normalisierung
    // ========================================================================

    /**
     * @return array{ok:bool, normalized:?string, size:int, errors:string[], warnings:string[]}
     */
    public function validateSvg(string $svg): array {
        $errors = [];
        $warnings = [];

        $svg = trim($svg);
        if ($svg === '') {
            return ['ok' => false, 'normalized' => null, 'size' => 0, 'errors' => ['Leere Datei.'], 'warnings' => []];
        }
        if (stripos($svg, '<!doctype') !== false || stripos($svg, '<!entity') !== false || str_contains($svg, '<!ENTITY')) {
            return ['ok' => false, 'normalized' => null, 'size' => 0, 'errors' => ['DOCTYPE/ENTITY nicht erlaubt (Sicherheit / SVG P/S).'], 'warnings' => []];
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $prev = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded || $dom->documentElement === null) {
            return ['ok' => false, 'normalized' => null, 'size' => 0, 'errors' => ['Datei ist kein gültiges XML/SVG.'], 'warnings' => []];
        }

        $root = $dom->documentElement;
        if (strtolower($root->localName) !== 'svg') {
            return ['ok' => false, 'normalized' => null, 'size' => 0, 'errors' => ['Wurzelelement ist kein <svg>.'], 'warnings' => []];
        }

        // Kommentare + Processing Instructions entfernen
        foreach (iterator_to_array($this->collectNodes($dom, [XML_COMMENT_NODE, XML_PI_NODE])) as $node) {
            $node->parentNode?->removeChild($node);
        }

        // Raster-Bild = harter Fehler (kein Vektor)
        if ($this->hasTag($dom, 'image')) {
            $errors[] = 'Enthält <image> (Rasterbild). BIMI verlangt echtes Vektor-SVG (SVG P/S).';
        }

        // Externe Referenzen (href/xlink:href auf http/https/data) = harter Fehler
        foreach (iterator_to_array($dom->getElementsByTagName('*')) as $el) {
            foreach (['href', 'xlink:href'] as $attr) {
                $val = $attr === 'href'
                    ? $el->getAttribute('href')
                    : $el->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
                if ($val !== '' && preg_match('#^\s*(https?:|data:|//)#i', $val)) {
                    $errors[] = 'Externe/eingebettete Referenz gefunden (' . $attr . '="' . mb_substr($val, 0, 40) . '…"). In SVG P/S nicht erlaubt.';
                    break;
                }
            }
        }

        // Verbotene Elemente entfernen (auto-fix)
        $strippedTags = [];
        foreach (self::STRIP_TAGS as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagNameNS('*', $tag)) as $el) {
                $el->parentNode?->removeChild($el);
                $strippedTags[$tag] = true;
            }
        }
        if ($strippedTags !== []) {
            $warnings[] = 'Nicht erlaubte Elemente entfernt: <' . implode('>, <', array_keys($strippedTags)) . '>.';
        }

        // <a> auspacken (Inhalt behalten, Hyperlink entfernen)
        foreach (iterator_to_array($dom->getElementsByTagNameNS('*', 'a')) as $a) {
            $parent = $a->parentNode;
            if ($parent === null) {
                continue;
            }
            while ($a->firstChild) {
                $parent->insertBefore($a->firstChild, $a);
            }
            $parent->removeChild($a);
            $warnings[] = 'Hyperlink <a> entfernt (Inhalt beibehalten).';
        }

        // on*-Event-Attribute entfernen
        foreach (iterator_to_array($dom->getElementsByTagName('*')) as $el) {
            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                if (stripos($attr->nodeName, 'on') === 0) {
                    $el->removeAttribute($attr->nodeName);
                    $warnings[] = 'Event-Handler-Attribut entfernt.';
                }
            }
        }

        // Wurzel-Attribute normalisieren
        $root->setAttribute('version', '1.2');
        $root->setAttribute('baseProfile', 'tiny-ps');
        if ($root->getAttribute('xmlns') === '' && $root->namespaceURI === null) {
            $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        }
        // x/y auf der Wurzel sind in P/S verboten
        foreach (['x', 'y'] as $a) {
            if ($root->hasAttribute($a)) {
                $root->removeAttribute($a);
                $warnings[] = 'Attribut "' . $a . '" auf <svg> entfernt (in P/S nicht erlaubt).';
            }
        }

        // viewBox quadratisch sicherstellen
        $viewBox = trim($root->getAttribute('viewBox'));
        if ($viewBox === '') {
            $w = (float) preg_replace('/[^0-9.]/', '', $root->getAttribute('width'));
            $h = (float) preg_replace('/[^0-9.]/', '', $root->getAttribute('height'));
            if ($w > 0 && $h > 0) {
                $root->setAttribute('viewBox', '0 0 ' . $this->num($w) . ' ' . $this->num($h));
                $viewBox = '0 0 ' . $this->num($w) . ' ' . $this->num($h);
                $warnings[] = 'viewBox aus width/height ergänzt.';
            } else {
                $errors[] = 'Kein viewBox und keine width/height – BIMI verlangt einen quadratischen viewBox.';
            }
        }
        if ($viewBox !== '') {
            $parts = preg_split('/[\s,]+/', $viewBox);
            if (is_array($parts) && count($parts) === 4) {
                $vw = (float) $parts[2];
                $vh = (float) $parts[3];
                if ($vw <= 0 || $vh <= 0 || abs($vw - $vh) > 0.5) {
                    $errors[] = 'viewBox ist nicht quadratisch (' . $vw . '×' . $vh . '). BIMI verlangt ein quadratisches Logo (1:1).';
                }
            } else {
                $errors[] = 'viewBox konnte nicht gelesen werden.';
            }
        }

        // <title> als erstes Kind sicherstellen
        $hasTitle = false;
        foreach ($root->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->localName) === 'title') {
                $hasTitle = true;
                break;
            }
        }
        if (!$hasTitle) {
            $title = $dom->createElement('title', 'Logo');
            $root->insertBefore($title, $root->firstChild);
            $warnings[] = '<title> ergänzt (in SVG P/S erforderlich).';
        }

        $normalized = $dom->saveXML();
        if ($normalized === false) {
            return ['ok' => false, 'normalized' => null, 'size' => 0, 'errors' => ['Serialisierung fehlgeschlagen.'], 'warnings' => $warnings];
        }
        $normalized = trim($normalized);
        $size = strlen($normalized);
        if ($size > self::MAX_SVG_BYTES) {
            $errors[] = 'SVG ist ' . round($size / 1024, 1) . ' KB groß (Maximum für BIMI: 32 KB).';
        }

        return [
            'ok' => $errors === [],
            'normalized' => $errors === [] ? $normalized : null,
            'size' => $size,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function num(float $n): string {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }

    private function hasTag(\DOMDocument $dom, string $tag): bool {
        return $dom->getElementsByTagNameNS('*', $tag)->length > 0;
    }

    /** @return \Generator<\DOMNode> */
    private function collectNodes(\DOMNode $node, array $types): \Generator {
        foreach ($node->childNodes ?? [] as $child) {
            if (in_array($child->nodeType, $types, true)) {
                yield $child;
            }
            yield from $this->collectNodes($child, $types);
        }
    }

    // ========================================================================
    // Logo / VMC speichern
    // ========================================================================

    /** @return array{ok:bool, errors:string[], warnings:string[], size:int} */
    public function saveLogo(string $domain, string $svg): array {
        $domain = $this->normalizeDomain($domain);
        $result = $this->validateSvg($svg);
        if (!$result['ok']) {
            return ['ok' => false, 'errors' => $result['errors'], 'warnings' => $result['warnings'], 'size' => $result['size']];
        }
        $cfg = $this->ensureConfig($domain);
        $cfg['svg'] = $result['normalized'];
        $cfg['svgSize'] = $result['size'];
        $cfg['svgUpdatedAt'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $cfg['updatedAt'] = $cfg['svgUpdatedAt'];
        $this->saveConfig($domain, $cfg);
        return ['ok' => true, 'errors' => [], 'warnings' => $result['warnings'], 'size' => $result['size']];
    }

    public function setVmc(string $domain, string $mode, string $url = '', string $pem = ''): array {
        $domain = $this->normalizeDomain($domain);
        $cfg = $this->ensureConfig($domain);
        $mode = strtolower(trim($mode));

        if ($mode === 'url') {
            $url = trim($url);
            if (!preg_match('#^https://#i', $url)) {
                return ['ok' => false, 'error' => 'VMC-URL muss mit https:// beginnen.'];
            }
            $cfg['vmcMode'] = 'url';
            $cfg['vmcUrl'] = $url;
            $cfg['vmcPem'] = null;
        } elseif ($mode === 'pem') {
            $pem = trim($pem);
            if (!str_contains($pem, 'BEGIN CERTIFICATE')) {
                return ['ok' => false, 'error' => 'Keine gültige PEM-Datei (erwarte "BEGIN CERTIFICATE").'];
            }
            $cfg['vmcMode'] = 'pem';
            $cfg['vmcPem'] = $pem;
            $cfg['vmcUrl'] = null;
        } else {
            $cfg['vmcMode'] = 'none';
            $cfg['vmcUrl'] = null;
            $cfg['vmcPem'] = null;
        }
        $cfg['updatedAt'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $this->saveConfig($domain, $cfg);
        return ['ok' => true];
    }

    // ========================================================================
    // Record / Status / öffentliche Payload
    // ========================================================================

    public function logoUrl(string $domain): string {
        return $this->urlGenerator->linkToRouteAbsolute(
            'souvera_central.bimi_public.logo',
            ['domain' => $this->normalizeDomain($domain)]
        );
    }

    public function vmcUrl(string $domain, ?array $cfg = null): ?string {
        $domain = $this->normalizeDomain($domain);
        $cfg ??= $this->getConfig($domain);
        if ($cfg === null) {
            return null;
        }
        if (($cfg['vmcMode'] ?? 'none') === 'url' && !empty($cfg['vmcUrl'])) {
            return $cfg['vmcUrl'];
        }
        if (($cfg['vmcMode'] ?? 'none') === 'pem' && !empty($cfg['vmcPem'])) {
            return $this->urlGenerator->linkToRouteAbsolute('souvera_central.bimi_public.vmc', ['domain' => $domain]);
        }
        return null;
    }

    public function buildRecord(string $domain, ?array $cfg = null): string {
        $domain = $this->normalizeDomain($domain);
        $cfg ??= $this->getConfig($domain);
        $value = 'v=BIMI1; l=' . $this->logoUrl($domain);
        $vmc = $this->vmcUrl($domain, $cfg);
        if ($vmc !== null) {
            $value .= '; a=' . $vmc;
        }
        return $value;
    }

    /**
     * Aggregierter Zustand (auch für Shield-Überwachung).
     */
    public function getPayload(string $domain): ?array {
        $domain = $this->normalizeDomain($domain);
        $cfg = $this->getConfig($domain);
        if ($cfg === null) {
            return null;
        }
        $hasLogo = !empty($cfg['svg']);
        $dmarcEnforced = (bool) ($cfg['dmarc']['enforced'] ?? false);
        $ready = $hasLogo && $dmarcEnforced;

        return [
            'domain' => $domain,
            'selector' => self::SELECTOR,
            'host' => self::SELECTOR . '._bimi.' . $domain,
            'type' => 'TXT',
            'record' => $this->buildRecord($domain, $cfg),
            'logoUrl' => $this->logoUrl($domain),
            'vmcUrl' => $this->vmcUrl($domain, $cfg),
            'vmcMode' => $cfg['vmcMode'] ?? 'none',
            'hasLogo' => $hasLogo,
            'svgSize' => $cfg['svgSize'] ?? 0,
            'dmarc' => $cfg['dmarc'] ?? null,
            'dmarcEnforced' => $dmarcEnforced,
            'ready' => $ready,
            'status' => $ready ? 'ready' : 'incomplete',
            'updatedAt' => $cfg['updatedAt'] ?? null,
        ];
    }

    public function getSvg(string $domain): ?string {
        $cfg = $this->getConfig($domain);
        return $cfg['svg'] ?? null;
    }

    public function getVmcPem(string $domain): ?string {
        $cfg = $this->getConfig($domain);
        if ($cfg === null || ($cfg['vmcMode'] ?? 'none') !== 'pem') {
            return null;
        }
        return $cfg['vmcPem'] ?? null;
    }
}
