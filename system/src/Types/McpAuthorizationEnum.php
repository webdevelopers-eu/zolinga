<?php

declare(strict_types=1);

namespace Zolinga\System\Types;

/**
 * MCP gateway authorization mode.
 *
 * Controls when the MCP gateway requires OAuth2 authentication:
 * - FORCE: require authorization for all requests, including tools/list.
 * - AUTO: only requests that require rights trigger 401/403.
 *
 * Maps to the `mcp.server.authorization` config key in zolinga.json.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 1.7.0
 */
enum McpAuthorizationEnum: string
{
    // User has to be logged in in order to access any MCP endpoint
    case FORCE = 'force';
    // The 401/403 is sent only when the user fails to authorize for a specific tool or resource
    // tools/list and other public tools/resources/prompts/... are accessible without authorization
    case AUTO = 'auto';
}