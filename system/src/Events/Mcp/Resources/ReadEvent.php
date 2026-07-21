<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp\Resources;

use ArrayObject;
use ArrayAccess;
use InvalidArgumentException;

/**
 * MCP `resources/read` event.
 *
 * Dispatched by the MCP gateway when a client sends a `resources/read`
 * JSON-RPC request. The event type is `mcp:resources/read:<scheme>` where
 * `<scheme>` is the URI scheme of the requested resource (e.g.
 * `mcp:resources/read:mcp-system` for `mcp-system:static:...` URIs).
 *
 * Handlers read the resource file and populate `$event->response` with
 * either `{ uri, mimeType, text }` for text resources or
 * `{ uri, mimeType, blob }` for binary resources (base64-encoded).
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/server/resources
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
class ReadEvent extends ResourcesEvent
{
    /**
     * Constructor.
     *
     * Extracts the URI scheme from the `uri` request parameter and appends
     * it to the event type so that handlers can register for specific schemes
     * (e.g. `mcp:resources/read:mcp-system`).
     *
     * @param string|int|null $jsonrpcId The JSON-RPC request id.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request The JSON-RPC params (contains `uri`).
     * @param ArrayAccess<string, mixed>|array<string, mixed> $response The JSON-RPC result.
     */
    public function __construct(
        string|int|null $jsonrpcId = null,
        ArrayAccess|array $request = new ArrayObject,
        ArrayAccess|array $response = new ArrayObject
    ) {
        $uri = $request['uri'] ?? '';
        $scheme = is_string($uri) ? (string) parse_url($uri, PHP_URL_SCHEME) : '';
        $type = 'mcp:resources/read' . ($scheme !== '' ? ':' . $scheme : '');

        if ($this->isAllowedScheme($scheme) === false) {
            throw new InvalidArgumentException(
                'Resource read request URI "' . $uri . '" uses a disallowed scheme ' . json_encode($scheme) . '. '
            );
        }

        parent::__construct($type, $jsonrpcId, $request, $response);
    }

    /**
     * Validate that the response URI uses an allowed scheme.
     *
     * Checks the `uri` field in the response contents array to ensure
     * it does not leak internal paths via disallowed URI schemes.
     *
     * @return void
     * @throws InvalidArgumentException If the response URI uses a disallowed scheme.
     */
    public function validateResponse(): void
    {
        $contents = $this->response['contents'] ?? [];
        if (!is_array($contents)) {
            return;
        }
        foreach ($contents as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $uri = $entry['uri'] ?? '';
            if (is_string($uri) && $uri !== '' && !$this->isAllowedScheme(parse_url($uri, PHP_URL_SCHEME) ?? '*missing-scheme*')) {
                throw new InvalidArgumentException(
                    'Resource read response URI "' . $uri . '" uses a disallowed scheme. '
                    . 'Allowed schemes: ' . implode(', ', self::ALLOWED_URI_SCHEMES) . '.'
                );
            }
        }
    }
}
