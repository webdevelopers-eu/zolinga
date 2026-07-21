<?php

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Events\{ListenerInterface};
use Zolinga\System\Events\Mcp\Tools\CallEvent;
use Zolinga\System\Types\StatusEnum;

/**
 * Minimal example MCP handler that echoes the `message` argument back.
 * Useful as a smoke test for the [MCP gateway](:Zolinga Core:Running the System:MCP).
 *
 * The handler sets `$event->response` to the raw structured payload that
 * conforms to the tool's `outputSchema` (declared in the manifest). The
 * gateway builds the MCP `{ content, isError, structuredContent }` envelope
 * around it.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */
class McpEchoHandler implements ListenerInterface
{
    /**
     * Handle the `echo` event (MCP `tools/call` with `params.name = "echo"`).
     *
     * Receives a {@see CallEvent} with `request = params.arguments`
     * (the `arguments` object from the JSON-RPC `tools/call` request). Sets
     * the raw structured payload on `$event->response`; the gateway wraps
     * it in the MCP envelope and serializes it as the JSON-RPC `result`.
     *
     * @param CallEvent $event
     * @return void
     */
    public function onEcho(CallEvent $event): void
    {
        $message = $event->request['message'] ?? null;
        if (!is_string($message) || $message === '') {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Missing or empty "message" argument.');
            return;
        }
        $event->response = [
            'echo' => $message,
            'receivedAt' => date('c'),
        ];
        $event->setStatus(StatusEnum::OK, 'OK');
    }
}
