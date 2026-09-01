<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp\Tools;

use ArrayObject;
use ArrayAccess;
use Zolinga\System\Mcp\Exceptions\McpInvalidParamsException;
use Zolinga\System\Mcp\McpHelper;

/**
 * Event for the MCP `tools/call` JSON-RPC method.
 *
 * Dispatched by the MCP gateway when a client invokes a tool. The event
 * `type` is the bare tool name (e.g. `alert`, `echo`) and the `request`
 * carries the tool's `arguments`.
 *
 * The gateway wraps the handler's `$event->response` in the MCP
 * `{ content, isError, structuredContent }` envelope. A non-OK status
 * maps to `result.isError = true` instead of a JSON-RPC `error` block.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
class CallEvent extends ToolsEvent
{
    /**
     * @param string|int|null $jsonrpcId The JSON-RPC request id.
     * @param array<string, mixed> $params The `tools/call` params: `{ name: string, arguments: object }`.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $response The tool result.
     * @throws McpInvalidParamsException Missing/invalid tool `name`.
     */
    public function __construct(
        string|int|null $jsonrpcId = null,
        array $params = [],
        ArrayAccess|array $response = new ArrayObject
    ) {
        $name = $params['name'] ?? null;
        if (!McpHelper::isValidToolName($name)) {
            throw new McpInvalidParamsException(
                'tools/call "name" must be 1..' . McpHelper::TOOL_NAME_MAX_LENGTH . ' chars of ' . McpHelper::TOOL_NAME_CHAR_CLASS . ' and must not start with "mcp:".',
                $jsonrpcId
            );
        }
        $arguments = $params['arguments'] ?? [];
        $request = is_array($arguments) ? $arguments : [];
        parent::__construct($name, $jsonrpcId, new ArrayObject($request), $response);
    }
}