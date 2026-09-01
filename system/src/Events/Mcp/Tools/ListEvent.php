<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp\Tools;

use ArrayObject;
use ArrayAccess;
use InvalidArgumentException;
use Zolinga\System\Mcp\McpHelper;

/**
 * Event for the MCP `tools/list` JSON-RPC method.
 *
 * Dispatched by the MCP gateway when a client requests the list of available
 * tools. The handler walks the merged manifest and returns every listener
 * that opts in to the `mcp` origin and declares a `schema.response`.
 *
 * Tools are appended to the response via {@see addTool()}, which validates
 * the tool name format and the schema shapes so a malformed entry can never
 * reach the wire.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-07-21
 */
class ListEvent extends ToolsEvent
{
    /**
     * @param string|int|null $jsonrpcId The JSON-RPC request id.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request The JSON-RPC params.
     * @param ArrayAccess<string, mixed>|array<string, mixed> $response The JSON-RPC result.
     */
    public function __construct(
        string|int|null $jsonrpcId = null,
        ArrayAccess|array $request = new ArrayObject,
        ArrayAccess|array $response = new ArrayObject
    ) {
        parent::__construct('mcp:tools/list', $jsonrpcId, $request, $response);
    }

    /**
     * Append a tool to the `tools/list` response.
     *
     * Validates the tool name against the MCP character class
     * (`[A-Za-z0-9_:-]{1,64}`, no `mcp:` prefix) via
     * {@see McpHelper::isValidToolName()} and checks that both schemas are
     * JSON objects (arrays). Throws {@see InvalidArgumentException} on any
     * violation so a malformed tool can never be advertised on the wire.
     *
     * The response is built lazily: the first call initialises
     * `$this->response['tools']` to an empty array.
     *
     * Example:
     * ```php
     * $event->addTool(
     *     name: 'echo',
     *     description: 'Echoes the message back.',
     *     inputSchema: ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]],
     *     outputSchema: ['type' => 'object', 'properties' => ['echo' => ['type' => 'string']]],
     * );
     * ```
     *
     * @param string $name The tool name; clients invoke it via `tools/call` with `params.name`.
     * @param string $description Human-readable description for the `tools/list` catalogue.
     * @param array<string, mixed> $inputSchema JSON Schema describing `params.arguments` (becomes `inputSchema`).
     * @param array<string, mixed> $outputSchema JSON Schema describing the handler's `$event->response` (becomes `outputSchema`).
     * @return void
     * @throws InvalidArgumentException When the name is not a valid tool name or a schema is not a JSON object.
     */
    public function addTool(
        string $name,
        string $description,
        array $inputSchema,
        array $outputSchema
    ): void {
        if (!McpHelper::isValidToolName($name)) {
            throw new InvalidArgumentException(
                'Invalid MCP tool name "' . $name . '"; must be 1..'
                . McpHelper::TOOL_NAME_MAX_LENGTH . ' chars of '
                . McpHelper::TOOL_NAME_CHAR_CLASS
                . ' and must not start with "mcp:".'
            );
        }

        if (array_is_list($inputSchema)) {
            throw new InvalidArgumentException(
                'Invalid inputSchema for tool "' . $name . '"; must be a JSON Schema object (associative array), not a list.'
            );
        }

        if (array_is_list($outputSchema)) {
            throw new InvalidArgumentException(
                'Invalid outputSchema for tool "' . $name . '"; must be a JSON Schema object (associative array), not a list.'
            );
        }

        if (!isset($this->response['tools']) || !is_array($this->response['tools'])) {
            $this->response['tools'] = [];
        }

        $this->response['tools'][] = [
            'name' => $name,
            'description' => $description,
            'inputSchema' => $inputSchema,
            'outputSchema' => $outputSchema,
        ];
    }
}