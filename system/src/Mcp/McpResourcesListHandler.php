<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\{ListenerInterface};
use Zolinga\System\Events\Mcp\Resources\ListEvent;
use Zolinga\System\Types\StatusEnum;

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
class McpResourcesListHandler implements ListenerInterface
{
    /**
     * Handle the `resources/list` event.
     *
     * @param ListEvent $event The resources/list event.
     * @return void
     */
    public function onList(ListEvent $event): void
    {
        $this->collectResources($event);
        $this->sortResources($event);
        $event->setStatus(StatusEnum::OK, 'OK');
    }

    /**
     * Scan all modules for `.meta.json` resource descriptors.
     *
     * @param ListEvent $event
     * @return void
     */
    private function collectResources(ListEvent $event): void
    {
        global $api;

        foreach ($api->manifest->moduleNames as $module) {
            $dir = $api->fs->toPath("module://$module/mcp/resources");
            if (!$dir || !is_dir($dir)) {
                continue;
            }

            foreach (glob($dir . '/*.meta.json') as $metaFile) {
                $this->processMetaFile($event, $module, $metaFile);
            }
        }
    }

    /**
     * Read and register a single `.meta.json` resource descriptor.
     *
     * @param ListEvent $event
     * @param string $module
     * @param string $metaFile Absolute path to the .meta.json file.
     * @return void
     */
    private function processMetaFile(ListEvent $event, string $module, string $metaFile): void
    {
        global $api;

        $json = json_decode((string) file_get_contents($metaFile), true);
        if (!is_array($json)) {
            $api->log->warning('system:mcp', "MCP resource meta file is not valid JSON: $metaFile");
            return;
        }

        $basename = basename($metaFile, '.meta.json');
        $json['uri'] = "mcp-system:$module:$basename";

        if (!isset($json['name']) || !is_string($json['name']) || $json['name'] === '') {
            $api->log->warning('system:mcp', "MCP resource meta file missing 'name' field: $metaFile");
            return;
        }

        try {
            $event->addResourceJson($json);
        } catch (\InvalidArgumentException $e) {
            $api->log->warning('system:mcp', 'Skipping MCP resource from ' . $metaFile . ': ' . $e->getMessage());
        }
    }

    /**
     * Sort resources by URI for deterministic output.
     *
     * @param ListEvent $event
     * @return void
     */
    private function sortResources(ListEvent $event): void
    {
        $resources = $event->response['resources'] ?? [];
        if (is_array($resources)) {
            usort($resources, fn (array $a, array $b): int => strcmp($a['uri'] ?? '', $b['uri'] ?? ''));
            $event->response['resources'] = $resources;
        }
    }
}
