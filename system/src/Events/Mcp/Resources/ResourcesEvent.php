<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp\Resources;

use Zolinga\System\Events\Mcp\McpEvent;

/**
 * Abstract base event for all MCP `resources/*` JSON-RPC methods.
 * 
 * @see https://modelcontextprotocol.io/specification/2025-11-25/server/resources
 *
 * Concrete subclasses: {@see ListEvent} (`resources/list`), {@see ReadEvent}
 * (`resources/read`).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
abstract class ResourcesEvent extends McpEvent
{
}