Priority: 0.4

# Processing Page Content

Any requests for non-existent resources are routed to the `./public/index.php` script. 

After the [POST and GET requests are processed](:Zolinga Core:Running the System:Page Request:Processing POST and GET), the `./public/index.php` script will dispatch the `\Zolinga\System\Events\ContentEvent` of type [system:content](:ref:event:system:content) with the origin set to *remote*.

Any listeners attached to this event will be executed in the order of their priority. Listeners are expected to populate the DOMDocument `$event->content` property with HTML content to be sent to the browser. 

If a handler produces its own output such as binary data or images, it should call `$event->preventDefault()` to prevent the default HTML output of `$event->content`. Optionally, $event->stopPropagation() can be called immediately after to prevent further processing of the event if HTML output is not intended.

It is recommended to avoid directly outputting anything from the handler, unless it is necessary. Instead, store the output in the `$event->content` property. This allows for further filters and content modifications by other lower priority handlers.

The `$event->path` property is writable, allowing handlers with higher priority to rewrite the path. Subsequent handlers can then react to the new path. Therefore, it is advised to use `$event->path` to detect the page URL rather then reading `$_SERVER['REQUEST_URI']` directly.

When the event status is `$event->status == ContentEvent::STATUS_UNDETERMINED`, it indicates that the content has not been added by any handler yet. If the status is `ContentEvent::STATUS_OK`, it means that the content has already been added by the previous handler, and subsequent handlers should not replace it. Instead, they can only modify or filter the content. So when you want to add content, check if the status is `ContentEvent::STATUS_UNDETERMINED`, and if yes and you have added the content, set the status by calling `$event->setStatus($event::STATUS_OK, "Content added.")`.

# Example

`zolinga.json`

```json
{
    "listen": [
        {
            "event": "system:content",
            "class": "Example\\ContentHandler",
            "method": "onContent",
            "origin": ["remote"],
            "priority": 0.6
        }
    ]
}
```

`ContentHandler.php`

```php
namespace Example;
use Zolinga\System\Events\ContentEvent;
use Zolinga\System\Events\ListenerInterface;
use \DOMDocument;

class ContentHandler implements ListenerInterface
{
    public function onContent(ContentEvent $event)
    {
        if ($event->status !== ContentEvent::STATUS_UNDETERMINED) {
            return; // already filled with content by other listener
        }

        $path = $event->path;
        /** @var \DOMDocument $content */
        $content = $event->content;

        if ($path == "/") {
            $content->loadHTML("<html><body><h1>Welcome to Zolinga</h1></body></html>");
            $event->setStatus($event::STATUS_OK, "Content added.");
        }
    }
}
```

# Related
{{Running the System}}