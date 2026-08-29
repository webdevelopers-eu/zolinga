<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Events\Mcp\McpEvent;
use Zolinga\System\Mcp\Exceptions\McpTenantException;

/**
 * Shared base class for MCP listeners/handlers.
 *
 * Provides common helpers for static MCP metadata handling.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-08-29
 */
abstract class McpHandler implements ListenerInterface
{

    /**
     * Whether the descriptor is visible for the event tenant.
     *
     * @param array<string>|null $tenants
     * @param string $search
     * @return bool
     */
    protected function isMatchingTenant(null|array $tenants, string $search): bool
    {
        $tenants = $tenants ?? [''];
        $resolved = [];

        if ($search === '') {
            return true; // Global tenant always matches.
        }

        // We inherit, so if tenant is "a/b/c" then we also match "a/b" and "a" and "" (global).
        foreach ($tenants as $key => $value) {
            do {
                if ($search === $value) {
                    return true;
                }
                $value = dirname($value);
            } while (strlen($value));
        }

        return false;
    }

    /**
     * Assert that the descriptor is visible for the event tenant.
     *
     * @param array<string>|null $tenants
     * @param string $search
     * @return void
     * @throws McpTenantException Tenant is not allowed for this descriptor.
     */
    protected function assertTenant(null|array $tenants, string $search): void
    {   
        if ($this->isMatchingTenant($tenants, $search)) {
            return;
        }

        throw new McpTenantException('Forbidden for this tenant.');
    }
}