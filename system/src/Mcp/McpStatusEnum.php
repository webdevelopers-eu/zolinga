<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Types\StatusEnum;

/**
 * MCP protocol-level error codes (JSON-RPC 2.0 wire codes) used by the
 * gateway.
 *
 * The negative range is reserved by the JSON-RPC 2.0 spec
 * (https://www.jsonrpc.org/specification#error_object): `-32700` to `-32600`
 * are pre-defined, and `-32000` to `-32099` are reserved for
 * implementation-defined server errors.
 *
 * This enum is the MCP-side counterpart of {@see StatusEnum}. It is the
 * canonical state for the gateway — HTTP response codes and the
 * JSON-RPC `error.code` are both derived from it (see {@see self::toStatus()}
 * and {@see self::value} respectively).
 *
 * The reverse mapping ({@see self::fromStatus()}) is a heuristic used when
 * the gateway has only a {@see StatusEnum} to work with (e.g. a handler
 * reported `BAD_REQUEST` without specifying the protocol-level cause).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
enum McpStatusEnum: int
{
    /** Invalid JSON was received by the server. */
    case JSON_RPC_PARSE_ERROR = -32700;

    /** The JSON sent is not a valid Request object. */
    case JSON_RPC_INVALID_REQUEST = -32600;

    /** The method does not exist or is not available. */
    case JSON_RPC_METHOD_NOT_FOUND = -32601;

    /** Invalid method parameter(s). */
    case JSON_RPC_INVALID_PARAMS = -32602;

    /** Internal JSON-RPC error. */
    case JSON_RPC_INTERNAL_ERROR = -32603;

    /**
     * Map this wire code to the HTTP-level {@see StatusEnum} it implies.
     *
     * @return StatusEnum
     */
    public function toStatus(): StatusEnum
    {
        return match ($this) {
            self::JSON_RPC_PARSE_ERROR, self::JSON_RPC_INVALID_REQUEST, self::JSON_RPC_INVALID_PARAMS => StatusEnum::BAD_REQUEST,
            self::JSON_RPC_METHOD_NOT_FOUND => StatusEnum::NOT_FOUND,
            self::JSON_RPC_INTERNAL_ERROR => StatusEnum::ERROR,
        };
    }

    /**
     * Best-effort reverse mapping: turn a {@see StatusEnum} into the
     * closest JSON-RPC 2.0 code. Used when a handler reports only the
     * HTTP-level status (e.g. via `$event->setStatus(BAD_REQUEST)`).
     *
     * @param StatusEnum $status
     * @return self
     */
    public static function fromStatus(StatusEnum $status): self
    {
        return match (true) {
            $status === StatusEnum::BAD_REQUEST => self::JSON_RPC_INVALID_PARAMS,
            $status === StatusEnum::NOT_FOUND, $status === StatusEnum::NOT_IMPLEMENTED => self::JSON_RPC_METHOD_NOT_FOUND,
            $status === StatusEnum::UNAUTHORIZED, $status === StatusEnum::FORBIDDEN => self::JSON_RPC_INVALID_REQUEST,
            default => self::JSON_RPC_INTERNAL_ERROR,
        };
    }
}
