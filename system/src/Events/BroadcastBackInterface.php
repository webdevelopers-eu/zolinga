<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

/**
 * Interface for events that can broadcast messages back to the client.
 *
 * Implemented by event classes that support client-side JavaScript broadcast
 * messages (e.g. {@see WebEvent}). Handlers can check `instanceof
 * BroadcastBackInterface` to decide whether to call {@see broadcastBack()}
 * without coupling to a specific event class.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-27
 */
interface BroadcastBackInterface
{
    /**
     * Request a client-side JavaScript broadcast message.
     *
     * @param string $name The name of the event to broadcast.
     * @param mixed $detail Optional detail to pass with the event.
     * @param bool $global Whether the event should be broadcast globally to all browser windows (default: true).
     * @return void
     */
    public function broadcastBack(string $name, mixed $detail = null, bool $global = true): void;
}