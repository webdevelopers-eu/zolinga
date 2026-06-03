<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\{ListenerInterface, McpEvent};
use Zolinga\System\Types\{OriginEnum, StatusEnum};
use Zolinga\System\Config\Atom\ListenAtom;

/**
 * Implements the MCP `tools:list` event handler.
 *
 * Walks the merged manifest, collects every listener that opts in to the
 * `mcp` origin AND whose event name matches `tools:call:*`, and returns
 * the JSON-RPC `tools/list` response `{ tools: [...] }`. Every exposed
 * tool MUST declare a `schema.response` (the gateway wraps
 * `$event->response` in the MCP `{ content, isError, structuredContent }`
 * envelope and the structured payload must conform to the declared
 * schema); tools without one are skipped and an error is logged.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpTools implements ListenerInterface
{
    /**
     * Handle `tools:list`. Returns a `{ tools: [...] }` payload describing all
     * listeners that opt in to the `mcp` origin and have a `schema.response`.
     *
     * @param McpEvent $event
     * @return void
     */
    public function onList(McpEvent $event): void
    {
        $event->response = [
            'tools' => $this->collectTools(),
        ];
        $event->setStatus(StatusEnum::OK, 'OK');
    }

    /**
     * Build the list of MCP tools from the merged manifest.
     *
     * Populates all supported `tools/call` listeners in response to the
     * `tools/list` MCP request. Each candidate must:
     *
     * 1. opt in to the `mcp` origin,
     * 2. have an event name starting with `tools:call:` (the per-tool
     *    event dispatched by the gateway), and
     * 3. declare a `schema.response` (the gateway wraps `$event->response`
     *    in the MCP `{ content, isError, structuredContent }` envelope, and
     *    the structured payload must conform to the declared schema).
     *
     * Tools failing condition 3 are skipped and an error is logged via
     * `$api->log->error()`. Deduplicates by event name (highest priority
     * wins).
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectTools(): array
    {
        global $api;

        $tools = [];
        $seen = [];

        /** @var ListenAtom $atom */
        foreach ($api->manifest['listen'] as $atom) {
            if (!in_array(OriginEnum::MCP, $atom['origin'], true)) {
                continue;
            }

            $eventName = (string) $atom['event'];
            $toolName = preg_replace('/^tools:call:/', '', $eventName);

            if ($toolName === $eventName) { // not tools:call:*
                continue;
            }

            // Every exposed tool MUST declare a `schema.response` so the
            // gateway knows the shape of `$event->response` and clients can
            // validate `result.structuredContent` against it.
            $responseSchemaUri = $atom['schema']['response'] ?? null;
            $responseSchema = $this->loadSchema($responseSchemaUri);
            if ($responseSchema === null) {
                $api->log->error('system:mcp', "MCP tool \"$toolName\" (event \"$eventName\") is missing a schema.response declaration; the gateway cannot safely expose it. Add a schema.response Zolinga URI to the listener's manifest entry.", [
                    'event' => $eventName,
                    'tool' => $toolName,
                    'class' => (string) $atom['class'],
                ]);
                continue;
            }

            // Reject tool names that don't conform to the MCP client-side
            // character set (`[A-Za-z0-9_-]{1,64}`). Without this check the
            // gateway would happily dispatch e.g. `tools:call:foo bar`, but
            // real-world MCP clients would reject or mangle such a name and
            // the tool would be effectively uncallable. Skipping here keeps
            // the manifest and the wire contract in sync: a bad name is
            // neither advertised nor callable.
            if (!McpHelper::isValidToolName($toolName)) {
                $api->log->error('system:mcp', "MCP tool \"$toolName\" (event \"$eventName\") has an invalid name; tool names must be 1.." . McpHelper::TOOL_NAME_MAX_LENGTH . " chars of [A-Za-z0-9_-]. The listener will be skipped.", [
                    'event' => $eventName,
                    'tool' => $toolName,
                    'class' => (string) $atom['class'],
                ]);
                continue;
            }

            if (isset($seen[$eventName])) {
                // Keep the highest-priority description for the same event.
                continue;
            }
            $seen[$eventName] = true;

            $tool = [
                'name' => $toolName,
                'description' => (string) ($atom['description'] ?? ''),
            ];

            $requestSchema = $this->loadSchema($atom['schema']['request'] ?? null);
            $tool['inputSchema'] = $requestSchema ?? ['type' => 'object', 'additionalProperties' => true];

            $tool['outputSchema'] = $responseSchema;

            $tools[] = $tool;
        }

        // Stable order so clients get deterministic output.
        usort($tools, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $tools;
    }

    /**
     * Resolve a Zolinga URI to a JSON Schema object.
     *
     * Returns `null` when the URI is empty or the file cannot be read/parsed;
     * the caller decides whether to substitute a default schema.
     *
     * @param string|null $uri
     * @return array<string, mixed>|null
     */
    private function loadSchema(?string $uri): ?array
    {
        if (!$uri) {
            return null;
        }

        global $api;

        $path = $api->fs->toPath($uri);
        if (!$path || !is_file($path) || !is_readable($path)) {
            $api->log->warning('system:mcp', "MCP schema not found: $uri");
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            $api->log->warning('system:mcp', "MCP schema is not a JSON object: $uri");
            return null;
        }

        return $decoded;
    }
}
