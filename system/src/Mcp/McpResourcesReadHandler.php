<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\Mcp\AbstractActionEvent;
use Zolinga\System\Events\Mcp\Resources\ReadEvent;
use Zolinga\System\Types\StatusEnum;

/**
 * Handles MCP `resources/read` requests for the `mcp-system` URI scheme.
 *
 * Parses the `mcp-system:<module>:<basename>` URI, resolves the
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
     * @param string $module
     * @param string $basename
     * @param string $requestUri The original URI from the request.
     * @return void
     */
    protected function doAction(AbstractActionEvent $event, string $module, string $basename, string $requestUri): void
    {
        assert($event instanceof ReadEvent);
        global $api;

        $api->log->info('mcp:system', "Reading resource: $requestUri");

        $metaPath = $this->resolveMetaPath($module, $basename);
        if ($metaPath === null) {
            $api->log->info('mcp:system', "Resource meta not found: $module/$basename");
            $event->setStatus(StatusEnum::NOT_FOUND, 'Resource not found: ' . $requestUri);
            return;
        }

        $meta = $this->loadMeta($metaPath);
        if ($meta === null || !isset($meta['uri'])) {
            $api->log->info('mcp:system', "Resource descriptor invalid: $requestUri");
            $event->setStatus(StatusEnum::NOT_FOUND, 'Resource descriptor missing or invalid: ' . $requestUri);
            return;
        }

        $contentPath = $api->fs->toPath($meta['uri']);
        if (!$contentPath || !is_file($contentPath)) {
            $api->log->info('mcp:system', "Resource content file not found: {$meta['uri']}");
            $event->setStatus(StatusEnum::NOT_FOUND, 'Resource content file not found: ' . $requestUri);
            return;
        }

        $contents = file_get_contents($contentPath);
        if ($contents === false) {
            $api->log->info('mcp:system', "Failed to read resource content: $requestUri");
            $event->setStatus(StatusEnum::ERROR, 'Failed to read resource content: ' . $requestUri);
            return;
        }

        $mimeType = $meta['mimeType'] ?? 'application/octet-stream';
        $this->buildResponse($event, $requestUri, $mimeType, $contents);
        $api->log->info('mcp:system', "Resource served: $requestUri ($mimeType, " . strlen($contents) . " bytes)");
        $event->setStatus(StatusEnum::OK, 'OK');
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
