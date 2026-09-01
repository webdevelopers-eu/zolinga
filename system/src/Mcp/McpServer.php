<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\Mcp\{McpEvent, Tools\CallEvent};
use Zolinga\System\Mcp\Exceptions\{McpException, McpInvalidRequestException, McpMethodNotFoundException, McpParseErrorException};
use Zolinga\System\Types\StatusEnum;
use Zolinga\System\Types\OriginEnum;
use Zolinga\System\Types\McpAuthorizationEnum;
use Zolinga\System\Events\AuthorizeEvent;

/**
 * Stateful per-request MCP gateway.
 * 
 * Note: The JSON-RPC 2.0 allows batching but MCP standards do not. 
 * Therefore we support only one request per HTTP request. 
 *
 * One instance per HTTP request. Reads the raw body in the constructor,
 * drives the full request lifecycle via {@see run()}, and is discarded.
 *
 * The pipeline is simple: parse JSON -> create McpEvent -> dispatch ->
 * serialize response. One message per request (MCP Streamable HTTP spec
 * does not use JSON-RPC batches).
 *
 * Usage from `public/mcp/index.php`:
 *
 * ```php
 * try {
 *     (new McpServer())->run();
 * } catch (McpException $e) {
 *     (new McpServer())->sendError($e);
 * }
 * ```
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpServer
{
    /**
     * The raw request body, captured in the constructor.
     */
    private readonly string $rawBody;

    /**
     * The single response payload built by dispatch, or null for
     * notifications (no reply).
     *
     * @var array<string, mixed>|null
     */
    private ?array $response = null;

    /**
     * The dispatched event, kept for the access log.
     */
    private ?McpEvent $event = null;

    /**
     * @param string|null $rawBody Raw request body. Defaults to `php://input`.
     *                            Pass an explicit value for testing.
     */
    public function __construct(?string $rawBody = null)
    {
        $this->rawBody = $rawBody ?? (string) file_get_contents('php://input');
    }

    /**
     * Full request lifecycle: HTTP method check, body parse, dispatch, send.
     * Entry point used by `public/mcp/index.php`.
     *
     * @return void
     * @return void
     */
    public function run(bool $forceAuth = false): void
    {
        global $api;

        if (empty($api->config['mcp']['enabled'])) {
            $this->sendDisabled();
            return;
        }

        $authorization = $forceAuth
            ? McpAuthorizationEnum::FORCE
            : McpAuthorizationEnum::from($api->config['mcp']['server']['authorization'] ?? 'force');

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'OPTIONS') {
            $this->sendOptionsOk();
            return;
        }

        if ($method !== 'POST') {
            $this->sendMethodNotAllowed($method);
            return;
        }

        // Issue: many MCP clients (like Hermes Agent) do not support per-tool access rights
        // and if tools/list is allowed they don't start OAuth flow. Going to /mcp?auth
        // will effectively result in the same behavior as mcp.server.authorization to "force"
        if ($authorization === McpAuthorizationEnum::FORCE) {
            // Should initialize the user
            $api->dispatchEvent(new AuthorizeEvent("system:authorize", OriginEnum::INTERNAL, ['oauth2:scope']));
            if ($api->user->isGuest()) {
                $this->sendUnauthorized();
                return;
            }
        }

        $data = $this->parseBody();

        $event = McpEvent::fromJsonRpc($data);
        $event->dispatch();

        $api->log->info('system:mcp', "MCP request processed: method={$event->type}, status={$event->status->name}");
        $this->send($event);
    }

    /**
     * Emit the response. 204 for notifications, otherwise JSON with
     * MCP protocol headers.
     *
     * @return void
     */
    private function send(McpEvent $event): void
    {
        $response = $this->buildResponse($event);

        if ($response === null) {
            if (!headers_sent()) {
                http_response_code(204);
            }
            $this->logAccess(204, $event);
            return;
        }

        // Derive HTTP status from the event (200 for OK, 401 for UNAUTHORIZED, etc.)
        $httpStatus = $event->status->value ?? 200;
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('MCP-Protocol-Version: ' . McpInitializeHandler::PROTOCOL_VERSION);
            $this->sendHeadersForStatus($event->status);
            http_response_code($httpStatus);
        }
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->logAccess($httpStatus, $event);
    }

    /**
     * Read and decode the raw request body as a JSON object.
     *
     * @return array<string, mixed>
     * @throws McpParseErrorException Invalid JSON or empty body.
     * @throws McpInvalidRequestException Top-level value is not a JSON object.
     */
    private function parseBody(): array
    {
        global $api;

        if (strlen($this->rawBody) > McpHelper::REQUEST_BODY_MAX_BYTES) {
            throw new McpParseErrorException(
                'Request body too large (' . strlen($this->rawBody) . ' bytes; limit is '
                    . McpHelper::REQUEST_BODY_MAX_BYTES . ').'
            );
        }

        if ($this->rawBody === '') {
            throw new McpParseErrorException('Empty request body.');
        }

        $decoded = json_decode($this->rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $lastError = json_last_error_msg();
            $api->log->warning('system:mcp', McpHelper::truncateForEcho('MCP parse error'), [
                'jsonError' => $lastError,
            ]);
            throw new McpParseErrorException('Error parsing JSON request: ' . $lastError);
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new McpInvalidRequestException(
                'Top-level value must be a JSON object.'
                . (is_array($decoded) && array_is_list($decoded) ? ' Batches are not supported.' : '')
            );
        }

        return $decoded;
    }

    /**
     * Build the JSON-RPC 2.0 response payload from a dispatched event.
     *
     * Calls {@see McpEvent::validateResponse()} before producing any output
     * to the client. If validation throws, the event status is set to ERROR
     * and an error response is returned instead.
     *
     * @param McpEvent $event
     * @return array<string, mixed>|null Null for notifications (HTTP 204).
     */
    private function buildResponse(McpEvent $event): ?array
    {
        // JSON-RPC notifications (no id) get no response body — HTTP 204.
        if ($event->jsonrpcId === null) {
            return null;
        }

        // Validate the response before producing any output to the client.
        try {
            $event->validateResponse();
        } catch (\Throwable $e) {
            global $api;
            $api->log->error('system:mcp', 'MCP response validation failed: ' . McpHelper::truncateForEcho($e->getMessage()), [
                'event' => McpHelper::truncateForEcho($event->type),
                'exception' => $e::class,
            ]);
            $event->setStatus(StatusEnum::ERROR, 'Response validation failed: ' . McpHelper::truncateForEcho($e->getMessage()));
        }

        if ($event->status === StatusEnum::UNDETERMINED) {
            return (new McpMethodNotFoundException(
                'Method or resource not found: ' . McpHelper::truncateForEcho($event->type),
                $event->jsonrpcId
            ))->toPayload();
        }

        if ($event instanceof CallEvent) {
            return [
                'jsonrpc' => '2.0',
                'id' => $event->jsonrpcId,
                'result' => McpHelper::envelope($event),
            ];
        }

        if ($event->isOk()) {
            return [
                'jsonrpc' => '2.0',
                'id' => $event->jsonrpcId,
                'result' => McpHelper::normalizeResponse($event->response),
            ];
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $event->jsonrpcId,
            'error' => [
                'code' => McpHelper::errorCodeFromStatus($event->status)->value,
                'message' => McpHelper::truncateForEcho($event->message ?: $event->status->name),
                'data' => McpHelper::statusData($event->status),
            ],
        ];
    }

    /**
     * Emit a top-level error response from an {@see McpException}.
     *
     * @param McpException $error
     * @return void
     */
    public function sendError(McpException $error): void
    {
        $httpStatus = $error->getHttpStatus() ?? 400;
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            $this->sendHeadersForStatus($error->getJsonrpcCode()->toStatus());
            http_response_code($httpStatus);
        }
        echo json_encode($error->toPayload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->logAccess($httpStatus);
    }

    /**
     * Send a 405 Method Not Allowed response for non-POST requests.
     *
     * @param string $method The HTTP method that was used.
     * @return void
     */
    private function sendMethodNotAllowed(string $method): void
    {
        global $api;

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(405);
        }
        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => -32600,
                'message' => 'Method Not Allowed: ' . McpHelper::truncateForEcho($method)
                    . ' is not supported by this non-streaming MCP gateway. This endpoint is meant for AI agents and other non-browser clients. Use POST requests only.',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->logAccess(405);
    }

    /**
     * Send a 204 No Content for CORS preflight (OPTIONS) requests.
     *
     * @return void
     */
    private function sendDisabled(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(404);
        }
        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => -32601,
                'message' => 'MCP gateway is disabled.',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->logAccess(404);
    }

    private function sendUnauthorized(): void
    {
        global $api;

        if (!headers_sent()) {
            $prmUrl = $api->url->resolveUrl('/.well-known/oauth-protected-resource/mcp');
            header('WWW-Authenticate: Bearer resource_metadata="' . $prmUrl . '"');            
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
        }

        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => -32600,
                'message' => 'Unauthorized: Authentication required.',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->logAccess(401);
    }

    private function sendOptionsOk(): void
    {
        if (!headers_sent()) {
            http_response_code(204);
        }
        $this->logAccess(204);
    }

    /**
     * Send additional HTTP headers based on the event status.
     *
     * Currently only emits the WWW-Authenticate header for 401 Unauthorized.
     *
     * @param ?StatusEnum $status The event status.
     * @return void
     */
    private function sendHeadersForStatus(?StatusEnum $status): void
    {
        if ($status === StatusEnum::UNAUTHORIZED) {
            $this->sendUnauthorized();
        }
    }

    /**
     * Log the MCP access request.
     *
     * @param int $status The HTTP status code.
     * @return void
     */
    private function logAccess(int $status, ?McpEvent $event = null): void
    {
        global $api;

        $token = (isset($_SERVER['HTTP_AUTHORIZATION']) ? array_map('trim', explode(' ', $_SERVER['HTTP_AUTHORIZATION'], 2)) : [null, null])
            + [null, null];
        $authInfo = trim(($token[0] ?? '') . ' ' . ($token[1] ? 'crc32=' . dechex(crc32($token[1])) : '')) ?: 'noauth';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '-';

        if (isset($_GET['debug'])) {
            $api->log->info('system:mcp', "🐞 DEBUG: TOKEN = {$token[1]}");
        }
        
        $api->log->info(
            'system:mcp', 
            McpHelper::truncateForEcho(
            "MCP Request[$authInfo]: status=$status, method=" . ($event?->type ?? '-') . ", size=" . strlen($this->rawBody) . "B"
        ), [
            'status' => $status,
            'size' => strlen($this->rawBody),
            'method' => McpHelper::truncateForEcho($event?->type ?? '-'),
            'user_agent' => McpHelper::truncateForEcho($ua),
            'request' => McpHelper::truncateForEcho($event?->request ?? '-'),
            'response' => McpHelper::truncateForEcho($this->response ?? '-')
        ]);
    }
}
