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
 * the gateway dispatches `tools:call:<name>` with `request = params.arguments`
 * and wraps the handler's response in the MCP
 * `{ content, isError, structuredContent }` envelope.
 *
 * The event origin is {@see OriginEnum::MCP}. Listener manifests opt in to MCP
 * delivery by listing "mcp" in the listener's `origin` array.
 *
 * Handlers populate `$event->response` with whatever should land under the
 * JSON-RPC `result` field. For plain events the gateway serializes it
 * verbatim. For `tools:call:*` events the gateway uses it as the raw
 * structured payload (which must conform to the tool's `outputSchema`) and
 * wraps it in the MCP envelope. Errors are signaled via `$event->setStatus()`
 * with a non-OK status; the gateway maps that to a JSON-RPC `error` object
 * for plain events and to `result.isError = true` for `tools:call:*`.
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
     * Constructor.
     *
     * @param string $type The JSON-RPC `method` name, used as the event type.
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
            new ArrayObject($data['response'] ?? [])
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
     * the Zolinga event type (`tools/call` → `tools:call:<name>`, otherwise
     * `/` → `:`), and returns a new event with the request set to `params`
     * (or `params.arguments` for `tools/call`).
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
            $type = 'tools:call:' . $name;
            $arguments = $params['arguments'] ?? [];
            $request = is_array($arguments) ? $arguments : [];
        } else {
            $type = str_replace('/', ':', $method);
            $request = $params;
        }

        return new static($type, $id, new ArrayObject($request));
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
