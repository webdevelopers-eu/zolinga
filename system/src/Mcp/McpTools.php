<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\{ListenerInterface};
use Zolinga\System\Events\Mcp\Tools\ListEvent;
use Zolinga\System\Types\{OriginEnum, StatusEnum};
use Zolinga\System\Config\Atom\ListenAtom;

/**
 * Implements the MCP `tools/list` event handler.
 *
 * Walks the merged manifest, collects every listener that opts in to the
 * `mcp` origin AND declares a `schema.response` (and is not a reserved MCP
 * protocol event), and returns the JSON-RPC `tools/list` response
 * `{ tools: [...] }`. The listener's event name is used verbatim as the
 * tool name — MCP tools are distinguished from other MCP events solely by
 * the `mcp` origin and the presence of a `schema.response`, not by an
 * event-name prefix. Every exposed tool MUST declare a `schema.response`
 * (the gateway wraps `$event->response` in the MCP
 * `{ content, isError, structuredContent }` envelope and the structured
 * payload must conform to the declared schema); tools without one are
 * skipped and an error is logged.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpTools implements ListenerInterface
{
    /**
     * Handle `tools/list`. Returns a `{ tools: [...] }` payload describing all
     * listeners that opt in to the `mcp` origin and have a `schema.response`.
     *
     * @param ListEvent $event
     * @return void
     */
    public function onList(ListEvent $event): void
    {
        $this->collectTools($event);
        $event->setStatus(StatusEnum::OK, 'OK');
    }

    /**
     * Build the list of MCP tools from the merged manifest.
     *
     * Populates all supported `tools/call` listeners in response to the
     * `tools/list` MCP request. Each candidate must:
     *
     * 1. opt in to the `mcp` origin,
     * 2. declare a `schema.response` (the gateway wraps `$event->response`
     *    in the MCP `{ content, isError, structuredContent }` envelope, and
     *    the structured payload must conform to the declared schema), and
     * 3. not be a reserved MCP protocol event (`mcp:initialize`,
     *    `mcp:tools/list`, `mcp:notifications/*`).
     *
     * The listener's event name is used verbatim as the tool name. MCP tools
     * and other MCP events are uniform; the only distinction is that a tool
     * declares a `schema.response` and is callable via `tools/call`.
     *
     * Tools failing condition 2 are skipped and an error is logged via
     * `$api->log->error()`. Each accepted tool is appended via
     * {@see ListEvent::addTool()}, which validates the name format and the
     * schema shapes. Deduplicates by event name (highest priority wins).
     *
     * @param ListEvent $event The event whose `response['tools']` is populated.
     * @return void
     */
    private function collectTools(ListEvent $event): void
    {
        global $api;

        $seen = [];

        /** @var ListenAtom $atom */
        foreach ($api->manifest['listen'] as $atom) {
            if (!in_array(OriginEnum::MCP, $atom['origin'], true)) {
                continue;
            }

            $eventName = (string) $atom['event'];

            // Exclude reserved MCP protocol events. These are MCP JSON-RPC
            // methods handled by dedicated listeners (mcp:initialize,
            // mcp:tools/list) or the mcp:notifications/* namespace, not
            // user-callable tools.
            if ($this->isReservedEvent($eventName)) {
                continue;
            }

            $toolName = $eventName;

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

            // Reject tool names that don't conform to the allowed character
            // set (`[A-Za-z0-9_:-]{1,64}`, no `mcp:` prefix). Without this check
            // the gateway would happily dispatch e.g. `foo bar`, but the name
            // would be invalid and the tool effectively uncallable. Skipping
            // here keeps the manifest and the wire contract in sync: a bad
            // name is neither advertised nor callable.
            if (!McpHelper::isValidToolName($toolName)) {
                $api->log->error('system:mcp', "MCP tool \"$toolName\" (event \"$eventName\") has an invalid name; tool names must be 1.." . McpHelper::TOOL_NAME_MAX_LENGTH . " chars of " . McpHelper::TOOL_NAME_CHAR_CLASS . " and must not start with \"mcp:\". The listener will be skipped.", [
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

            $requestSchema = $this->loadSchema($atom['schema']['request'] ?? null);
            $inputSchema = $requestSchema ?? ['type' => 'object', 'additionalProperties' => true];

            try {
                $event->addTool(
                    $toolName,
                    (string) ($atom['description'] ?? ''),
                    $inputSchema,
                    $responseSchema
                );
            } catch (\InvalidArgumentException $e) {
                // addTool re-validates name and schema shape; a failure here
                // means the manifest data is inconsistent with the wire
                // contract. Log and skip rather than poisoning the response.
                $api->log->error('system:mcp', 'Skipping MCP tool "' . $toolName . '": ' . $e->getMessage(), [
                    'event' => $eventName,
                    'tool' => $toolName,
                    'class' => (string) $atom['class'],
                ]);
            }
        }

        // Stable order so clients get deterministic output.
        $tools = $event->response['tools'] ?? [];
        if (is_array($tools)) {
            usort($tools, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
            $event->response['tools'] = $tools;
        }
    }

    /**
     * Whether an event name is a reserved MCP protocol event that must not be
     * exposed as a callable tool in `tools/list`.
     *
     * All non-`tools/call` MCP events are prefixed with `mcp:` by the gateway
     * (e.g. `mcp:initialize`, `mcp:tools/list`,
     * `mcp:notifications/initialized`).
     * `McpHelper::isValidToolName()` explicitly rejects names starting with
     * `mcp:`, so user tools can never collide with the protocol prefix even
     * though `:` is an allowed character. This lets us exclude protocol
     * events with a single prefix check instead of a hardcoded list — new
     * protocol methods are automatically excluded.
     *
     * @param string $eventName
     * @return bool
     */
    private function isReservedEvent(string $eventName): bool
    {
        return str_starts_with($eventName, 'mcp:');
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
