Priority: 0.9

# Zolinga Module Manifest

Each module has a `zolinga.json` file in its root that contains following information:

- `name` - Human readable name of the module. Note that the internally used "module name" is always the directory name in which the module is located.
- `version` - Version of the module. 
- `description` - Description of the module. For human consumption.
- `authors` - Array of authors. For human consumption.
- `attributes` - This is a JSON object that is currently not used by Zolinga Core. It is intended for custom attributes, future features, third party integrations, etc.
- `listen` - Array of event subscriptions. This is a crucial part of the Zolinga Core. It specifies what code to run in response to an event.
- `emit` - This section is purely informational and is used by Zolinga WIKI to generate documentation. It lists all the events that your module can emit.
- `autoload` - Array of PHP class name mappings to file paths. This is used by the Zolinga Core to autoload classes.
- `config` - This section is used to define configuration options for the module. It is merged with the global and local configuration files. For details see `$api->config` service.
- `webComponents` - this section declares what [HTML Web Component](https://developer.mozilla.org/en-US/docs/Web/API/Web_components) names are registered by the module.


## Listen Section

The `listen` section is an array of event subscriptions. Each subscription is an object with following properties:

- `description` - Human readable description of the event subscription. Will be used by Zolinga WIKI.
- `event` - Name of the event to listen to. This is a URI formatted string. E.g. `system:content:created`.
- `class` - Name of the PHP class that will handle the event. This class must implement the `\Zolinga\System\Events\ListenerInterface` interface.
- `method` - Name of the method that will handle the event. This method must be public and accept a single argument of type `\Zolinga\System\Events\Event`.
- `origin` - Subscribe only to events that originate from this source. The source is PHP Enumeration `\Zolinga\System\Types\OriginEnum`. Currently supported values are following.
    - `internal` - Events that were directly emitted by the Zolinga Core or another module.
    - `remote` - Events that originated from a remote source. E.g. a AJAX request, POST/GET requests, etc.
    - `cli` - Events that originated from a command line interface (CLI). E.g. by running `bin/zolinga my:event`
    - `custom` - future unexpected uses by third party modules not envisioned by the Zolinga Core programmers.
    - `\*` - Any origin. This handler will handle all named events despite their origin.
- `priority` - Optional. The priority is a float in the range of 0 to 1 (exclusive). The default priority is 0.5. The priority determines the order in which the event listeners are executed in response to an event. Higher numbers mean higher priority.

Example of a `listen` section:

```json
{
    "listen": [
        {
            "description": "This event is emitted when a new content is created.",
            "event": "system:content:created",
            "class": "\\Example\\System\\Events\\ContentCreatedListener",
            "method": "onContentCreated",
            "origin": ["internal", "remote],
            "priority": 0.6
        }
    ]
}
```

The Zolinga Core supports so-called services. These are classes that are instantiated once and aggregated as properties on global `$api` object and then shared across the whole application. E.g. The "log" service can be accessed as `$api->log->error(...)`.

The object that becomes a service is the listener with highest priority that listens to a service initialization event `system:service:{SERVICE_NAME}` event. The "log" service definition looks like this:

```json
  "listen": [
    {
      "description": "The system logger facility $api->log.",
      "event": "system:service:log",
      "class": "\\Example\\System\\Logger\\LogService",
      "origin": [
        "internal"
      ],
      "priority": 0.65
    }
  ]
```

Since this is a common declaration you can use a sugar syntax and instead of using "event" property you can use "service" property. The above example can be rewritten as:

```json
  "listen": [
    {
      "description": "The system logger facility $api->log.",
      "service": "log",
      "class": "\\Example\\System\\Logger\\LogService",
      "origin": [
        "internal"
      ],
      "priority": 0.65
    }
  ]
```

## Emit Section

The format is similar to "listen" section except for the "priority" and "method" properties that are not present in "emit" section. The "class" property declares the class of the event object. This information is used by Zolinga WIKI to [generate documentation](:ref:event).

Example:

```json
{
    "emit": [
        {
            "description": "This event is emitted when a new content is created.",
            "event": "system:content:created",
            "class": "\\Example\\System\\Events\\ContentCreatedEvent",
            "origin": ["internal"]
        }
    ]
}
```

## Autoload Section

The `autoload` section is an array of class name mappings to file paths. This is used by the Zolinga Core to autoload classes. The object keys are the class names and the values are the file paths. The file paths are relative to the module directory.

Example:

```json
{
    "autoload": {
        "Example\\System\\": "src/Events/",
        "Vendor\\SomeClass": "vendor/SomeClass.php"
    }
}
```

## Config Section

The `config` section is used to define **default** configuration options for the module. It is merged with the global and local configuration files. It is a JSON object that has not clearly defined structure. It is up to the module to define the structure of the configuration.

Example:

```json
{
    "config": {
        "db": {
            "host": "localhost",
            "user": "zolinga",
            "password": "zolinga"
        }
    }
}
```

The parsed and merged configuration sections are available through the `$api->config` object.

## WebComponents Section

The `webComponents` section declares what [HTML Web Component](https://developer.mozilla.org/en-US/docs/Web/API/Web_components) names are registered by the module. 

The syntax is simple:

```json
{
  "webComponents": [
    {
      "tag": "wiki-color",
      "description": "WIKI theme color picker",
      "module": "wiki/wiki-color.js"
    },
    {
      "tag": "wiki-login",
      "description": "WIKI authorization",
      "module": "wiki/wiki-login.js"
    }
  ]
}
```

- `tag` property is the name of the web component.
- `description` property is a human readable description of the web component.
- `module` property is the path to the ECMAScript module file that contains a default export class which will be registered as a web component. The path is relative to module's "dist" folder `{MODULE}/install/dist/` which is mapped to web server as `https://{DOMAIN}/dist/{MODULE}/`. 

For more information about web components see [MDN Web Components](https://developer.mozilla.org/en-US/docs/Web/Web_Components) and Zolinga [Web Components](:Zolinga Core:Web Components) article.