<?php

declare(strict_types=1);

/**
 * Souvera Central - MCP Endpoint (Knowledge Base)
 *
 * Stateless Model Context Protocol over HTTP (JSON-RPC 2.0). Der Nextcloud-
 * interne Agent liest hier live die interne Wissensbasis (souvera_ai_kb):
 *
 *   Tools: kb_list, kb_get, kb_search (ausschließlich read-only)
 *
 * Authentifizierung: Bearer-Token, der bei Aktivierung der KI automatisch
 * erzeugt, verschlüsselt in der Central-DB abgelegt und dem Agenten INTERN
 * über die Shared API (AiMcpTokenService::getToken()) übergeben wird.
 * Der Endpoint ist nur aktiv, wenn die KI-Funktion aktiviert ist.
 *
 * Ausnahme von der SouveraAdminMiddleware — die Autorisierung läuft über
 * den Token, nicht über die NC-Session. Siehe docs/SHARED_AI_MCP.md.
 */

namespace OCA\SouveraCentral\Controller;

use OCA\SouveraCentral\Db\AiKbArticle;
use OCA\SouveraCentral\Service\AiConfigService;
use OCA\SouveraCentral\Service\AiKbService;
use OCA\SouveraCentral\Service\AiMcpTokenService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class McpController extends Controller
{
    /** Vom Server angebotene MCP-Protokollversion (Fallback, wenn der Client keine angibt). */
    public const PROTOCOL_VERSION = '2026-07-28';

    public const SERVER_NAME = 'souvera-central';
    public const SERVER_TITLE = 'Souvera AI Knowledge Base';

    public function __construct(
        IRequest $request,
        private AiConfigService $aiConfig,
        private AiMcpTokenService $mcpToken,
        private AiKbService $kb,
        private LoggerInterface $logger,
    ) {
        parent::__construct('souvera_central', $request);
    }

    /**
     * MCP over HTTP: ausschließlich POST (JSON-RPC). GET liefert 405.
     */
    #[PublicPage]
    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function call(): JSONResponse
    {
        try {
            return $this->handleCall();
        } catch (\Throwable $e) {
            // Nie einen nackten 500 an den Agenten liefern — sauberer
            // JSON-RPC-Fehler + vollständiger Trace im Server-Log.
            $this->logger->error('Souvera AI: MCP request failed: ' . $e->getMessage(), [
                'app' => 'souvera_central',
                'exception' => $e,
            ]);
            return $this->error(null, -32603, 'Internal error');
        }
    }

    private function handleCall(): JSONResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $raw = file_get_contents('php://input');
        $message = json_decode((string) $raw, true);
        if (!is_array($message) || !isset($message['method'])) {
            return $this->error($message['id'] ?? null, -32700, 'Parse error');
        }

        $id = $message['id'] ?? null;
        $method = (string) $message['method'];
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        switch ($method) {
            case 'initialize':
                $requested = isset($params['protocolVersion']) && is_string($params['protocolVersion'])
                    ? $params['protocolVersion']
                    : self::PROTOCOL_VERSION;
                $result = [
                    'protocolVersion' => $requested,
                    'capabilities' => ['tools' => ['listChanged' => false]],
                    'serverInfo' => [
                        'name' => self::SERVER_NAME,
                        'title' => self::SERVER_TITLE,
                        'version' => $this->aiConfig->snapshot()['central_version'] ?: '0.0.0',
                    ],
                ];
                break;

            case 'notifications/initialized':
            case 'notifications/cancelled':
                return new JSONResponse([], Http::STATUS_ACCEPTED);

            case 'tools/list':
                $result = ['tools' => $this->toolsDefinition()];
                break;

            case 'tools/call':
                $result = $this->handleToolCall($params, $id);
                if ($result instanceof JSONResponse) {
                    return $result;
                }
                break;

            case 'ping':
                $result = [];
                break;

            default:
                return $this->error($id, -32601, 'Method not found: ' . $method);
        }

        return new JSONResponse([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    #[PublicPage]
    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function callGet(): JSONResponse
    {
        $response = new JSONResponse(['error' => 'Method Not Allowed. Use POST (MCP over HTTP).'], Http::STATUS_METHOD_NOT_ALLOWED);
        $response->addHeader('Allow', 'POST');
        return $response;
    }

    // ================================================================
    // Auth & Gate
    // ================================================================

    /**
     * Token-Prüfung + AI-Gate. Liefert eine Fehlerantwort oder null bei Erfolg.
     */
    private function guard(): ?JSONResponse
    {
        if (!$this->aiConfig->isEnabled()) {
            return new JSONResponse(['error' => 'Souvera AI is disabled'], Http::STATUS_FORBIDDEN);
        }

        $auth = $this->request->getHeader('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', trim($auth), $m)) {
            return new JSONResponse(['error' => 'Missing bearer token'], Http::STATUS_UNAUTHORIZED);
        }
        if (!$this->mcpToken->isValidToken($m[1])) {
            return new JSONResponse(['error' => 'Invalid bearer token'], Http::STATUS_UNAUTHORIZED);
        }

        return null;
    }

    // ================================================================
    // Tools
    // ================================================================

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolsDefinition(): array
    {
        return [
            [
                'name' => 'kb_list',
                'description' => 'List all knowledge base articles (id, title). Use kb_get to read the full content.',
                'inputSchema' => ['type' => 'object', 'properties' => [], 'additionalProperties' => false],
            ],
            [
                'name' => 'kb_get',
                'description' => 'Read one knowledge base article by id (full Markdown content).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'Article id from kb_list or kb_search'],
                    ],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'kb_search',
                'description' => 'Search the knowledge base (company info, FAQ, processes) by keywords. Returns matching articles with excerpts.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search keywords'],
                        'limit' => ['type' => 'integer', 'description' => 'Maximum results (default 10, max 25)'],
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|JSONResponse result oder JSON-RPC-Error-Response
     */
    private function handleToolCall(array $params, $id)
    {
        $name = (string) ($params['name'] ?? '');
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        try {
            switch ($name) {
                case 'kb_list':
                    $articles = array_map(static fn (AiKbArticle $a) => $a->toArray(false), $this->kb->list());
                    return $this->toolResult($articles);

                case 'kb_get':
                    $article = $this->kb->get((int) ($args['id'] ?? 0));
                    if ($article === null) {
                        return $this->toolResult('Article not found.', true);
                    }
                    return $this->toolResult($article->toArray());

                case 'kb_search':
                    $limit = min(25, max(1, (int) ($args['limit'] ?? 10)));
                    $matches = $this->kb->search((string) ($args['query'] ?? ''), $limit);
                    $out = array_map(function (AiKbArticle $a) {
                        return [
                            'id' => $a->getId(),
                            'title' => $a->getTitle(),
                            'excerpt' => $this->kb->excerpt($a),
                        ];
                    }, $matches);
                    return $this->toolResult($out);

                default:
                    return $this->error($id, -32602, 'Unknown tool: ' . $name);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Souvera AI: MCP tool call failed (' . $name . '): ' . $e->getMessage(), [
                'app' => 'souvera_central',
                'exception' => $e,
            ]);
            return $this->toolResult('Internal error while executing tool.', true);
        }
    }

    /**
     * MCP tools/call-Ergebnis: Text-Content (JSON-payload oder Klartext).
     *
     * @param mixed $payload
     */
    private function toolResult($payload, bool $isError = false): array
    {
        $text = is_string($payload)
            ? $payload
            : (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => $isError,
        ];
    }

    /**
     * @param mixed $id
     */
    private function error($id, int $code, string $message): JSONResponse
    {
        return new JSONResponse([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ]);
    }
}
