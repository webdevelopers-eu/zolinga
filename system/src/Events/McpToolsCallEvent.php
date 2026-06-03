<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

/**
 * Specialized {@see McpEvent} used for `tools/call` invocations (the per-tool
 * event `tools:call:<name>` dispatched by the MCP gateway).
 *
 * The gateway builds the JSON-RPC `result` envelope from this event:
 *
 * - `$response` — the **raw structured payload** the tool produced. It MUST
 *   conform to the tool's `outputSchema` declared in the manifest. The
 *   gateway echoes it back to the client verbatim as
 *   `result.structuredContent`.
 * - `$content` — optional list of human-readable text/image/audio/resource
 *   blocks (per the MCP `tools/call` spec). When non-empty, the gateway uses
 *   it as `result.content`. When empty, the gateway falls back to
 *   `[{ type: "text", text: json_encode($response) }]` so legacy clients
 *   that only read `result.content[0].text` still get the structured data.
 * - `$status` / `$message` — when set to a non-OK {@see StatusEnum} value
 *   (or stays at the default `UNDETERMINED` because no listener handled the
 *   event), the gateway produces an MCP error envelope with
 *   `result.isError = true` and the message in `result.content[0].text`.
 *
 * Tool handlers should populate `$response` (and optionally call
 * {@see McpToolsCallEvent::addTextContent()}). They should NOT build the
 * `{ content, isError, structuredContent }` envelope themselves — the
 * gateway does that.
 *
 * Example handler:
 *
 * ```php
 * public function onEcho(McpToolsCallEvent $event): void
 * {
 *     $message = $event->request['message'] ?? '';
 *     if (!is_string($message) || $message === '') {
 *         $event->setStatus(StatusEnum::BAD_REQUEST, 'Missing or empty "message" argument.');
 *         $event->addTextContent('Missing or empty "message" argument.');
 *         return;
 *     }
 *     $event->response = [
 *         'echo' => $message,
 *         'receivedAt' => date('c'),
 *     ];
 *     $event->setStatus(StatusEnum::OK, 'OK');
 * }
 * ```
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpToolsCallEvent extends McpEvent
{
    /**
     * Optional human-readable content blocks (per the MCP `tools/call` spec).
     * Each entry follows the MCP content-block shape
     * (e.g. `{ type: "text", text: "..." }`).
     *
     * When this array is empty the gateway falls back to a single text block
     * containing the JSON encoding of `$response`, so legacy clients that
     * only read `result.content[0].text` still receive the structured data.
     *
     * @var list<array<string, mixed>>
     */
    public array $content = [];

    /**
     * Append a text block to the human-readable content list.
     *
     * Convenience wrapper for the common case of returning a short status
     * message alongside a structured response.
     *
     * @param string $text
     * @return void
     */
    public function addTextContent(string $text): void
    {
        $this->content[] = ['type' => 'text', 'text' => $text];
    }
}
