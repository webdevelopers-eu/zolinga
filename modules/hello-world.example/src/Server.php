<?php

declare(strict_types=1);

namespace Example\HelloWorld;
use Zolinga\System\Events\RequestResponseEvent;

class Server
{
    public function __construct()
    {
    }

    public function onHelloRequest(RequestResponseEvent $event):void
    {
        $event->response['hello'] = "world";
        $event->setStatus($event::STATUS_OK, "Hello World");
    }
}