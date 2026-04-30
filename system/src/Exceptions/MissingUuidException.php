<?php

declare(strict_types=1);

namespace Zolinga\System\Exceptions;

/**
 * Thrown when an Event's UUID is null or empty before dispatch or serialization.
 */
class MissingUuidException extends \RuntimeException
{
    public function __construct(string $eventClass, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Event of class {$eventClass} has a null or empty UUID. Every event must have a non-null, non-empty UUID before dispatch or serialization.",
            2310,
            $previous
        );
    }
}
