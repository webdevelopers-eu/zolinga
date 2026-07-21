<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp\Prompts;

use Zolinga\System\Events\Mcp\McpEvent;

/**
 * Abstract base event for all MCP `prompts/*` JSON-RPC methods.
 *
 * Concrete subclasses: {@see ListEvent} (`prompts/list`), {@see GetEvent}
 * (`prompts/get`).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
abstract class PromptsEvent extends McpEvent
{
}