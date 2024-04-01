<?php

declare(strict_types=1);

namespace Zolinga\System\Config\Atom;

use Zolinga\System\Events\Event;
use Zolinga\System\Types\OriginEnum;

/**
 * Represents the "emit" section atom in the configuration.
 * 
 * The emit atom is used to define the events that the system emits.
 * 
 * @property string $event The event type
 * @property string $class The class that emits the event
 * @property array<OriginEnum> $origin The origin of the event
 * @property string|null $description The description of the event
 * @extends \ArrayObject<string, mixed>
 * 
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-03-12
 */
class EmitAtom extends \ArrayObject implements AtomInterface
{
    use EventMatchTrait;

    /**
     * Constructor
     *
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        if (empty($data['origin'])) {
            $data['origin'] = [];
        } elseif (!is_array($data['origin'])) {
            $data['origin'] = [$data['origin']];
        }

        $data['origin'] = array_unique($data['origin']);
        sort($data['origin']);

        $data = [
            "event" => $data['event'],
            "class" => '\\' . ltrim($data['class'], '\\'),
            "origin" => array_map(fn ($originName) => OriginEnum::from($originName), $data['origin']),
            "description" => $data['description'] ?? null
        ];

        parent::__construct($data);
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        throw new \Exception("Configuration atoms are immutable");
    }
}
