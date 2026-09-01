<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\Mcp\AbstractListEvent;
use Zolinga\System\Types\StatusEnum;

/**
 * Abstract base for MCP list handlers that discover items from
 * `mcp/<type>/*.meta.json` files across all modules.
 *
 * Provides the shared scan-and-collect lifecycle: iterate modules, glob
 * meta files, validate JSON, check authorization, rewrite the identifier
 * to the `mcp-system:<module>:<basename>` scheme, and append via
 * {@see AbstractListEvent::addFromMeta()}. Subclasses declare the
 * subdirectory, response key, and identifier field name.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-09-01
 */
abstract class AbstractMcpListHandler extends McpHandler
{
    /**
     * The subdirectory under each module's `mcp/` folder (e.g. `prompts`, `resources`).
     *
     * @var string
     */
    protected const SUBDIR = '';

    /**
     * The response key holding the list (e.g. `prompts`, `resources`).
     *
     * @var string
     */
    protected const RESPONSE_KEY = '';

    /**
     * The meta field to rewrite with the `mcp-system:<module>:<basename>` URI
     * (e.g. `name` for prompts, `uri` for resources).
     *
     * @var string
     */
    protected const ID_FIELD = '';

    /**
     * Entry point: collect items from meta files, sort, and set OK status.
     *
     * @param AbstractListEvent $event The list event to populate.
     * @return void
     */
    public function onList(AbstractListEvent $event): void
    {
        $this->collect($event);
        $this->sort($event);
        $event->setStatus(StatusEnum::OK, 'OK');
    }

    /**
     * Scan all modules for meta files and append valid items to the event.
     *
     * @param AbstractListEvent $event The list event to populate.
     * @return void
     */
    private function collect(AbstractListEvent $event): void
    {
        global $api;

        foreach ($api->manifest->moduleNames as $module) {
            $dir = $api->fs->toPath("module://$module/mcp/" . static::SUBDIR);
            if (!$dir || !is_dir($dir)) {
                continue;
            }

            foreach (glob($dir . '/*.meta.json') as $metaFile) {
                $this->processMetaFile($event, $module, $metaFile);
            }
        }
    }

    /**
     * Read, validate, authorize, and append a single meta file's descriptor.
     *
     * @param AbstractListEvent $event The list event to populate.
     * @param string $module The module name.
     * @param string $metaFile Absolute path to the `.meta.json` file.
     * @return void
     */
    private function processMetaFile(AbstractListEvent $event, string $module, string $metaFile): void
    {
        global $api;

        $json = json_decode((string) file_get_contents($metaFile), true);
        if (!is_array($json)) {
            $api->log->warning('system:mcp', 'MCP ' . static::SUBDIR . ' meta file is not valid JSON: ' . $metaFile);
            return;
        }

        if (is_array($json['zolinga']) && isset($json['zolinga']['right'])) {
            if (!$api->isAuthorized($json['zolinga']['right'])) {
                return;
            }
        }

        $basename = basename($metaFile, '.meta.json');
        $uri = "mcp-system:$module:$basename";

        $json[static::ID_FIELD] = $uri;

        if (!$this->validateMeta($json, $metaFile)) {
            return;
        }

        try {
            $event->addFromMeta($json);
        } catch (\InvalidArgumentException $e) {
            $api->log->warning('system:mcp', 'Skipping MCP ' . static::SUBDIR . ' from ' . $metaFile . ': ' . $e->getMessage());
        }
    }

    /**
     * Validate the meta JSON after ID rewriting. Return false to skip.
     *
     * Base implementation checks that the `name` field exists and is non-empty
     * (required by resources). Override to relax or change the check.
     *
     * @param array<string, mixed> $json The meta JSON (already rewritten).
     * @param string $metaFile The source file path (for logging).
     * @return bool True to proceed, false to skip.
     */
    protected function validateMeta(array $json, string $metaFile): bool
    {
        global $api;

        if (!isset($json['name']) || !is_string($json['name']) || $json['name'] === '') {
            $api->log->warning('system:mcp', 'MCP ' . static::SUBDIR . ' meta file missing "name" field: ' . $metaFile);
            return false;
        }

        return true;
    }

    /**
     * Sort the collected items by their ID field for deterministic output.
     *
     * @param AbstractListEvent $event The list event whose response to sort.
     * @return void
     */
    private function sort(AbstractListEvent $event): void
    {
        $items = $event->response[static::RESPONSE_KEY] ?? [];
        if (is_array($items)) {
            usort($items, fn (array $a, array $b): int => strcmp($a[static::ID_FIELD] ?? '', $b[static::ID_FIELD] ?? ''));
            $event->response[static::RESPONSE_KEY] = $items;
        }
    }
}