<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\{McpEvent, McpToolsCallEvent};
use Zolinga\System\Mcp\Exceptions\{McpException, McpInvalidParamsException, McpInvalidRequestException, McpMethodNotFoundException, McpParseErrorException};
use Zolinga\System\Types\StatusEnum;


/**
 * Stateful per-request MCP gateway.
 *
 * One instance is created per HTTP request, used to drive the entire request
 * lifecycle, and discarded. It reads the raw request body in the constructor,
 * exposes {@see McpServer::process()} as the main entry point, and tracks
 * the responses it has built up so {@see McpServer::send()} can emit them in
 * a single shot.
 *
 * Typical usage from a thin `index.php`:
 *
 * ```php
 * try {
 *     (new McpServer())->process()->send();
 * } catch (McpException $e) {
 *     (new McpServer())->sendException($e);
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
     * Decoded request value: `null` (not yet parsed), an associative array
     * (single request), or a list of associative arrays (batch).
     *
     * @var array<string, mixed>|list<array<string, mixed>>|null
     */
    private array|null $decoded = null;

    /**
     * Whether the request was a JSON-RPC batch.
     */
    private bool $isBatch = false;

    /**
     * Collected JSON-RPC response payloads.
     *
     * @var list<array<string, mixed>>
     */
    private array $responses = [];

    /**
     * JSON-RPC method name of the first dispatched request, captured for
     * the access log. Null when the envelope was invalid (parse error,
     * missing method, etc.). Bounded by {@see McpHelper::METHOD_NAME_MAX_LENGTH}.
     */
    private ?string $firstMethod = null;

    /**
     * The `tools/call` `name` of the first dispatched request, captured
     * for the access log. Null when the request was not a `tools/call`.
     * Bounded by {@see McpHelper::TOOL_NAME_MAX_LENGTH} and already
     * validated against the tool-name character class.
     */
    private ?string $firstToolName = null;

    /**
     * @param string|null $rawBody Raw request body. Defaults to `php://input`.
     *                            Pass an explicit value for testing.
     */
    public function __construct(?string $rawBody = null)
    {
        $this->rawBody = $rawBody ?? (string) file_get_contents('php://input');
    }

    /**
     * Full request lifecycle: handle the HTTP method, run body-size /
     * parse / dispatch, and send the response. This is the entry point
     * used by `public/mcp/index.php`.
     *
     * HTTP method handling (per the MCP Streamable HTTP spec):
     *
     * - **POST** — the only method that carries a JSON-RPC message. Body
     *   is decoded and dispatched.
     * - **GET** — per spec, MAY open an SSE stream or return 405. This
     *   gateway is non-streaming, so it returns 405 with `Allow: GET,
     *   POST, DELETE` so clients can tell the difference between "method
     *   not allowed" and "endpoint down".
     * - **DELETE** — per spec, used to terminate a session. Returns 405
     *   for the same reason as GET.
     * - **HEAD / OPTIONS / PUT / PATCH / anything else** — also 405.
     *
     * @return void
     */
    public function run(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if (!in_array($method, ['POST'])) { // only POST all other 405 which means non SSE stream
            $this->sendMethodNotAllowed($method);
            return;
        }

        $this->checkBodySize();
        try {
            $this->process();
        } catch (McpException $e) {
            $this->sendException($e);
            return;
        }
        $this->send();
    }

    /**
     * Emit an HTTP 405 Method Not Allowed response for non-POST requests
     * (GET, DELETE, HEAD, OPTIONS, etc.). Per the MCP Streamable HTTP
     * spec, returning 405 to GET is allowed when the server does not
     * offer an SSE stream; the `Allow` header tells the client which
     * methods the endpoint does support.
     *
     * @param string $method The original request method (for the access log).
     * @return void
     */
    private function sendMethodNotAllowed(string $method): void
    {
        if (!headers_sent()) {
            header('Allow: GET, POST, DELETE');
            header('Content-Type: application/json; charset=utf-8');
            $this->sendSessionHeader();
            http_response_code(405);
        }
        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => -32600,
                'message' => 'Method Not Allowed: ' . McpHelper::truncateForEcho($method) . ' is not supported by this non-streaming MCP gateway.',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->logAccess(405, null);
    }

    /**
     * Parse, validate and dispatch the request.
     *
     * On success, returns `$this` for chaining. Throws {@see McpException}
     * subclasses for top-level errors (parse error, invalid envelope,
     * internal error) — catch them and call {@see McpServer::sendException()}.
     *
     * @return $this
     * @throws McpException
     */
    public function process(): self
    {
        $this->checkBodySize();
        $this->decodeBody();
        $this->dispatchAll();
        return $this;
    }

    /**
     * Reject requests whose Content-Length exceeds {@see McpHelper::REQUEST_BODY_MAX_BYTES}
     * (64 KiB by default) before allocating memory for the body. The check
     * is advisory: when Content-Length is missing (chunked encoding) we
     * fall back to a post-decode size check in {@see self::decodeBody()}.
     *
     * Sends HTTP 413 Payload Too Large and aborts so the rest of the
     * bootstrap never runs for an oversized request.
     *
     * @return void
     */
    private function checkBodySize(): void
    {
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        if ($contentLength > McpHelper::REQUEST_BODY_MAX_BYTES) {
            $this->logSuspicious('oversize body', [
                'contentLength' => $contentLength,
                'limit' => McpHelper::REQUEST_BODY_MAX_BYTES,
            ]);
            $this->logAccess(413, null);
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(413);
            }
            echo json_encode([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => McpStatusEnum::JSON_RPC_PARSE_ERROR->value,
                    'message' => 'Request body too large (' . $contentLength . ' bytes; limit is ' . McpHelper::REQUEST_BODY_MAX_BYTES . ').',
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit(0);
        }
    }

    /**
     * Emit a single log line describing a suspicious request, with all
     * string fields bounded via {@see McpHelper::truncateForEcho()}. Use
     * this instead of raw `$api->log->error('system:mcp', $message, $context)`
     * whenever the message or context may contain attacker-controlled
     * content (request body fragments, headers, etc.).
     *
     * @param string $what Short label, e.g. `'oversize body'`, `'parse error'`, `'invalid session id'`.
     * @param array<string, mixed> $context Sanitized context (already truncated by the caller if needed).
     * @return void
     */
    private function logSuspicious(string $what, array $context = []): void
    {
        global $api;

        $api->log->warning(
            'system:mcp',
            McpHelper::truncateForEcho('Suspicious MCP request: ' . $what),
            $context
        );
    }

    /**
     * Emit the collected response payload. Sets `Content-Type: application/json`
     * and `MCP-Protocol-Version` headers. Returns HTTP 204 if the entire
     * request was a notification (no replies to send).
     *
     * @return void
     */
    public function send(): void
    {
        if ($this->responses === []) {
            $this->sendNoContent();
            return;
        }

        $payload = $this->isBatch ? $this->responses : $this->responses[0];
        $this->sendJson($payload);
        $this->logAccess(200, $this->firstDispatchedMethod(), $this->isBatch, $this->firstDispatchedToolName());
    }

    /**
     * Emit a single error response from a top-level {@see McpException}.
     *
     * @param McpException $error
     * @return void
     */
    public function sendException(McpException $error): void
    {
        $status = $error->getHttpStatus() ?? 400;
        if ($error->getHttpStatus() !== null && !headers_sent()) {
            http_response_code($error->getHttpStatus());
        }
        $this->sendJson($error->toPayload());
        $this->logAccess($status);
    }

    /**
     * Emit a one-line access log entry for every request that reaches
     * the gateway. Called from every response path (200, 204, 4xx, 5xx,
     * 405, 413, parse error, etc.) so operators always have a per-request
     * record of what hit `/mcp/` and what happened.
     *
     * Intentionally short and bounded so a flood of attacker traffic
     * cannot fill the disk. Logs the request contents (method, tool,
     * size, batch flag, id hint) and the response status — IP and
     * User-Agent are NOT included because Apache already records those
     * in its access log. Only attacker-controlled fields are echoed,
     * each pre-truncated via {@see McpHelper::truncateForEcho()}.
     *
     * @param int $status HTTP status code that was (or will be) sent.
     * @param string|null $method JSON-RPC method name (or null if the envelope was invalid).
     * @param bool $isBatch True for batch requests.
     * @param string|null $toolName The `tools/call` `name` argument (or null).
     * @return void
     */
    public function logAccess(int $status, ?string $method = null, bool $isBatch = false, ?string $toolName = null): void
    {
        global $api;
        $context = [
            'status' => $status,
            'size' => strlen($this->rawBody),
            'batch' => $isBatch,
            'tool' => $toolName,
            'method' => $method,
        ];

        $statusName = StatusEnum::tryFrom($status)?->name ?? 'UNKNOWN';

        $api->log->info(
            'system:mcp', 
            "MCP Request: " . McpHelper::truncateForEcho("status=$status $statusName, method=$method, tool=$toolName, batch=" . ($isBatch ? 'yes' : 'no') . ", size=" . strlen($this->rawBody) . "B"),
            $context
        );
    }

    /**
     * Read and decode the raw request body. Sets `$this->decoded` and
     * `$this->isBatch`.
     *
     * @return void
     * @throws McpParseErrorException
     */
    private function decodeBody(): void
    {
        // Post-decode fallback for requests without a Content-Length
        // header (e.g. chunked transfer encoding). Catches oversize
        // bodies the {@see self::checkBodySize()} pre-check missed.
        if (strlen($this->rawBody) > McpHelper::REQUEST_BODY_MAX_BYTES) {
            $this->logSuspicious('oversize body (post-decode)', [
                'rawBytes' => strlen($this->rawBody),
                'limit' => McpHelper::REQUEST_BODY_MAX_BYTES,
            ]);
            throw new McpParseErrorException(
                'Request body too large (' . strlen($this->rawBody) . ' bytes; limit is ' . McpHelper::REQUEST_BODY_MAX_BYTES . ').'
            );
        }

        if ($this->rawBody === '') {
            throw new McpParseErrorException('Empty request body.');
        }

        $decoded = json_decode($this->rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logSuspicious('parse error', [
                'jsonError' => json_last_error_msg(),
                'bodyPreview' => McpHelper::truncateForEcho($this->rawBody),
            ]);
            throw new McpParseErrorException('Parse error: ' . json_last_error_msg());
        }

        // Per JSON-RPC 2.0 a top-level scalar / bool / null is a parse
        // error (the spec requires the request to be an object or an
        // array of objects). The previous code silently swallowed it and
        // returned HTTP 204.
        if (!is_array($decoded)) {
            $this->logSuspicious('non-object top-level value', [
                'type' => get_debug_type($decoded),
            ]);
            throw new McpParseErrorException('Top-level value must be a JSON object or array of objects.');
        }

        if (array_is_list($decoded)) {
            if ($decoded === []) {
                $this->logSuspicious('empty batch', []);
                throw new McpInvalidRequestException('Empty batch.');
            }
            if (count($decoded) > McpHelper::BATCH_MAX_REQUESTS) {
                $this->logSuspicious('oversize batch', [
                    'count' => count($decoded),
                    'limit' => McpHelper::BATCH_MAX_REQUESTS,
                ]);
                throw new McpInvalidRequestException(
                    'Batch too large (' . count($decoded) . ' requests; limit is ' . McpHelper::BATCH_MAX_REQUESTS . ').'
                );
            }
            $this->isBatch = true;
            $this->decoded = $decoded;
            return;
        }

        $this->isBatch = false;
        $this->decoded = [$decoded];
    }

    /**
     * Dispatch every request in the (now single-list) `$this->decoded` and
     * collect the replies.
     *
     * @return void
     */
    private function dispatchAll(): void
    {
        $decoded = $this->decoded ?? [];
        foreach ($decoded as $req) {
            $reply = $this->dispatchOne($req);
            if ($reply !== null) {
                $this->responses[] = $reply;
            }
        }
    }

    /**
     * Dispatch a single JSON-RPC request, returning the reply payload or
     * `null` for notifications.
     *
     * @param mixed $req
     * @return array<string, mixed>|null
     */
    private function dispatchOne(mixed $req): ?array
    {
        global $api;

        try {
            [$method, $id, $params] = McpRequestValidator::requireRequest($req);
        } catch (McpInvalidRequestException $e) {
            $api->log->error('system:mcp', 'MCP request validation failed: ' . McpHelper::truncateForEcho($e->getMessage()), [
                'requestPreview' => McpHelper::truncateForEcho(json_encode($req, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            ]);
            // Per JSON-RPC 2.0: a notification with an invalid envelope gets no reply.
            $reqIsArray = is_array($req);
            $hasId = $reqIsArray && array_key_exists('id', $req);
            if (!$hasId) {
                if (!\Zolinga\System\IS_CLI && !headers_sent()) {
                    header('X-MCP-Error: invalid-json-rpc-2.0-envelope');
                }
                return null;
            }
            $rawId = $reqIsArray ? ($req['id'] ?? null) : null;

            // When the validator rejected the request because of the id
            // (wrong type, too long, etc.) do NOT echo the offending
            // value back in the error response. Returning `null` is
            // compliant with JSON-RPC 2.0 (\"id MUST be included in the
            // Response if it was included in the Request\" — we honor
            // presence but null the value so a multi-kilobyte id cannot
            // be reflected).
            $validId = $this->coerceId($rawId);
            if (!str_contains($e->getMessage(), '"id" field')) {
                // The error was about something other than the id, so
                // it's safe to echo the validated id back.
                $validId = is_string($validId) && strlen($validId) > McpHelper::REQUEST_ID_MAX_LENGTH
                    ? substr($validId, 0, McpHelper::REQUEST_ID_MAX_LENGTH)
                    : $validId;
            } else {
                $validId = null;
            }
            return (new McpInvalidRequestException($e->getMessage(), $validId))->toPayload();
        }

        // Capture the method (and tool name, if applicable) for the access log.
        if ($this->firstMethod === null) {
            $this->firstMethod = $method;
        }

        // Enforce a max length on `method` BEFORE we build an event type
        // from it. Anything longer is treated as "method not found" without
        // echoing the value back, to keep error responses bounded.
        if (strlen($method) > McpHelper::METHOD_NAME_MAX_LENGTH) {
            $this->logSuspicious('oversize method', [
                'methodLength' => strlen($method),
                'limit' => McpHelper::METHOD_NAME_MAX_LENGTH,
            ]);
            return (new McpMethodNotFoundException(
                'Method not found: <name too long, ' . strlen($method) . ' chars; limit is ' . McpHelper::METHOD_NAME_MAX_LENGTH . '>',
                $id
            ))->toPayload();
        }

        // The gateway is a thin translator: every JSON-RPC `method` becomes a
        // Zolinga event `type` by replacing `/` with `:`. So `tools/list` →
        // `tools:list`, `notifications/initialized` → `notifications:initialized`.
        // The only special case is `tools/call`, which is expanded to the
        // per-tool event `tools:call:<name>` with `params.arguments` as the
        // request. A missing/non-string `name` is surfaced as method-not-found.
        if ($method === 'tools/call') {
            $name = $params['name'] ?? null;
            if (!McpHelper::isValidToolName($name)) {
                return (new McpInvalidParamsException(
                    'tools/call "name" must be 1..' . McpHelper::TOOL_NAME_MAX_LENGTH
                        . ' chars of [A-Za-z0-9_-].',
                    $id
                ))->toPayload();
            }
            if ($this->firstToolName === null) {
                $this->firstToolName = $name;
            }
            $arguments = $params['arguments'] ?? [];
            if (!is_array($arguments)) {
                $arguments = [];
            }
            $eventType = 'tools:call:' . $name;
            $eventRequest = $arguments;
        } else {
            $eventType = str_replace('/', ':', $method);
            $eventRequest = $params;
        }

        $event = $this->dispatchEvent($eventType, $eventRequest, $id, $name ?? null);

        // Notifications get dispatched for side effects only; no reply is sent.
        if ($id === null) {
            return null;
        }

        return $this->buildResponse($event, $method, $id);
    }

    /**
     * Dispatch a Zolinga event for the given resolved type. Any escaped
     * `Throwable` is logged and recorded on the event as an internal error.
     *
     * Per-tool `tools:call:<name>` events are instantiated as
     * {@see McpToolsCallEvent} so the gateway can wrap their response in the
     * MCP `{ content, isError, structuredContent }` envelope. All other MCP
     * events use the plain {@see McpEvent}.
     *
     * @param string $eventType
     * @param array<string, mixed> $eventRequest
     * @param string|int|null $id
     * @param string|null $toolName For `tools/call`, the JSON-RPC `name` argument; used to label the "Unknown tool" message.
     * @return McpEvent
     */
    private function dispatchEvent(string $eventType, array $eventRequest, string|int|null $id, ?string $toolName = null): McpEvent
    {
        global $api;

        $event = str_starts_with($eventType, 'tools:call:')
            ? new McpToolsCallEvent($eventType, $id, $eventRequest)
            : new McpEvent($eventType, $id, $eventRequest);

        try {
            $event->dispatch();
        } catch (\Throwable $e) {
            $api->log->error('system:mcp', 'MCP dispatch failed: ' . McpHelper::truncateForEcho($e->getMessage()), [
                'event' => McpHelper::truncateForEcho($eventType),
                'exception' => $e::class,
            ]);
            $event->setStatus(StatusEnum::ERROR, 'Internal error: ' . McpHelper::truncateForEcho($e->getMessage()));
        }

        // For `tools:call:<name>`, an undetermined status means no listener
        // registered for that tool. Surface a friendly "Unknown tool" message
        // so the envelope carries something useful. Truncated so a malicious
        // long name can't bloat the response.
        if ($event instanceof McpToolsCallEvent && $event->status === StatusEnum::UNDETERMINED) {
            $name = $toolName ?? substr($eventType, strlen('tools:call:'));
            $event->setStatus(
                StatusEnum::NOT_FOUND,
                'Unknown tool: ' . McpHelper::truncateForEcho($name)
            );
        }

        return $event;
    }

    /**
     * Build the JSON-RPC 2.0 response payload from a dispatched event.
     *
     * For {@see McpToolsCallEvent} (i.e. `tools/call` invocations) the result
     * is always the MCP envelope `{ content, isError, structuredContent }`,
     * including the error case (which becomes `isError: true` with the
     * message in `content[0].text`). For all other events the legacy
     * behaviour is preserved: OK status → raw `$event->response` as
     * `result`; non-OK status → JSON-RPC `error` block; undetermined →
     * method-not-found error.
     *
     * @param McpEvent $event
     * @param string $method The raw JSON-RPC method (used in error messages).
     * @param string|int $id
     * @param string|null $toolName For `tools/call`, the JSON-RPC `name` argument (used in the "Unknown tool" message).
     * @return array<string, mixed>
     */
    private function buildResponse(McpEvent $event, string $method, string|int $id, ?string $toolName = null): array
    {
        if ($event instanceof McpToolsCallEvent) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => McpHelper::envelope($event),
            ];
        }

        if ($event->status === StatusEnum::UNDETERMINED) {
            return (new McpMethodNotFoundException(
                'Method not found: ' . McpHelper::truncateForEcho($method),
                $id
            ))->toPayload();
        }

        if ($event->isOk()) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => McpHelper::normalizeResponse($event->response),
            ];
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => McpHelper::errorCodeFromStatus($event->status)->value,
                'message' => McpHelper::truncateForEcho($event->message ?: $event->status->name),
                'data' => McpHelper::statusData($event->status),
            ],
        ];
    }

    /**
     * Coerce a raw `id` value to a valid `string|int|null` for use in error
     * payloads. Returns `null` for anything that is not a string or int.
     *
     * @param mixed $id
     * @return string|int|null
     */
    private function coerceId(mixed $id): string|int|null
    {
        if (is_string($id) || is_int($id)) {
            return $id;
        }
        return null;
    }

    /**
     * Return the JSON-RPC method name of the first dispatched request, for
     * the access log. Returns `null` when the envelope was invalid (parse
     * error, missing method, etc.).
     *
     * @return string|null
     */
    private function firstDispatchedMethod(): ?string
    {
        return $this->firstMethod;
    }

    /**
     * Return the `tools/call` `name` of the first dispatched request, for
     * the access log. Returns `null` when the request was not a
     * `tools/call`.
     *
     * @return string|null
     */
    private function firstDispatchedToolName(): ?string
    {
        return $this->firstToolName;
    }

    /**
     * Emit an HTTP 204 No Content response (used when the request is a
     * notification or a batch of notifications).
     *
     * @return void
     */
    private function sendNoContent(): void
    {
        if (!headers_sent()) {
            $this->sendSessionHeader();
            http_response_code(204);
        }
        $this->logAccess(204);
    }

    /**
     * Emit a JSON response with the MCP protocol headers.
     *
     * @param array<string, mixed>|list<array<string, mixed>> $payload
     * @return void
     */
    private function sendJson(array $payload): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('MCP-Protocol-Version: ' . McpInitializeHandler::PROTOCOL_VERSION);
            $this->sendSessionHeader();
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Send the `Mcp-Session-Id` response header carrying the current PHP
     * session id, so clients can resume the session on subsequent requests.
     *
     * No-op when the session is not active (e.g. CLI dispatch) or the id
     * is empty.
     *
     * @return void
     */
    private function sendSessionHeader(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $id = session_id();
        if ($id === '') {
            return;
        }
        header('Mcp-Session-Id: ' . $id);
    }
}
