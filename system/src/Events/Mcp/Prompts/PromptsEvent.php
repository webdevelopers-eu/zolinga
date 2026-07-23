<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp\Prompts;

use Zolinga\System\Events\Mcp\McpEvent;

/**
 * Abstract base event for all MCP `prompts/*` JSON-RPC methods.
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/server/prompts
 *
 * Concrete subclasses: {@see ListEvent} (`prompts/list`), {@see GetEvent}
 * (`prompts/get`).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
abstract class PromptsEvent extends McpEvent
{
    /**
     * URI schemes allowed in prompt names returned to MCP clients.
     *
     * Stricter than Resources: prompts are always server-side, so no
     * `http`/`https` schemes are permitted.
     *
     * @var array<string>
     */
    public const ALLOWED_URI_SCHEMES = ['mcp-*'];

    /**
     * Check if a URI scheme is allowed.
     *
     * @param string $scheme The scheme to check.
     * @return bool True if the scheme matches an allowed pattern.
     */
    protected function isAllowedScheme(string $scheme): bool
    {
        foreach (self::ALLOWED_URI_SCHEMES as $allowedScheme) {
            if ($allowedScheme === $scheme) {
                return true;
            }
            if (str_ends_with($allowedScheme, '*') && str_starts_with($scheme, rtrim($allowedScheme, '*'))) {
                return true;
            }
        }
        return false;
    }
}
