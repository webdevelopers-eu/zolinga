# Event Authorization

As mentioned in [Events and Listeners](:Zolinga Core:Events and Listeners), the `right` property in a listener declaration checks whether the user has the right to execute the event. This allows you to implement centralized rights checking for sensitive listeners.

Example:

```json
{
    "listen": [
        {
            "event": "example:get-secret",
            "description": "Requests very important secret.",
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

In this example, the `onRequest` method of the `Vault` class will only be called if the user has the right `see secret-page`. If the user does not have the right, the listener is skipped and the event's status is set to [unauthorized](:ref:class:Zolinga:System:Types:StatusEnum).

# Authorization Step

Authorization is done by dispatching the [system:authorize](:ref:event:system:authorize) event. The event object is of type `\Zolinga\System\Events\AuthorizeEvent` and has the `unauthorized` property that holds the array of right names to check. The event has the method `authorize(...$right)` that records that the user has the given right.

If the right does not match the user's rights, the event will be marked as unauthorized and the listener will be skipped. The event's status will be set to [unauthorized](:ref:class:Zolinga:System:Types:StatusEnum).

# Authorization Providers

By default there are no authorization providers (listeners on the *internal* event `system:authorize`) except for the WIKI authorization provider, which is only interested in the `system:wiki:read` right and ignores any other rights.

If you install the [Zolinga Rights Management System](https://github.com/webdevelopers-eu/zolinga-rms), the RMS Authorization Provider will check whether the currently logged-in user has the right listed in `$event->right`.

# Custom Authorization Providers

You can implement your own authorization provider. For example, consider this simple provider that checks if the user has the right `see secret-page`:

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

Your authorization provider should do one of the following:

- If you recognize any of the rights listed in `$event->unauthorized` and can confirm the user has the right, call `$event->authorize(...$right)`, OR
- Do nothing. Do not call `$event->authorize()` and do not set any status on the event.

With this example, all listeners that have the `right` property set to `see secret-page` will only be called if they pass your check (or any other authorization provider's check).

# Manual Authorization Checks

Sometimes you need to check a right outside of the normal event dispatch flow -- for example, when serving a resource, rendering a page, or performing a custom action. Use `$api->isAuthorized()`:

```php
global $api;

if (!$api->isAuthorized('see secret-page')) {
    // Not authorized
    return;
}
// Authorized: proceed
```

You can also pass an array of rights and get the `AuthorizeEvent` for closer inspection:

```php
global $api;

if (!$api->isAuthorized(['see secret-page', 'mcp:tools'], $authEvent)) {
    return $authEvent->status; // returns StatusEnum::UNAUTHORIZED or StatusEnum::FORBIDDEN
}
```

`$api->isAuthorized(string|array $rights, ?AuthorizeEvent &$authEvent = null): bool` dispatches the `system:authorize` event with the given right(s) and returns `true` if all rights are authorized, `false` otherwise. The optional second parameter gives you access to the `AuthorizeEvent` for closer inspection:

- `$authEvent->requiresLogin` -- whether failure should result in 401 (true) or 403 (false)
- `$authEvent->unauthorized` -- array of rights that were not satisfied
- `$authEvent->authorized` -- array of rights that were satisfied
- `$authEvent->status` -- `StatusEnum::OK` if no unauthorized rights exist, `UNAUTHORIZED` or `FORBIDDEN` otherwise
- `$authEvent->isAuthorized()` -- returns `true` if all rights are authorized (no parameters; same as the `$api->isAuthorized()` return value)

Note that `$api->isAuthorized()` is relatively expensive because it dispatches a pluggable `system:authorize` event that may span multiple authorization subsystems. If you know your rights are managed exclusively by the [Zolinga RMS](https://github.com/webdevelopers-eu/zolinga-rms) module, prefer using its optimized methods directly (e.g. `$api->user->hasRight($right)`) instead of this robust system.

# Notes

- The event is [stoppable](:ref:class:Zolinga:System:Events:StoppableInterface). When you call `$event->authorize()` and there are no rights left to check, propagation is automatically stopped.
- The `system:authorize` listener declaration cannot use the `right` property, as it would result in an infinite loop. You cannot require authorization to dispatch the authorization event to a listener.

# Related

- [List of Authorization Providers](:ref:event:system:authorize)
- `Zolinga\System\Events\AuthorizeEvent`
- [Events and Listeners](:Zolinga Core:Events and Listeners)
