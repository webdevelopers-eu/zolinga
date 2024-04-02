Priority: 0.9

# Authorization of Event Dispatching

The `Zolinga\System\Events\AuthorizeEvent` of type "system:authorize" is dispatched when the event is about to be executed and the target listener has the `right` property set in its declaration inside `zolinga.json`.

For more information about authorization events refer to [Event Authorization](:Zolinga Core:Events and Listeners:Event Authorization) article.