<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\{ListenerInterface};
use Zolinga\System\Events\Mcp\Prompts\ListEvent;
use Zolinga\System\Types\StatusEnum;

/**
 * Handles MCP `prompts/list` requests.
 *
 * Discovers module-provided prompts by scanning each module's
 * `mcp/prompts/*.meta.json` files. Rewrites the `name` field to the
 * external `mcp-system:<module>:<basename>` scheme so that internal
 * Zolinga paths are never leaked to MCP clients.
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/server/prompts
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-22
 */
class McpPromptsListHandler implements ListenerInterface
{
    /**
     * Handle the `prompts/list` event.
     *
     * @param ListEvent $event The prompts/list event.
     * @return void
     */
    public function onList(ListEvent $event): void
    {
        $this->collectPrompts($event);
        $this->sortPrompts($event);
        $event->setStatus(StatusEnum::OK, 'OK');
    }

    /**
     * Scan all modules for `.meta.json` prompt descriptors.
     *
     * @param ListEvent $event
     * @return void
     */
    private function collectPrompts(ListEvent $event): void
    {
        global $api;

        foreach ($api->manifest->moduleNames as $module) {
            $dir = $api->fs->toPath("module://$module/mcp/prompts");
            if (!$dir || !is_dir($dir)) {
                continue;
            }

            foreach (glob($dir . '/*.meta.json') as $metaFile) {
                $this->processMetaFile($event, $module, $metaFile);
            }
        }
    }

    /**
     * Read and register a single `.meta.json` prompt descriptor.
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
            $api->log->warning('system:mcp', "MCP prompt meta file is not valid JSON: $metaFile");
            return;
        }

        $basename = basename($metaFile, '.meta.json');
        $uri = "mcp-system:$module:$basename";

        // Warn if name field doesn't match filename (it's ignored for static prompts).
        if (isset($json['name'])) {
            $api->log->warning('system:mcp', "MCP prompt has name '{$json['name']}'. The filename URI is used as the prompt name; your 'name' field will be set to '$uri'.");
        }

        $json['name'] = $uri;

        try {
            $event->addPromptJson($json);
        } catch (\InvalidArgumentException $e) {
            $api->log->warning('system:mcp', 'Skipping MCP prompt from ' . $metaFile . ': ' . $e->getMessage());
        }
    }

    /**
     * Sort prompts by name for deterministic output.
     *
     * @param ListEvent $event
     * @return void
     */
    private function sortPrompts(ListEvent $event): void
    {
        $prompts = $event->response['prompts'] ?? [];
        if (is_array($prompts)) {
            usort($prompts, fn(array $a, array $b): int => strcmp($a['name'] ?? '', $b['name'] ?? ''));
            $event->response['prompts'] = $prompts;
        }
    }
}
