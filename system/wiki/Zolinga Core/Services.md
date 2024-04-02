Priority: 0.75

# Services

Service in Zolinga framework is a singleton object that is created once and then shared across the system. Services are used to provide common functionality to the system. For example the `log` service is used to log messages to the log file. 

All services are accessible as properties of the `$api` global object. For example to access the `log` service you can use `$api->log->info('example', 'Hello, world!')`.

## Creating a Service

To create a service you need to create a class that implements the `Zolinga\System\Events\ServiceInterface` interface. The interface is completely empty and is used only to mark the class as a service. The `Zolinga\System\Events\ServiceInterface` in fact extends the `Zolinga\System\Events\ListenerInterface` because your service is also a listener that listens to the service event that is dispatched when the service is created.

In short, the system dispatches the *internal* `Zolinga\System\Events\Event` event of name `system:service:{SERVICE_NAME}` where `{SERVICE_NAME}` is the name of the service being accessed. For example the first call to `$api->log` will dispatch the [system:service:log](:ref:event:system:service:log) event and the subscriber with highest priority that listens to this event will be used as the service. 

Yes, it is that simple. Nothing new. Just [another event](:Zolinga Core:Events and Listeners).

Let's look how the `log` service is created in `zolinga.json`.

```json
{
    "listen": [
        {
            "description": "The system logger facility $api->log.",
            "service": "log",
            "class": "\\Zolinga\\System\\Logger\\LogService",
            "origin": [
                "internal"
            ],
            "priority": 0.5
        }
    ]
}
```

As you have noticed the `"service": "log"` is the syntax sugar for `"event": "system:service:log"`. You can use either of them interchangeably.

What happens is that the moment you reference `$api->log` the system dispatches the [system:service:log](:ref:event:system:service:log) event and the `\\Zolinga\\System\\Logger\\LogService` object is created and stored in the `$api->log` property.

# Related

- [Events and Listeners](:Zolinga Core:Events and Listeners)
- [List of Currently Available Services](:ref:service)