<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp;

use ArrayObject;
use ArrayAccess;
use Zolinga\System\Events\RequestResponseEvent;
use Zolinga\System\Events\StoppableInterface;
use Zolinga\System\Events\StoppableTrait;
use Zolinga\System\Mcp\Exceptions\{McpInvalidRequestException, McpMethodNotFoundException};
use Zolinga\System\Mcp\McpHelper;
use Zolinga\System\Types\OriginEnum;

/**
 * Abstract base event for all MCP (Model Context Protocol) JSON-RPC requests.
 *
 * The MCP gateway at `public/mcp/index.php` receives a JSON-RPC 2.0 request,
 * parses it via {@see fromJsonRpc()}, and dispatches the appropriate concrete
 * event subclass. The event origin is {@see OriginEnum::MCP}. Listener
 * manifests opt in to MCP delivery by listing "mcp" in the listener's
 * `origin` array.
 *
 * Concrete subclasses:
 * - {@see InitializeEvent} — `initialize` lifecycle request.
 * - {@see Tools\CallEvent} — `tools/call` invocation (a tool execution).
 * - {@see Tools\ListEvent} — `tools/list` request (tool discovery).
 * - {@see Prompts\ListEvent}, {@see Prompts\GetEvent} — prompts protocol methods.
 * - {@see Resources\ListEvent}, {@see Resources\ReadEvent} — resources protocol methods.
 *
 * Handlers populate `$event->response` with whatever should land under the
 * JSON-RPC `result` field. Errors are signaled via `$event->setStatus()` with
 * a non-OK status; the gateway maps that to a JSON-RPC `error` object for
 * plain events and to `result.isError = true` for `tools/call` events.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
abstract class McpEvent extends RequestResponseEvent implements StoppableInterface
{
    use StoppableTrait;

    /**
     * JSON-RPC request id (string or number). `null` for notifications.
     *
     * @var string|int|null
     */
    public string|int|null $jsonrpcId = null;

    /**
     * Constructor.
     *
     * @param string $type The event type (JSON-RPC method or tool name).
     * @param string|int|null $jsonrpcId The JSON-RPC request id, or null for notifications.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request The JSON-RPC `params` payload.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $response The JSON-RPC `result` to return.
     */
    public function __construct(
        string $type,
        string|int|null $jsonrpcId = null,
        ArrayAccess|array $request = new ArrayObject,
        ArrayAccess|array $response = new ArrayObject
    ) {
        parent::__construct($type, OriginEnum::MCP, $request, $response);
        $this->jsonrpcId = $jsonrpcId;
    }

    /**
     * Validate the response before it is sent to the client.
     *
     * Called by the MCP gateway before producing any output. If the response
     * is invalid, this method should throw an exception that prevents the
     * output from being produced. The base implementation does nothing;
     * descendant event classes may override it to enforce response-level
     * constraints (e.g. URI scheme whitelisting for resources).
     *
     * @return void
     * @throws \Throwable If the response is invalid and must not be sent.
     */
    public function validateResponse(): void
    {
        // Base implementation: no validation. Override in descendants as needed.
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        $ret = parent::jsonSerialize();
        $ret['jsonrpcId'] = $this->jsonrpcId;
        return $ret;
    }

    /**
     * Create a ready-to-dispatch event from a decoded JSON-RPC 2.0 request.
     *
     * Validates the envelope (`jsonrpc`, `method`, `id`, `params`), resolves
     * the concrete event subclass based on the JSON-RPC `method` name, and
     * returns a new event instance with the request set to `params` (or
     * `params.arguments` for `tools/call`).
     *
     * @param array<string, mixed> $data Decoded JSON-RPC request object.
     * @return McpEvent
     * @throws McpInvalidRequestException Missing/invalid `jsonrpc`, `method`, `id`, or `params`.
     * @throws McpMethodNotFoundException  Unknown/unsupported JSON-RPC `method`.
     */
    public static function fromJsonRpc(array $data): McpEvent
    {
        if (($data['jsonrpc'] ?? null) !== '2.0') {
            throw new McpInvalidRequestException('Missing or invalid "jsonrpc" field; must be "2.0".');
        }

        $method = $data['method'] ?? null;
        if (!is_string($method) || $method === '') {
            throw new McpInvalidRequestException('Missing or invalid "method" field; must be a non-empty string.');
        }

        $id = self::extractId($data['id'] ?? null);

        $params = $data['params'] ?? [];
        if (!is_array($params)) {
            throw new McpInvalidRequestException('Invalid "params" field; must be an object or array.');
        }

        return self::buildEvent($method, $id, $params);
    }

    /**
     * Map a JSON-RPC method to the correct concrete event subclass.
     *
     * @param string $method The JSON-RPC method name.
     * @param string|int|null $id The normalized JSON-RPC request id.
     * @param array<string, mixed> $params The JSON-RPC params.
     * @return McpEvent
     * @throws McpMethodNotFoundException  Unknown/unsupported JSON-RPC `method`.
     * @throws McpInvalidParamsException  `tools/call` with missing/invalid `name`.
     */
    private static function buildEvent(string $method, string|int|null $id, array $params): McpEvent
    {
        return match ($method) {
            'initialize' => new InitializeEvent($id, $params),
            'tools/list' => new Tools\ListEvent($id, $params),
            'tools/call' => new Tools\CallEvent($id, $params),
            'prompts/list' => new Prompts\ListEvent($id, $params),
            'prompts/get' => new Prompts\GetEvent($id, $params),
            'resources/list' => new Resources\ListEvent($id, $params),
            'resources/read' => new Resources\ReadEvent($id, $params),
            default => throw new McpMethodNotFoundException(
                'Method not found: ' . $method,
                $id
            ),
        };
    }

    /**
     * Normalize and validate the JSON-RPC request id.
     *
     * @param mixed $id Raw id from the request.
     * @return string|int|null
     * @throws McpInvalidRequestException Invalid id type or length.
     */
    private static function extractId(mixed $id): string|int|null
    {
        if ($id !== null && !is_string($id) && !is_int($id)) {
            throw new McpInvalidRequestException('Invalid "id" field; must be a string, integer or null.');
        }
        if (is_string($id)) {
            if ($id === '') {
                return null;
            }
            if (strlen($id) > McpHelper::REQUEST_ID_MAX_LENGTH) {
                throw new McpInvalidRequestException(
                    'Invalid "id" field; string must be at most ' . McpHelper::REQUEST_ID_MAX_LENGTH . ' chars.'
                );
            }
        }
        return $id;
    }
}
