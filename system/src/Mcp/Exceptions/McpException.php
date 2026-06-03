<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp\Exceptions;

use RuntimeException;
use Zolinga\System\Mcp\McpStatusEnum;
use Zolinga\System\Types\StatusEnum;

/**
 * Base class for MCP gateway exceptions.
 *
 * The canonical state is the {@see McpStatusEnum} wire code (the value
 * that ends up in JSON-RPC `error.code`). The HTTP response status is
 * derived from it via {@see McpStatusEnum::toStatus()} — the wire code
 * is finer-grained than the HTTP code (parse-error, invalid-request and
 * invalid-params all share 400, but the spec gives them distinct codes).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly McpStatusEnum $jsonrpcCode,
        private readonly string|int|null $requestId = null,
        private readonly mixed $data = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * JSON-RPC 2.0 wire code (the `error.code`).
     *
     * @return McpStatusEnum
     */
    public function getJsonrpcCode(): McpStatusEnum
    {
        return $this->jsonrpcCode;
    }

    /**
     * HTTP response status code implied by the wire code, or `null` to
     * leave it untouched.
     *
     * @return int|null
     */
    public function getHttpStatus(): ?int
    {
        return $this->jsonrpcCode->toStatus()->value ?: null;
    }

    /**
     * The JSON-RPC request id this error corresponds to, or `null` for
     * top-level (parse, transport) errors that fire before the id is known.
     *
     * @return string|int|null
     */
    public function getRequestId(): string|int|null
    {
        return $this->requestId;
    }

    /**
     * Optional structured data attached to the JSON-RPC `error.data` field.
     *
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Render the error as a JSON-RPC 2.0 error response payload.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $error = [
            'code' => $this->jsonrpcCode->value,
            'message' => $this->message,
        ];
        if ($this->data !== null) {
            $error['data'] = $this->data;
        }
        return [
            'jsonrpc' => '2.0',
            'id' => $this->requestId,
            'error' => $error,
        ];
    }
}
