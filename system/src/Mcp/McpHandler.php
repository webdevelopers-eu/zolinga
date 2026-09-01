<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\ListenerInterface;

/**
 * Shared base class for MCP listeners/handlers.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-08-29
 */
abstract class McpHandler implements ListenerInterface
{
    protected function isAuthorizedMeta(array $json): bool
    {
        global $api;

        if (is_array($json['zolinga'] ?? null) && isset($json['zolinga']['right'])) {
            if (!$api->isAuthorized($json['zolinga']['right'])) {
                return false;
            }
        }

        return true;
    }

}