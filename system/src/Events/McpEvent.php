<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

use ArrayObject, ArrayAccess;
use Zolinga\System\Types\OriginEnum;

/**
 * Event triggered by the MCP (Model Context Protocol) HTTP gateway at `public/mcp/index.php`.
 *
 * The MCP gateway receives a JSON-RPC 2.0 request, extracts the `method` name and
 * the `params` payload and dispatches an `McpEvent` whose `type` is the
 * JSON-RPC `method` (with `/` replaced by `:`) and whose `request` is the
 * `params` (or an empty ArrayObject if `params` is null). The only special
 * case is `tools/call`, which is dispatched as
 * {@see McpToolsCallEvent} with `type = "tools:call:<name>"` and
 * `request = params.arguments` — the gateway wraps the handler's response
 * in the MCP `{ content, isError, structuredContent }` envelope for those.
 *
 * The event origin is {@see OriginEnum::MCP}. Listener manifests opt in to MCP
 * delivery by listing "mcp" in the listener's `origin` array.
 *
 * Handlers populate `$event->response` with whatever should land under the
 * JSON-RPC `result` field. For plain `McpEvent` the gateway serializes it
 * verbatim. For `McpToolsCallEvent` the gateway uses it as the raw structured
 * payload (which must conform to the tool's `outputSchema`) and wraps it.
 * Errors are signaled via `$event->setStatus()` with a non-OK status; the
 * gateway maps that to a JSON-RPC `error` object for plain `McpEvent` and
 * to `result.isError = true` for `McpToolsCallEvent`.
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
}
