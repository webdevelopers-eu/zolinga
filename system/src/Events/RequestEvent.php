<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

use ArrayObject, ArrayAccess;
use Zolinga\System\Types\OriginEnum;

/**
 * System event class that represents a request that does not require response.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-05
 */
class RequestEvent extends Event {

    /**
     * The request object passed to an Event's constructr.
     *
     * @var ArrayAccess<string, mixed>|array<string, mixed>
     */
    public ArrayAccess|array $request;

    /**
     * Constructor.
     *
     * @param string $type The event type in the form of URI
     * @param OriginEnum $origin The origin of the event - internal, external or CLI. See more \Zolinga\System\Types\OriginEnum
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request
     */
    public function __construct(string $type, OriginEnum $origin = OriginEnum::INTERNAL, ArrayAccess|array $request = new ArrayObject)
    {
        parent::__construct($type, $origin);
        $this->request = $request;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return mixed
     */    
    public function jsonSerialize() : mixed {
        return [
            ...parent::jsonSerialize(),
            'request' => $this->request
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
            new ArrayObject($data['request'])
        );
        $event->uuid = $data['uuid'];
        if ($data['status']) {
            $event->setStatus($data['status'], $data['message']);
        }
        return $event;
    }
}