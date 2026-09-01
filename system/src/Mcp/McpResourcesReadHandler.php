<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\Mcp\AbstractActionEvent;
use Zolinga\System\Events\Mcp\Resources\ReadEvent;
use Zolinga\System\Types\StatusEnum;

/**
 * Handles MCP `resources/read` requests for the `mcp-system` URI scheme.
 *
 * Parses the `mcp-system://<module>/<subdir>/<basename>` URI, resolves the
 * corresponding `.meta.json` descriptor, reads the actual content file,
 * and returns it as either `text` or `blob` (base64-encoded) based on
 * the MIME type declared in the `.meta.json`.
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/server/resources
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-22
 */
class McpResourcesReadHandler extends AbstractMcpActionHandler
{
    protected const SUBDIR = 'resources';
    protected const REQUEST_KEY = 'uri';

    /**
     * Read the resource file and populate the event response.
     *
     * @param AbstractActionEvent $event
     * @param string $metaPath Absolute path to the `.meta.json` file.
     * @param string $mcpUri The original MCP URI from the request.
     * @return array<string, mixed>|null Resolved resource descriptor, or null on failure. The event response is populated on success.
     */
    protected function doAction(AbstractActionEvent $event, string $metaPath, string $mcpUri): ?array
    {
        assert($event instanceof ReadEvent);
        global $api;

        $api->log->info('mcp:system', "Reading resource: $metaPath");

        $meta = parent::doAction($event, $metaPath, $mcpUri);
        if ($meta === null) {
            return null;
        }

        if (!isset($meta['uri'])) {
            $api->log->info('mcp:system', "Resource descriptor invalid: missing 'uri' field: $mcpUri");
            $event->setStatus(StatusEnum::NOT_FOUND, 'Resource descriptor missing or invalid.');
            return null;
        }

        $contentPath = $api->fs->toPath($meta['uri']);
        if (!$contentPath || !is_file($contentPath)) {
            $api->log->info('mcp:system', "Resource content file not found: {$meta['uri']} ($mcpUri)");
            $event->setStatus(StatusEnum::NOT_FOUND, 'Resource content file not found.');
            return null;
        }

        $contents = file_get_contents($contentPath);
        if ($contents === false) {
            $api->log->info('mcp:system', "Failed to read resource content: {$meta['uri']} ($mcpUri)");
            $event->setStatus(StatusEnum::ERROR, 'Failed to read resource content: ' . $mcpUri);
            return null;
        }

        $mimeType = $meta['mimeType'] ?? 'application/octet-stream';
        $this->buildResponse($event, $mcpUri, $mimeType, $contents);
        $api->log->info('mcp:system', "Resource served: $metaPath ($mimeType, " . strlen($contents) . " bytes) ($mcpUri)");
        $event->setStatus(StatusEnum::OK, 'OK');
        return null;
    }

    /**
     * Build the response payload: text for text/ MIME types, blob otherwise.
     *
     * @param ReadEvent $event
     * @param string $uri
     * @param string $mimeType
     * @param string $contents
     * @return void
     */
    private function buildResponse(ReadEvent $event, string $uri, string $mimeType, string $contents): void
    {
        $entry = ['uri' => $uri, 'mimeType' => $mimeType];

        if (str_starts_with($mimeType, 'text/')) {
            $entry['text'] = $contents;
        } else {
            $entry['blob'] = base64_encode($contents);
        }

        $event->response = ['contents' => [$entry]];
    }
}
