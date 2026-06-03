<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Mcp\Exceptions\McpInvalidRequestException;

/**
 * Stateless validator for JSON-RPC 2.0 request envelopes.
 *
 * Use {@see McpRequestValidator::requireRequest()} as the public entry point;
 * it normalises the parsed JSON value, validates each field, and returns a
 * clean envelope (with the validated `id` and `params` defaults applied) or
 * throws {@see McpInvalidRequestException}.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
final class McpRequestValidator
{
    /**
     * Validate a parsed JSON-RPC 2.0 request envelope.
     *
     * @param mixed $data The decoded JSON value (single object or batch item).
     * @return array{0: string, 1: string|int|null, 2: array<string, mixed>} A tuple
     *         `[method, id, params]` with the validated fields.
     * @throws McpInvalidRequestException
     */
    public static function requireRequest(mixed $data): array
    {
        if (!is_array($data)) {
            throw new McpInvalidRequestException('Request must be a JSON object.');
        }
        if (($data['jsonrpc'] ?? null) !== '2.0') {
            throw new McpInvalidRequestException('Missing or invalid "jsonrpc" field; must be "2.0".');
        }

        $method = $data['method'] ?? null;
        if (!is_string($method) || $method === '') {
            throw new McpInvalidRequestException('Missing or invalid "method" field; must be a non-empty string.');
        }

        $id = self::validateId($data['id'] ?? null);
        $params = self::validateParams($data['params'] ?? []);

        return [$method, $id, $params];
    }

    /**
     * Normalise and validate a JSON-RPC `id` field. Returns `null` for both
     * missing keys and explicit `null` values (notifications).
     *
     * String ids longer than {@see McpHelper::REQUEST_ID_MAX_LENGTH} are
     * rejected as invalid (capped before the value can be echoed back in
     * error responses or written to the log). The returned id is also
     * capped so the caller cannot accidentally re-reflect an oversized
     * value supplied via a non-strict path.
     *
     * @param mixed $id
     * @return string|int|null
     * @throws McpInvalidRequestException
     */
    private static function validateId(mixed $id): string|int|null
    {
        if ($id === null) {
            return null;
        }
        if (is_int($id)) {
            return $id;
        }
        if (is_string($id)) {
            if ($id === '') {
                // Empty string is technically allowed by the spec, but it
                // is pointless and a common probe value — treat as null.
                return null;
            }
            if (strlen($id) > McpHelper::REQUEST_ID_MAX_LENGTH) {
                // Don't echo the offending value back; a generic message
                // is enough and prevents log/response amplification.
                throw new McpInvalidRequestException(
                    'Invalid "id" field; string must be at most ' . McpHelper::REQUEST_ID_MAX_LENGTH . ' chars.'
                );
            }
            return $id;
        }
        throw new McpInvalidRequestException('Invalid "id" field; must be a string, integer or null.');
    }

    /**
     * Normalise the JSON-RPC `params` field. Defaults to an empty object
     * (`[]`) when missing.
     *
     * @param mixed $params
     * @return array<string, mixed>
     * @throws McpInvalidRequestException
     */
    private static function validateParams(mixed $params): array
    {
        if ($params === []) {
            return [];
        }
        if (!is_array($params)) {
            throw new McpInvalidRequestException('Invalid "params" field; must be an object or array.');
        }
        return $params;
    }
}
