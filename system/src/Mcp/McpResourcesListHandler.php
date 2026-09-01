<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

/**
 * Handles MCP `resources/list` requests.
 *
 * Discovers module-provided resources by scanning each module's
 * `mcp/resources/*.meta.json` files. Rewrites the internal `uri` field
 * to the external `mcp-system:<module>:<basename>` scheme so that
 * internal Zolinga paths are never leaked to MCP clients.
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/server/resources
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-22
 */
class McpResourcesListHandler extends AbstractMcpListHandler
{
    protected const SUBDIR = 'resources';
    protected const RESPONSE_KEY = 'resources';
    protected const ID_FIELD = 'uri';
}
