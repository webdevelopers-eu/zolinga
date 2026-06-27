<?php

declare(strict_types=1);

namespace Zolinga\System\Events\Content;

use Zolinga\System\Types\{StatusEnum, SeverityEnum};
use Zolinga\System\Events\{ContentEvent, StoppableInterface, StoppableTrait};

/**
 * Represents a request to generate plain text content for a page response.
 *
 * Handlers are expected to populate the $event->response property with a string of plain text
 * to be sent to the client.
 *
 * If a handler produces its own output such as binary data or images, it should call $event->preventDefault()
 * to prevent the default text output. Optionally, $event->stopPropagation() can be called immediately after to prevent
 * further processing of the event if text output is not intended.
 *
 * It is recommended to avoid directly outputting anything from the handler, unless it is necessary.
 * Instead, store the output in the `$event->response` property. This allows for further filters and
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
class TextContentEvent extends ContentEvent
{
    public string $response = '';

    /**
     * 
     * @param mixed $path The URL path to the content.
     * @return void
     */
    public function __construct(mixed $path) {
        parent::__construct("system:content:text", self::ORIGIN_REMOTE, $path);
    }

    public function getContent(): string {
        return $this->response;
    }
    
    public function getOutput(): string {
        return $this->response;
    }
}