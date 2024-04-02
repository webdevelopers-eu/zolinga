<?php

declare(strict_types=1);

namespace Example\Acme;

use Zolinga\System\Events\{RequestResponseEvent, RequestEvent, ListenerInterface, ServiceInterface};

/**
 * This class is a skeleton for a module.
 * 
 * @author John Doe <john@example.com>
 */
class AcmeListener implements ListenerInterface /* if you need to act as a service then ServiceInterface */
{
    /**
     * Request event listener to ?acme=... POST/GET requests 
     * 
     * @param RequestEvent $event
     * @return void
     */
    public function onAcmeRequest(RequestEvent $event): void
    {
        echo "Hello, " . ($event->request['name'] ?: 'ACME') . "!\n";
        echo "ACME Request: " . json_encode($event->request) . "\n";
        $event->setStatus($event::STATUS_OK, "Hello, Acme!");
    }

    /**
     * Will respond to AJAX, internal or CLI "example:acme" event.
     * 
     * E.g. 
     *
     *  bin/zolinga example:acme --name=John
     * 
     * @param RequestResponseEvent $event
     * @return void
     */
    public function onAcmeEvent(RequestResponseEvent $event): void
    {
        $event->response['acme'] = "Hello, " . ($event->request['name'] ?? 'unknown') . "!";
        $event->setStatus($event::STATUS_OK, "Hello, Acme!");
    }
}
