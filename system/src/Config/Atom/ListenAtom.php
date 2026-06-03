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
 * @property array<OriginEnum> $origin the origin of the event. Can be internal, remote, cli or mcp.
 * @property string|null $description the description of the listener
 * @property float $priority the priority of the listener
 * @property string|false $right the right that needs to be satisfied for the event to be authorized to be processed by the target listener having the rights set.
 * @property array{request?: string, response?: string}|null $schema optional JSON Schema file paths (Zolinga URI) describing the request/response payloads. Used by discovery surfaces like the MCP server's `initialize` response.
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

        // Optional `schema` block: {request?: string, response?: string}. Each value is
        // a Zolinga URI (e.g. "module://my-module/schema/mcp/tool.json") pointing to a
        // JSON Schema file. Discovery surfaces (like the MCP `initialize` handler) may
        // resolve and embed these schemas into their response.
        if (isset($data['schema']) && !is_array($data['schema'])) {
            throw new ConfigException("The schema attribute must be an object with optional 'request' and 'response' string keys: " . json_encode($data));
        }
        $schema = [
            'request' => isset($data['schema']['request']) && is_string($data['schema']['request']) ? $data['schema']['request'] : null,
            'response' => isset($data['schema']['response']) && is_string($data['schema']['response']) ? $data['schema']['response'] : null,
        ];

        $data = [
            "event" => $eventName,
            "class" => '\\' . ltrim($class, '\\'),
            "method" => $data['method'] ?? null,
            "origin" => $origin,
            "description" => $data['description'] ?? null,
            "priority" => floatval($data['priority'] ?? 0.5),
            "right" => empty($data['right']) ? false : (string) $data['right'],
            "schema" => $schema,
        ];

        parent::__construct($data);
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        throw new \Exception("Configuration atoms are immutable");
    }
}
