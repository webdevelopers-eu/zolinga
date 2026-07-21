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
class McpToolsListHandler implements ListenerInterface
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
            if (!$this->isMcpToolCandidate($atom)) {
                continue;
            }

            $eventName = (string) $atom['event'];
            $toolName = $eventName;

            if (isset($seen[$eventName])) {
                // Keep the highest-priority description for the same event.
                continue;
            }

            $responseSchema = $this->requireResponseSchema($atom, $toolName);
            if ($responseSchema === null) {
                continue;
            }

            if (!$this->requireValidToolName($atom, $toolName)) {
                continue;
            }

            $seen[$eventName] = true;
            $this->registerTool($event, $atom, $toolName, $responseSchema);
        }

        $this->sortTools($event);
    }

    /**
     * Whether a listener atom is a candidate MCP tool: it opts in to the
     * `mcp` origin and is not a reserved MCP protocol event.
     *
     * @param ListenAtom $atom
     * @return bool
     */
    private function isMcpToolCandidate(ListenAtom $atom): bool
    {
        if (!in_array(OriginEnum::MCP, $atom['origin'], true)) {
            return false;
        }

        return !$this->isReservedEvent((string) $atom['event']);
    }

    /**
     * Load the required `schema.response` for a tool, or log an error and
     * return `null` when it is missing/unreadable.
     *
     * @param ListenAtom $atom
     * @param string $toolName
     * @return array<string, mixed>|null
     */
    private function requireResponseSchema(ListenAtom $atom, string $toolName): ?array
    {
        global $api;

        $eventName = (string) $atom['event'];
        $responseSchema = $this->loadSchema($atom['schema']['response'] ?? null);
        if ($responseSchema !== null) {
            return $responseSchema;
        }

        $api->log->error('system:mcp', "MCP tool \"$toolName\" (event \"$eventName\") is missing a schema.response declaration; the gateway cannot safely expose it. Add a schema.response Zolinga URI to the listener's manifest entry.", [
            'event' => $eventName,
            'tool' => $toolName,
            'class' => (string) $atom['class'],
        ]);
        return null;
    }

    /**
     * Validate the tool name format, logging an error and returning `false`
     * when it does not conform to the MCP character class.
     *
     * @param ListenAtom $atom
     * @param string $toolName
     * @return bool
     */
    private function requireValidToolName(ListenAtom $atom, string $toolName): bool
    {
        global $api;

        if (McpHelper::isValidToolName($toolName)) {
            return true;
        }

        $eventName = (string) $atom['event'];
        $api->log->error('system:mcp', "MCP tool \"$toolName\" (event \"$eventName\") has an invalid name; tool names must be 1.." . McpHelper::TOOL_NAME_MAX_LENGTH . " chars of " . McpHelper::TOOL_NAME_CHAR_CLASS . " and must not start with \"mcp:\". The listener will be skipped.", [
            'event' => $eventName,
            'tool' => $toolName,
            'class' => (string) $atom['class'],
        ]);
        return false;
    }

    /**
     * Append a tool to the event response via {@see ListEvent::addTool()},
     * loading the optional `schema.request` and logging+skipping on
     * validation failure.
     *
     * @param ListEvent $event
     * @param ListenAtom $atom
     * @param string $toolName
     * @param array<string, mixed> $responseSchema
     * @return void
     */
    private function registerTool(ListEvent $event, ListenAtom $atom, string $toolName, array $responseSchema): void
    {
        global $api;

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
                'event' => (string) $atom['event'],
                'tool' => $toolName,
                'class' => (string) $atom['class'],
            ]);
        }
    }

    /**
     * Sort the collected tools by name for deterministic output.
     *
     * @param ListEvent $event
     * @return void
     */
    private function sortTools(ListEvent $event): void
    {
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
