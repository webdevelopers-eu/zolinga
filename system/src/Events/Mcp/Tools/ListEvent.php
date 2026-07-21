<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp\Tools;

use ArrayObject;
use ArrayAccess;

/**
 * Event for the MCP `tools/list` JSON-RPC method.
 *
 * Dispatched by the MCP gateway when a client requests the list of available
 * tools. The handler walks the merged manifest and returns every listener
 * that opts in to the `mcp` origin and declares a `schema.response`.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
class ListEvent extends ToolsEvent
{
    /**
     * @param string|int|null $jsonrpcId The JSON-RPC request id.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request The JSON-RPC params.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $response The JSON-RPC result.
     */
    public function __construct(
        string|int|null $jsonrpcId = null,
        ArrayAccess|array $request = new ArrayObject,
        ArrayAccess|array $response = new ArrayObject
    ) {
        parent::__construct('mcp:tools/list', $jsonrpcId, $request, $response);
    }
}