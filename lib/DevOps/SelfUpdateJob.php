<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\DevOps;

use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class SelfUpdateJob extends TimedJob
{
    use SelfUpdateTrait;

    public function __construct()
    {
        $this->setInterval(900);
    }

    protected function getAppId(): string
    {
        return 'souvera_central';
    }

    protected function run($argument): void
    {
        try {
            $result = $this->checkAndUpdate();
            $resultJson = json_encode($result, JSON_UNESCAPED_SLASHES);
            \OCP\Server::get(LoggerInterface::class)->info(
                'souvera_central self-update: ' . $resultJson
            );
        } catch (\Throwable $e) {
            \OCP\Server::get(LoggerInterface::class)->error(
                'souvera_central self-update EXCEPTION: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
