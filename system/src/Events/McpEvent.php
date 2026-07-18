<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

use ArrayObject;
use ArrayAccess;
use Zolinga\System\Mcp\Exceptions\{McpInvalidParamsException, McpInvalidRequestException};
use Zolinga\System\Mcp\McpHelper;
use Zolinga\System\Types\OriginEnum;

/**
 * Event triggered by the MCP (Model Context Protocol) HTTP gateway at `public/mcp/index.php`.
 *
 * The MCP gateway receives a JSON-RPC 2.0 request, extracts the `method` name and
 * the `params` payload and dispatches an `McpEvent` whose `type` is the
 * JSON-RPC `method` (with `/` replaced by `:`) and whose `request` is the
 * `params` (or an empty ArrayObject if `params` is null). For `tools/call`
 * the gateway dispatches an event whose `type` is the bare tool name
 * (`params.name`) with `request = params.arguments` and wraps the handler's
 * response in the MCP `{ content, isError, structuredContent }` envelope.
 * The {@see $isToolCall} flag distinguishes `tools/call` invocations from
 * plain JSON-RPC methods so the gateway knows when to apply envelope wrapping.
 *
 * The event origin is {@see OriginEnum::MCP}. Listener manifests opt in to MCP
 * delivery by listing "mcp" in the listener's `origin` array. MCP tools and
 * other MCP events are uniform: the only distinction is that a `tools/call`
 * invocation sets {@see $isToolCall} and is wrapped in the MCP envelope.
 *
 * Handlers populate `$event->response` with whatever should land under the
 * JSON-RPC `result` field. For plain events the gateway serializes it
 * verbatim. For `tools/call` events the gateway uses it as the raw
 * structured payload (which must conform to the tool's `outputSchema`) and
 * wraps it in the MCP envelope. Errors are signaled via `$event->setStatus()`
 * with a non-OK status; the gateway maps that to a JSON-RPC `error` object
 * for plain events and to `result.isError = true` for `tools/call`.
 *
 * Example listener manifest:
 *
 * ```json
 * {
 *     "event": "ipdefender:search",
 *     "class": "\\Ipdefender\\Mcp\\SearchHandler",
 *     "method": "onSearch",
 *     "origin": ["mcp"],
 *     "description": "Search the trademark database.",
 *     "schema": { "request": "module://ipdefender/schema/mcp/search-request.json" }
 * }
 * ```
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpEvent extends RequestResponseEvent implements StoppableInterface
{
    use StoppableTrait;

    /**
     * JSON-RPC request id (string or number). `null` for notifications.
     *
     * @var string|int|null
     */
    public string|int|null $jsonrpcId = null;

    /**
     * Whether this event is a `tools/call` invocation (as opposed to a plain
     * JSON-RPC method like `initialize` or `tools/list`). Set by
     * {@see fromJsonRpc()} when the JSON-RPC `method` is `tools/call`. The
     * gateway uses this flag to decide whether to wrap the handler's
     * response in the MCP `{ content, isError, structuredContent }`
     * envelope and to map a non-OK status to `result.isError` instead of a
     * JSON-RPC `error` block.
     *
     * @var bool
     */
    public bool $isToolCall = false;

    /**
     * Constructor.
     *
     * @param string $type The JSON-RPC `method` name, used as the event type.
     * @param string|int|null $jsonrpcId The JSON-RPC request id, or null for notifications.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request The JSON-RPC `params` payload.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $response The JSON-RPC `result` to return.
     * @param bool $isToolCall Whether this is a `tools/call` invocation.
     */
    public function __construct(
        string $type,
        string|int|null $jsonrpcId = null,
        ArrayAccess|array $request = new ArrayObject,
        ArrayAccess|array $response = new ArrayObject,
        bool $isToolCall = false
    ) {
        parent::__construct($type, OriginEnum::MCP, $request, $response);
        $this->jsonrpcId = $jsonrpcId;
        $this->isToolCall = $isToolCall;
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
        $ret['isToolCall'] = $this->isToolCall;
        return $ret;
    }

    /**
     * Create a new instance from an array of data.
     *
     * @param array<string, mixed> $data The data to create the event from. See McpEvent::jsonSerialize() for the structure.
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $event = new static(
            $data['type'],
            $data['jsonrpcId'] ?? null,
            new ArrayObject($data['request'] ?? []),
            new ArrayObject($data['response'] ?? []),
            (bool) ($data['isToolCall'] ?? false)
        );
        $event->uuid = $data['uuid'] ?? null;
        if (!empty($data['status'])) {
            $event->setStatus($data['status'], $data['message'] ?? '');
        }
        return $event;
    }

    /**
     * Create a ready-to-dispatch event from a decoded JSON-RPC 2.0 request.
     *
     * Validates the envelope (`jsonrpc`, `method`, `id`, `params`), resolves
     * the Zolinga event type (`tools/call` → the bare tool name from
     * `params.name`, otherwise `mcp:` + the original JSON-RPC `method`), and
     * returns a new event with the request set to `params` (or
     * `params.arguments` for `tools/call`). For `tools/call` the
     * {@see $isToolCall} flag is set to `true`.
     *
     * @param array<string, mixed> $data Decoded JSON-RPC request object.
     * @return static
     * @throws McpInvalidRequestException Missing/invalid `jsonrpc`, `method`, `id`, or `params`.
     * @throws McpInvalidParamsException  `tools/call` with missing/invalid `name`.
     */
    public static function fromJsonRpc(array $data): static
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

        if ($method === 'tools/call') {
            $name = $params['name'] ?? null;
            if (!McpHelper::isValidToolName($name)) {
                throw new McpInvalidParamsException(
                    'tools/call "name" must be 1..' . McpHelper::TOOL_NAME_MAX_LENGTH . ' chars of [A-Za-z0-9_-].',
                    $id
                );
            }
            // The bare tool name is the event type. MCP tools are distinguished
            // from protocol events by the absence of the `mcp:` prefix (tool
            // names are [A-Za-z0-9_-], so they can never collide).
            $type = $name;
            $arguments = $params['arguments'] ?? [];
            $request = is_array($arguments) ? $arguments : [];
            return new static($type, $id, new ArrayObject($request), new ArrayObject, true);
        }

        // Protocol/management events are prefixed with `mcp:` so they are
        // distinguishable from user tools by name alone. The original JSON-RPC
        // method path is kept verbatim (e.g. `tools/list` → `mcp:tools/list`);
        // no slash-to-colon conversion is applied.
        $type = 'mcp:' . $method;
        return new static($type, $id, new ArrayObject($params));
    }

    /**
     * Normalize and validate the JSON-RPC request id.
     *
     * Kept as a helper because its multi-branch validation logic would
     * significantly decrease readability if inlined into fromJsonRpc.
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
