<?php
namespace Example\HelloWorld;

use Zolinga\System\Events\{RequestEvent,ContentEvent,ListenerInterface};
use \ArrayObject;

/**
 * This class contains two simple methods to handle events.
 * 
 * The first method, outputPage, handles the system:content event and generates the page output.
 * 
 * The second method, onHelloRequest, handles the URL POST or GET request 
 * ?hello[myVar1]=<value1>&hello[myVar2]=<value2>...
 * 
 * It also demonstrates the use of the service API $api->time to get the current time.
 * For more information on the time service API, see the TimeService.php in this directory
 * and its declaration in zolinga.json .
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-03
 */
class Page implements ListenerInterface
{
    /**
     * This will hold the request data from ?hello[...]=... URL POST or GET request.
     * @see onHelloRequest
     * @var ?iterable<string, mixed>
     */
    private ?iterable $requestData = null;

    /**
     * Array to store messages for page output.
     * @var array<string>
     */
    private array $messages = [];

    /**
     * Handles the system:content event and generates the page output.
     *
     * @param ContentEvent $event The event object
     * @return void
     */
    public function outputPage(ContentEvent $event): void
    {
        global $api;

        if ($event->status != ContentEvent::STATUS_UNDETERMINED) {
            // The event has already been handled by another listener with higher priority.
            // It is most likely that there is already a page generated from a different handler.
            // trigger_error("Hello World: The $event has already been handled by another listener with higher priority.", E_USER_NOTICE);
            return;
        }

        $output = "<html><head><title>Hello World</title></head><body>\n";
        $output .= "<h1>".htmlspecialchars($api->config['helloWorld']['myGreeting']) . "</h1>\n\n";
        $output .= "<p>Current time from \$api->time service: " . $api->time->getTime() . "</p>\n\n";
        // @var ArrayObject<string, mixed> $this->requestData
        $output .= "<p><a href='?hello[myParam]=myValue'>?hello[...]=...</a> Request data: " . htmlspecialchars(print_r($this->requestData, true)) . "</p>\n\n";
        $output .= "<p>Messages: " . htmlspecialchars(implode("<br>\n", $this->messages)) . "</p>\n\n";
        $output .= "</body></html>";

        $event->setContent($output);
        $event->setStatus(ContentEvent::STATUS_OK, "Page output successfully generated.");
    }
}
