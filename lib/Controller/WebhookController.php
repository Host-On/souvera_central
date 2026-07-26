<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\DevOps\WebhookUpdateTrait;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class WebhookController extends Controller
{
    use WebhookUpdateTrait;

    public function __construct(IRequest $request)
    {
        parent::__construct('souvera_central', $request);
    }

    protected function getAppId(): string { return 'souvera_central'; }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function update(): DataResponse { return $this->runUpdate(); }
}
