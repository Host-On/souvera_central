<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Service;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Message;
use ZBateson\MailMimeParser\MailMimeParser;

/**
 * Injiziert eine zentrale Signatur in eine RAW-MIME-Nachricht.
 *
 * Verwendet zbateson/mail-mime-parser (vendor, siehe composer.json):
 * korrektes Handling von nested multiparts, quoted-printable/base64,
 * Charset (UTF-8-Re-Encodierung modifizierter Parts) — genau die Stelle,
 * an der handgestrickte MIME-Aufsätze regelmäßig brechen.
 *
 * Alle Skip-Regeln (verschlüsselt, ICS, Marker, …) wurden vom Aufrufer
 * geprüft; hier nur noch die Injektion selbst.
 */
class SignatureInjectionService {
    private const MARKER_HEADER = 'X-Souvera-Signature';
    /** CID-Präfix der Signatur-Assets (souvera-sig-<slug>). */
    public const ASSET_CID_PREFIX = 'souvera-sig-';

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function isAlreadySigned(string $rawMessage): bool {
        return \stripos(\substr($rawMessage, 0, 8192), self::MARKER_HEADER) !== false
            || \stripos($rawMessage, 'class="souvera-sig"') !== false
            || \stripos($rawMessage, 'class=\'souvera-sig\'') !== false;
    }

