<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp\Exceptions;

use Zolinga\System\Mcp\McpStatusEnum;

/**
 * Thrown when an unexpected error occurs while dispatching a Zolinga event
 * (a `Throwable` escapes from a handler).
 *
 * HTTP 500 on the response (derived from the wire code).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpInternalErrorException extends McpException
{
    public function __construct(string $message, string|int|null $requestId, ?\Throwable $previous = null)
    {
        parent::__construct($message, McpStatusEnum::JSON_RPC_INTERNAL_ERROR, $requestId, null, $previous);
    }
}
