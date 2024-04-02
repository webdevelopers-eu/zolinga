<?php

declare(strict_types=1);

namespace Zolinga\System\Types;

/**
 * Module can be in one of these states.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-02-02
 */
enum ModuleStatesEnum: string
{
    case UNCHANGED = 'unchanged';
    case CHANGED = 'changed';
    case NEW = 'new';
    case REMOVED = 'removed';
}
