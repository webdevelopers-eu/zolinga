<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

use Zolinga\System\Types\{StatusEnum, SeverityEnum, OriginEnum};
use JsonSerializable;

/**
 * Represents a request to generate a document for page content.
 *
 * 
 * Handlers are expected to populate the $event->content property with HTML content to be sent to the browser.
 * 
 * If a handler produces its own output such as binary data or images, it should call $event->preventDefault()
 * to prevent the default HTML output. Optionally, $event->stopPropagation() can be called immediately after to prevent
 * further processing of the event if HTML output is not intended.
 * 
 * It is recommended to avoid directly outputting anything from the handler, unless it is necessary. 
 * Instead, store the output in the `$event->content` property. This allows for further filters and 
 * content modifications by other lower priority handlers.
 * 
 * The $event->path property is writable, allowing handlers with higher priority to rewrite the path. Subsequent
 * handlers can then react to the new path. Therefore, it is advised to use $event->path instead of directly
 * reading $_SERVER['REQUEST_URI'].
 * 
 * When the event status is $event->status == ContentEvent::STATUS_UNDETERMINED, it indicates that the content has not been added
 * by any handler yet. If the status is ContentEvent::STATUS_OK, it means that the content has already been added
 * by the previous handler, and subsequent handlers should not replace it. Instead, they can only modify or filter the content.
 * So when you want to add content, check if the status is ContentEvent::STATUS_UNDETERMINED, and if yes and you have added
 * the content, set the status to ContentEvent::STATUS_OK.
 * 
 * The event is stoppable.
 */
class ContentEvent extends Event implements StoppableInterface
{
    use StoppableTrait;

    /**
     * The request URL path part without the query string and the trailing slash.
     * 
     * Root path is represented by an empty string. 
     * 
     * Example: "/api/v1/users"
     *
     * @var string
     */
    public string $path;

    public readonly \DOMDocument $content;

    /**
     * The URL path to the content.
     * 
     * @param mixed $path The URL path to the content.
     * @return void
     */
    public function __construct(mixed $path) {
        parent::__construct("system:content", self::ORIGIN_REMOTE);

        $this->content = new \DOMDocument('1.0', 'UTF-8'); 
        $this->content->formatOutput = false;
        $this->content->substituteEntities = false;
        $this->content->strictErrorChecking = false;
        $this->content->recover = true;
        $this->content->resolveExternals = false;
        $this->content->validateOnParse = false;
        $this->content->xmlStandalone = true;

        $this->path = rtrim($path ?: '', '/');
    }

    
    /**
     * Set the XML content using string.
     *
     * @param string $content The content to set.
     * @return void
     */
    public function setContent(string $content): void {
        $this->content->loadXML($content, LIBXML_COMPACT | LIBXML_NOCDATA | LIBXML_NONET | LIBXML_NSCLEAN) or throw new \Exception("Invalid XML content");
    }

    /**
     * Set the HTML content using string.
     *
     * @param string $content The HTML content to set.
     * @return void
     */
    public function setContentHTML(string $content): void {
        // LIBXML_NOERROR to suppress custom HTML tag warnings: Warning: DOMDocument::loadHTML(): Tag invalid wiki-search in Entity
        $content='<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$content; // Add meta tag to force UTF-8 encoding
        $this->content->loadHTML($content, LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOWARNING) or throw new \Exception("Invalid HTML content");
    }

    /**
     * Get the content as the XML string.
     *
     * @return string|false The content in XML format.
     */
    public function getContent(): string|false {
        return $this->content->saveXML(options: LIBXML_NOXMLDECL | LIBXML_NSCLEAN);
    }

    /**
     * Get the content as the HTML string.
     *
     * @return string|false The content in HTML format.
     */
    public function getContentHTML(): string|false {
        return $this->content->saveHTML();
    }

    /**
     * Check if the content is not empty and there are tags in the <body> tag.
     *
     * @return bool True if the content is NOT empty, false otherwise.
     */
    public function hasContent(): bool {
        // Check that it has child nodes and something is in <body> tag
        return $this->content->documentElement->hasChildNodes()
            && $this->content->getElementsByTagName('body')->item(0)?->hasChildNodes();
    }

    /**
     * Magic method to convert the object to JSON string.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array {
        return [
            ...parent::jsonSerialize(),
            'path' => $this->path,
            'content' => $this->getContent(),
        ];
    }

}