<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\Mcp\McpEvent;
use Zolinga\System\Types\StatusEnum;

/**
 * Uncategorised helpers for the MCP gateway.
 *
 * Use sparingly — prefer a dedicated class (`McpInitializeHandler`,
 * `McpToolsListHandler`, etc.) when a helper carries real domain logic.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
final class McpHelper
{
    /**
     * Maximum permitted length of an MCP tool name.
     */
    public const TOOL_NAME_MAX_LENGTH = 64;

    /**
     * Maximum permitted length of a single JSON-RPC `id` field (string or
     * integer). Protects against log/response amplification when an attacker
     * sends a multi-kilobyte id and we echo it back in error responses.
     */
    public const REQUEST_ID_MAX_LENGTH = 64;

    /**
     * Maximum permitted length of a JSON-RPC `method` field (string).
     * Anything longer is rejected as `method not found` (without echoing
     * the value back) to keep error responses bounded.
     */
    public const METHOD_NAME_MAX_LENGTH = 256;

    /**
     * Maximum permitted length of the raw HTTP request body in bytes. The
     * gateway rejects anything larger with HTTP 413 before the body is
     * read into memory, preventing OOM and bandwidth-amplification DoS.
     */
    public const REQUEST_BODY_MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    /**
     * Maximum length (in characters) of a string echoed back in an error
     * message. Strings longer than this are truncated with an ellipsis.
     * Applied via {@see self::truncateForEcho()} to all attacker-controlled
     * values that land in error/log output.
     */
    public const ERROR_ECHO_MAX_LENGTH = 200;

    /**
     * Regex fragment for a single allowed character in an MCP tool name.
     * Includes ':' so that Zolinga event names (e.g. `my-module:search`)
     * can be used verbatim as MCP tool names. The `mcp:` prefix is
     * reserved for protocol events and rejected separately by
     * {@see self::isValidToolName()}.
     * Exposed so callers can build derived patterns if needed.
     */
    public const TOOL_NAME_CHAR_CLASS = '[A-Za-z0-9_:-]';

    private function __construct()
    {
    }

    /**
     * Truncate a string to a safe length for echoing back in an error
     * message or writing to the log. Strips control characters (including
     * NUL, CR, LF) and trims to {@see self::ERROR_ECHO_MAX_LENGTH} bytes.
     *
     * Applied to every attacker-controlled value that lands in user-facing
     * responses or the log: method name, id, params fields, etc. The
     * gateway must NEVER reflect untrusted input at full length, otherwise
     * a single 10 MB body becomes a 10 MB log entry / response.
     *
     * @param mixed $value
     * @return string
     */
    public static function truncateForEcho(mixed $value): string
    {
        if (!is_string($value)) {
            $value = is_scalar($value) || $value === null ? (string) $value : '<non-scalar>';
        }
        // Strip control characters (NUL, CR, LF, TAB, etc.) to keep log
        // lines single-line and prevent log-injection attacks.
        $clean = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '?', $value);
        if (strlen($clean) > self::ERROR_ECHO_MAX_LENGTH) {
            return substr($clean, 0, self::ERROR_ECHO_MAX_LENGTH) . '... [' . strlen($clean) . ' chars truncated]';
        }
        return $clean;
    }

    /**
     * Sanitize a free-form value for the log. Same rules as
     * {@see self::truncateForEcho()} but always produces a string.
     *
     * @param mixed $value
     * @return string
     */
    public static function sanitizeForLog(mixed $value): string
    {
        return self::truncateForEcho($value);
    }

    /**
     * Check whether a string is a valid MCP tool name.
     *
     * Tool names must be 1..{@see self::TOOL_NAME_MAX_LENGTH} characters long
     * and contain only ASCII letters, digits, underscore, hyphen and colon.
     * The colon is allowed so that Zolinga event names (e.g.
     * `my-module:search`) can be used verbatim as MCP tool names.
     *
     * Names starting with the `mcp:` prefix are rejected to prevent
     * collision with protocol events dispatched by the gateway
     * (e.g. `mcp:tools/list`).
     *
     * The same check is applied in two places:
     *
     * - {@see McpServer::dispatchOne()} rejects `tools/call` requests with
     *   an invalid `name` argument with a JSON-RPC `Invalid params` error.
     * - {@see McpToolsListHandler::collectTools()} skips listeners whose declared tool
     *   name fails this check (logging an error) so the bad tool is never
     *   advertised in `tools/list` and never callable in practice.
     *
     * @param mixed $name
     * @return bool
     */
    public static function isValidToolName(mixed $name): bool
    {
        if (!is_string($name) || $name === '') {
            return false;
        }
        if (strlen($name) > self::TOOL_NAME_MAX_LENGTH) {
            return false;
        }
        if (str_starts_with($name, 'mcp:')) {
            return false;
        }
        return (bool) preg_match(
            '/^' . self::TOOL_NAME_CHAR_CLASS . '+$/',
            $name
        );
    }

    /**
     * Map a Zolinga {@see StatusEnum} to a JSON-RPC 2.0 error code enum.
     *
     * Thin wrapper around {@see McpStatusEnum::fromStatus()} kept here so
     * callers can use a stable "McpHelper" surface without importing the
     * enum directly.
     *
     * @param StatusEnum $status
     * @return McpStatusEnum
     */
    public static function errorCodeFromStatus(StatusEnum $status): McpStatusEnum
    {
        return McpStatusEnum::fromStatus($status);
    }

    /**
     * Recursively convert `ArrayObject` instances to plain arrays so the
     * `json_encode()` output contains plain JSON objects/arrays.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function normalizeResponse(mixed $value): mixed
    {
        if ($value instanceof \ArrayObject) {
            $value = $value->getArrayCopy();
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::normalizeResponse($v);
            }
            return $out;
        }
        return $value;
    }

    /**
     * Build the response `data` payload for a status-derived JSON-RPC error.
     *
     * @param StatusEnum $status
     * @return array{status: int, statusName: string}
     */
    public static function statusData(StatusEnum $status): array
    {
        return [
            'status' => $status->value,
            'statusName' => $status->name,
        ];
    }

    /**
     * Build the MCP `tools/call` result envelope for an {@see McpEvent}.
     *
     * The shape is:
     *
     * - `isError` — `true` when the event status is non-OK
     *   (or `UNDETERMINED`, meaning no listener handled the event).
     * - `content` — a single text block carrying `json_encode($event->response)`
     *   on success, or `$event->message` on error (the spec fallback for
     *   clients that only read `content[0].text`).
     * - `structuredContent` — the event's raw `$response` payload, normalized
     *   through {@see McpHelper::normalizeResponse()}. Omitted when the
     *   normalized response is an empty array AND the call is in error
     *   (spec allows it to be optional).
     *
     * @param McpEvent $event
     * @return array{content: list<array<string, mixed>>, isError: bool, structuredContent?: mixed}
     */
    public static function envelope(McpEvent $event): array
    {
        $isError = !$event->isOk();
        $structured = self::normalizeResponse($event->response);

        $text = $isError
            ? ($event->message ?: 'Tool returned an error.')
            : (is_string($structured) ? $structured : json_encode(
                $structured,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
        $content = [['type' => 'text', 'text' => $text]];

        $envelope = [
            'content' => $content,
            'isError' => $isError,
        ];
        if (!$isError || (is_array($structured) ? $structured !== [] : true)) {
            $envelope['structuredContent'] = $structured;
        }
        return $envelope;
    }
}
