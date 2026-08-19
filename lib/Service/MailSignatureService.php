<?php

declare(strict_types=1);

/**
 * Souvera Central - Mail Signature / Sieve generator
 *
 * Erzeugt aus der EINEN, zentral in Central gepflegten Signatur-Vorlage ein
 * Stalwart-Sieve-„System Script" (MTA DATA-Stage), das den Footer an ALLE
 * ausgehenden SMTP-Mails anhängt. Die Personalisierung (%name%, %email%, …)
 * wird serverseitig aus dem From-Header abgeleitet – dieselbe Vorlage wie im
 * Webmail (souvera_mail), damit es nur EINE Signatur gibt.
 *
 * Reine, seiteneffektfreie String-Erzeugung (unit-testbar). Das Deployen des
 * Scripts nach Stalwart (JMAP SieveSystemScript/set + DATA-Stage aktivieren)
 * ist bewusst getrennt und auf einer Live-Instanz (>= 0.16.6) zu verifizieren.
 */

namespace OCA\SouveraCentral\Service;

class MailSignatureService {

    /** Zuordnung Central-Platzhalter -> Sieve-Variablen (aus From-Header abgeleitet). */
    private const VAR_MAP = [
        '%first_name%' => '${first_name}',
        '%last_name%' => '${last_name}',
        '%name%' => '${sender_name}',
        '%email%' => '${sender_email}',
        '%domain%' => '${sender_domain}',
    ];

    /** Ersetzt die Central-Platzhalter durch Sieve-Variablen. */
    public static function mapVars(string $s): string {
        return strtr($s, self::VAR_MAP);
    }

    /** Escapet einen String für eine Sieve-Quoted-String (nur \ und "). */
    public static function toSieveString(string $s): string {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $s);
    }

    /** Grobe HTML->Text-Wandlung für den text/plain-Teil. */
    public static function htmlToText(string $html): string {
        $t = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html);
        $t = preg_replace('/<\s*\/\s*(p|div|tr|h[1-6])\s*>/i', "\n", $t);
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/\n{3,}/", "\n\n", $t);
        return trim($t);
    }

    /**
     * Baut das vollständige Sieve-System-Script.
     *
     * @param string $html   Signatur-Vorlage (HTML) mit %-Platzhaltern
     * @param string|null $text Optionaler Klartext; sonst aus HTML abgeleitet
     */
    public static function buildSieveScript(string $html, ?string $text = null): string {
        $html = trim($html);
        if ($text === null || trim($text) === '') {
            $text = self::htmlToText($html);
        }

        $htmlSig = self::toSieveString(self::mapVars($html));
        $textSig = self::toSieveString(self::mapVars($text));

        $script = <<<SIEVE
require ["variables", "mime", "foreverypart", "replace", "extracttext"];

# ============================================================================
# Souvera Central - globale Mail-Signatur (AUTO-GENERIERT)
# Quelle: Central-Oberflaeche (Einstellungen > Mail-Signatur). NICHT hier editieren.
# ENTWURF: vor Produktivbetrieb auf Stalwart >= 0.16.6 testen (DKIM/MIME).
# Einbindung: Settings > MTA > Session > DATA Stage -> dieses Script auswaehlen.
# ============================================================================

# --- Absenderdaten aus dem From-Header ableiten (Personalisierung) ---
set "sender_name" "";
set "sender_email" "";
set "sender_domain" "";
set "first_name" "";
set "last_name" "";

if header :matches "From" "*<*@*>*" {
    # Format: Anzeigename <lokal@domain>
    set "sender_name" "\${1}";
    set "sender_email" "\${2}@\${3}";
    set "sender_domain" "\${3}";
} elsif header :matches "From" "*@*" {
    # Nur Adresse ohne Anzeigename
    set "sender_email" "\${1}@\${2}";
    set "sender_domain" "\${2}";
}

# Vor-/Nachname aus dem Anzeigenamen (Heuristik: erstes Wort = Vorname)
if string :matches "\${sender_name}" "* *" {
    set "first_name" "\${1}";
    set "last_name" "\${2}";
} else {
    set "first_name" "\${sender_name}";
}

# --- Signatur an den Body anhaengen (je MIME-Teil) ---
foreverypart {
    if header :mime :contenttype "content-type" "text/html" {
        extracttext :text "body";
        replace "\${body}$htmlSig";
    } elsif header :mime :contenttype "content-type" "text/plain" {
        extracttext :text "body";
        replace "\${body}\r\n-- \r\n$textSig";
    }
}
SIEVE;

        return $script;
    }
}
