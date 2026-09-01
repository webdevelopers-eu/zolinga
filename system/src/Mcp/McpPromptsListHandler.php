<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

/**
 * Handles MCP `prompts/list` requests.
 *
 * Discovers module-provided prompts by scanning each module's
 * `mcp/prompts/*.meta.json` files. Rewrites the `name` field to the
 * external `mcp-system://<module>/<subdir>/<basename>` scheme so that internal
 * Zolinga paths are never leaked to MCP clients.
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/server/prompts
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-22
 */
class McpPromptsListHandler extends AbstractMcpListHandler
{
    protected const SUBDIR = 'prompts';
    protected const RESPONSE_KEY = 'prompts';
    protected const ID_FIELD = 'name';

    /**
     * Prompts do not require a `name` field in the meta file — the filename
     * basename is always used as the identifier.
     *
     * @param array<string, mixed> $json The meta JSON (already rewritten).
     * @param string $metaFile The source file path (for logging).
     * @return bool
     */
    protected function validateMeta(array $json, string $metaFile): bool
    {
        return true;
    }
}
