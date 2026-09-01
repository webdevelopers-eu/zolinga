<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp\Resources;

use ArrayObject;
use ArrayAccess;
use InvalidArgumentException;
use Zolinga\System\Events\Mcp\AbstractListEvent;

/**
 * MCP `resources/list` event.
 *
 * Dispatched by the MCP gateway when a client sends a `resources/list`
 * JSON-RPC request. Handlers populate the response with resource descriptors
 * via {@see addFromMeta()} or the convenience wrapper {@see add()}.
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/server/resources
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
class ListEvent extends AbstractListEvent
{
    /**
     * Resources allow http and https in addition to mcp-* schemes.
     *
     * @var array<int, string>
     */
    public const ALLOWED_URI_SCHEMES = ['mcp-*', 'http', 'https'];

    /**
     * Constructor.
     *
     * @param string|int|null $jsonrpcId The JSON-RPC request id.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request The JSON-RPC params.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $response The JSON-RPC result.
     */
    public function __construct(
        string|int|null $jsonrpcId = null,
        ArrayAccess|array $request = new ArrayObject,
        ArrayAccess|array $response = new ArrayObject
    ) {
        parent::__construct('mcp:resources/list', $jsonrpcId, $request, $response);
    }

    /**
     * Append a resource descriptor to the `resources/list` response.
     *
     * Validates the resource via {@see validateItem()} before appending.
     * Extra keys (title, description, mimeType, icons, etc.) are passed
     * through for future-proofing.
     *
     * @param array<string, mixed> $meta Resource descriptor.
     * @return void
     * @throws InvalidArgumentException Missing required fields or disallowed URI scheme.
     */
    public function addFromMeta(array $meta): void
    {
        $this->validateItem($meta);

        $this->appendItem('resources', $meta);
    }

    /**
     * Convenience wrapper for {@see addFromMeta()}.
     *
     * @param string $uri Resource URI (must use an allowed scheme).
     * @param string $name Unique resource identifier.
     * @param string $title Human-readable title (optional).
     * @param string $description One-line description (optional).
     * @param string $mimeType MIME type; determines text vs blob response (optional).
     * @param array<int, array<string, mixed>> $icons Icon descriptors (optional).
     * @return void
     * @throws InvalidArgumentException Via {@see addFromMeta()}.
     */
    public function add(
        string $uri,
        string $name,
        string $title = '',
        string $description = '',
        string $mimeType = '',
        array $icons = []
    ): void {
        $resource = ['uri' => $uri, 'name' => $name];
        if ($title !== '') {
            $resource['title'] = $title;
        }
        if ($description !== '') {
            $resource['description'] = $description;
        }
        if ($mimeType !== '') {
            $resource['mimeType'] = $mimeType;
        }
        if ($icons !== []) {
            $resource['icons'] = $icons;
        }
        $this->addFromMeta($resource);
    }

/**
     * Validate a single resource descriptor.
     *
     * Requires `uri` (non-empty string with an allowed scheme) and `name`
     * (non-empty string). Throws on any violation.
     *
     * @param array<string, mixed> $item Resource descriptor.
     * @return void
     * @throws InvalidArgumentException Missing required fields or disallowed URI scheme.
     */
    private function validateItem(array $item): void
    {
        $uri = $item['uri'] ?? null;
        if (!is_string($uri) || $uri === '') {
            throw new InvalidArgumentException('Resource "uri" must be a non-empty string.');
        }

        if (!$this->isAllowedScheme(parse_url($uri, PHP_URL_SCHEME) ?? '*missing-scheme*')) {
            throw new InvalidArgumentException(
                'Resource "uri" scheme is not allowed. Allowed schemes: '
                . implode(', ', static::ALLOWED_URI_SCHEMES)
                . '. Consider using a custom "mcp-<name>" scheme to avoid leaking internal paths.'
            );
        }

        $name = $item['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new InvalidArgumentException('Resource "name" must be a non-empty string.');
        }
    }
}
