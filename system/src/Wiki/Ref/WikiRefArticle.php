<?php
declare(strict_types=1);


namespace Zolinga\System\Wiki\Ref;
use Zolinga\System\Wiki\WikiArticle;
use Zolinga\System\Wiki\WikiText;
use Zolinga\System\Events\WikiRefIntegrationEvent;

class WikiRefArticle extends WikiArticle
{
    public readonly float $priority;

    public function __construct(string $uri)
    {
        $this->priority = 1;
        parent::__construct($uri, null);
        $this->title = "Zolinga Explorer";

        $this->contentFiles[] = new WikiText(<<<EOT
        # Zolinga Explorer

        This part of the documentation outlines key system features. It's largely auto-generated from the source code and provides a real-time perspective of how Zolinga Core perceives the system.
        EOT, WikiText::MIME_MARKDOWN);
    }

    protected function initChildren(): void
    {
        $this->addChild(
            new WikiEventListArticle(":ref:event"),
            new WikiServiceListArticle(":ref:service"),
            new WikiWebComponentListArticle(":ref:wc"),
            new WikiAutoloadArticle(":ref:class")
        );

        // Integrations
        $discovery = new WikiRefIntegrationEvent(
            "system:wiki:ref:discovery", 
            WikiRefIntegrationEvent::ORIGIN_INTERNAL
        );
        $discovery->dispatch();

        foreach ($discovery->articles as $article) {
            $this->addChild($article);
        }
    }
}
