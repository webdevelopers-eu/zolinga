<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

/**
 * Event that is triggered when a CLI request is received and a response is expected.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-04-05
 */
class CliRequestResponseEvent extends RequestResponseEvent implements StoppableInterface
{
    use StoppableTrait;
}