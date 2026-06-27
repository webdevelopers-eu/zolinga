<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

use Zolinga\System\Types\{StatusEnum, SeverityEnum, OriginEnum};
use JsonSerializable;

/**
 * Represents a request to generate a document for page content.
 *
 * This is a generic parent class for content events of various types, such as HTML, JSON, plain text,
 * and other content formats. Concrete subclasses define the specific content type (e.g. HtmlContentEvent,
 * JsonContentEvent), while the behavior described here applies to all of them.
 * 
 * Handlers are expected to populate the $event->content property with the appropriate content for the
 * given content type (e.g. HTML markup for browsers, a JSON payload for API responses, plain text, etc.)
 * to be sent to the client.
 * 
 * If a handler produces its own output such as binary data or images, it should call $event->preventDefault()
 * to prevent the default output of the corresponding content type. Optionally, $event->stopPropagation() can be
 * called immediately after to prevent further processing of the event if the default output is not intended.
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
abstract class ContentEvent extends Event implements StoppableInterface
{
    use StoppableTrait;

/**
     * The request URL path part without the query string and the trailing slash.
     * This path is writable, allowing handlers to rewrite it. Handlers should use this property 
     * instead of directly reading $_SERVER['REQUEST_URI'] to determine the content to generate, 
     * as it may have been rewritten by higher priority handlers.
     * 
     * The $this->canonicalPath property can be used to set the canonical path for the content
     * The $this->originalPath property contains the original request path before any rewriting, and is read-only. 
     * 
     * Root path is represented by an empty string. 
     * 
     * Example: "/api/v1/users"
     * 
     * Note, this path can be rewritten by handlers, so it may not be the same as the original request path. 
     *
     * @var string
     */
    public string $path;

    /**
     * Canonical path to the content, if applicable. 
     * At the beginning it is the same as $this->path, but handlers can set it to the canonical path of the content
     * if the content can be accessed by multiple paths. This allows for better SEO and consistent URLs.
     * 
     * This path should be used for generating links to the content, while $this->path should be used for determining
     * what content to generate based on the request URL.
     *
     * @var string
     */
    public string $canonicalPath;

    /**
     * The original request URL path part without the query string and the trailing slash
     * before any rewriting by handlers. 
     * 
     * Root path is represented by an empty string. 
     * 
     * Example: "/api/v1/users"
     *
     * @var string
     */
    public readonly string $originalPath;
    
    /**
     * Constructs a new ContentEvent.
     *
     * @param string $name The name of the event.
     * @param OriginEnum $origin The origin of the event (local or remote).
     * @param mixed $path The URL path to the content.
     */
    public function __construct(string $name, OriginEnum $origin, string $path) {
        parent::__construct($name, $origin);

        $this->path = rtrim($path ?: '', '/');
        $this->originalPath = $this->path;
        $this->canonicalPath = $this->path;
    }

    public function setStatus(StatusEnum $status, string $message): StatusEnum
    {
        global $api;

        $ret = parent::setStatus($status, $message);

        if (!in_array($this->status, [StatusEnum::UNDETERMINED, StatusEnum::OK])) {
            $callerInfo = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
            $callerInfo2 = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[0] ?? null;
            $caller = "" . ($callerInfo['class'] ?? '') . ($callerInfo['type'] ?? '') . ($callerInfo['function'] ?? '');
            $location = basename($callerInfo2['file'] ?? 'unknown') . ":" . ($callerInfo2['line'] ?? 'unknown');
            $api->log->log(
                $this->error ? SeverityEnum::ERROR : SeverityEnum::INFO,
                'system:content', 
                "ContentEvent status set to {$this->status->value} by $caller in $location with message: $message"
            );
        }

        return $ret;
    }

    /**
     * Get string representing the contents that will be sent to the browser or other client.
     *
     * @return string|false The content in the appropriate format for the content type (e.g. HTML, JSON, plain text).
     */
    abstract public function getOutput(): string | false;

    /**
     * Get the content in the serializable format for json or other purposes. 
     *
     * @return mixed The content in the appropriate format for the content type string for HTML, object for JSON...
     */
    abstract public function getContent(): mixed;

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