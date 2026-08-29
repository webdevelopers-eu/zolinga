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
     * Answers question if user can see given object based on tenants.
     * 
     * Is user's tenant matching or under any of object's tenants?
     * 
     * The assumption is that user can see objects in parent's tenants, but not in sibling or child tenants. 
     * 
     * E.g.
     * isMatchingTenant(['a/b/c'], 'a/b/c') => true
     * isMatchingTenant(['a/b/c'], 'a/b') => false
     * isMatchingTenant(['a/b/c'], 'a/b/c/d') => true
     * 
     * @param array<string>|null $objectTenants tenants that the object is defined for, at least one of them must match or be a parent of the user's tenant
     * @param string $userTenant the tenant the user is currently in, or '' for the root tenant
     * @return bool
     */
    protected function isMatchingTenant(null|array $objectTenants, string $userTenant): bool
    {
        $objectTenants = $objectTenants ?? [''];

        foreach ($objectTenants as $objectTenant) {
            if (str_starts_with($userTenant, $objectTenant)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assert that the descriptor is visible for the event tenant.
     *
     * @param array<string>|null $objectTenants tenants that the object is defined for
     * @param string $userTenant the tenant the user is currently in, or '' for the root tenant
     * @return void
     * @throws McpTenantException Tenant is not allowed for this descriptor.
     */
    protected function assertTenant(null|array $objectTenants, string $userTenant): void
    {   
        if ($this->isMatchingTenant($objectTenants, $userTenant)) {
            return;
        }

        throw new McpTenantException('Forbidden for this tenant.');
    }
}