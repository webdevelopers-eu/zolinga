<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\{ListenerInterface};
use Zolinga\System\Events\Mcp\Resources\ReadEvent;
use Zolinga\System\Types\StatusEnum;

/**
 * Handles MCP `resources/read` requests for the `mcp-system` URI scheme.
 *
 * Parses the `mcp-system:static:<module>:<basename>` URI, resolves the
 * corresponding `.meta.json` descriptor, reads the actual content file,
 * and returns it as either `text` or `blob` (base64-encoded) based on
 * the MIME type declared in the `.meta.json`.
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/server/resources
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-22
 */
class McpResourcesReadHandler implements ListenerInterface
{
    /**
     * Handle the `resources/read:mcp-system` event.
     *
     * @param ReadEvent $event The resources/read event.
     * @return void
     */
    public function onRead(ReadEvent $event): void
    {
        $uri = $event->request['uri'] ?? null;
        if (!is_string($uri) || $uri === '') {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Missing or empty "uri" parameter.');
            return;
        }

        $parts = $this->parseUri($uri);
        if ($parts === null) {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Invalid resource URI: ' . $uri);
            return;
        }

        $this->readResource($event, $parts['module'], $parts['basename'], $uri);
        $event->setStatus(StatusEnum::OK, 'OK');
    }

    /**
     * Parse a `mcp-system:static:<module>:<basename>` URI into its components.
     *
     * @param string $uri
     * @return array{module: string, basename: string}|null
     */
    private function parseUri(string $uri): ?array
    {
        $parts = explode(':', $uri, 4);
        if (count($parts) < 4) {
            return null;
        }
        if ($parts[0] !== 'mcp-system' || $parts[1] !== 'static') {
            return null;
        }

        $module = basename($parts[2]);
        $basename = basename($parts[3]);

        // Directory traversal protection: basename must match the raw value.
        if ($module !== $parts[2] || $basename !== $parts[3]) {
            return null;
        }

        return ['module' => $module, 'basename' => $basename];
    }

    /**
     * Read the resource file and populate the event response.
     *
     * @param ReadEvent $event
     * @param string $module
     * @param string $basename
     * @param string $requestUri The original URI from the request.
     * @return void
     */
    private function readResource(ReadEvent $event, string $module, string $basename, string $requestUri): void
    {
        global $api;

        $api->log->info('mcp:system', "Reading resource: $requestUri");

        $basename = basename($basename); // Just to be sure.
        $metaUri = "module://$module/mcp/resources/$basename.meta.json";
        $metaPath = $api->fs->toPath($metaUri);
        if (!$metaPath || !is_file($metaPath)) {
            $api->log->info('mcp:system', "Resource meta not found: $metaUri");
            $event->setStatus(StatusEnum::NOT_FOUND, 'Resource not found: ' . $requestUri);
            return;
        }

        $meta = json_decode((string) file_get_contents($metaPath), true);
        if (!is_array($meta) || !isset($meta['uri'])) {
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
     * Build the response payload: text for text/* MIME types, blob otherwise.
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
