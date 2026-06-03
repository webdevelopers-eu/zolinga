<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp\Exceptions;

use Zolinga\System\Mcp\McpStatusEnum;

/**
 * Thrown when the request body is missing or not valid JSON.
 *
 * HTTP 400 on the response (derived from the wire code).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpParseErrorException extends McpException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, McpStatusEnum::JSON_RPC_PARSE_ERROR, null, null, $previous);
    }
}
