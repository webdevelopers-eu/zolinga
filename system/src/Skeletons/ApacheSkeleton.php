<?php

declare(strict_types=1);

namespace Zolinga\System\Skeletons;

use Zolinga\System\Events\{RequestResponseEvent, ListenerInterface};
use const Zolinga\System\ROOT_DIR;
use Exception;

/**
 * This class is a skeleton for a module.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-03-25
 */
class ApacheSkeleton implements ListenerInterface
{
    /**
     * Print the Apache host skeleton.
     * 
     *   bin/zolinga skeleton:apache
     *
     * @param RequestResponseEvent $event
     * @return void
     */
    public function onApache(RequestResponseEvent $event): void
    {
        throw new Exception("Not implemented yet.");
    }
}
