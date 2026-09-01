<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp;

use ArrayObject;
use ArrayAccess;

/**
 * Event for MCP JSON-RPC notifications (requests without an `id`).
 *
 * The MCP spec defines several notifications that clients send after
 * `initialize` (e.g. `notifications/initialized`). This server does not
 * act on them, but some clients fail hard if the server returns an error
 * for an unknown method. This event is a no-op sink: it dispatches, no
 * listener is expected to handle it, and the gateway replies with HTTP
 * 204 No Content (the JSON-RPC notification contract — no response body).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-09-01
 */
class NotificationEvent extends McpEvent
{
    /**
     * @param string $method The JSON-RPC method name (e.g. `notifications/initialized`).
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request The JSON-RPC params.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $response The JSON-RPC result (unused).
     */
    public function __construct(
        string $method,
        ArrayAccess|array $request = new ArrayObject,
        ArrayAccess|array $response = new ArrayObject
    ) {
        parent::__construct($method, null, $request, $response);
    }
}