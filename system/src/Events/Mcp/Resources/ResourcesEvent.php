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
    /**
     * URI schemes allowed in resource URIs returned to MCP clients.
     *
     * Modules should use custom `mcp-<name>` schemes to avoid leaking
     * internal Zolinga paths. The `http` and `https` schemes are allowed
     * for resources served at public URLs.
     *
     * @var array<string>
     */
    public const ALLOWED_URI_SCHEMES = ['mcp-system', 'http', 'https'];

    /**
     * Check if a URI uses an allowed scheme.
     *
     * @param string $uri The resource URI to check.
     * @return bool True if the URI scheme is in the allowed list.
     */
    protected function isAllowedScheme(string $scheme): bool
    {
        return in_array($scheme, self::ALLOWED_URI_SCHEMES, true);
    }
}
