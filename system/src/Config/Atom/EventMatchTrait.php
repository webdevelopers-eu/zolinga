<?php

namespace Zolinga\System\Config\Atom;

use Zolinga\System\Events\Event;
use Zolinga\System\Types\OriginEnum;

/**
 * Event matching feature.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-21
 */
trait EventMatchTrait
{

    /**
     * Check if the subscription is for the given event
     *
     * Note: The pattern's type character '*' works as a wild card 0 or more characters.
     * 
     * @param Event $event
     * @return bool
     */
    public function matchByEvent(Event $event): bool
    {
        if (
            $event->origin !== OriginEnum::ANY /* this is special origin for special purposes, namely for the Wiki discovery */
            &&
            !in_array($event->origin, $this['origin'])
            &&
            !in_array(OriginEnum::ANY, $this['origin'])
        ) {
            return false;
        }

        if (
            !$this->matchEventNames($this['event'], $event->type)
            &&
            !$this->matchEventNames($event->type, $this['event'])
        ) {
            return false;
        }

        return true;
    }

    private function matchEventNames(string $pattern, string $name): bool
    {
        $pattern = str_replace(['\\*'], ['.*'], preg_quote($pattern, '/'));
        return (bool) preg_match("/^$pattern$/", $name);
    }
}
