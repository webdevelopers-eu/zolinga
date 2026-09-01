<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Mcp;

/**
 * Abstract base for MCP list discovery events (tools/list, prompts/list, resources/list).
 *
 * Provides the lazy-initialisation helper {@see appendItem()} that all
 * `list`-style events share: the response array is created on first use
 * and items are appended under the given key.
 *
 * Concrete subclasses: {@see Prompts\ListEvent}, {@see Resources\ListEvent},
 * {@see Tools\ListEvent}.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-09-01
 */
abstract class AbstractListEvent extends McpEvent
{
    /**
     * Append a descriptor built from a metadata array to the list response.
     *
     * Each concrete subclass validates the descriptor and appends it under
     * the appropriate response key (`prompts`, `resources`, `tools`).
     *
     * @param array<string, mixed> $meta The descriptor metadata.
     * @return void
     * @throws \InvalidArgumentException If the descriptor is invalid.
     */
    abstract public function addFromMeta(array $meta): void;

    /**
     * Append an item to a list-style response.
     *
     * Lazily initialises `$this->response[$key]` to an empty array on
     * first use, then pushes `$item` onto it.
     *
     * @param string $key The response key holding the list (e.g. `prompts`, `resources`, `tools`).
     * @param array<string, mixed> $item The descriptor to append.
     * @return void
     */
    protected function appendItem(string $key, array $item): void
    {
        if (!isset($this->response[$key]) || !is_array($this->response[$key])) {
            $this->response[$key] = [];
        }

        $this->response[$key][] = $item;
    }
}