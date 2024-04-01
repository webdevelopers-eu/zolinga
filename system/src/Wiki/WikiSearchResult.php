<?php

declare(strict_types=1);


namespace Zolinga\System\Wiki;
use JsonSerializable;

/**
 * Class WikiSearchResult
 *
 * This class represents a search result.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-02-28
 */
class WikiSearchResult implements JsonSerializable {
    public string $uri;
    public string $title;
    public float $priority;
    public string $snippet;

    public function __construct(string $uri, string $title, float $priority, string $snippet) {
        $this->uri = $uri;
        $this->title = $title;
        $this->priority = $priority;
        $this->snippet = $snippet;
    }

    public function jsonSerialize(): mixed {
        return [
            'uri' => $this->uri,
            'title' => $this->title,
            'priority' => $this->priority,
            'snippet' => $this->snippet
        ];
    }
}