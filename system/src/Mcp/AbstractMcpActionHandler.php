<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\Mcp\AbstractActionEvent;
use Zolinga\System\Types\StatusEnum;

/**
 * Abstract base for MCP action handlers that resolve a
 * `mcp-system:<module>:<basename>` request to a `.meta.json` file.
 *
 * Provides the shared parse-and-resolve lifecycle: extract the request
 * parameter, parse the `mcp-system` URI into module + basename, validate
 * the module exists, load the `.meta.json` file, and delegate to
 * {@see doAction()} for type-specific processing.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-09-01
 */
abstract class AbstractMcpActionHandler extends McpHandler
{
    /**
     * The subdirectory under each module's `mcp/` folder (e.g. `prompts`, `resources`).
     *
     * @var string
     */
    protected const SUBDIR = '';

    /**
     * The request parameter key holding the URI/name (e.g. `name`, `uri`).
     *
     * @var string
     */
    protected const REQUEST_KEY = '';

    /**
     * Entry point: validate the request, parse the identifier, and delegate.
     *
     * @param AbstractActionEvent $event The action event to handle.
     * @return void
     */
    public function onAction(AbstractActionEvent $event): void
    {
        $id = $event->request[static::REQUEST_KEY] ?? null;
        if (!is_string($id) || $id === '') {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Missing or empty "' . static::REQUEST_KEY . '" parameter.');
            return;
        }

        $parts = $this->parseId($id);
        if ($parts === null) {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Invalid ' . static::SUBDIR . ' identifier: ' . $id);
            return;
        }

        $this->doAction($event, $parts['module'], $parts['basename'], $id);
    }

    /**
     * Parse a `mcp-system:<module>:<basename>` identifier into its parts.
     *
     * @param string $id The identifier to parse.
     * @return array{module: string, basename: string}|null Null if invalid.
     */
    private function parseId(string $id): ?array
    {
        $parts = explode(':', $id, 3);
        if (count($parts) < 3 || $parts[0] !== 'mcp-system') {
            return null;
        }

        $module = basename($parts[1]);
        $basename = basename($parts[2]);

        if ($module !== $parts[1] || $basename !== $parts[2]) {
            return null;
        }

        return ['module' => $module, 'basename' => $basename];
    }

    /**
     * Resolve the `.meta.json` file path for a module + basename.
     *
     * @param string $module The module name.
     * @param string $basename The file basename (without `.meta.json`).
     * @return string|null The absolute path, or null if not found.
     */
    protected function resolveMetaPath(string $module, string $basename): ?string
    {
        global $api;

        if (!in_array($module, $api->manifest->moduleNames, true)) {
            return null;
        }

        $zPath = "module://$module/mcp/" . static::SUBDIR . "/$basename.meta.json";
        $realPath = $api->fs->toPath($zPath);
        if (!$realPath || !is_file($realPath)) {
            return null;
        }

        return $realPath;
    }

    /**
     * Load and decode a `.meta.json` file.
     *
     * @param string $metaPath Absolute path to the `.meta.json` file.
     * @return array<string, mixed>|null The decoded JSON, or null if invalid.
     */
    protected function loadMeta(string $metaPath): ?array
    {
        $meta = json_decode((string) file_get_contents($metaPath), true);
        if (!is_array($meta)) {
            return null;
        }

        return $meta;
    }

    /**
     * Perform the type-specific action after the identifier is parsed.
     *
     * @param AbstractActionEvent $event The action event to populate.
     * @param string $module The resolved module name.
     * @param string $basename The resolved file basename.
     * @param string $requestId The original request identifier (for logging).
     * @return void
     */
    abstract protected function doAction(AbstractActionEvent $event, string $module, string $basename, string $requestId): void;
}