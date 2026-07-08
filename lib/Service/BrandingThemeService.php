<?php

declare(strict_types=1);

/**
 * Souvera Central - Theme-basierte l10n-Overrides (tiefe App-Umbenennung)
 *
 * Erzeugt fuer die Ziel-Apps Talk (spreed) und Office/Collabora
 * (richdocuments + richdocumentscode) transformierte Uebersetzungsdateien, in
 * denen die Produktnamen instanzweit ersetzt werden: "Talk" -> "Link",
 * "Office"/"Collabora" -> "Desk". Die Dateien werden vom Command
 * `souvera:branding:install-theme` in ein NC-Theme geschrieben
 * (themes/<theme>/apps/<appId>/l10n/<lang>.{js,json}) - der native, saubere
 * Nextcloud-Weg, fremde App-Texte zu ueberschreiben.
 *
 * Diese Klasse ist bewusst frei von Nextcloud-/Filesystem-Abhaengigkeiten
 * (nur ConfigService), damit die Transformationslogik deterministisch
 * testbar ist.
 */

namespace OCA\SouveraCentral\Service;

class BrandingThemeService {
    /** Apps, deren Anzeigetexte instanzweit umbenannt werden. */
    public const TARGET_APPS = ['spreed', 'richdocuments', 'richdocumentscode'];

    public const DEFAULT_PLURAL = 'nplurals=2; plural=(n != 1);';

    public function __construct(
        private ConfigService $config,
    ) {
    }

    /**
     * Ersetzungsregeln je App, geordnet von der spezifischsten zur allgemeinsten
     * Phrase (laengere Phrasen zuerst, damit "Collabora Online" vor "Collabora"
     * greift).
     *
     * @return array<string,string> Suchbegriff => Ersatz
     */
    public function getReplacements(string $appId): array {
        $talk = $this->config->getBrandingTalkName();
        $office = $this->config->getBrandingOfficeName();
        switch ($appId) {
            case 'spreed':
                // "Talk"/"Nextcloud Talk" -> "Link"/"Nextcloud Link"
                return ['Talk' => $talk];
            case 'richdocuments':
            case 'richdocumentscode':
                return [
                    'Collabora Online' => $office,
                    'Collabora' => $office,
                    'Office' => $office,
                ];
            default:
                return [];
        }
    }

    /**
     * Ersetzt die Produktnamen in EINEM Uebersetzungswert (wortgenau,
     * gross-/kleinschreibungsbeachtend, da Eigennamen).
     */
    public function transformValue(string $appId, string $value): string {
        foreach ($this->getReplacements($appId) as $search => $replace) {
            $pattern = '/\b' . preg_quote($search, '/') . '\b/u';
            $replaced = preg_replace($pattern, addcslashes($replace, '\\$'), $value);
            if ($replaced !== null) {
                $value = $replaced;
            }
        }
        return $value;
    }

    /**
     * Transformiert eine komplette translations-Map (inkl. Plural-Arrays).
     *
     * @param array<string,mixed> $map
     * @return array<string,mixed>
     */
    public function transformTranslations(string $appId, array $map): array {
        $out = [];
        foreach ($map as $key => $val) {
            if (is_array($val)) {
                $out[$key] = array_map(
                    fn ($v) => is_string($v) ? $this->transformValue($appId, $v) : $v,
                    $val,
                );
            } else {
                $out[$key] = is_string($val) ? $this->transformValue($appId, $val) : $val;
            }
        }
        return $out;
    }

    /**
     * Zaehlt, wie viele Werte sich durch die Transformation aendern wuerden.
     *
     * @param array<string,mixed> $map
     */
    public function countChanges(string $appId, array $map): int {
        $changed = 0;
        foreach ($map as $key => $val) {
            $new = is_array($val)
                ? array_map(fn ($v) => is_string($v) ? $this->transformValue($appId, $v) : $v, $val)
                : (is_string($val) ? $this->transformValue($appId, $val) : $val);
            if ($new !== $val) {
                $changed++;
            }
        }
        return $changed;
    }

    /**
     * Rendert die translations-Map als Nextcloud-`<lang>.json`.
     *
     * @param array<string,mixed> $map
     */
    public function renderJson(array $map, string $plural): string {
        return json_encode(
            ['translations' => $map, 'pluralForm' => $plural],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
        ) . "\n";
    }

    /**
     * Rendert die translations-Map als Nextcloud-`<lang>.js`
     * (OC.L10N.register-Format).
     *
     * @param array<string,mixed> $map
     */
    public function renderJs(string $appId, array $map, string $plural): string {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $lines = ['OC.L10N.register(', '    "' . $appId . '",', '    {'];
        $keys = array_keys($map);
        $count = count($keys);
        foreach ($keys as $i => $k) {
            $comma = $i < $count - 1 ? ',' : '';
            $lines[] = '    ' . json_encode($k, $flags) . ' : ' . json_encode($map[$k], $flags) . $comma;
        }
        $lines[] = '},';
        $lines[] = '"' . $plural . '");';
        return implode("\n", $lines) . "\n";
    }
}
