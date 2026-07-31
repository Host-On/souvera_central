<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\DevOps;

use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

/**
 * Self-update entry point. One instance per managed app is registered with
 * the job list (argument `['app' => <appId>]`) — the app id determines the
 * GitHub repository and the Nextcloud app version to compare/update.
 *
 * Runs every 5 minutes; the trait applies its own rate limiting on the
 * stable channel.
 */
class SelfUpdateJob extends TimedJob
{
    use SelfUpdateTrait;

    private string $jobAppId = 'souvera_central';

    public function __construct(ITimeFactory $time)
    {
        parent::__construct($time);
        $this->setInterval(300); // 5 minutes on dev channel
    }

    protected function getAppId(): string
    {
        return $this->jobAppId;
    }

    protected function run($argument): void
    {
        if (\is_array($argument) && \is_string($argument['app'] ?? null) && $argument['app'] !== '') {
            $this->jobAppId = $argument['app'];
        }
        try {
            $result = $this->checkAndUpdate();
            $resultJson = json_encode($result, JSON_UNESCAPED_SLASHES);
            \OCP\Server::get(LoggerInterface::class)->info(
                'souvera_central self-update (' . $this->jobAppId . '): ' . $resultJson
            );
        } catch (\Throwable $e) {
            \OCP\Server::get(LoggerInterface::class)->error(
                'souvera_central self-update EXCEPTION (' . $this->jobAppId . '): ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
