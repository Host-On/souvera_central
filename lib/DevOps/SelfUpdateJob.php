<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\DevOps;

use OCA\SouveraCentral\DevOps\SelfUpdateTrait;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class SelfUpdateJob extends TimedJob
{
    use SelfUpdateTrait;

    private const DEFAULT_REPO = 'PhiGi87/souvera_central';

    protected function getAppId(): string { return 'souvera_central'; }

    public function __construct() { $this->setInterval(3 * 3600); }

    protected function run($argument): void
    {
        try {
            $result = $this->checkAndUpdate(self::DEFAULT_REPO);
            if (!empty($result['success'])) {
                \OCP\Server::get(LoggerInterface::class)->info('souvera_central self-update: ' . \json_encode($result));
            }
        } catch (\Throwable) {}
    }
}
