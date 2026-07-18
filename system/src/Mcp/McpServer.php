<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\McpEvent;
use Zolinga\System\Mcp\Exceptions\{McpException, McpInvalidRequestException, McpMethodNotFoundException, McpParseErrorException};
use Zolinga\System\Types\StatusEnum;

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
     */
    public function run(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'OPTIONS') {
            $this->sendOptionsOk();
            return;
        }

        if ($method !== 'POST') {
            $this->sendMethodNotAllowed($method);
            return;
        }

        $data = $this->parseBody();
        $this->response = $this->dispatch($data);
        $this->send();
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
            $api->log->warning('system:mcp', McpHelper::truncateForEcho('MCP parse error'), [
                'jsonError' => json_last_error_msg(),
            ]);
            throw new McpParseErrorException('Parse error: ' . json_last_error_msg());
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
     * Dispatch a single JSON-RPC request and return the response payload,
     * or null for notifications.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function dispatch(array $data): ?array
    {
        global $api;

        try {
            $event = McpEvent::fromJsonRpc($data);
        } catch (McpException $e) {
            // Invalid envelope: notifications (no id) get no reply.
            if (!array_key_exists('id', $data)) {
                return null;
            }
            $payload = $e->toPayload();
            $rawId = $data['id'];
            $payload['id'] = is_string($rawId) || is_int($rawId) ? $rawId : null;
            return $payload;
        }

        $this->event = $event;

        try {
            $event->dispatch();
        } catch (\Throwable $e) {
            $api->log->error('system:mcp', 'MCP dispatch failed: ' . McpHelper::truncateForEcho($e->getMessage()), [
                'event' => McpHelper::truncateForEcho($event->type),
                'exception' => $e::class,
            ]);
            $event->setStatus(StatusEnum::ERROR, 'Internal error: ' . McpHelper::truncateForEcho($e->getMessage()));
        }

        // tools/call with no listener -> friendly "Unknown tool".
        if ($event->isToolCall && $event->status === StatusEnum::UNDETERMINED) {
            $event->setStatus(StatusEnum::NOT_FOUND, 'Unknown tool: ' . McpHelper::truncateForEcho($event->type));
        }

        // Notifications: dispatched for side effects, no reply.
        if ($event->jsonrpcId === null) {
            return null;
        }

        return $this->buildResponse($event);
    }

    /**
     * Build the JSON-RPC 2.0 response payload from a dispatched event.
     *
     * @param McpEvent $event
     * @return array<string, mixed>
     */
    private function buildResponse(McpEvent $event): array
    {
        if ($event->isToolCall) {
            return [
                'jsonrpc' => '2.0',
                'id' => $event->jsonrpcId,
                'result' => McpHelper::envelope($event),
            ];
        }

        if ($event->status === StatusEnum::UNDETERMINED) {
            return (new McpMethodNotFoundException(
                'Method not found: ' . McpHelper::truncateForEcho($event->type),
                $event->jsonrpcId
            ))->toPayload();
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
     * Emit the response. 204 for notifications, otherwise JSON with
     * MCP protocol headers.
     *
     * @return void
     */
    private function send(): void
    {
        if ($this->response === null) {
            if (!headers_sent()) {
                http_response_code(204);
            }
            $this->logAccess(204);
            return;
        }

        // Derive HTTP status from the event (200 for OK, 401 for UNAUTHORIZED, etc.)
        $httpStatus = $this->event?->status->value ?? 200;
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('MCP-Protocol-Version: ' . McpInitializeHandler::PROTOCOL_VERSION);
            $this->sendHeadersForStatus($this->event?->status);
            http_response_code($httpStatus);
        }
        echo json_encode($this->response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->logAccess($httpStatus);
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
     * Emit HTTP 405 for non-POST requests.
     *
     * @param string $method
     * @return void
     */
    private function sendMethodNotAllowed(string $method): void
    {
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
                    . ' is not supported by this non-streaming MCP gateway.',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->logAccess(405);
    }

    /**
     * Respond to an OPTIONS probe with 204 No Content.
     *
     * Safety net: normally OPTIONS is handled by public/mcp/index.php which
     * exits before the system loads. This is reached only if McpServer is
     * invoked directly. CORS headers are not emitted here — that is the
     * entry point's responsibility.
     *
     * @return void
     */
    private function sendOptionsOk(): void
    {
        if (!headers_sent()) {
            http_response_code(204);
        }
        $this->logAccess(204);
    }

    /**
     * Send status-specific HTTP headers. Currently handles UNAUTHORIZED
     * (401) by emitting the WWW-Authenticate header pointing to the
     * OAuth Protected Resource Metadata (RFC 9728). Easy to extend for
     * other status-specific headers.
     *
     * @param StatusEnum|null $status
     * @return void
     */
    private function sendHeadersForStatus(?StatusEnum $status): void
    {
        if ($status === StatusEnum::UNAUTHORIZED) {
            global $api;
            $prmUrl = $api->url->resolveUrl('/.well-known/oauth-protected-resource');
            header('WWW-Authenticate: Bearer resource_metadata="' . $prmUrl . '"');
        }
    }

    /**
     * Write a one-line access log entry.
     *
     * @param int $status HTTP status code sent.
     * @return void
     */
    private function logAccess(int $status): void
    {
        global $api;
        $event = $this->event;

        $token = (isset($_SERVER['HTTP_AUTHORIZATION']) ? array_map('trim', explode(' ', $_SERVER['HTTP_AUTHORIZATION'], 2)) : [null, null])
            + [null, null];
        $authInfo = trim(($token[0] ?? '') . ' ' . ($token[1] ? 'crc32=' . dechex(crc32($token[1])) : ''));
        
        $api->log->info('system:mcp', McpHelper::truncateForEcho(
            "MCP Request[$authInfo]: status=$status, method=" . ($event?->type ?? '-')
            . ", size=" . strlen($this->rawBody) . "B"
        ), [
            'status' => $status,
            'size' => strlen($this->rawBody),
            'method' => $event?->type,
        ]);
    }
}
 