    /**
     * WEBMAIL-Sonderfall: der Composer bettet die zentrale Signatur direkt
     * in den Mail-Body ein (Marker → die komplette Injektion muss skippen),
     * kann aber die `cid:souvera-sig-*`-Bildreferenzen selbst nicht als
     * MIME-Parts einbetten. Ohne diesen Schritt sehen EMPFÄNGER die Bilder
     * nicht (und die Sent-Ansicht ebenso).
     *
     * Diese Methode ergänzt für eine bereits markierte Mail NUR die fehlenden
     * Inline-Parts (idempotent: vorhandene Content-IDs bleiben unberührt).
     *
     * @param string $rawMessage die Roh-Mail (Marker bereits enthalten)
     * @param list<array{cid: string, name: string, mime: string, data: string}>|null $assets
     * @return string|null modifizierte Roh-Mail oder null (unverändert)
     */
    public function ensureCidParts(string $rawMessage, ?array $assets = null): ?string {
        try {
            if (!$this->isAlreadySigned($rawMessage)) {
                return null; // Nur für Marker-Mails (Webmail-Composer-Pfad)
            }

            $parser = new MailMimeParser();
            $message = $parser->parse($rawMessage, false);

            if ($this->isCryptoOrCalendar($message)) {
                return null;
            }

            $htmlPart = $message->getHtmlPart();
            if ($htmlPart === null) {
                return null;
            }
            $body = (string) $htmlPart->getContent();

            // Welche CIDs verlangt der Body?
            $wanted = [];
            if (\preg_match_all('/cid:([a-z0-9\-_]+)/i', $body, $m)) {
                foreach (\array_unique($m[1]) as $cid) {
                    $wanted[\strtolower($cid)] = true;
                }
            }
            if ($wanted === []) {
                return null;
            }

            // Welche Content-IDs existieren bereits als Parts?
            $existing = [];
            $count = $message->getAttachmentCount();
            for ($i = 0; $i < $count; $i++) {
                $part = $message->getAttachmentPart($i);
                $cidHeader = $part?->getHeaderValue('Content-ID');
                if (\is_string($cidHeader)) {
                    $existing[\trim(\strtolower(\trim($cidHeader), '<>'))] = true;
                }
            }

            $changed = false;
            foreach (($assets ?? []) as $asset) {
                if (!isset($asset['cid'], $asset['mime'], $asset['data']) || $asset['data'] === '') {
                    continue;
                }
                $cid = \strtolower((string) $asset['cid']);
                if (!isset($wanted[$cid]) || isset($existing[$cid])) {
                    continue; // nicht referenziert oder bereits eingebettet
                }
                $message->addAttachmentPart($asset['data'], $asset['mime'], $asset['name'] ?? 'bild', 'inline');
                $acount = $message->getAttachmentCount();
                $part = $acount > 0 ? $message->getAttachmentPart($acount - 1) : null;
                $part?->setRawHeader('Content-ID', '<' . $asset['cid'] . '>');
                $changed = true;
            }

            if (!$changed) {
                return null;
            }

            $message->setRawHeader(self::MARKER_HEADER, 'injected');
            $out = $message->getStream()->getContents();
            if ($out === '' || $out === $rawMessage) {
                return null;
            }
            return $out;
        } catch (\Throwable $e) {
            $this->logger->error('Souvera signature: ensureCidParts failed (fail-open): ' . $e->getMessage(), [
                'app' => 'souvera_central', 'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * @param array{html: string, text: string} $sig
     * @param list<array{cid: string, name: string, mime: string, data: string}>|null $assets
     * @param array{name: string, mime: string, data: string}|null $logo
     * @return string|null modifizierte Roh-Mail oder null (unverändert lassen)
     */
    public function inject(string $rawMessage, array $sig, ?array $assets = null): ?string {
        try {
            if ($this->isAlreadySigned($rawMessage)) {
                return null;
            }

            $parser = new MailMimeParser();
            $message = $parser->parse($rawMessage, false);

            if ($this->isCryptoOrCalendar($message)) {
                return null;
            }

            $html = $this->wrapHtml($sig['html']);
            $text = $this->plainSignature($sig['text']);

            $htmlPart = $message->getHtmlPart();
            $textPart = $message->getTextPart();

            // JEDE vorhandene Repräsentation bekommt die Signatur
            // (multipart/alternative: HTML + Text unabhängig voneinander).
            if ($htmlPart !== null) {
                $body = (string) $htmlPart->getContent();
                $htmlPart->setContent($this->insertHtml($body, $html));
            }
            if ($textPart !== null) {
                $textPart->setContent((string) $textPart->getContent() . "\n" . $text);
            }
            if ($htmlPart === null && $textPart === null) {
                return null;
            }

            // Alle Assets als Inline-Parts mit ihren CIDs einbetten —
            // das Template referenziert sie per <img src="cid:<cid>">.
            foreach (($assets ?? []) as $asset) {
                if (!isset($asset['cid'], $asset['mime'], $asset['data']) || $asset['data'] === '') { continue; }
                $message->addAttachmentPart($asset['data'], $asset['mime'], $asset['name'] ?? 'bild', 'inline');
                $count = $message->getAttachmentCount();
                $part = $count > 0 ? $message->getAttachmentPart($count - 1) : null;
                $part?->setRawHeader('Content-ID', '<' . $asset['cid'] . '>');
            }

            $message->setRawHeader(self::MARKER_HEADER, 'injected');

            $out = $message->getStream()->getContents();
            if ($out === '' || $out === $rawMessage) {
                return null;
            }
            return $out;
        } catch (\Throwable $e) {
            $this->logger->error('Souvera signature: injection failed (fail-open): ' . $e->getMessage(), [
                'app' => 'souvera_central',
                'exception' => $e,
            ]);
            return null;
        }
    }

    /** Signatur-HTML in den souvera-sig-Wrapper setzen (Marker + Style-Hook). */
    private function wrapHtml(string $html): string {
        $inner = \rtrim($html);
        return '<div class="souvera-sig" style="margin-top:18px">' . $inner . '</div>';
    }

    /** Plain-Text-Signatur mit dem RFC-5322-Trenner ("-- ") versehen. */
    private function plainSignature(string $text): string {
        $text = \rtrim($text);
        if ($text === '') {
            return '';
        }
        return (\str_starts_with($text, '-- ') ? '' : "-- \n") . $text;
    }

    /**
     * Smart-Placement: über dem ERSTEN <blockquote> einfügen (Replies),
     * sonst vor </body> bzw. ans Ende.
     */
    private function insertHtml(string $body, string $sigHtml): string {
        $marker = \stripos($body, '<blockquote');
        if ($marker !== false) {
            return \substr($body, 0, $marker) . $sigHtml . "\n" . \substr($body, $marker);
        }
        $bodyClose = \strripos($body, '</body>');
        if ($bodyClose !== false) {
            return \substr($body, 0, $bodyClose) . $sigHtml . "\n" . \substr($body, $bodyClose);
        }
        return $body . "\n" . $sigHtml;
    }

    /** Skip-Regeln: kryptografisch geschützte oder calendaring-nutzende Mails. */
    private function isCryptoOrCalendar(Message $message): bool {
        $ctype = \strtolower((string) $message->getHeaderValue('Content-Type'));
        if (\str_contains($ctype, 'multipart/signed')
            || \str_contains($ctype, 'multipart/encrypted')
            || \str_contains($ctype, 'application/pkcs7-mime')
            || \str_contains($ctype, 'application/pgp-encrypted')
            || \str_contains($ctype, 'text/calendar')) {
            return true;
        }
        foreach ($message->getAllParts() as $part) {
            $p = \strtolower((string) $part->getHeaderValue('Content-Type'));
            if (\str_contains($p, 'application/pkcs7-mime')
                || \str_contains($p, 'application/pgp-signature')
                || \str_contains($p, 'text/calendar')) {
                return true;
            }
        }
        return false;
    }
}
