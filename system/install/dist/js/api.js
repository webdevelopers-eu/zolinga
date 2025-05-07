import Event from './event.js';

/**
* The Zolinga API javascript object.
* 
* Example:
* 
* import {default as zolinga} from '/dist/system/zolinga.js';
* const event = new zolinga.Event('example.org:api:myEvent', requestData);
* event.dispatch(); // same as API.dispatchEvent(event);
* console.log("Event", event.response);
* 
* @author Daniel Sevcik <danny@zolinga.net>
*/
class Api {
    /**
    * The API_GATE constant is the URL of the API gateway.
    */
    API_GATE = '/dist/system/gate/';

    /**
    * The broadcast channel to send and receive messages.
    */
    #broadcast;

    /**
    * The list of listeners for broadcast messages.
    */
    #listeners = new Set();

    /**
    * Event class used to instantiate new events.
    */
    // Event = Event;

    /** 
    * Event class used to instantiate new events.
    */

    constructor() {
        this.Event = Event;
        this.Event.api = this;

        this.#broadcast = new BroadcastChannel('zolinga');
        this.#broadcast.addEventListener('message', this.#onMessage.bind(this));
        window.addEventListener('message', this.#onMessage.bind(this));
    }

    /**
    * 
    * 
    * Simple AJAX gateway for sending authentication requests.
    * 
    * Example:
    * 
    * import {default as zolinga} from '/dist/system/zolinga.js';
    * const event = new zolinga.Event('example.org:api:myEvent', requestData);
    * event.dispatch(); // same as API.dispatchEvent(event);
    * console.log("Event", event.response);
    * 
    * @param {Event|String} event or event type
    * @param {Object|null} data if event is a string, this is the request data, Event object will be constructed from this 
    * @returns {Promise} resolved with Event object
    */
    async dispatchEvent(event, data = null) {
        if (typeof event === 'string') {
            event = new this.Event(event, data ?? {});
        }

        try {
            // AJAX request using fetch
            const op = ((data && data.op) || null);
            const info = `${event.type}${op && '/' + op || ''}${data.id && ':' + data.id || ''}`;
            const response = await fetch(`${this.API_GATE}?${info}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify([{
                    uuid: event.uuid,
                    type: event.type,
                    origin: event.origin,
                    request: event.request
                }])
            });

            if (!response.ok) {
                throw new Error('AJAX API: Network response was not ok', { "cause": response });
            }

            const responseDataAll = await response.json();
            if (!responseDataAll || !responseDataAll.length) {
                throw new Error('AJAX API: No data received from server', { "cause": response });
            }
            const responseData = responseDataAll[0];
            if (!responseData) {
                throw new Error('AJAX API: No Event response received from server', { "cause": response });
            }
            if (event.uuid !== responseData.uuid) {
                throw new Error('AJAX API: Response UUID does not match request UUID', { "cause": response });
            }

            event.setStatus(responseData.status, responseData.message);
            event.response = responseData.response;
            event.request = data;

            console.log('AJAX API Event %s (%s) with Response:', event.type, op, event);
            this.broadcast('event-response:' + event.type, event);

            return event;
        } catch (error) {
            // Print raw output
            console.error('AJAX API Error:', error, event, data);
            throw error;
        }
    }

    /**
       * Send a broadcast message to all subscribers of the name.
       * The broadcast message will not trigger the listeners in this object.
       *
       * @param {String} name Event name that will be broadcasted.
       * @param {Object} detail Serializable object that will be broadcasted. See BroadcastChannel.postMessage() for more information.
       * @param {boolean} global Send the name to all subscribers in all windows, not just in the current window.
       * @returns {Api} this object for chaining
       */
    broadcast(name, detail = null, global = false) {
        const payload = {
            name,
            "detail": typeof detail?.toJSON === 'function' ? detail.toJSON() : detail,
            "source": null
        };

        const origin = window.location.origin;
        if (global) {
            // We want to receive it in this.#broadcast so we create new BroadcastChannel
            const broadcast = new BroadcastChannel('zolinga');
            broadcast.postMessage(payload);
            broadcast.close();
        } else {
            window.postMessage(payload, window.location.origin);
        }

        return this;
    }

    /**
       * Listen to a broadcast message with the given name.
       *
       * @param {String} name Broadcast name to listen to.
       * @param {Function} callback the callback function that will be called when the broadcast message is received.
       * @returns {Api} this object for chaining
       */
    listen(name, callback) {
        this.#listeners.add({ name, callback });
        return this;
    }

    #onMessage(ev) {
        if (ev.origin !== window.location.origin) {
            return;
        }

        const name = ev.data.name;
        const detail = ev.data.detail;

        this.#listeners.forEach((listener) => {
            if (listener.name === name) {
                listener.callback(detail);
            }
        });
    }
};


const api = new Api();

export default api;