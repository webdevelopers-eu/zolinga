/**
 * Event class
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-02-09
 */
class Event {
    /**
     * Event type in the format of a URI. Example: example.org:api:myEvent
     *
     * @var {string}
     */
    #type;

    /**
     * Origin of the event. Can be internal, remote, or cli.
     * Note it is an enum and not a string, so to access the value use event.origin.value.
     *
     * @var {OriginEnum}
     */
    #origin = 'remote'; // Always remote by default for JS API

    /**
     * Status of the event.
     *
     * @var {StatusEnum}
     */
    #status;

    /**
     * Status message.
     *
     * @var {string|null}
     */
    #message = null;

    request;
    response;

    // Enum shortcuts
    static STATUS_ERROR = 500;
    static STATUS_NOT_FOUND = 404;
    static STATUS_OK = 200;
    static STATUS_UNAUTHORIZED = 401;
    static STATUS_FORBIDDEN = 403;
    static STATUS_BAD_REQUEST = 400;
    static STATUS_UNDETERMINED = 0;

    // Is not used on the client side, just for compatibility with server side Event class
    static ORIGIN_INTERNAL = 'internal';
    static ORIGIN_REMOTE = 'remote';
    static ORIGIN_CLI = 'cli';

    static api = null;

    constructor(type, request = {}) {
        this.#type = type;
        this.#origin = Event.ORIGIN_REMOTE;
        this.request = request;
        this.#status = Event.STATUS_UNDETERMINED;
        this.uuid = 'xxxx-xxxx-xxxx-xxxx'.replace(/x/g, (c) => (Math.random() * 16 | 0).toString(16));
    }

    get type() {
        return this.#type;
    }

    get origin() {
        return this.#origin;
    }

    get status() {
        return this.#status;
    }

    get message() {
        return this.#message;
    }

    get ok() {
        return this.#status === Event.STATUS_OK;
    }

    get error() {
        return this.#status === Event.STATUS_ERROR;
    }

    get undetermined() {
        return this.#status === Event.STATUS_UNDETERMINED;
    }

    get unauthorized() {
        return this.#status === Event.STATUS_UNAUTHORIZED;
    }

    get forbidden() {
        return this.#status === Event.STATUS_FORBIDDEN;
    }

    setStatus(status, message) {
        if (this.#status === Event.STATUS_UNDETERMINED || this.#status === Event.STATUS_OK) {
            this.#status = status;
            this.#message = message;
        }
        return this.#status;
    }

    isTrusted() {
        return this.#origin === Event.ORIGIN_INTERNAL || this.#origin === Event.ORIGIN_CLI;
    }

    async dispatch() {
        // Assuming there is a global API object
        return await Event.api.dispatchEvent(this);
    }
}

export default Event;
