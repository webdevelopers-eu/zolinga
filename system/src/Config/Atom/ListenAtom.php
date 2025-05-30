<?php

declare(strict_types=1);

namespace Zolinga\System\Config\Atom;

use Zolinga\System\Config\ConfigException;
use Zolinga\System\Events\Event;
use Zolinga\System\Types\OriginEnum;

/**
 * The Object representing the zolinga.json's listen element.
 *
 * @property string $event the event type in the format of an URI. Example: example.org:api:myEvent
 * @property string $class the class name of the listener
 * @property string|null $method the method name of the listener
 * @property array<OriginEnum> $origin the origin of the event. Can be internal, remote or cli.
 * @property string|null $description the description of the listener
 * @property float $priority the priority of the listener
 * @property string|false $right the right that needs to be satisfied for the event to be authorized to be processed by the target listener having the rights set.
 * @extends \ArrayObject<string, mixed>
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-07
 */
class ListenAtom extends \ArrayObject implements AtomInterface
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

        // Sugar syntax "service" and "request" conversion to "event"
        if (!empty($data['service'])) {
            $data['event'] = 'system:service:' . $data['service'];
            $data['origin'] = ['internal'];
        } elseif (!empty($data['request'])) {
            $data['event'] = 'system:request:' . $data['request'];
            $data['origin'] = ['remote'];
        }

        $data['origin'] = array_unique($data['origin']);
        sort($data['origin']);

        if (!empty($data['right']) && !is_string($data['right'])) {
            throw new ConfigException("The right attribute must be a string or false: " . json_encode($data));
        }

        $class = $data['class'] ?? null
            or throw new ConfigException("The class attribute is required in the listen atom: " . json_encode($data));
        $eventName = $data['event'] ?? null
            or throw new ConfigException("The event attribute is required in the listen atom: " . json_encode($data));

        $origin = array_map(function ($originName) use ($data) {
            $origin = OriginEnum::tryFrom($originName);
            if (!$origin) {
                throw new ConfigException(
                    "Invalid origin value " . json_encode($originName) ." in listen atom. " .
                    "Valid values are: " . json_encode(array_column(OriginEnum::cases(), 'value')) . " " .
                    "Atom: " . json_encode($data)
                );
            }
            return $origin;
        }, $data['origin'] ?? []);

        $data = [
            "event" => $eventName,
            "class" => '\\' . ltrim($class, '\\'),
            "method" => $data['method'] ?? null,
            "origin" => $origin,
            "description" => $data['description'] ?? null,
            "priority" => floatval($data['priority'] ?? 0.5),
            "right" => empty($data['right']) ? false : (string) $data['right'],
        ];

        parent::__construct($data);
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        throw new \Exception("Configuration atoms are immutable");
    }
}
