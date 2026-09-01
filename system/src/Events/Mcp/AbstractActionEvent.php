<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp;

use ArrayAccess;
use Zolinga\System\Mcp\Exceptions\McpInvalidRequestException;

/**
 * Abstract base for MCP action events: prompts/get, resources/read, tools/call.
 *
 * Provides the shared scheme-extraction helper {@see buildSchemeType()} used
 * by {@see Prompts\GetEvent} and {@see Resources\ReadEvent} to build
 * scheme-qualified event types (e.g. `mcp:prompts/get:mcp-system`).
 * {@see Tools\CallEvent} does not use scheme-qualified types and overrides
 * the constructor entirely.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-09-01
 */
abstract class AbstractActionEvent extends McpEvent
{
    /**
     * The request parameter key holding the URI/name (e.g. `name`, `uri`).
     *
     * @var string
     */
    protected const REQUEST_KEY = '';

    /**
     * The base event type prefix (e.g. `mcp:prompts/get`).
     *
     * @var string
     */
    protected const TYPE_PREFIX = '';

    /**
     * Human-readable label for error messages (e.g. `Prompt get`, `Resource read`).
     *
     * @var string
     */
    protected const ERROR_LABEL = '';

    /**
     * Extract the URI scheme from the request and build a scheme-qualified
     * event type.
     *
     * Reads the request parameter identified by {@see REQUEST_KEY}, extracts
     * its URI scheme, validates it against {@see ALLOWED_URI_SCHEMES}, and
     * returns the event type `{TYPE_PREFIX}:{scheme}`.
     *
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request The JSON-RPC params.
     * @return string The scheme-qualified event type.
     * @throws McpInvalidRequestException If the request value uses a disallowed or missing scheme.
     */
    protected function buildSchemeType(ArrayAccess|array $request): string
    {
        $value = $request[static::REQUEST_KEY] ?? '';
        $scheme = is_string($value) ? (string) parse_url($value, PHP_URL_SCHEME) : '';
        $type = static::TYPE_PREFIX . ($scheme !== '' ? ':' . $scheme : '');

        if ($this->isAllowedScheme($scheme) === false) {
            throw new McpInvalidRequestException(
                static::ERROR_LABEL . ' request "' . $value . '" uses a disallowed scheme ' . json_encode($scheme) . '.'
            );
        }

        return $type;
    }
}