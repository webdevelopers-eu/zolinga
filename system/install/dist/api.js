import Event from './lib/event.js';

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
     * Event class used to instantiate new events.
     */
    // Event = Event;

    /** 
    * Event class used to instantiate new events.
    */

    constructor() {
        this.Event = Event;
        this.Event.api = this;
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
            const response = await fetch(this.API_GATE, {
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

            const data = await response.json();
            if (!data || !data.length) {
                throw new Error('AJAX API: No data received from server', { "cause": response });
            }
            const responseData = data[0];
            if (!responseData) {
                throw new Error('AJAX API: No Event response received from server', { "cause": response });
            }
            if (event.uuid !== responseData.uuid) {
                throw new Error('AJAX API: Response UUID does not match request UUID', { "cause": response });
            }

            event.setStatus(responseData.status, responseData.message);
            event.response = responseData.response;

            console.log('AJAX API Response:', responseData);
            return event;
        } catch (error) {
            // Print raw output
            console.error('AJAX API Error:', error, event, data);
            throw error;
        }
    }
};


const api = new Api();

export default api;