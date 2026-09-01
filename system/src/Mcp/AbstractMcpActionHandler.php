<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\Mcp\AbstractActionEvent;
use Zolinga\System\Types\StatusEnum;

/**
 * Abstract base for MCP action handlers that resolve a
 * `mcp-system://<module>/<subdir>/<basename>` request to a `.meta.json` file.
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
        $mcpUri = $event->request[static::REQUEST_KEY] ?? null;
        if (!is_string($mcpUri) || $mcpUri === '') {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Missing or empty "' . static::REQUEST_KEY . '" parameter.');
            return;
        }

        $metaPath = $this->mcpUriToPath($mcpUri);
        if ($metaPath === null) {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Invalid ' . static::SUBDIR . ' identifier: ' . $mcpUri);
            return;
        }

        $this->doAction($event, $metaPath, $mcpUri);
    }

    /**
     * Parse a `mcp-system://<module>/<subdir>/<basename>` identifier into its parts.
     *
     * @param string $mcpUri The identifier to parse.
     * @return string|null Null if invalid.
     */
    private function mcpUriToPath(string $mcpUri): ?string
    {
        global $api;

        $parts = parse_url($mcpUri);
        if (
            $parts === false 
            || !isset($parts['scheme'], $parts['host'], $parts['path'])
            || $parts['scheme'] !== 'mcp-system'
            || $parts['host'] === ''
            || $parts['path'] === '') {
            return null;
        }

        $module = basename($parts['host']);
        $dirname = basename(dirname($parts['path']));
        $basename = basename($parts['path']);

        if ($dirname !== static::SUBDIR) {
            $api->log->error('system:mcp', "MCP " . static::SUBDIR . " request '$mcpUri' has invalid subdirectory: $dirname, expected: " . static::SUBDIR);
            return null;
        }

        $zUri = 'module://' . $module . '/mcp/' . static::SUBDIR . '/' . $basename . '.meta.json';
        $path = $api->fs->toPath($zUri);
        if (!$path || !is_file($path)) {
            $api->log->error('system:mcp', "MCP " . static::SUBDIR . " request '$mcpUri' resolved to non-existent file: $zUri");
            return null;
        }

        return $path;
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
     * Resolve and load the `.meta.json` file for the given module + basename.
     *
     * On failure, sets the event status and logs the error (including
     * `$metaPath`). Descendants should call `parent::doAction()` first;
     * if it returns null, they must return early.
     *
     * @param AbstractActionEvent $event The action event to populate on failure.
     * @param string $metaPath Absolute path to the `.meta.json` file.
     * @return array<string, mixed>|null The decoded `.meta.json`, or null on failure.
     */
    protected function doAction(AbstractActionEvent $event, string $metaPath, string $mcpUri): ?array
    {
        global $api;

        $meta = $this->loadMeta($metaPath);
        if ($meta === null) {
            $api->log->error('system:mcp', "MCP " . static::SUBDIR . " request '$metaPath' has invalid JSON in file: $metaPath");
            $event->setStatus(StatusEnum::ERROR, ucfirst(static::SUBDIR) . ' definition is invalid: ' . $metaPath);
            return null;
        }

        if (!$this->isAuthorizedMeta($meta)) {
            $api->log->info('system:mcp', "MCP " . static::SUBDIR . " request '$mcpUri' denied: insufficient rights");
            $event->setStatus(StatusEnum::FORBIDDEN, ucfirst(static::SUBDIR) . ' not accessible: ' . $mcpUri);
            return null;
        }

        return $meta;
    }
}