<?php

declare(strict_types=1);

/**
 * Souvera Central - Mail Settings API (read-only, for other Souvera apps)
 *
 * Liefert die zentral in Central verwalteten Mail-Einstellungen (aktuell die
 * globale Signatur) an andere Apps – insbesondere souvera_mail. Dieser Controller
 * ist bewusst von der Souvera-Admin-Pflicht AUSGENOMMEN (siehe
 * SouveraAdminMiddleware), damit ihn JEDER angemeldete Souvera-User beim Verfassen
 * einer Mail abfragen kann. Er ist strikt lesend.
 */

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\Service\ConfigService;
use OCA\SouveraCentral\Service\SignatureInjectionService;
use OCA\SouveraCentral\Service\SignatureResolverService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;

class MailSettingsApiController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private ConfigService $config,
        private IUserSession $userSession,
        private SignatureResolverService $resolver,
        private IDBConnection $db,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Zentrale Mail-Einstellungen (globale Signatur) für souvera_mail.
     *
     * @return DataResponse mit { signature_enabled, signature_template,
     *         signature_format, server_side, variables }
     */
    #[NoAdminRequired]
    public function getMailSettings(): DataResponse {
        try {
            return new DataResponse($this->config->getMailSignatureSettings());
        } catch (\Throwable $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Finale, gerenderte Signatur für den ANGEMELDETEN User — die Quelle
     * für die Compose-Injection in souvera_mail (Webmail sieht die
     * Signatur beim Schreiben, nicht erst beim Empfang).
     */
    #[NoAdminRequired]
    public function getResolvedSignature(): DataResponse {
        $user = $this->userSession->getUser();
        $email = $user?->getEMailAddress() ?? '';
        if ($email === '') {
            return new DataResponse(['found' => false, 'error' => 'Keine E-Mail-Adresse im Profil']);
        }
        $sig = $this->resolver->resolveForEmail($email);
        if ($sig === null) {
            return new DataResponse(['found' => false]);
        }
        return new DataResponse([
            'found' => true,
            'html' => $sig['html'],
            'text' => $sig['text'],
            'source' => $sig['source'],
            'assetCidPrefix' => SignatureInjectionService::ASSET_CID_PREFIX,
            // Die referenzierten Assets als Data-URLs — der Composer bettet
            // sie direkt ins srcdoc ein (netzwerk- und flapping-unabhängig).
            'assets' => $this->assetsAsDataUrls((string) $sig['html']),
        ]);
    }

    /**
     * Liefert alle im Signatur-HTML referenzierten Assets (cid:souvera-sig-*)
     * als [{cid, dataUrl}] — für die flapping-freie Composer-Vorschau.
     *
     * @return list<array{cid: string, dataUrl: string}>
     */
    private function assetsAsDataUrls(string $html): array {
        $out = [];
        $prefix = SignatureInjectionService::ASSET_CID_PREFIX;
        if (!\preg_match_all('/cid:(' . \preg_quote($prefix, '/') . '[a-z0-9\-_]+)/i', $html, $m)) {
            return $out;
        }
        try {
            $rows = $this->db->executeQuery('SELECT name, mime, data FROM *PREFIX*souvera_central_sig_assets')->fetchAll();
        } catch (\Throwable $e) {
            return $out;
        }
        // Slug-Ableitung identisch zu getSignatureAsset / Hook-Controller.
        $byCid = [];
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            $slug = \strtolower(\preg_replace('/[^a-z0-9]+/i', '-', \pathinfo($name, PATHINFO_FILENAME)) ?? 'bild');
            $slug = \trim($slug, '-') ?: 'bild';
            $byCid[$prefix . $slug] = $row;
        }
        foreach (\array_unique($m[1]) as $cid) {
            $row = $byCid[\strtolower($cid)] ?? null;
            if ($row === null || !isset($row['data']) || $row['data'] === '') { continue; }
            $out[] = [
                'cid' => $cid,
                'dataUrl' => 'data:' . (string) ($row['mime'] ?? 'image/png') . ';base64,' . \base64_encode((string) $row['data']),
            ];
        }
        return $out;
    }

    /**
     * Logo-Bytes für die WYSIWYG-Vorschau im Webmail-Composer
     * (angemeldete Souvera-User; das HOOK-Logo wird serverseitig als
     * Inline-CID eingebettet, hier geht es nur um die Vorschau).
     */
    #[NoAdminRequired]
    public function getSignatureLogo(): DataDownloadResponse {
        try {
            $row = $this->db->executeQuery('SELECT name, mime, data FROM *PREFIX*souvera_central_sig_assets LIMIT 1')->fetch();
            if (!\is_array($row) || !isset($row['data'])) {
                return new DataDownloadResponse('', 'logo.png', 'image/png');
            }
            return new DataDownloadResponse(
                (string) $row['data'],
                (string) ($row['name'] ?? 'logo.png'),
                (string) ($row['mime'] ?? 'image/png')
            );
        } catch (\Throwable $e) {
            return new DataDownloadResponse('', 'logo.png', 'image/png');
        }
    }

    /**
     * Asset-Bytes per Slug für die Composer-Vorschau (angemeldete User).
     *
     * Die zentrale Signatur referenziert Bilder als `cid:souvera-sig-<slug>` —
     * ein Schema, das der Browser im Composer nicht laden kann. souvera_mail
     * ersetzt die cid-Referenz NUR zur Anzeige durch diese URL; gesendet wird
     * weiter die cid-Variante (der Stalwart-Hook bettet die Bytes als
     * MIME-Parts ein — Empfänger sehen die Bilder eingebettet).
     */
    #[NoAdminRequired]
    public function getSignatureAsset(string $slug): DataDownloadResponse {
        $prefix = \OCA\SouveraCentral\Service\SignatureInjectionService::ASSET_CID_PREFIX;
        try {
            // Slug → Dateiname: die Registry speichert den Originalnamen,
            // der Slug ist die normalisierte Form (identisch zu
            // SignatureAdminController::slugForFilename / Hook-Controller).
            $rows = $this->db->executeQuery('SELECT name, mime FROM *PREFIX*souvera_central_sig_assets')->fetchAll();
            $match = null;
            foreach ($rows as $row) {
                $name = (string) ($row['name'] ?? '');
                $s = \strtolower(\preg_replace('/[^a-z0-9]+/i', '-', \pathinfo($name, PATHINFO_FILENAME)) ?? 'bild');
                $s = \trim($s, '-') ?: 'bild';
                if ($prefix . $s === $prefix . $slug) {
                    $match = $name;
                    break;
                }
            }
            if ($match === null) {
                return new DataDownloadResponse('', 'fehlt.png', 'image/png');
            }
            $row = $this->db->executeQuery(
                'SELECT name, mime, data FROM *PREFIX*souvera_central_sig_assets WHERE name = ?',
                [$match]
            )->fetch();
            if (!\is_array($row) || !isset($row['data'])) {
                return new DataDownloadResponse('', 'fehlt.png', 'image/png');
            }
            return new DataDownloadResponse(
                (string) $row['data'],
                (string) ($row['name'] ?? 'asset'),
                (string) ($row['mime'] ?? 'image/png')
            );
        } catch (\Throwable $e) {
            return new DataDownloadResponse('', 'fehlt.png', 'image/png');
        }
    }
}
