<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp\Exceptions;

use Zolinga\System\Mcp\McpStatusEnum;

/**
 * Thrown when the dispatched Zolinga event has no listener (or returned an
 * undetermined status).
 *
 * HTTP 404 on the response (derived from the wire code).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpMethodNotFoundException extends McpException
{
    public function __construct(string $message, string|int|null $requestId, ?\Throwable $previous = null)
    {
        parent::__construct($message, McpStatusEnum::JSON_RPC_METHOD_NOT_FOUND, $requestId, null, $previous);
    }
}
