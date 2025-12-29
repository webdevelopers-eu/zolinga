<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

use ArrayObject, ArrayAccess;
use Zolinga\System\Helpers\ZArgs;
use Zolinga\System\Types\OriginEnum;

/**
 * System event class that represents a request that does not require response.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-05
 */
class RequestEvent extends Event {

    private ?ZArgs $zargs = null;

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
     * Define a CLI option (yargs-like API).
     *
     * @param string $key Option key (supports dot-separated paths).
     * @param ?string $alias Alias key (e.g. 'h' for help).
     * @param ?string $describe Help description.
     * @param ?array<int, mixed> $choices Allowed values (supports Enum::cases()).
     * @param bool $demandOption Require presence (default counts as present).
     * @param ?string $type Supported: 'string', 'number', 'int', 'float', 'boolean'.
     * @param mixed $default Default value. If omitted, no default is applied.
     * @return static
     */
    public function option(
        string $key,
        ?string $alias = null,
        ?string $describe = null,
        ?array $choices = null,
        bool $demandOption = false,
        ?string $type = null,
        mixed $default = null,
    ): static {
        if (func_num_args() >= 7) {
            $this->getZArgs()->option(
                $key,
                alias: $alias,
                describe: $describe,
                choices: $choices,
                demandOption: $demandOption,
                type: $type,
                default: $default,
            );
        } else {
            $this->getZArgs()->option(
                $key,
                alias: $alias,
                describe: $describe,
                choices: $choices,
                demandOption: $demandOption,
                type: $type,
            );
        }
        return $this;
    }

    /**
     * When enabled, unknown parameters cause CliUnknownInputException.
     * When disabled (default), unknown parameters are allowed.
     *
     * @param bool $enabled
     * @return static
     */
    public function strict(bool $enabled = true): static
    {
        $this->getZArgs()->strict($enabled);
        return $this;
    }

    /**
     * Apply a custom validation/transformation to an option.
     *
     * @param string $key
     * @param callable $fn function(mixed $value, string $key, array<string, mixed> $all): mixed
     * @return static
     */
    public function coerce(string $key, callable $fn): static
    {
        $this->getZArgs()->coerce($key, $fn);
        return $this;
    }

    /**
     * Configure a help flag key.
     *
     * If present/truthy in input, parse() prints generated help and returns without validating.
     *
     * @param string $key
     * @return static
     */
    public function help(string $key = 'help'): static
    {
        $this->getZArgs()->help($key);
        return $this;
    }

    /**
     * Validate and normalize input using ZArgs.
     *
     * On success, updates $this->request with normalized values and returns them.
     *
     * @return array<string, mixed>
     */
    public function parse(): array
    {
        $parsed = $this->getZArgs()->parse();
        $this->setRequestArray($parsed);
        return $parsed;
    }

    private function getZArgs(): ZArgs
    {
        if ($this->zargs === null) {
            $this->zargs = new ZArgs($this->getRequestArray());
        }
        return $this->zargs;
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestArray(): array
    {
        if (is_array($this->request)) {
            return $this->request;
        }

        if ($this->request instanceof ArrayObject) {
            /** @var array<string, mixed> */
            return $this->request->getArrayCopy();
        }

        if ($this->request instanceof \Traversable) {
            /** @var array<string, mixed> */
            return iterator_to_array($this->request);
        }

        throw new \RuntimeException('Unsupported request container: ' . get_debug_type($this->request));
    }

    /**
     * @param array<string, mixed> $request
     */
    private function setRequestArray(array $request): void
    {
        if (is_array($this->request)) {
            $this->request = $request;
            return;
        }
        if ($this->request instanceof ArrayObject) {
            $this->request->exchangeArray($request);
            return;
        }
        throw new \RuntimeException('Unsupported request container: ' . get_debug_type($this->request));
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return mixed
     */    
    public function jsonSerialize() : mixed {
        return [
            ...parent::jsonSerialize(),
            'request' => $this->request,
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