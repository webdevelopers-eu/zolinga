<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp\Tools;

use Zolinga\System\Events\Mcp\McpEvent;

/**
 * Abstract base event for all MCP `tools/*` JSON-RPC methods.
 *
 * Concrete subclasses: {@see CallEvent} (`tools/call`), {@see ListEvent}
 * (`tools/list`).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
abstract class ToolsEvent extends McpEvent
{
}