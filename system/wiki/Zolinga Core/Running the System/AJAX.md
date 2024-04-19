# AJAX

Sending requests and receiving responses from the server is trivial. You can use the following code to send a request to the server:

```javascript
import api from '/dist/system/api.js';

const event = await api.dispatchEvent('example:test', { data: 'Hello, World!' });
```

As you would guess, Javascript will make a request to the server and wait for the response. The server will repack the AJAX request into a `\Zolinga\System\Events\RequestResponseEvent` and dispatch it to the system from *remote* origin. The system will process the request and return the response back to the client.

Simple.

Although it is recommended to strictly build your new application using ECMA6 modules, you can still use non-modular approach to include the `api.js` file in your project using `import()` function. This is how you can do it:

```javascript
function dispatchEvent(name, data) {
    return import('/dist/system/api.js')
        .then(exported => {
            return exported.default.dispatchEvent(name, data);
        });
}

dispatchEvent('example:test', { data: 'Hello, World!' })
    .then(event => {
        console.log("RECEIVED", event.status, event.message, event.response);
    });
```

## Listening To Event Responses

After the server processes the request, it will dispatch a response event back to the client. You can listen to the response event the same way you would listen to any Web Component events either using
`WebComponent.listen("event-response:" + eventType, callback)` or `API.listen("event-response:" + eventType, callback)`. E.g.:

```javascript
import api from '/dist/system/api.js';

api.listen("event-response:example:test", eventData => {
    console.log("GLOBAL LISTENER RECEIVED", eventData.status, eventData.message, eventData.response);
});

api
    .dispatchEvent('example:test', { data: 'Hello, World!' })
    .then(event => {
        console.log("INITIATOR RECEIVED", event.status, event.message, event.response);
    });
```

Refer to [WebComponent](:Zolinga Core:Web Components:WebComponent Class) for more information on how to listen to events.

## Broadasting

The [WebComponent](:Zolinga Core:Web Components:WebComponent Class) has ability to broadcast data using `WebComponent.broadcast(name, detail = null, global = false)` method. You can use the same method on the `API` object to broadcast data to all listeners. And you can subscribe to the broadcasted messages using `API.listen(name, callback)` the same way you would do it on the WebComponent. 

```javascript
import api from '/dist/system/api.js';

api.listen("example:broadcast", eventData => {
    console.log("LISTENER", eventData);
});

api.broadcast("example:broadcast", { data: 'Hello, World!' }, true /* to all windows */);
```

# Server Side Processing

You can declare your event listeners the [usual way](:Zolinga Core:Events and Listeners). Do not forget to add the `remote` to `zolinga.json`'s listener declaration.

Example: 

```json
{
    "listen": [
        {
            "description": "Example test event",
            "event": "example:test",
            "class": "Example\\Test",
            "origin": ["remote"]
        }
    ]
}
```

# Security

Always keep in mind that any event from the `remote` source is inherently insecure. You should always check the data and be aware of the origin of the request.

It is very common that half of the AJAX events will need to have some kind of authorization one way or another. You can use the `right` property in the listener declaration to check if the user has the right to execute the event. For more read the [Event Authorization](:Zolinga Core:Events and Listeners:Event Authorization) article.


# Related
{{Running the System}}