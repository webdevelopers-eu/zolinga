Priority: 0.8

# Events and Listeners

The Zolinga Core is an event-driven system. The system is designed to be modular and extensible.

Dispatching and processing events is how things get done in Zolinga for the most part. For example, when you visited this page, an `\Zolinga\System\Events\ContentEvent` named [system:content](:ref:event:system:content) marked as being from  `remote` origin was emitted by `index.php` which jumpstarted the process of rendering this page.

The Zolinga event model is losely modeled after the Javascript event model. The named event is dispatched and it is represented by Event object. The event object is passed to the listeners. When the event is dispatched, the listeners are called in the order of their priority.

The listeners are defined in `zolinga.json` manifest file in your module's root directory. The `listen` section is an array of listener declarations.

## Listen Section

This is the example of the `listen` section:

```json
{
    "listen": [
        {
            "event": "system:content",
            "description": "This listener listens to the system:content event.",
            "class": "\\Example\\System\\Listeners\\ContentListener",
            "method": "onContent",
            "origin": [
                "remote"
            ],
            "priority": 0.9,
            "right": "see secret-page"
        }
    ]
}
```

When the page is visited and `index.php` fires [system:content](:ref:event:system:content) event represented by `\Zolinga\System\Events\ContentEvent` object, the `onContent` method of `ContentListener` class is called. Since in the example the priority is pretty high (0.9), this listener will be probably called before other listeners that listen to the same event.

- `event`
> The URI name of the event. It can be anything. Good practice is to prefix the event with the module name. Components are usualy separated by colon.
- `service`
> This is a syntax sugar for the `event` property. The `"event": "system:service:myService"` is equivalent to `"service": "myService"`. Automatically adds "internal" origin. See [more](:Zolinga Core:Services).
- `request`
> This is a syntax sugar for the `event` property. The `"event": "system:request:myRequest"` is equivalent to `"request": "myRequest"`. Automatically adds "remote" origin. See [more](:Zolinga Core:Running the System:Page Request).
- `description`
> A short description of the listener. You will see this in the Zolinga WIKI documentation for that event in [Zolinga Core / Events](:ref:event) section.
- `class`
> The class name of the listener. This is the class that contains the method that will be called when the event is dispatched.
- `method`
> Optional. The method name that will be called when the event is dispatched. The method must accept the object of type `\Zolinga\System\Events\Event` object as the first argument.
- `origin`
> Optional. The priority is a float in the range of 0 to 1 (exclusive). The default priority is 0.5. The priority determines the order in which the event listeners are executed in response to an event. Higher numbers mean higher priority.
> An array of origins. The listener will be called only if the event origin is in this array. The origin is set when the Event object is created. The origin is PHP Enum object `\Zolinga\System\Types\OriginEnum`. The possible values are:
> - `internal` - The event and its data originate from trusted source - the application itself.
> - `remote` - the event or its data originate from a remote source. E.g. a POST/GET request, AJAX request, etc.
> - `cli` - the event or its data originate from a trusted source - command line interface (CLI). E.g. by running `bin/zolinga my:event`
> - `custom` - future unexpected uses by third party modules not envisioned by the Zolinga Core programmers.
> - `*` - Any origin. This handler will handle all named events despite their origin.
- `priority`
> Optional. The priority is a float in the range of 0 to 1 (exclusive). The default priority is 0.5. The priority determines the order in which the event listeners are executed in response to an event. Higher numbers mean higher priority.
- `right`
> Optional. The listener will be called only if the user has the mentioned right. The implementation of right checking is left to application. What Zolinga Core does is it fires yet another event [system:authorize](:ref:event:system:authorize) and if the event authorizes the user the listener is called otherwise it is skipped. This way you can implement centralized rights checking in your application for sensitive listeners. For details refer to `\Zolinga\System\Events\AuthorizeEvent` class.

## Stoppable Events

Some events may implement the `\Zolinga\System\Events\StoppableInterface` interface. This interface will allow you to prevent other listeners to receive the event. The event will be stopped when the `stopPropagation` method is called on the event object.

The [system:content](:ref:event:system:content) event is stoppable. If the `onContent` method of `ContentListener` class calls `$event->stopPropagation()` method, the other listeners that listen to the [system:content](:ref:event:system:content) event will not be called.

Example of stoppable event Class:

```php
namespace Example\System\Events;

use Zolinga\System\Events\StoppableInterface;
use Zolinga\System\Events\StoppableTrait;
use Zolinga\System\Events\Event;

class MyEvent extends Event implements StoppableInterface
{
    use StoppableTrait;
}
```

## Dispatching Events

You can dispatch events from your code. The event is dispatched by creating an instance of the event object and calling the `dispatch` method on the that object.

Example of dispatching event:

```php
use Zolinga\System\Events\Event;

$event = new Event('example:event', Event::ORIGIN_INTERNAL);
$event->dispatch();

echo $event->status; // Number
echo $event->message; // "Done."?

```

## Standard Events

You can create and dispatch any custom Event that extends `\Zolinga\System\Events\Event` class. However, Zolinga Core provides some standard event classes that you can use out of the box. The standard events are defined in the `Zolinga\System\Events` namespace.

The standard event classes.

- `\Zolinga\System\Events\Event`
    - The base, most simple event class. It has no special properties or methods. It is used as a base class for all other events.
- `\Zolinga\System\Events\RequestEvent`
    - This is the extension of the `\Zolinga\System\Events\Event` class. It has the `request` property that is an array. This event can be used for any events that need to carry the request data but do not expect any response data. 
    - Zolinga Core uses this event class for POST or GET requests. See more in [Zolinga Core/Running the System/Page Request](:Zolinga Core:Running the System:Page Request) section.
- `\Zolinga\System\Events\RequestResponseEvent`
    - This is the extension of the `\Zolinga\System\Events\RequestEvent` class. In addition to `request` property, it has the `response` property that is an array. This event can be used for any events that need to carry the request data and expect some response data.
    - Primarily for internal and CLI events that require request and response data but don't need browser-specific functionality.
- `\Zolinga\System\Events\WebEvent`
    - This extends the `\Zolinga\System\Events\RequestResponseEvent` class.
    - Includes `broadcastBack()` method for sending client-side events back to the browser that initiated the request.
    - Specifically designed for web-based AJAX requests and responses.
    - Zolinga Core uses this event class for AJAX requests. See more in [Zolinga Core/Running the System/AJAX](:Zolinga Core:Running the System:AJAX) section.
- `\Zolinga\System\Events\AuthorizeEvent`
    - This special event is supposed to be dispatched when you want to check if the user has the right to do something. The event has the `right` property that is a string and holds the right name. It has the method `AuthorizeEvent::authorize()` that sets the status OK on the event which signalizes to emitter that the user has the right.
    - The event is dispatched by the Zolinga Core when the `right` property is set in the listener declaration in `zolinga.json`. See details in `\Zolinga\System\Events\AuthorizeEvent` class.