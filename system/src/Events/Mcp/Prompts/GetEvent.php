<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp\Prompts;

use ArrayObject;
use ArrayAccess;
use Zolinga\System\Events\Mcp\AbstractActionEvent;
use Zolinga\System\Mcp\Exceptions\McpInvalidRequestException;

/**
 * MCP `prompts/get` event.
 *
 * Dispatched by the MCP gateway when a client sends a `prompts/get`
 * JSON-RPC request. The event type is `mcp:prompts/get:<scheme>` where
 * `<scheme>` is the URI scheme of the requested prompt name (e.g.
 * `mcp:prompts/get:mcp-system` for `mcp-system:...` names).
 *
 * Handlers read the prompt definition and populate `$event->response` with
 * `{ description?, messages: [...] }`.
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/server/prompts
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
class GetEvent extends AbstractActionEvent
{
    protected const REQUEST_KEY = 'name';
    protected const TYPE_PREFIX = 'mcp:prompts/get';
    protected const ERROR_LABEL = 'Prompt get';

    /**
     * Constructor.
     *
     * Extracts the URI scheme from the `name` request parameter and appends
     * it to the event type so that handlers can register for specific schemes
     * (e.g. `mcp:prompts/get:mcp-system`).
     *
     * @param string|int|null $jsonrpcId The JSON-RPC request id.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request The JSON-RPC params (contains `name`).
     * @param ArrayAccess<string, mixed>|array<string, mixed> $response The JSON-RPC result.
     * @throws McpInvalidRequestException If the name uses a disallowed or missing scheme.
     */
    public function __construct(
        string|int|null $jsonrpcId = null,
        ArrayAccess|array $request = new ArrayObject,
        ArrayAccess|array $response = new ArrayObject
    ) {
        $type = $this->buildSchemeType($request);
        parent::__construct($type, $jsonrpcId, $request, $response);
    }

    /**
     * Validate that embedded resource URIs in the response use allowed schemes.
     *
     * Checks `messages[N].content.resource.uri` (the MCP spec embedded resource
     * URI field) against the allowed scheme whitelist. Safety net for future
     * dynamic handlers that embed resources.
     *
     * @return void
     * @throws \InvalidArgumentException If an embedded resource URI uses a disallowed scheme.
     */
    public function validateResponse(): void
    {
        $messages = $this->response['messages'] ?? [];
        if (!is_array($messages)) {
            return;
        }
        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $content = $msg['content'] ?? [];
            if (!is_array($content)) {
                continue;
            }
            $resource = $content['resource'] ?? null;
            if (!is_array($resource)) {
                continue;
            }
            $uri = $resource['uri'] ?? '';
            if (is_string($uri) && $uri !== '' && !$this->isAllowedScheme(parse_url($uri, PHP_URL_SCHEME) ?? '*missing-scheme*')) {
                throw new \InvalidArgumentException(
                    'Prompt message embedded resource URI "' . $uri . '" uses a disallowed scheme. '
                    . 'Allowed schemes: ' . implode(', ', static::ALLOWED_URI_SCHEMES) . '.'
                );
            }
        }
    }
}
