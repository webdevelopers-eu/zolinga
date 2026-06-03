<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp\Exceptions;

use Zolinga\System\Mcp\McpStatusEnum;

/**
 * Thrown when a handler reports an invalid-params condition (e.g. missing
 * required argument, validation failure).
 *
 * HTTP 400 on the response (derived from the wire code).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpInvalidParamsException extends McpException
{
    public function __construct(string $message, string|int|null $requestId, ?\Throwable $previous = null)
    {
        parent::__construct($message, McpStatusEnum::JSON_RPC_INVALID_PARAMS, $requestId, null, $previous);
    }
}
