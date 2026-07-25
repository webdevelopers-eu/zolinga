# Authorization Event

This page lists all the Authorization Providers (listeners listening to *internal* event `system:authorize`) that are currently available.

The Authorization Provider is supposed to go through the list of `$event->unauthorized` rights and call `$event->authorize(...$events)` for all the rights that currently logged in user has.

For more information refer to [Event Authorization](:Zolinga Core:Events and Listeners:Event Authorization) article. This event can be used to check any rights for any purpose. It is not limited only to Event Authorization.

For manual authorization checks outside the event dispatch flow, see [Manual Authorization Checks](:Zolinga Core:Events and Listeners:Event Authorization#manual-authorization-checks).