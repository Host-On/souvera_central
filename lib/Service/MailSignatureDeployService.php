<?php

declare(strict_types=1);

/**
 * Souvera Central - Mail Signature Deployment (serverseitig)
 *
 * Bindeglied zwischen der zentral gepflegten Signatur-Einstellung (ConfigService),
 * dem reinen Sieve-Generator (MailSignatureService) und dem JMAP-Gateway
 * (StalwartService). Rollt die EINE globale Signatur als Stalwart-System-Sieve-
 * Script aus bzw. entfernt sie – abhängig vom Zustand der Central-Einstellungen.
 *
 * Aufruf-Punkte: SettingsApiController (beim Speichern) und der occ-Befehl
 * souvera_central:mailsignature:sieve --deploy|--remove.
 */

namespace OCA\SouveraCentral\Service;

use Psr\Log\LoggerInterface;

class MailSignatureDeployService {
    public function __construct(
        private ConfigService $config,
        private StalwartService $stalwart,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Gleicht das serverseitige Signatur-Deployment mit den aktuellen Central-
     * Einstellungen ab: deployt das Sieve-Script, wenn die Signatur AKTIV UND
     * „serverseitig erzwingen" AN ist (und eine Vorlage existiert); sonst entfernt
     * es das Script wieder. Idempotent.
     *
     * @return array{action:string, ok:bool, deployed:bool, wired:bool, removed:bool,
     *               existing_script:?string, error:?string}
     */
    public function sync(): array {
        return $this->shouldDeploy()
            ? $this->deploy()
            : $this->remove();
    }

    /** Soll die Signatur serverseitig ausgerollt werden? */
    public function shouldDeploy(): bool {
        return $this->config->isMailSignatureEnabled()
            && $this->config->isMailSignatureServerSide()
            && trim($this->config->getMailSignatureTemplate()) !== '';
    }

    /**
     * Deployt das Signatur-Sieve-Script nach Stalwart.
     *
     * @return array{action:string, ok:bool, deployed:bool, wired:bool, removed:bool,
     *               existing_script:?string, error:?string}
     */
    public function deploy(): array {
        $base = ['action' => 'deploy', 'ok' => false, 'deployed' => false, 'wired' => false,
            'removed' => false, 'existing_script' => null, 'error' => null];

        if (!$this->stalwart->isAvailable()) {
            $base['error'] = 'stalwart_unavailable';
            return $base;
        }

        $template = $this->config->getMailSignatureTemplate();
        if (trim($template) === '') {
            $base['error'] = 'no_template';
            return $base;
        }

        $script = MailSignatureService::buildSieveScript($template);
        $res = $this->stalwart->deploySignatureScript(
            $script,
            'Souvera Central - globale Mail-Signatur (auto-generiert, nicht manuell editieren)'
        );

        $result = array_merge($base, [
            'ok' => $res['deployed'] && $res['error'] === null,
            'deployed' => $res['deployed'],
            'wired' => $res['wired'],
            'existing_script' => $res['existing_script'],
            'error' => $res['error'],
        ]);

        if ($result['deployed'] && !$result['wired'] && $result['existing_script'] !== null) {
            $this->logger->warning('MailSignatureDeploy: Script deployed, DATA-Stage NICHT verdrahtet (fremdes Script aktiv)', [
                'existing_script' => $result['existing_script'],
            ]);
        }
        return $result;
    }

    /**
     * Entfernt das Signatur-Sieve-Script wieder (serverseitig aus).
     *
     * @return array{action:string, ok:bool, deployed:bool, wired:bool, removed:bool,
     *               existing_script:?string, error:?string}
     */
    public function remove(): array {
        $base = ['action' => 'remove', 'ok' => false, 'deployed' => false, 'wired' => false,
            'removed' => false, 'existing_script' => null, 'error' => null];

        if (!$this->stalwart->isAvailable()) {
            $base['error'] = 'stalwart_unavailable';
            return $base;
        }

        $res = $this->stalwart->removeSignatureScript();
        return array_merge($base, [
            'ok' => $res['removed'] && $res['error'] === null,
            'removed' => $res['removed'],
            'existing_script' => $res['existing_script'],
            'error' => $res['error'],
        ]);
    }
}
