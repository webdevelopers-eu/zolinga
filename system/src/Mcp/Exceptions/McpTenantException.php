<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp\Exceptions;

use Zolinga\System\Mcp\McpStatusEnum;

/**
 * Control-flow exception for MCP tenant filtering.
 *
 * Used by handlers that share tenant visibility checks but need different
 * outcomes: list handlers skip the descriptor, direct get/read handlers abort
 * the request with a forbidden response.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-08-29
 */
class McpTenantException extends McpException
{
	public function __construct(string $message, string|int|null $requestId = null, ?\Throwable $previous = null)
	{
		parent::__construct($message, McpStatusEnum::JSON_RPC_INVALID_REQUEST, $requestId, null, $previous);
	}

	public function getHttpStatus(): ?int
	{
		return 403;
	}
}