<?php

declare(strict_types=1);

namespace Zolinga\System\Events;
use Zolinga\System\Wiki\WikiArticle;

/**
 * Harvests the Article objects that go under :ref in WIKI.
 * 
 * It is the way to add articles to the :ref: namespace.
 * 
 * Example:
 * 
 * $event->addArticle(new WikiArticle(":ref:service"));
 * 
 * @property-read WikiArticle[] $articles
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-28
 */
class WikiRefIntegrationEvent extends Event {
    /**
     * @var WikiArticle[]
     */
    private array $articles = [];

    public function addArticle(WikiArticle $article): void {
        $this->articles[] = $article;
    }

    public function __get(string $name): mixed {
        switch ($name) {
            case "articles":
                return $this->articles;
            default:
                return parent::__get($name);
        }
    }
}