<?php

namespace Example\HelloWorld;

use Zolinga\System\Events\{Event,ServiceInterface};

// Note that services implement the ServiceInterface and not the ListenerInterface.
class TimeService implements ServiceInterface
{
    public function getTime(): string
    {
        return "⏰ " . date('Y-m-d H:i:s');
    }
}
