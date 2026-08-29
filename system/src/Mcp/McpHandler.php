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
     * Normalize the optional tenant filter for a static MCP descriptor.
     *
     * Missing or invalid `tenants` metadata falls back to the default
     * non-tenant route. Non-string entries are ignored.
     *
     * @param array<string, mixed> $meta
     * @return array<int, string>
     */
    protected function extractTenants(array $meta): array
    {
        $rawTenants = $meta['tenants'] ?? [''];
        if (!is_array($rawTenants)) {
            return [''];
        }

        $tenants = [];
        foreach ($rawTenants as $tenant) {
            if (is_string($tenant)) {
                $tenants[] = $tenant;
            }
        }

        return $tenants;
    }

    /**
     * Whether the descriptor is visible for the event tenant.
     *
     * @param array<string, mixed> $meta
     * @param McpEvent $event
     * @return bool
     */
    protected function isMatchingTenant(array $meta, McpEvent $event): bool
    {
        return in_array($event->tenant, $this->extractTenants($meta), true);
    }

    /**
     * Assert that the descriptor is visible for the event tenant.
     *
     * @param array<string, mixed> $meta
     * @param McpEvent $event
     * @return void
     * @throws McpTenantException Tenant is not allowed for this descriptor.
     */
    protected function assertTenant(array $meta, McpEvent $event): void
    {
        if ($this->isMatchingTenant($meta, $event)) {
            return;
        }

        throw new McpTenantException('Forbidden for this tenant.', $event->jsonrpcId);
    }
}