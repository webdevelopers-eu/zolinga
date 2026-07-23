<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp\Prompts;

use ArrayObject;
use ArrayAccess;
use InvalidArgumentException;

/**
 * MCP `prompts/list` event.
 *
 * Dispatched by the MCP gateway when a client sends a `prompts/list`
 * JSON-RPC request. Handlers populate the response with prompt descriptors
 * via {@see addPromptJson()} or the convenience wrapper {@see addPrompt()}.
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/server/prompts
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
class ListEvent extends PromptsEvent
{
    /**
     * Constructor.
     *
     * @param string|int|null $jsonrpcId The JSON-RPC request id.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request The JSON-RPC params.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $response The JSON-RPC result.
     */
    public function __construct(
        string|int|null $jsonrpcId = null,
        ArrayAccess|array $request = new ArrayObject,
        ArrayAccess|array $response = new ArrayObject
    ) {
        parent::__construct('mcp:prompts/list', $jsonrpcId, $request, $response);
    }

    /**
     * Append a prompt descriptor to the `prompts/list` response.
     *
     * Validates the prompt via {@see validatePrompt()} before appending.
     * Strips `messages` and internal `uri` fields so they never leak to
     * the list response — `messages` is only served by `prompts/get`.
     *
     * @param array<string, mixed> $promptJson Prompt descriptor.
     * @return void
     * @throws InvalidArgumentException Missing required fields or disallowed scheme.
     */
    public function addPromptJson(array $promptJson): void
    {
        $pickFields = ['name', 'title', 'description', 'arguments', 'icons'];
        $subset = array_intersect_key($promptJson, array_flip($pickFields));
        
        $this->validatePrompt($subset);

        if (!isset($this->response['prompts']) || !is_array($this->response['prompts'])) {
            $this->response['prompts'] = [];
        }

        $this->response['prompts'][] = $subset;
    }

    /**
     * Convenience wrapper for {@see addPromptJson()}.
     *
     * @param string $name Prompt identifier (must use an allowed scheme).
     * @param string $title Human-readable title (optional).
     * @param string $description One-line description (optional).
     * @param array<int, array<string, mixed>> $arguments Prompt arguments (optional).
     * @param array<int, array<string, mixed>> $icons Icon descriptors (optional).
     * @return void
     * @throws InvalidArgumentException Via {@see addPromptJson()}.
     */
    public function addPrompt(
        string $name,
        string $title = '',
        string $description = '',
        array $arguments = [],
        array $icons = []
    ): void {
        $prompt = ['name' => $name];
        if ($title !== '') {
            $prompt['title'] = $title;
        }
        if ($description !== '') {
            $prompt['description'] = $description;
        }
        if ($arguments !== []) {
            $prompt['arguments'] = $arguments;
        }
        if ($icons !== []) {
            $prompt['icons'] = $icons;
        }
        $this->addPromptJson($prompt);
    }

    /**
     * Validate the response: check each prompt name has an allowed scheme.
     *
     * @return void
     * @throws InvalidArgumentException If any prompt name uses a disallowed scheme.
     */
    public function validateResponse(): void
    {
        $prompts = $this->response['prompts'] ?? [];
        if (!is_array($prompts)) {
            return;
        }
        foreach ($prompts as $prompt) {
            if (!is_array($prompt)) {
                continue;
            }
            $this->validatePrompt($prompt);
        }
    }

    /**
     * Validate a single prompt descriptor.
     *
     * Requires `name` (non-empty string with an allowed scheme).
     *
     * @param array<string, mixed> $prompt Prompt descriptor.
     * @return void
     * @throws InvalidArgumentException Missing required fields or disallowed scheme.
     */
    private function validatePrompt(array $prompt): void
    {
        $name = $prompt['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new InvalidArgumentException('Prompt "name" must be a non-empty string.');
        }

        if (!$this->isAllowedScheme(parse_url($name, PHP_URL_SCHEME) ?? '*missing-scheme*')) {
            throw new InvalidArgumentException(
                'Prompt "name" scheme is not allowed. Allowed schemes: '
                . implode(', ', self::ALLOWED_URI_SCHEMES)
                . '. Consider using a custom "mcp-<name>" scheme.'
            );
        }
    }
}
