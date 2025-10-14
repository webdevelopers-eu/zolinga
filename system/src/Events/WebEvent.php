<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

use ArrayObject, ArrayAccess;
use Zolinga\System\Types\OriginEnum;

/**
 * System event class that represents a web request and a response.
 * This event class is specifically designed for web requests that are coming from
 * the JavaScript API and need the ability to broadcast events back to the client.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2025-10-14
 */
class WebEvent extends RequestResponseEvent {

    /**
     * Client-side events to broadcast back to the client.
     * 
     * Format: broadcastBack = [
     *  [name:string, detail:mixed, global:bool]
     *   ...
     * ]
     * 
     * @var array
     */
    public private(set) array $broadcastBack = [];


    /**
     * Request client-side javacript broadcast message.
     *
     * @param string $name The name of the event to broadcast
     * @param mixed $detail Optional detail to pass with the event
     * @param bool $global Whether the event should be broadcast globally to all browser windows (default: true)
     * @return void
     */
    public function broadcastBack(string $name, mixed $detail = null, bool $global = true): void {
        $this->broadcastBack[] = [
            'name' => $name,
            'detail' => $detail,
            'global' => $global
        ];
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        $ret = parent::jsonSerialize();
        $ret['broadcastBack'] = $this->broadcastBack;
        return $ret;
    }
}