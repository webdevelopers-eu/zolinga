<?php

declare(strict_types=1);

namespace Zolinga\System\Types;

/**
 * The event can originate from these sources.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-02-02
 */
enum OriginEnum: string
{
    case INTERNAL = 'internal';
    case REMOTE = 'remote';
    case CLI = 'cli';
    case CUSTOM = 'custom'; // Future unexpected uses...
    case ANY = '*'; // This origin behaves like it was any origin.
}
