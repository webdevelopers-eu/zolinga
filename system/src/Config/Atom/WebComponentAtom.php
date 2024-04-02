<?php

declare(strict_types=1);

namespace Zolinga\System\Config\Atom;

/**
 * Atom for web component configuration.
 * 
 * $atom = new WebComponentAtom([...]);
 * echo $atom['tag'];
 * 
 * @property string $tag
 * @property string $module
 * @property float $priority
 * @property string $description
 * @extends \ArrayObject<string, mixed>
 * 
 * @package Zolinga\System\Config\Atom
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date ????-??-??
 */
class WebComponentAtom extends \ArrayObject implements AtomInterface {
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data) {
        $data = [
            "tag" => $data['tag'],
            "module" => $data['module'],
            "priority" => floatval($data['priority'] ?? 0.5),
            "description" => $data['description'] ?? "",
        ];

        parent::__construct($data);
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        throw new \Exception("Configuration atoms are immutable");
    }
}