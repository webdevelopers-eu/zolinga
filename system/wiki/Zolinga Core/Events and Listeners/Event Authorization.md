# Event Authorization

As mentioned in [Events and Listeners](:Zolinga Core:Events and Listeners), the `right` property in the listener declaration is used to check if the user has the right to execute the event. This is a very powerful feature that allows you to implement centralized rights checking in your application for sensitive listeners.

Example:

```json
{
    "listen": [
        {
            "event": "example:get-secret",
            "description": "Rquests very important secret.",
            "class": "\\Example\\Vault",
            "method": "onRequest",
            "origin": [
                "remote"
            ],
            "right": "see secret-page"
        }
    ]
}
```

In this example, the `onRequest` method of the `Vault` class will be called only if the user has the right `see secret-page`. If the user does not have the right, the listener will be skipped and event's status will be set to [unauthorized](:ref:class:Zolinga:System:Types:StatusEnum) .

# Authorization Step

The authorization is done by dispatching the [system:authorize](:ref:event:system:authorize) event. The event object is of type `\Zolinga\System\Events\AuthorizeEvent` and has the `unauthorized` property that holds the array of right names to check. The event has the method `authorize(...$right)` that records that user has given right.

In case that the right does not match the user's rights, the event will be marked as unauthorized and the listener will be skipped. The event's status will be set to [unauthorized](:ref:class:Zolinga:System:Types:StatusEnum) .

# Authorization Providers

By default there are no authorization providers (listeners listening to *internal* event `system:authorize`) except for WIKI authorization provider that is interested only in the "system:wiki:read" right and will ignore any other rights.

If you install the [Zolinga Rights Management System](https://github.com/webdevelopers-eu/zolinga-rms) then the RMS Authorization Provider will try to check if currently logged user has the right listed in `$event->right` property.

# Custom Authorization Providers

You can implement your own authorization provider. For example consider this simple authorization provider that checks if the user has the right `see secret-page`:

`zolinga.json`

```json
{
    "listen": [
        {
            "event": "system:authorize",
            "description": "Checks if user has the right 'see secret-page'.",
            "class": "\\Example\\AuthorizationProvider",
            "method": "onAuthorize",
            "origin": [
                "internal"
            ]
        }
    ]
}
```

The implementation of the `onAuthorize` method in the `AuthorizationProvider` class:

```php
namespace Example;
use Zolinga\System\Events\AuthorizeEvent;
use Zolinga\System\Events\ListenerInterface;

class AuthorizationProvider implements ListenerInterface
{
    public function onAuthorize(AuthorizeEvent $event)
    {
        foreach($event->unauthorized as $right) {
            if ($right == 'see secret-page' && !empty($_SESSION['isSuperuser'])) {
                $event->authorize($right);
            }
        }
    }
}
```

Note that your authorization provider is supposed to do one of the following:

- If you recognize any of rights listed in `$event->unauthorized` array, and you can confirm that the user has the right, call `$event->authorize(...$right)` method, OR
- do nothing. Do not call `$event->authorize()` method, and do not set any status on the event. 

Considering this example all listeners that have the `right` property set to `see secret-page` will be called only if they pass your check (or possibly any other's authorization provider check).

# Notes 

- The event is [stoppable](:ref:class:Zolinga:System:Events:StoppableInterface) and when you call `$event->authorize()` method and there are no rights left to check the propagation will be automatically stopped.
- The "system:authorize" listener declaration cannot use "right" property as it would result in loop. You cannot request authorization to dispatch authorization event to a listener...


# Related

- [List of Authorization Providers](:ref:event:system:authorize) 
- `Zolinga\System\Events\AuthorizeEvent`
- [Events and Listeners](:Zolinga Core:Events and Listeners)
