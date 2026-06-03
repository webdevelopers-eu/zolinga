<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp\Exceptions;

use Zolinga\System\Mcp\McpStatusEnum;

/**
 * Thrown when a JSON-RPC request envelope is missing required fields or
 * contains invalid types.
 *
 * HTTP 400 on the response (derived from the wire code).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpInvalidRequestException extends McpException
{
    public function __construct(string $message, string|int|null $requestId = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, McpStatusEnum::JSON_RPC_INVALID_REQUEST, $requestId, null, $previous);
    }
}
