<?php

declare(strict_types=1);

namespace Zolinga\System\Types;

/**
 * The message can have one of these severities
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-03-02
 */
enum WikiMimeEnum: string
{
    case HTML = 'text/html';
    case MARKDOWN = 'text/markdown';
}
