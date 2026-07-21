<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp\Resources;

use ArrayObject;
use ArrayAccess;

/**
 * Event for the MCP `resources/read` JSON-RPC method.
 *
 * Dispatched by the MCP gateway when a client requests the content of a
 * specific resource by URI.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
class ReadEvent extends ResourcesEvent
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
        parent::__construct('mcp:resources/read', $jsonrpcId, $request, $response);
    }
}