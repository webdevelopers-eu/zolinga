<?php
declare(strict_types=1);    

namespace Zolinga\System\Events;

/**
 * This interface is used to stop the propagation of an event.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-06
 */
interface StoppableInterface
{
    /**
     * Stops the propagation of the event to further event listeners.
     *
     * @return void
     */
    public function stopPropagation(): void;

    /**
     * Returns whether further event listeners should be triggered.
     *
     * @return bool
     */
    public function isPropagationStopped(): bool;

    /**
     * Prevents the default action of the event.
     *
     * @return void
     */
    public function preventDefault(): void;

    /**
     * Returns whether the default action of the event has been prevented.
     *
     * @return bool
     */
    public function isDefaultPrevented(): bool;
}    