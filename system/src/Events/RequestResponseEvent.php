<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

use ArrayObject, ArrayAccess;
use Zolinga\System\Types\OriginEnum;

/**
 * System event class that represents a request and a response.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-05
 */
class RequestResponseEvent extends RequestEvent {

    /**
     * The response object passed to an Event's constructr.
     *
     * @var ArrayAccess<string, mixed>|array<string, mixed>
     */
    public ArrayAccess|array $response;

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
     * Constructor.
     *
     * @param string $type The event type in the form of URI
     * @param OriginEnum $origin The origin of the event - internal, external or CLI. See more \Zolinga\System\Types\OriginEnum
     * @param ArrayAccess<string, mixed>|array<string, mixed> $response
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request
     */
    public function __construct(string $type, OriginEnum $origin = OriginEnum::INTERNAL, ArrayAccess|array $request = new ArrayObject, ArrayAccess|array $response = new ArrayObject)
    {
        parent::__construct($type, $origin, $request);
        $this->response = $response;
    }

    /**
     * Request client-side javacript broadcast message.
     *
     * @param string $name The name of the event to broadcast
     * @param mixed $detail Optional detail to pass with the event
     * @param bool $global Whether the event should be broadcast globally to all browser windows (default: false)
     * @return void
     */
    public function broadcastBack(string $name, mixed $detail = null, bool $global = false): void {
        $this->broadcastBack[] = [
            'name' => $name,
            'detail' => $detail,
            'global' => $global
        ];
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed {
        return [
            ...parent::jsonSerialize(),
            'response' => $this->response,
            'broadcastBack' => $this->broadcastBack,
        ];
    }

    /**
     * Create a new instance from an array of data.
     *
     * @param array $data The data to create the event from. See RequestResponseEvent::jsonSerialize() for the structure.
     * @return static
     */
    public static function fromArray(array $data): static {
        $event = new static(
            $data['type'],
            OriginEnum::tryFrom($data['origin']),
            new ArrayObject($data['request']),
            new ArrayObject($data['response'])
        );
        $event->uuid = $data['uuid'];
        if ($data['status']) {
            $event->setStatus($data['status'], $data['message']);
        }
        return $event;
    }
}