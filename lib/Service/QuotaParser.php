<?php

declare(strict_types=1);

/**
 * Souvera Central - Quota-Parser (abhängigkeitsfrei, rein testbar)
 *
 * Wandelt menschenlesbare Größenangaben (z. B. "50G", "500M", "1T", "50 GB",
 * "50GiB") in Bytes um und formatiert Bytes wieder lesbar. Es wird die binäre
 * Basis (1 GiB = 1024^3) verwendet - identisch zur Postfach-Quota-Auswahl im UI.
 *
 * 0 / none / unlimited / unbegrenzt => 0 Bytes (= unbegrenzt).
 */

namespace OCA\SouveraCentral\Service;

class QuotaParser {
    /** Standard-Postfach-Quota, falls nichts konfiguriert ist: 50 GiB. */
    public const DEFAULT_BYTES = 53687091200;

    /**
     * Größenangabe in Bytes umwandeln. Liefert null bei ungültiger Eingabe.
     * 0 = unbegrenzt.
     */
    public static function toBytes(string $input): ?int {
        $s = strtolower(trim($input));
        if ($s === '') {
            return null;
        }
        if (in_array($s, ['0', 'none', 'unlimited', 'unbegrenzt'], true)) {
            return 0;
        }
        // Zahl (optional Dezimal) + optionale Einheit K/M/G/T + optional i + optional b
        if (!preg_match('/^([0-9]+(?:[.,][0-9]+)?)\s*([kmgt]?)(?:i?b)?$/', $s, $m)) {
            return null;
        }
        $value = (float) str_replace(',', '.', $m[1]);
        $mult = [
            '' => 1,
            'k' => 1024,
            'm' => 1024 ** 2,
            'g' => 1024 ** 3,
            't' => 1024 ** 4,
        ][$m[2]];
        $bytes = (int) round($value * $mult);
        return $bytes < 0 ? null : $bytes;
    }

    /**
     * Bytes lesbar formatieren (binär). 0 oder weniger => "Unbegrenzt".
     */
    public static function format(int $bytes): string {
        if ($bytes <= 0) {
            return 'Unbegrenzt';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        $str = ($value == floor($value)) ? (string) (int) $value : number_format($value, 1);
        return $str . ' ' . $units[$i];
    }
}
